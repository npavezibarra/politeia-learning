<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Inclusion approvals for Especializaciones (LearnDash Groups) and Programas (course_program).
 *
 * Implements a snapshot + per-person approval workflow:
 * - Creator submits a snapshot (participants + percentages + included items).
 * - All participants (except creator) must approve.
 * - Until all approve: container/product remain in draft.
 */
class PL_CC_Inclusion_Approvals
{
    public const SNAPSHOTS_TABLE = 'politeia_inclusion_snapshots';
    public const APPROVALS_TABLE = 'politeia_inclusion_approvals';

    public const META_ACTIVE_SNAPSHOT = '_pcg_inclusion_snapshot_active';
    public const META_PENDING_SNAPSHOT = '_pcg_inclusion_snapshot_pending';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_SUPERSEDED = 'superseded';

    public static function init(): void
    {
        add_action('wp_ajax_pcg_get_my_pending_approvals', [__CLASS__, 'ajax_get_my_pending_approvals']);
        add_action('wp_ajax_pcg_approve_inclusion_snapshot', [__CLASS__, 'ajax_approve_snapshot']);
        add_action('wp_ajax_pcg_reject_inclusion_snapshot', [__CLASS__, 'ajax_reject_snapshot']);
    }

    /**
     * Create a new snapshot and its approvals.
     *
     * @return array{snapshot_id:int,status:string,approver_user_ids:int[]}
     */
    public static function create_snapshot(string $container_type, int $container_id, int $created_by, array $payload): array
    {
        global $wpdb;
        $snapshots_table = $wpdb->prefix . self::SNAPSHOTS_TABLE;
        $approvals_table = $wpdb->prefix . self::APPROVALS_TABLE;

        // Ensure tables exist (db upgrade might not have run yet in this request).
        self::ensure_tables_exist();
        if (!self::tables_exist()) {
            return [
                'snapshot_id' => 0,
                'status' => self::STATUS_DRAFT,
                'approver_user_ids' => [],
                'db_error' => sprintf(
                    /* translators: 1: snapshots table name, 2: approvals table name */
                    __('Faltan tablas de base de datos requeridas (%1$s, %2$s).', 'politeia-learning'),
                    $snapshots_table,
                    $approvals_table
                ),
            ];
        }

        $payload = self::normalize_payload($payload, $created_by);
        $hash = hash('sha256', wp_json_encode($payload));

        $participants = $payload['participants'] ?? [];
        $approver_user_ids = self::compute_approver_user_ids($participants, $created_by);
        $status = empty($approver_user_ids) ? self::STATUS_APPROVED : self::STATUS_PENDING;

        // Supersede any previous pending snapshot.
        $existing_pending = (int) get_post_meta($container_id, self::META_PENDING_SNAPSHOT, true);
        if ($existing_pending > 0) {
            $wpdb->update(
                $snapshots_table,
                ['status' => self::STATUS_SUPERSEDED],
                ['id' => $existing_pending],
                ['%s'],
                ['%d']
            );
        }

        $wpdb->insert($snapshots_table, [
            'container_type' => $container_type,
            'container_id' => $container_id,
            'status' => $status,
            'created_by' => $created_by,
            'snapshot_hash' => $hash,
            'payload' => wp_json_encode($payload),
        ], ['%s', '%d', '%s', '%d', '%s', '%s']);

        $snapshot_id = (int) $wpdb->insert_id;
        $last_error = (string) $wpdb->last_error;
        if ($snapshot_id <= 0) {
            return [
                'snapshot_id' => 0,
                'status' => self::STATUS_DRAFT,
                'approver_user_ids' => [],
                'db_error' => $last_error !== '' ? $last_error : __('No se pudo crear la solicitud de aprobación.', 'politeia-learning'),
            ];
        }

        $approvals_created = 0;
        $approval_error = '';
        if ($snapshot_id > 0 && !empty($approver_user_ids)) {
            foreach ($participants as $p) {
                $user_id = (int) ($p['user_id'] ?? 0);
                if ($user_id <= 0 || !in_array($user_id, $approver_user_ids, true)) {
                    continue;
                }

                $ok = $wpdb->insert($approvals_table, [
                    'snapshot_id' => $snapshot_id,
                    'approver_user_id' => $user_id,
                    'status' => 'pending',
                    'role_slug' => sanitize_text_field((string) ($p['role_slug'] ?? '')),
                    'role_description' => wp_kses_post((string) ($p['role_description'] ?? '')),
                    'profit_percentage' => (float) ($p['profit_percentage'] ?? 0),
                ], ['%d', '%d', '%s', '%s', '%s', '%f']);
                if ($ok === false) {
                    $approval_error = (string) $wpdb->last_error;
                    break;
                }
                $approvals_created++;
            }
        }

        // If we expected approvals but couldn't create them, rollback snapshot.
        if ($status === self::STATUS_PENDING && (empty($approver_user_ids) || $approvals_created <= 0 || $approval_error !== '')) {
            $wpdb->delete($approvals_table, ['snapshot_id' => $snapshot_id], ['%d']);
            $wpdb->delete($snapshots_table, ['id' => $snapshot_id], ['%d']);

            return [
                'snapshot_id' => 0,
                'status' => self::STATUS_DRAFT,
                'approver_user_ids' => [],
                'db_error' => $approval_error !== '' ? $approval_error : __('No se pudieron crear las aprobaciones requeridas.', 'politeia-learning'),
            ];
        }

        if ($snapshot_id > 0) {
            if ($status === self::STATUS_APPROVED) {
                update_post_meta($container_id, self::META_ACTIVE_SNAPSHOT, $snapshot_id);
                delete_post_meta($container_id, self::META_PENDING_SNAPSHOT);
            } else {
                update_post_meta($container_id, self::META_PENDING_SNAPSHOT, $snapshot_id);
            }
        }

        return [
            'snapshot_id' => $snapshot_id,
            'status' => $status,
            'approver_user_ids' => $approver_user_ids,
            'db_error' => $snapshot_id > 0 ? '' : $last_error,
        ];
    }

    public static function get_pending_snapshot_id(int $container_id): int
    {
        return (int) get_post_meta($container_id, self::META_PENDING_SNAPSHOT, true);
    }

    public static function get_active_snapshot_id(int $container_id): int
    {
        return (int) get_post_meta($container_id, self::META_ACTIVE_SNAPSHOT, true);
    }

    /**
     * Get snapshot payload.
     *
     * @return array<string,mixed>|null
     */
    public static function get_snapshot_payload(int $snapshot_id): ?array
    {
        global $wpdb;
        $snapshots_table = $wpdb->prefix . self::SNAPSHOTS_TABLE;

        $row = $wpdb->get_row($wpdb->prepare("SELECT payload FROM {$snapshots_table} WHERE id = %d", $snapshot_id));
        if (!$row || empty($row->payload)) {
            return null;
        }

        $decoded = json_decode((string) $row->payload, true);
        if (!is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    public static function ajax_get_my_pending_approvals(): void
    {
        check_ajax_referer('pcg_creator_nonce', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(['message' => __('No autorizado.', 'politeia-learning')], 401);
        }

        global $wpdb;
        $approvals_table = $wpdb->prefix . self::APPROVALS_TABLE;
        $snapshots_table = $wpdb->prefix . self::SNAPSHOTS_TABLE;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT a.id as approval_id, a.snapshot_id, a.role_slug, a.role_description, a.profit_percentage,
                        s.container_type, s.container_id, s.created_by, s.status as snapshot_status, s.created_at
                 FROM {$approvals_table} a
                 INNER JOIN {$snapshots_table} s ON s.id = a.snapshot_id
                 WHERE a.approver_user_id = %d AND a.status = %s AND s.status = %s
                 ORDER BY s.created_at DESC",
                $user_id,
                'pending',
                self::STATUS_PENDING
            )
        );

        $data = [];
        foreach ((array) $rows as $row) {
            $container_id = (int) $row->container_id;
            $data[] = [
                'approval_id' => (int) $row->approval_id,
                'snapshot_id' => (int) $row->snapshot_id,
                'container_type' => (string) $row->container_type,
                'container_id' => $container_id,
                'container_title' => get_the_title($container_id),
                'created_by' => (int) $row->created_by,
                'created_by_name' => get_the_author_meta('display_name', (int) $row->created_by),
                'role_slug' => (string) $row->role_slug,
                'role_description' => (string) $row->role_description,
                'profit_percentage' => (float) $row->profit_percentage,
            ];
        }

        wp_send_json_success($data);
    }

    public static function ajax_approve_snapshot(): void
    {
        check_ajax_referer('pcg_creator_nonce', 'nonce');
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(['message' => __('No autorizado.', 'politeia-learning')], 401);
        }

        $snapshot_id = absint($_POST['snapshot_id'] ?? 0);
        if (!$snapshot_id) {
            wp_send_json_error(['message' => __('ID inválido.', 'politeia-learning')], 400);
        }

        $result = self::set_approval_decision($snapshot_id, $user_id, 'approved');
        if (!$result['success']) {
            wp_send_json_error(['message' => $result['message']], $result['code']);
        }

        wp_send_json_success([
            'snapshot_id' => $snapshot_id,
            'snapshot_status' => $result['snapshot_status'],
        ]);
    }

    public static function ajax_reject_snapshot(): void
    {
        check_ajax_referer('pcg_creator_nonce', 'nonce');
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(['message' => __('No autorizado.', 'politeia-learning')], 401);
        }

        $snapshot_id = absint($_POST['snapshot_id'] ?? 0);
        if (!$snapshot_id) {
            wp_send_json_error(['message' => __('ID inválido.', 'politeia-learning')], 400);
        }

        $result = self::set_approval_decision($snapshot_id, $user_id, 'rejected');
        if (!$result['success']) {
            wp_send_json_error(['message' => $result['message']], $result['code']);
        }

        wp_send_json_success([
            'snapshot_id' => $snapshot_id,
            'snapshot_status' => $result['snapshot_status'],
        ]);
    }

    /**
     * Update an approval row and maybe finalize snapshot.
     *
     * @return array{success:bool,code:int,message:string,snapshot_status:string}
     */
    private static function set_approval_decision(int $snapshot_id, int $user_id, string $decision): array
    {
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            return ['success' => false, 'code' => 400, 'message' => __('Decisión inválida.', 'politeia-learning'), 'snapshot_status' => ''];
        }

        global $wpdb;
        $approvals_table = $wpdb->prefix . self::APPROVALS_TABLE;
        $snapshots_table = $wpdb->prefix . self::SNAPSHOTS_TABLE;

        $approval = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$approvals_table} WHERE snapshot_id = %d AND approver_user_id = %d", $snapshot_id, $user_id));
        if (!$approval) {
            return ['success' => false, 'code' => 403, 'message' => __('No autorizado.', 'politeia-learning'), 'snapshot_status' => ''];
        }

        if ($approval->status !== 'pending') {
            return ['success' => false, 'code' => 409, 'message' => __('Esta solicitud ya fue respondida.', 'politeia-learning'), 'snapshot_status' => (string) $wpdb->get_var($wpdb->prepare("SELECT status FROM {$snapshots_table} WHERE id = %d", $snapshot_id))];
        }

        $wpdb->update(
            $approvals_table,
            [
                'status' => $decision,
                'decided_at' => current_time('mysql'),
            ],
            [
                'snapshot_id' => $snapshot_id,
                'approver_user_id' => $user_id,
            ],
            ['%s', '%s'],
            ['%d', '%d']
        );

        // If rejected by any, snapshot becomes rejected.
        if ($decision === 'rejected') {
            $wpdb->update(
                $snapshots_table,
                ['status' => self::STATUS_REJECTED],
                ['id' => $snapshot_id],
                ['%s'],
                ['%d']
            );

            $container = $wpdb->get_row($wpdb->prepare("SELECT container_type, container_id FROM {$snapshots_table} WHERE id = %d", $snapshot_id));
            if ($container) {
                delete_post_meta((int) $container->container_id, self::META_PENDING_SNAPSHOT);
                // Auto-generate a new draft proposal without the rejecting user.
                self::create_draft_snapshot_after_rejection((string) $container->container_type, (int) $container->container_id, $snapshot_id, $user_id);
            }

            return ['success' => true, 'code' => 200, 'message' => '', 'snapshot_status' => self::STATUS_REJECTED];
        }

        // If all approvals approved, finalize.
        $remaining = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$approvals_table} WHERE snapshot_id = %d AND status = %s", $snapshot_id, 'pending'));
        if ($remaining > 0) {
            return ['success' => true, 'code' => 200, 'message' => '', 'snapshot_status' => self::STATUS_PENDING];
        }

        $wpdb->update(
            $snapshots_table,
            ['status' => self::STATUS_APPROVED],
            ['id' => $snapshot_id],
            ['%s'],
            ['%d']
        );

        $container = $wpdb->get_row($wpdb->prepare("SELECT container_type, container_id FROM {$snapshots_table} WHERE id = %d", $snapshot_id));
        if ($container) {
            $container_id = (int) $container->container_id;
            update_post_meta($container_id, self::META_ACTIVE_SNAPSHOT, $snapshot_id);
            delete_post_meta($container_id, self::META_PENDING_SNAPSHOT);

            /**
             * Action fired when an inclusion snapshot is fully approved.
             *
             * @param string $container_type group|program
             * @param int    $container_id
             * @param int    $snapshot_id
             */
            do_action('pcg_inclusion_snapshot_approved', (string) $container->container_type, $container_id, $snapshot_id);
        }

        return ['success' => true, 'code' => 200, 'message' => '', 'snapshot_status' => self::STATUS_APPROVED];
    }

    private static function normalize_payload(array $payload, int $created_by): array
    {
        $payload['participants'] = is_array($payload['participants'] ?? null) ? $payload['participants'] : [];
        $payload['included'] = is_array($payload['included'] ?? null) ? $payload['included'] : [];
        $payload['split_locked'] = !empty($payload['split_locked']);
        $payload['created_by'] = $created_by;
        return $payload;
    }

    /**
     * Participants that need to approve: anyone with > 0% and not the creator.
     *
     * @param array<int,array<string,mixed>> $participants
     * @return int[]
     */
    private static function compute_approver_user_ids(array $participants, int $created_by): array
    {
        $ids = [];
        foreach ($participants as $p) {
            $uid = (int) ($p['user_id'] ?? 0);
            $pct = (float) ($p['profit_percentage'] ?? 0);
            if ($uid > 0 && $uid !== $created_by && $pct > 0) {
                $ids[] = $uid;
            }
        }
        return array_values(array_unique($ids));
    }

    /**
     * After a rejection, create a new draft snapshot removing the rejecting user and any included items owned by them.
     */
    private static function create_draft_snapshot_after_rejection(string $container_type, int $container_id, int $rejected_snapshot_id, int $rejecting_user_id): void
    {
        $payload = self::get_snapshot_payload($rejected_snapshot_id);
        if (!$payload) {
            return;
        }

        $created_by = (int) ($payload['created_by'] ?? 0);
        if ($created_by <= 0) {
            $created_by = (int) get_post_field('post_author', $container_id);
        }

        $participants = is_array($payload['participants'] ?? null) ? $payload['participants'] : [];
        $participants = array_values(array_filter($participants, function ($p) use ($rejecting_user_id) {
            return (int) ($p['user_id'] ?? 0) !== $rejecting_user_id;
        }));

        // Remove included items owned by the rejecting user (so creator must re-propose).
        $included = is_array($payload['included'] ?? null) ? $payload['included'] : [];
        $included = array_values(array_filter($included, function ($item) use ($rejecting_user_id) {
            $type = (string) ($item['type'] ?? '');
            $id = (int) ($item['id'] ?? 0);
            if (!$id) {
                return false;
            }
            $post_type = $type === 'course' ? 'sfwd-courses' : ($type === 'group' ? 'groups' : '');
            if ($post_type === '' || get_post_type($id) !== $post_type) {
                return false;
            }
            return (int) get_post_field('post_author', $id) !== $rejecting_user_id;
        }));

        // Rebalance percentages (equal split unless locked; if locked, scale remaining to 100).
        $split_locked = !empty($payload['split_locked']);
        $participants = self::rebalance_percentages($participants, $split_locked);

        $new_payload = $payload;
        $new_payload['participants'] = $participants;
        $new_payload['included'] = $included;
        $new_payload['split_locked'] = $split_locked;

        global $wpdb;
        $snapshots_table = $wpdb->prefix . self::SNAPSHOTS_TABLE;
        $hash = hash('sha256', wp_json_encode($new_payload));

        $wpdb->insert($snapshots_table, [
            'container_type' => $container_type,
            'container_id' => $container_id,
            'status' => self::STATUS_DRAFT,
            'created_by' => $created_by,
            'snapshot_hash' => $hash,
            'payload' => wp_json_encode($new_payload),
        ], ['%s', '%d', '%s', '%d', '%s', '%s']);

        $draft_id = (int) $wpdb->insert_id;
        if ($draft_id > 0) {
            update_post_meta($container_id, self::META_PENDING_SNAPSHOT, $draft_id);
        }
    }

    /**
     * @param array<int,array<string,mixed>> $participants
     * @return array<int,array<string,mixed>>
     */
    private static function rebalance_percentages(array $participants, bool $locked): array
    {
        $n = count($participants);
        if ($n <= 0) {
            return $participants;
        }

        if (!$locked) {
            $base = floor(10000 / $n); // hundredths
            $remainder = 10000 - ($base * $n);

            foreach ($participants as $i => $p) {
                $hund = $base + ($i === 0 ? $remainder : 0);
                $participants[$i]['profit_percentage'] = $hund / 100;
            }
            return $participants;
        }

        $sum = 0.0;
        foreach ($participants as $p) {
            $sum += (float) ($p['profit_percentage'] ?? 0);
        }
        if ($sum <= 0) {
            return self::rebalance_percentages($participants, false);
        }

        foreach ($participants as $i => $p) {
            $pct = (float) ($p['profit_percentage'] ?? 0);
            $participants[$i]['profit_percentage'] = round(($pct / $sum) * 100, 2);
        }

        // Fix rounding drift by adjusting first.
        $new_sum = 0.0;
        foreach ($participants as $p) {
            $new_sum += (float) ($p['profit_percentage'] ?? 0);
        }
        $delta = round(100 - $new_sum, 2);
        $participants[0]['profit_percentage'] = round(((float) ($participants[0]['profit_percentage'] ?? 0)) + $delta, 2);

        return $participants;
    }

    private static function ensure_tables_exist(): void
    {
        global $wpdb;
        if (!$wpdb) {
            return;
        }

        $snapshots_table = $wpdb->prefix . self::SNAPSHOTS_TABLE;
        $approvals_table = $wpdb->prefix . self::APPROVALS_TABLE;

        $snapshots_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $snapshots_table));
        $approvals_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $approvals_table));

        if ($snapshots_exists === $snapshots_table && $approvals_exists === $approvals_table) {
            return;
        }

        if (class_exists('PL_Installer')) {
            PL_Installer::install();
        }
    }

    private static function tables_exist(): bool
    {
        global $wpdb;
        if (!$wpdb) {
            return false;
        }

        $snapshots_table = $wpdb->prefix . self::SNAPSHOTS_TABLE;
        $approvals_table = $wpdb->prefix . self::APPROVALS_TABLE;

        $snapshots_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $snapshots_table));
        $approvals_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $approvals_table));

        return ($snapshots_exists === $snapshots_table) && ($approvals_exists === $approvals_table);
    }
}
