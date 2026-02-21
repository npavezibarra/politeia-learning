<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Persists a "profit split snapshot" onto WooCommerce order line items at purchase time.
 *
 * This is required so revenue splits are historically correct even if a container is later revised
 * (via the "fork/new version" workflow).
 *
 * Stored per order item:
 * - _pl_container_type: course|group|program
 * - _pl_container_id: post id
 * - _pl_split_snapshot_id: int (0 if not using inclusion snapshot)
 * - _pl_split_payload: json array of participants [{user_id, profit_percentage, role_slug, role_description}]
 */
class PL_Woo_Order_Split_Snapshot
{
    public static function init(): void
    {
        // Fire on paid statuses. We guard idempotency per order item.
        add_action('woocommerce_order_status_processing', [__CLASS__, 'maybe_snapshot_for_order'], 10, 1);
        add_action('woocommerce_order_status_completed', [__CLASS__, 'maybe_snapshot_for_order'], 10, 1);
        add_action('woocommerce_payment_complete', [__CLASS__, 'maybe_snapshot_for_order'], 10, 1);
    }

    public static function maybe_snapshot_for_order(int $order_id): void
    {
        if (!class_exists('WooCommerce') || !function_exists('wc_get_order')) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        foreach ($order->get_items('line_item') as $item) {
            if (!is_a($item, 'WC_Order_Item_Product')) {
                continue;
            }

            // Idempotent: only snapshot once.
            $existing = (string) $item->get_meta('_pl_split_payload', true);
            if ($existing !== '') {
                continue;
            }

            $product_id = (int) $item->get_product_id();
            if ($product_id <= 0) {
                continue;
            }

            $parent_id = (int) wp_get_post_parent_id($product_id);
            $base_product_id = $parent_id > 0 ? $parent_id : $product_id;

            [$container_type, $container_id] = self::resolve_container_from_product($base_product_id);
            if ($container_type === '' || $container_id <= 0) {
                continue;
            }

            $snapshot_id = 0;
            $payload = [];

            if (in_array($container_type, ['group', 'program'], true) && class_exists('PL_CC_Inclusion_Approvals')) {
                $snapshot_id = (int) get_post_meta($container_id, PL_CC_Inclusion_Approvals::META_ACTIVE_SNAPSHOT, true);
                if ($snapshot_id > 0) {
                    $snap = PL_CC_Inclusion_Approvals::get_snapshot_payload($snapshot_id);
                    if (is_array($snap) && is_array($snap['participants'] ?? null)) {
                        $payload = (array) $snap['participants'];
                    }
                }
            }

            if (empty($payload)) {
                $payload = self::payload_from_roles_or_default($container_type, $container_id);
            }

            if (empty($payload)) {
                continue;
            }

            $item->update_meta_data('_pl_container_type', $container_type);
            $item->update_meta_data('_pl_container_id', $container_id);
            $item->update_meta_data('_pl_split_snapshot_id', $snapshot_id);
            $item->update_meta_data('_pl_split_payload', wp_json_encode(array_values($payload)));
            $item->save();
        }
    }

    /**
     * @return array{0:string,1:int}
     */
    private static function resolve_container_from_product(int $product_id): array
    {
        // Program has precedence over group because program products also store _related_group.
        $program_id = (int) get_post_meta($product_id, '_pcg_related_program', true);
        if ($program_id > 0 && get_post_type($program_id) === 'course_program') {
            return ['program', $program_id];
        }

        $groups = get_post_meta($product_id, '_related_group', true);
        if (is_array($groups) && !empty($groups)) {
            $gid = (int) ($groups[0] ?? 0);
            if ($gid > 0 && get_post_type($gid) === 'groups') {
                return ['group', $gid];
            }
        }

        $courses = get_post_meta($product_id, '_related_course', true);
        if (is_array($courses) && !empty($courses)) {
            $cid = (int) ($courses[0] ?? 0);
            if ($cid > 0 && get_post_type($cid) === 'sfwd-courses') {
                return ['course', $cid];
            }
        }

        return ['', 0];
    }

    /**
     * @return array<int,array{user_id:int,role_slug:string,role_description:string,profit_percentage:float}>
     */
    private static function payload_from_roles_or_default(string $container_type, int $container_id): array
    {
        global $wpdb;
        if (!$wpdb) {
            return [];
        }

        $roles_table = $wpdb->prefix . 'politeia_course_roles';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT user_id, role_slug, role_description, profit_percentage
                 FROM {$roles_table}
                 WHERE object_type = %s AND object_id = %d",
                $container_type,
                $container_id
            )
        );

        $out = [];
        foreach ((array) $rows as $r) {
            $uid = (int) ($r->user_id ?? 0);
            if ($uid <= 0) {
                continue;
            }
            $out[] = [
                'user_id' => $uid,
                'role_slug' => (string) ($r->role_slug ?? ''),
                'role_description' => (string) ($r->role_description ?? ''),
                'profit_percentage' => (float) ($r->profit_percentage ?? 0),
            ];
        }

        // Default: 100% to author if no roles exist yet.
        if (empty($out)) {
            $author_id = (int) get_post_field('post_author', $container_id);
            if ($author_id > 0) {
                $out[] = [
                    'user_id' => $author_id,
                    'role_slug' => __('Autor principal', 'politeia-learning'),
                    'role_description' => '',
                    'profit_percentage' => 100.0,
                ];
            }
        }

        return $out;
    }
}
