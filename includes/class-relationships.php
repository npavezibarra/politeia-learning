<?php
/**
 * User-to-user relationships (pure WordPress).
 *
 * Types:
 * - follow: requires approval
 * - friend: requires approval (effective if accepted)
 * - subscribe: created from payment (auto-accepted, with optional expiry)
 * - block: hides profile/actions (auto-accepted)
 */

if (!defined('ABSPATH')) {
    exit;
}

class PL_Relationships
{
    public const TABLE = 'politeia_user_relationships';

    public const TYPE_FOLLOW = 'follow';
    public const TYPE_FRIEND = 'friend';
    public const TYPE_SUBSCRIBE = 'subscribe';
    public const TYPE_BLOCK = 'block';

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_REVOKED = 'revoked';

    public const META_POLICY_PUBLIC = 'pl_policy_public';
    public const META_POLICY_FOLLOW = 'pl_policy_follow';
    public const META_POLICY_FRIEND = 'pl_policy_friend';
    public const META_POLICY_SUBSCRIBE = 'pl_policy_subscribe';
    public const META_SUBSCRIBE_PERIOD_DAYS = 'pl_subscribe_period_days';

    public static function init(): void
    {
        add_action('admin_post_pl_relationship_request', [__CLASS__, 'handle_request']);
        add_action('admin_post_pl_relationship_respond', [__CLASS__, 'handle_respond']);
        add_action('admin_post_pl_relationship_block', [__CLASS__, 'handle_block']);
        add_action('admin_post_pl_relationship_unblock', [__CLASS__, 'handle_unblock']);

        // Payment integrations can call this action to grant a subscription.
        add_action('pl_subscription_payment_completed', [__CLASS__, 'handle_subscription_payment_completed'], 10, 4);

        // Optional WooCommerce compatibility (only if order meta is present).
        add_action('woocommerce_order_status_completed', [__CLASS__, 'maybe_handle_woocommerce_order_completed'], 10, 1);

        add_action('pl_relationships_expire_subscriptions', [__CLASS__, 'expire_subscriptions']);
        if (!wp_next_scheduled('pl_relationships_expire_subscriptions')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'pl_relationships_expire_subscriptions');
        }
    }

    public static function table_name(): string
    {
        global $wpdb;
        return $wpdb ? ($wpdb->prefix . self::TABLE) : '';
    }

    /**
     * @return array<string, mixed>
     */
    public static function default_policy(string $kind): array
    {
        $all_tabs = ['main', 'courses', 'writings', 'specializations', 'thoughts', 'plans', 'book'];

        switch ($kind) {
            case 'public':
                return [
                    'profile_tabs' => ['main'],
                ];
            case self::TYPE_FOLLOW:
                return [
                    'profile_tabs' => ['main', 'writings'],
                ];
            case self::TYPE_FRIEND:
                return [
                    'profile_tabs' => $all_tabs,
                ];
            case self::TYPE_SUBSCRIBE:
                return [
                    'profile_tabs' => $all_tabs,
                ];
            default:
                return [
                    'profile_tabs' => ['main'],
                ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function get_owner_policy(int $owner_user_id, string $kind): array
    {
        if ($owner_user_id <= 0) {
            return self::default_policy($kind);
        }

        $meta_key = match ($kind) {
            'public' => self::META_POLICY_PUBLIC,
            self::TYPE_FOLLOW => self::META_POLICY_FOLLOW,
            self::TYPE_FRIEND => self::META_POLICY_FRIEND,
            self::TYPE_SUBSCRIBE => self::META_POLICY_SUBSCRIBE,
            default => self::META_POLICY_PUBLIC,
        };

        $policy = get_user_meta($owner_user_id, $meta_key, true);
        if (!is_array($policy)) {
            $policy = [];
        }

        $merged = array_merge(self::default_policy($kind), $policy);
        $tabs = $merged['profile_tabs'] ?? [];
        if (!is_array($tabs)) {
            $tabs = [];
        }
        $tabs = array_values(array_unique(array_filter(array_map('sanitize_key', $tabs))));
        $merged['profile_tabs'] = $tabs;

        return $merged;
    }

    /**
     * Returns the highest access level for $viewer -> $owner.
     *
     * Order: subscribe > friend > follow > public.
     */
    public static function get_access_level(int $viewer_user_id, int $owner_user_id): string
    {
        if ($owner_user_id <= 0) {
            return 'public';
        }
        if ($viewer_user_id > 0 && $viewer_user_id === $owner_user_id) {
            return 'owner';
        }
        if ($viewer_user_id <= 0) {
            return 'public';
        }
        if (self::is_blocked($viewer_user_id, $owner_user_id)) {
            return 'blocked';
        }

        if (self::is_effective($viewer_user_id, $owner_user_id, self::TYPE_SUBSCRIBE)) {
            return self::TYPE_SUBSCRIBE;
        }
        if (self::is_effective_friendship($viewer_user_id, $owner_user_id)) {
            return self::TYPE_FRIEND;
        }
        if (self::is_effective($viewer_user_id, $owner_user_id, self::TYPE_FOLLOW)) {
            return self::TYPE_FOLLOW;
        }

        return 'public';
    }

    public static function is_effective_friendship(int $a_user_id, int $b_user_id): bool
    {
        return self::is_effective($a_user_id, $b_user_id, self::TYPE_FRIEND)
            || self::is_effective($b_user_id, $a_user_id, self::TYPE_FRIEND);
    }

    public static function is_effective(int $from_user_id, int $to_user_id, string $type): bool
    {
        $rel = self::get_relationship($from_user_id, $to_user_id, $type);
        if (!$rel) {
            return false;
        }

        if (($rel['status'] ?? '') !== self::STATUS_ACCEPTED) {
            return false;
        }

        if ($type === self::TYPE_SUBSCRIBE) {
            $expires_at = $rel['expires_at'] ?? null;
            if (is_string($expires_at) && $expires_at !== '') {
                $ts = strtotime($expires_at);
                if ($ts !== false && $ts < time()) {
                    return false;
                }
            }
        }

        return true;
    }

    public static function is_blocked(int $viewer_user_id, int $owner_user_id): bool
    {
        if ($viewer_user_id <= 0 || $owner_user_id <= 0) {
            return false;
        }
        $rel = self::get_relationship($owner_user_id, $viewer_user_id, self::TYPE_BLOCK);
        return $rel && (($rel['status'] ?? '') === self::STATUS_ACCEPTED);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get_relationship(int $from_user_id, int $to_user_id, string $type): ?array
    {
        global $wpdb;
        if (!$wpdb || $from_user_id <= 0 || $to_user_id <= 0) {
            return null;
        }

        $table = self::table_name();
        if ($table === '') {
            return null;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, from_user_id, to_user_id, rel_type, status, expires_at, created_at, updated_at
                 FROM {$table}
                 WHERE from_user_id = %d AND to_user_id = %d AND rel_type = %s
                 LIMIT 1",
                $from_user_id,
                $to_user_id,
                $type
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function get_pending_requests_for_owner(int $owner_user_id): array
    {
        global $wpdb;
        if (!$wpdb || $owner_user_id <= 0) {
            return [];
        }

        $table = self::table_name();
        if ($table === '') {
            return [];
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, from_user_id, to_user_id, rel_type, status, expires_at, created_at
                 FROM {$table}
                 WHERE to_user_id = %d AND status = %s AND rel_type IN (%s,%s)
                 ORDER BY created_at DESC
                 LIMIT 200",
                $owner_user_id,
                self::STATUS_PENDING,
                self::TYPE_FOLLOW,
                self::TYPE_FRIEND
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    public static function upsert_relationship(int $from_user_id, int $to_user_id, string $type, string $status, ?string $expires_at = null): bool
    {
        global $wpdb;
        if (!$wpdb || $from_user_id <= 0 || $to_user_id <= 0 || $from_user_id === $to_user_id) {
            return false;
        }

        $allowed_types = [self::TYPE_FOLLOW, self::TYPE_FRIEND, self::TYPE_SUBSCRIBE, self::TYPE_BLOCK];
        $allowed_status = [self::STATUS_PENDING, self::STATUS_ACCEPTED, self::STATUS_REJECTED, self::STATUS_REVOKED];
        if (!in_array($type, $allowed_types, true) || !in_array($status, $allowed_status, true)) {
            return false;
        }

        $table = self::table_name();
        if ($table === '') {
            return false;
        }

        $data = [
            'from_user_id' => $from_user_id,
            'to_user_id' => $to_user_id,
            'rel_type' => $type,
            'status' => $status,
            'expires_at' => $expires_at,
        ];

        $formats = ['%d', '%d', '%s', '%s', '%s'];

        $existing = self::get_relationship($from_user_id, $to_user_id, $type);
        if ($existing) {
            $updated = $wpdb->update(
                $table,
                [
                    'status' => $status,
                    'expires_at' => $expires_at,
                ],
                ['id' => (int) $existing['id']],
                ['%s', '%s'],
                ['%d']
            );
            return $updated !== false;
        }

        $inserted = $wpdb->insert($table, $data, $formats);
        return (bool) $inserted;
    }

    public static function request_relationship(int $from_user_id, int $to_user_id, string $type): bool
    {
        if ($type === self::TYPE_SUBSCRIBE || $type === self::TYPE_BLOCK) {
            return false;
        }
        if (self::is_blocked($from_user_id, $to_user_id)) {
            return false;
        }
        return self::upsert_relationship($from_user_id, $to_user_id, $type, self::STATUS_PENDING);
    }

    public static function respond_to_request(int $owner_user_id, int $request_id, string $decision): bool
    {
        global $wpdb;
        if (!$wpdb || $owner_user_id <= 0 || $request_id <= 0) {
            return false;
        }

        $table = self::table_name();
        if ($table === '') {
            return false;
        }

        $decision = strtolower($decision);
        $new_status = match ($decision) {
            'accept', 'accepted' => self::STATUS_ACCEPTED,
            'reject', 'rejected' => self::STATUS_REJECTED,
            default => '',
        };
        if ($new_status === '') {
            return false;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, to_user_id, rel_type, status
                 FROM {$table}
                 WHERE id = %d
                 LIMIT 1",
                $request_id
            ),
            ARRAY_A
        );
        if (!is_array($row) || (int) ($row['to_user_id'] ?? 0) !== $owner_user_id) {
            return false;
        }
        if (($row['status'] ?? '') !== self::STATUS_PENDING) {
            return false;
        }

        $updated = $wpdb->update(
            $table,
            ['status' => $new_status],
            ['id' => $request_id],
            ['%s'],
            ['%d']
        );
        return $updated !== false;
    }

    public static function set_block(int $owner_user_id, int $blocked_user_id, bool $blocked): bool
    {
        if ($blocked) {
            return self::upsert_relationship($owner_user_id, $blocked_user_id, self::TYPE_BLOCK, self::STATUS_ACCEPTED);
        }
        return self::upsert_relationship($owner_user_id, $blocked_user_id, self::TYPE_BLOCK, self::STATUS_REVOKED);
    }

    /**
     * Payment callback.
     *
     * @param int $subscriber_user_id
     * @param int $owner_user_id
     * @param int|null $period_days Optional override. If null, uses owner meta `pl_subscribe_period_days`.
     * @param array<string,mixed> $context
     */
    public static function handle_subscription_payment_completed(int $subscriber_user_id, int $owner_user_id, ?int $period_days = null, array $context = []): void
    {
        if ($subscriber_user_id <= 0 || $owner_user_id <= 0 || $subscriber_user_id === $owner_user_id) {
            return;
        }
        if (self::is_blocked($subscriber_user_id, $owner_user_id)) {
            return;
        }

        $days = $period_days;
        if (!$days) {
            $days = absint(get_user_meta($owner_user_id, self::META_SUBSCRIBE_PERIOD_DAYS, true));
        }
        if ($days <= 0) {
            $days = 30;
        }

        $expires_at = gmdate('Y-m-d H:i:s', time() + ($days * DAY_IN_SECONDS));
        self::upsert_relationship($subscriber_user_id, $owner_user_id, self::TYPE_SUBSCRIBE, self::STATUS_ACCEPTED, $expires_at);
    }

    /**
     * WooCommerce fallback:
     * If order meta contains `pl_subscribe_to_user_id`, we grant a subscription to that user.
     */
    public static function maybe_handle_woocommerce_order_completed($order_id): void
    {
        if (!function_exists('wc_get_order')) {
            return;
        }
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $subscriber_user_id = (int) $order->get_user_id();
        $owner_user_id = (int) $order->get_meta('pl_subscribe_to_user_id');
        if ($subscriber_user_id <= 0 || $owner_user_id <= 0) {
            return;
        }

        $period_days = (int) $order->get_meta('pl_subscribe_period_days');
        if ($period_days <= 0) {
            $period_days = null;
        }

        self::handle_subscription_payment_completed($subscriber_user_id, $owner_user_id, $period_days, [
            'source' => 'woocommerce',
            'order_id' => (int) $order_id,
        ]);
    }

    public static function expire_subscriptions(): void
    {
        global $wpdb;
        if (!$wpdb) {
            return;
        }
        $table = self::table_name();
        if ($table === '') {
            return;
        }

        $now = gmdate('Y-m-d H:i:s');
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table}
                 SET status = %s
                 WHERE rel_type = %s
                   AND status = %s
                   AND expires_at IS NOT NULL
                   AND expires_at < %s",
                self::STATUS_REVOKED,
                self::TYPE_SUBSCRIBE,
                self::STATUS_ACCEPTED,
                $now
            )
        );
    }

    /**
     * Admin-post: request follow/friend.
     */
    public static function handle_request(): void
    {
        if (!is_user_logged_in()) {
            wp_die('Unauthorized', 401);
        }
        check_admin_referer('pl_relationship_request');

        $from_user_id = (int) get_current_user_id();
        $to_user_id = isset($_POST['to_user_id']) ? absint($_POST['to_user_id']) : 0;
        $type = isset($_POST['rel_type']) ? sanitize_key((string) $_POST['rel_type']) : '';

        $ok = self::request_relationship($from_user_id, $to_user_id, $type);
        self::redirect_back(['pl_rel' => $ok ? 'requested' : 'error']);
    }

    /**
     * Admin-post: respond to request (owner only).
     */
    public static function handle_respond(): void
    {
        if (!is_user_logged_in()) {
            wp_die('Unauthorized', 401);
        }
        check_admin_referer('pl_relationship_respond');

        $owner_user_id = (int) get_current_user_id();
        $request_id = isset($_POST['request_id']) ? absint($_POST['request_id']) : 0;
        $decision = isset($_POST['decision']) ? sanitize_key((string) $_POST['decision']) : '';

        $ok = self::respond_to_request($owner_user_id, $request_id, $decision);
        self::redirect_back(['pl_rel' => $ok ? 'updated' : 'error']);
    }

    public static function handle_block(): void
    {
        if (!is_user_logged_in()) {
            wp_die('Unauthorized', 401);
        }
        check_admin_referer('pl_relationship_block');

        $owner_user_id = (int) get_current_user_id();
        $blocked_user_id = isset($_POST['blocked_user_id']) ? absint($_POST['blocked_user_id']) : 0;
        $ok = self::set_block($owner_user_id, $blocked_user_id, true);
        self::redirect_back(['pl_rel' => $ok ? 'blocked' : 'error']);
    }

    public static function handle_unblock(): void
    {
        if (!is_user_logged_in()) {
            wp_die('Unauthorized', 401);
        }
        check_admin_referer('pl_relationship_unblock');

        $owner_user_id = (int) get_current_user_id();
        $blocked_user_id = isset($_POST['blocked_user_id']) ? absint($_POST['blocked_user_id']) : 0;
        $ok = self::set_block($owner_user_id, $blocked_user_id, false);
        self::redirect_back(['pl_rel' => $ok ? 'unblocked' : 'error']);
    }

    /**
     * @param array<string,string> $args
     */
    private static function redirect_back(array $args = []): void
    {
        $ref = wp_get_referer();
        $url = is_string($ref) && $ref !== '' ? $ref : home_url('/');
        foreach ($args as $k => $v) {
            $url = add_query_arg($k, $v, $url);
        }
        wp_safe_redirect($url);
        exit;
    }
}

