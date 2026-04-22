<?php
/**
 * Inclusion approvals for Specializations (learni_specialization) and Programs (learni_program).
 * 
 * Refactored to use Traits for modularity.
 * Now focuses on AJAX handlers and state orchestration.
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load Logic Trait
require_once __DIR__ . '/traits/trait-approvals-logic.php';

class PL_CC_Inclusion_Approvals
{
    use PL_CC_Approvals_Logic_Trait;

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

    public static function get_pending_snapshot_id(int $container_id): int
    {
        return (int) get_post_meta($container_id, self::META_PENDING_SNAPSHOT, true);
    }

    public static function get_active_snapshot_id(int $container_id): int
    {
        return (int) get_post_meta($container_id, self::META_ACTIVE_SNAPSHOT, true);
    }

    public static function ajax_get_my_pending_approvals(): void
    {
        check_ajax_referer('pcg_creator_nonce', 'nonce');
        $user_id = get_current_user_id();
        if (!$user_id) wp_send_json_error(['message' => __('No autorizado.', 'politeia-learning')], 401);

        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT a.id as approval_id, a.snapshot_id, a.role_slug, a.role_description, a.profit_percentage,
                    s.container_type, s.container_id, s.created_by, s.status as snapshot_status, s.created_at
             FROM {$wpdb->prefix}" . self::APPROVALS_TABLE . " a
             INNER JOIN {$wpdb->prefix}" . self::SNAPSHOTS_TABLE . " s ON s.id = a.snapshot_id
             WHERE a.approver_user_id = %d AND a.status = %s AND s.status = %s
             ORDER BY s.created_at DESC", $user_id, 'pending', self::STATUS_PENDING
        ));

        $data = [];
        foreach ((array) $rows as $row) {
            $data[] = [
                'approval_id' => (int) $row->approval_id, 'snapshot_id' => (int) $row->snapshot_id,
                'container_type' => (string) $row->container_type, 'container_id' => (int) $row->container_id,
                'container_title' => get_the_title((int) $row->container_id), 'created_by' => (int) $row->created_by,
                'created_by_name' => get_the_author_meta('display_name', (int) $row->created_by),
                'role_slug' => (string) $row->role_slug, 'role_description' => (string) $row->role_description, 'profit_percentage' => (float) $row->profit_percentage,
            ];
        }
        wp_send_json_success($data);
    }

    public static function ajax_approve_snapshot(): void
    {
        check_ajax_referer('pcg_creator_nonce', 'nonce');
        $user_id = get_current_user_id();
        if (!$user_id) wp_send_json_error(['message' => __('No autorizado.', 'politeia-learning')], 401);

        $snapshot_id = absint($_POST['snapshot_id'] ?? 0);
        if (!$snapshot_id) wp_send_json_error(['message' => __('ID inválido.', 'politeia-learning')], 400);

        $result = self::set_approval_decision($snapshot_id, $user_id, 'approved');
        if (!$result['success']) wp_send_json_error(['message' => $result['message']], $result['code']);

        wp_send_json_success(['snapshot_id' => $snapshot_id, 'snapshot_status' => $result['snapshot_status']]);
    }

    public static function ajax_reject_snapshot(): void
    {
        check_ajax_referer('pcg_creator_nonce', 'nonce');
        $user_id = get_current_user_id();
        if (!$user_id) wp_send_json_error(['message' => __('No autorizado.', 'politeia-learning')], 401);

        $snapshot_id = absint($_POST['snapshot_id'] ?? 0);
        if (!$snapshot_id) wp_send_json_error(['message' => __('ID inválido.', 'politeia-learning')], 400);

        $result = self::set_approval_decision($snapshot_id, $user_id, 'rejected');
        if (!$result['success']) wp_send_json_error(['message' => $result['message']], $result['code']);

        wp_send_json_success(['snapshot_id' => $snapshot_id, 'snapshot_status' => $result['snapshot_status']]);
    }

    private static function set_approval_decision(int $snapshot_id, int $user_id, string $decision): array
    {
        global $wpdb;
        $a_table = $wpdb->prefix . self::APPROVALS_TABLE;
        $s_table = $wpdb->prefix . self::SNAPSHOTS_TABLE;

        $approval = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$a_table} WHERE snapshot_id = %d AND approver_user_id = %d", $snapshot_id, $user_id));
        if (!$approval) return ['success' => false, 'code' => 403, 'message' => __('No autorizado.', 'politeia-learning'), 'snapshot_status' => ''];
        if ($approval->status !== 'pending') return ['success' => false, 'code' => 409, 'message' => __('Respondida.', 'politeia-learning'), 'snapshot_status' => (string)$wpdb->get_var($wpdb->prepare("SELECT status FROM {$s_table} WHERE id = %d", $snapshot_id))];

        $wpdb->update($a_table, ['status' => $decision, 'decided_at' => current_time('mysql')], ['snapshot_id' => $snapshot_id, 'approver_user_id' => $user_id], ['%s', '%s'], ['%d', '%d']);

        if ($decision === 'rejected') {
            $wpdb->update($s_table, ['status' => self::STATUS_REJECTED], ['id' => $snapshot_id], ['%s'], ['%d']);
            $container = $wpdb->get_row($wpdb->prepare("SELECT container_type, container_id FROM {$s_table} WHERE id = %d", $snapshot_id));
            if ($container) {
                delete_post_meta((int)$container->container_id, self::META_PENDING_SNAPSHOT);
                self::create_draft_snapshot_after_rejection((string)$container->container_type, (int)$container->container_id, $snapshot_id, $user_id);
            }
            return ['success' => true, 'code' => 200, 'message' => '', 'snapshot_status' => self::STATUS_REJECTED];
        }

        if ((int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$a_table} WHERE snapshot_id = %d AND status = %s", $snapshot_id, 'pending')) > 0) {
            return ['success' => true, 'code' => 200, 'message' => '', 'snapshot_status' => self::STATUS_PENDING];
        }

        $wpdb->update($s_table, ['status' => self::STATUS_APPROVED], ['id' => $snapshot_id], ['%s'], ['%d']);
        $container = $wpdb->get_row($wpdb->prepare("SELECT container_type, container_id FROM {$s_table} WHERE id = %d", $snapshot_id));
        if ($container) {
            update_post_meta((int)$container->container_id, self::META_ACTIVE_SNAPSHOT, $snapshot_id);
            delete_post_meta((int)$container->container_id, self::META_PENDING_SNAPSHOT);
            do_action('pcg_inclusion_snapshot_approved', (string)$container->container_type, (int)$container->container_id, $snapshot_id);
        }
        return ['success' => true, 'code' => 200, 'message' => '', 'snapshot_status' => self::STATUS_APPROVED];
    }
}
