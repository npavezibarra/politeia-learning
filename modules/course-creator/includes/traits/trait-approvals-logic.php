<?php
/**
 * Trait for Inclusion Approvals core logic and database operations.
 */

if (!defined('ABSPATH'))
    exit;

trait PL_CC_Approvals_Logic_Trait
{
    /**
     * Create a new snapshot and its approvals.
     */
    public static function create_snapshot(string $container_type, int $container_id, int $created_by, array $payload): array
    {
        global $wpdb;
        $snapshots_table = $wpdb->prefix . self::SNAPSHOTS_TABLE;
        $approvals_table = $wpdb->prefix . self::APPROVALS_TABLE;

        self::ensure_tables_exist();
        if (!self::tables_exist()) {
            return [
                'snapshot_id' => 0,
                'status' => self::STATUS_DRAFT,
                'approver_user_ids' => [],
                'db_error' => sprintf(__('Faltan tablas de base de datos requeridas (%1$s, %2$s).', 'politeia-learning'), $snapshots_table, $approvals_table),
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
            $wpdb->update($snapshots_table, ['status' => self::STATUS_SUPERSEDED], ['id' => $existing_pending], ['%s'], ['%d']);
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
        if ($snapshot_id <= 0) {
            return ['snapshot_id' => 0, 'status' => self::STATUS_DRAFT, 'approver_user_ids' => [], 'db_error' => __('No se pudo crear la solicitud.', 'politeia-learning')];
        }

        $approvals_created = 0;
        foreach ($participants as $p) {
            $user_id = (int) ($p['user_id'] ?? 0);
            if ($user_id <= 0 || !in_array($user_id, $approver_user_ids, true)) continue;
            $wpdb->insert($approvals_table, ['snapshot_id' => $snapshot_id, 'approver_user_id' => $user_id, 'status' => 'pending', 'role_slug' => sanitize_text_field($p['role_slug']), 'role_description' => wp_kses_post($p['role_description']), 'profit_percentage' => (float)$p['profit_percentage']], ['%d', '%d', '%s', '%s', '%s', '%f']);
            $approvals_created++;
        }

        if ($snapshot_id > 0) {
            if ($status === self::STATUS_APPROVED) {
                update_post_meta($container_id, self::META_ACTIVE_SNAPSHOT, $snapshot_id);
                delete_post_meta($container_id, self::META_PENDING_SNAPSHOT);
            } else {
                update_post_meta($container_id, self::META_PENDING_SNAPSHOT, $snapshot_id);
            }
        }

        return ['snapshot_id' => $snapshot_id, 'status' => $status, 'approver_user_ids' => $approver_user_ids, 'db_error' => ''];
    }

    public static function get_snapshot_payload(int $snapshot_id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT payload FROM {$wpdb->prefix}" . self::SNAPSHOTS_TABLE . " WHERE id = %d", $snapshot_id));
        return ($row && !empty($row->payload)) ? json_decode((string) $row->payload, true) : null;
    }

    private static function compute_approver_user_ids(array $participants, int $created_by): array
    {
        $ids = [];
        foreach ($participants as $p) {
            $uid = (int) ($p['user_id'] ?? 0);
            if ($uid > 0 && $uid !== $created_by && (float)($p['profit_percentage'] ?? 0) > 0) $ids[] = $uid;
        }
        return array_values(array_unique($ids));
    }

    private static function create_draft_snapshot_after_rejection(string $container_type, int $container_id, int $rejected_snapshot_id, int $rejecting_user_id): void
    {
        $payload = self::get_snapshot_payload($rejected_snapshot_id);
        if (!$payload) return;

        $created_by = (int) ($payload['created_by'] ?? get_post_field('post_author', $container_id));
        $participants = array_values(array_filter((array)$payload['participants'], function ($p) use ($rejecting_user_id) { return (int) ($p['user_id'] ?? 0) !== $rejecting_user_id; }));
        $included = array_values(array_filter((array)$payload['included'], function ($item) use ($rejecting_user_id) {
            $id = (int) ($item['id'] ?? 0);
            return $id > 0 && (int) get_post_field('post_author', $id) !== $rejecting_user_id;
        }));

        $split_locked = !empty($payload['split_locked']);
        $participants = self::rebalance_percentages($participants, $split_locked);

        $new_payload = array_merge($payload, ['participants' => $participants, 'included' => $included, 'split_locked' => $split_locked]);
        global $wpdb;
        $wpdb->insert($wpdb->prefix . self::SNAPSHOTS_TABLE, ['container_type' => $container_type, 'container_id' => $container_id, 'status' => self::STATUS_DRAFT, 'created_by' => $created_by, 'snapshot_hash' => hash('sha256', wp_json_encode($new_payload)), 'payload' => wp_json_encode($new_payload)], ['%s', '%d', '%s', '%d', '%s', '%s']);
        if ($wpdb->insert_id) update_post_meta($container_id, self::META_PENDING_SNAPSHOT, (int)$wpdb->insert_id);
    }

    private static function rebalance_percentages(array $participants, bool $locked): array
    {
        $n = count($participants);
        if ($n <= 0) return $participants;

        if (!$locked) {
            $base = floor(10000 / $n);
            $remainder = 10000 - ($base * $n);
            foreach ($participants as $i => $p) $participants[$i]['profit_percentage'] = ($base + ($i === 0 ? $remainder : 0)) / 100;
            return $participants;
        }

        $sum = 0.0;
        foreach ($participants as $p) $sum += (float) ($p['profit_percentage'] ?? 0);
        if ($sum <= 0) return self::rebalance_percentages($participants, false);

        $new_sum = 0.0;
        foreach ($participants as $i => $p) {
            $participants[$i]['profit_percentage'] = round(((float)$p['profit_percentage'] / $sum) * 100, 2);
            $new_sum += $participants[$i]['profit_percentage'];
        }
        $participants[0]['profit_percentage'] = round($participants[0]['profit_percentage'] + (100 - $new_sum), 2);
        return $participants;
    }

    private static function ensure_tables_exist(): void
    {
        global $wpdb;
        $s_table = $wpdb->prefix . self::SNAPSHOTS_TABLE;
        $a_table = $wpdb->prefix . self::APPROVALS_TABLE;
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $s_table)) === $s_table && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $a_table)) === $a_table) return;
        if (class_exists('PL_Installer')) PL_Installer::install();
    }

    private static function tables_exist(): bool
    {
        global $wpdb;
        return ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->prefix . self::SNAPSHOTS_TABLE)) === $wpdb->prefix . self::SNAPSHOTS_TABLE) && ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->prefix . self::APPROVALS_TABLE)) === $wpdb->prefix . self::APPROVALS_TABLE);
    }

    private static function normalize_payload(array $payload, int $created_by): array
    {
        return array_merge($payload, ['participants' => (array)($payload['participants'] ?? []), 'included' => (array)($payload['included'] ?? []), 'split_locked' => !empty($payload['split_locked']), 'created_by' => $created_by]);
    }
}
