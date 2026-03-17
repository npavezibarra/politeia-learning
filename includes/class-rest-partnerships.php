<?php
/**
 * REST endpoints for partnerships.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PL_Rest_Partnerships
{
    private const COURSE_POST_TYPE = 'sfwd-courses';
    private const COURSE_TEACHERS_META_KEY = '_pcg_course_teachers';
    private const INVITES_TABLE_SLUG = 'politeia_plan_participant_invites';
    private const ACCEPT_INVITE_PATH = '/accept-invite';

    public static function init(): void
    {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
        add_action('init', [__CLASS__, 'handle_invite_accept']);
    }

    public static function register_routes(): void
    {
        register_rest_route('politeia/v1', '/partnerships/add', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'add_partner'],
            'permission_callback' => static function () {
                return is_user_logged_in();
            },
            'args' => [
                'object_type' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_key',
                ],
                'object_id' => [
                    'required' => true,
                    'sanitize_callback' => 'absint',
                ],
                'user_id' => [
                    'required' => true,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        register_rest_route('politeia/v1', '/partnerships/invite', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'invite_partner'],
            'permission_callback' => static function () {
                return is_user_logged_in();
            },
            'args' => [
                'object_type' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_key',
                ],
                'object_id' => [
                    'required' => true,
                    'sanitize_callback' => 'absint',
                ],
                'first_name' => [
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'last_name' => [
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'email' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_email',
                ],
            ],
        ]);

        register_rest_route('politeia/v1', '/friends/search', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'search_friends'],
            'permission_callback' => static function () {
                return is_user_logged_in();
            },
            'args' => [
                'q' => [
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field',
                    'default' => '',
                ],
            ],
        ]);
    }

    public static function add_partner(WP_REST_Request $request)
    {
        $object_type = (string) ($request['object_type'] ?? '');
        $object_id = (int) ($request['object_id'] ?? 0);
        $user_id = (int) ($request['user_id'] ?? 0);

        if ($object_type !== 'course') {
            return new WP_Error('bad_request', 'Unsupported object type', ['status' => 400]);
        }

        if ($object_id <= 0 || $user_id <= 0) {
            return new WP_Error('bad_request', 'Invalid parameters', ['status' => 400]);
        }

        if (!get_userdata($user_id)) {
            return new WP_Error('bad_request', 'User not found', ['status' => 400]);
        }

        $current_user = (int) get_current_user_id();

        // Ensure current user owns (or can manage) the course.
        if (!self::user_can_manage_course($current_user, $object_id)) {
            return new WP_Error('forbidden', 'Not allowed', ['status' => 403]);
        }

        if (!class_exists('PL_Partnerships_Repository') || !method_exists('PL_Partnerships_Repository', 'add_partner')) {
            return new WP_Error('server_error', 'Partnerships repository not available', ['status' => 500]);
        }

        $result = PL_Partnerships_Repository::add_partner($object_type, $object_id, $user_id, 'partner');

        return rest_ensure_response([
            'success' => $result ? true : false,
        ]);
    }

    public static function invite_partner(WP_REST_Request $request)
    {
        $object_type = (string) ($request['object_type'] ?? '');
        $object_id = (int) ($request['object_id'] ?? 0);
        $first_name = sanitize_text_field((string) ($request['first_name'] ?? ''));
        $last_name = sanitize_text_field((string) ($request['last_name'] ?? ''));
        $email = sanitize_email((string) ($request['email'] ?? ''));
        $invitee_name = trim($first_name . ' ' . $last_name);

        if ($object_type !== 'course') {
            return new WP_Error('bad_request', 'Unsupported object type', ['status' => 400]);
        }

        if ($object_id <= 0 || !$email || !is_email($email)) {
            return new WP_Error('bad_request', 'Invalid parameters', ['status' => 400]);
        }

        $current_user = (int) get_current_user_id();
        if (!self::user_can_manage_course($current_user, $object_id)) {
            return new WP_Error('forbidden', 'Not allowed', ['status' => 403]);
        }

        // If a partner already exists, do not allow inviting another.
        if (class_exists('PL_Partnerships_Repository') && method_exists('PL_Partnerships_Repository', 'get_object_partners')) {
            $partners = PL_Partnerships_Repository::get_object_partners('course', $object_id);
            if (!empty($partners)) {
                return new WP_Error('conflict', 'Partner already exists', ['status' => 409]);
            }
        }

        $token = self::create_invite($object_type, $object_id, $email, 'partner');
        if ('' === $token) {
            return new WP_Error('server_error', 'Failed to create invite', ['status' => 500]);
        }

        $accept_url = add_query_arg(
            ['token' => $token],
            home_url(self::ACCEPT_INVITE_PATH)
        );

        if (class_exists('PL_Email') && method_exists('PL_Email', 'send_course_invite')) {
            PL_Email::send_course_invite($email, $accept_url, $object_id, $invitee_name);
        } else {
            self::send_course_invite_email($email, $accept_url, $object_id, $invitee_name);
        }

        return rest_ensure_response([
            'success' => true,
            'accept_url' => $accept_url,
        ]);
    }

    public static function search_friends(WP_REST_Request $request)
    {
        $term = trim((string) ($request['q'] ?? ''));
        $current_user = (int) get_current_user_id();

        $term_len = function_exists('mb_strlen') ? mb_strlen($term) : strlen($term);
        if ($term === '' || $term_len < 2) {
            return rest_ensure_response([]);
        }

        if (!function_exists('friends_get_friend_user_ids')) {
            return rest_ensure_response([]);
        }

        $friend_ids = friends_get_friend_user_ids($current_user);
        $friend_ids = array_values(array_unique(array_filter(array_map('absint', (array) $friend_ids))));

        if (empty($friend_ids)) {
            return rest_ensure_response([]);
        }

        $users = get_users([
            'include' => $friend_ids,
            'search' => '*' . $term . '*',
            'search_columns' => ['user_login', 'display_name'],
            'number' => 20,
        ]);

        $out = array_map(static function ($u) {
            return [
                'id' => (int) $u->ID,
                'name' => (string) $u->display_name,
            ];
        }, (array) $users);

        return rest_ensure_response($out);
    }

    /**
     * Handle accepting an invite token via /accept-invite?token=... .
     *
     * This is intentionally NOT a REST endpoint to keep the "click email link" UX simple.
     */
    public static function handle_invite_accept(): void
    {
        $path = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
        $path = (string) parse_url($path, PHP_URL_PATH);
        $path = rtrim($path, '/');

        if ($path !== rtrim(self::ACCEPT_INVITE_PATH, '/')) {
            return;
        }

        if (!isset($_GET['token'])) {
            return;
        }

        $raw_token = strtolower(trim((string) wp_unslash($_GET['token'])));
        if (!preg_match('/^[a-f0-9]{64}$/', $raw_token)) {
            return;
        }

        $token_hash = hash('sha256', $raw_token);

        global $wpdb;
        $invites_table = $wpdb->prefix . self::INVITES_TABLE_SLUG;

        $invite = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT *
                 FROM {$invites_table}
                 WHERE token_hash = %s
                   AND status = %s
                 LIMIT 1",
                $token_hash,
                'pending'
            ),
            ARRAY_A
        );

        if (!$invite) {
            return;
        }

        // Expiry check (table stores UTC-ish datetimes as plain DATETIME).
        $now_ts = current_time('timestamp', true);
        $expires_ts = strtotime((string) ($invite['expires_at'] ?? '') . ' UTC');
        if ($expires_ts && $expires_ts < $now_ts) {
            $now = current_time('mysql');
            $wpdb->update(
                $invites_table,
                [
                    'status' => 'expired',
                    'updated_at' => $now,
                ],
                ['id' => (int) ($invite['id'] ?? 0)],
                ['%s', '%s'],
                ['%d']
            );
            return;
        }

        $user_id = (int) get_current_user_id();
        if ($user_id <= 0) {
            wp_redirect(wp_login_url(home_url(self::ACCEPT_INVITE_PATH) . '?token=' . rawurlencode($raw_token)));
            exit;
        }

        $current_user = wp_get_current_user();
        $current_email = self::normalize_email((string) ($current_user->user_email ?? ''));
        $invite_email = self::normalize_email((string) ($invite['invitee_email'] ?? ''));
        if ('' === $current_email || '' === $invite_email || $current_email !== $invite_email) {
            return;
        }

        $object_type = (string) ($invite['object_type'] ?? 'reading_plan');
        $object_id = (int) ($invite['object_id'] ?? 0);
        $role = sanitize_key((string) ($invite['role'] ?? 'observer'));

        if ($object_id <= 0) {
            return;
        }

        if (!class_exists('PL_Partnerships_Repository') || !method_exists('PL_Partnerships_Repository', 'add_partner')) {
            return;
        }

        $ok = PL_Partnerships_Repository::add_partner($object_type, $object_id, $user_id, $role);
        if (!$ok) {
            return;
        }

        $now = current_time('mysql');
        $wpdb->update(
            $invites_table,
            [
                'status' => 'accepted',
                'accepted_at' => $now,
                'invitee_user_id' => $user_id,
                'updated_at' => $now,
            ],
            ['id' => (int) ($invite['id'] ?? 0)],
            ['%s', '%s', '%d', '%s'],
            ['%d']
        );

        $redirect = home_url('/dashboard');
        if ($object_type === 'course') {
            $maybe = get_permalink($object_id);
            if ($maybe) {
                $redirect = $maybe;
            }
        }

        wp_redirect($redirect);
        exit;
    }

    private static function user_can_manage_course(int $user_id, int $course_id): bool
    {
        if ($user_id <= 0 || $course_id <= 0) {
            return false;
        }

        if (current_user_can('manage_options')) {
            return true;
        }

        $post = get_post($course_id);
        if (!$post || ($post->post_type ?? '') !== self::COURSE_POST_TYPE) {
            return false;
        }

        $author_id = (int) ($post->post_author ?? 0);
        if ($author_id === $user_id) {
            return true;
        }

        $teacher_ids = get_post_meta($course_id, self::COURSE_TEACHERS_META_KEY, false);
        $teacher_ids = array_map('absint', (array) $teacher_ids);
        if (in_array($user_id, $teacher_ids, true)) {
            return true;
        }

        return false;
    }

    private static function normalize_email(string $email): string
    {
        return strtolower(trim($email));
    }

    private static function invites_table_column_exists(string $table, string $column): bool
    {
        global $wpdb;
        return (bool) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s LIMIT 1',
                $table,
                $column
            )
        );
    }

    private static function create_invite(string $object_type, int $object_id, string $email, string $role = 'observer'): string
    {
        global $wpdb;
        if (!$wpdb) {
            return '';
        }

        $object_type = sanitize_key($object_type);
        $object_id = (int) $object_id;
        $email = sanitize_email($email);
        $role = sanitize_key($role ?: 'observer');

        if ($object_type === '' || $object_id <= 0 || !$email || !is_email($email)) {
            return '';
        }

        $table = $wpdb->prefix . self::INVITES_TABLE_SLUG;
        $table_exists = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table);
        if (!$table_exists) {
            return '';
        }

        // We require the new columns for course invites.
        $has_object_type = self::invites_table_column_exists($table, 'object_type');
        $has_object_id = self::invites_table_column_exists($table, 'object_id');
        if (!$has_object_type || !$has_object_id) {
            return '';
        }

        $token = bin2hex(random_bytes(32));
        $token_hash = hash('sha256', $token);
        $now = current_time('mysql');
        $expires_at = gmdate('Y-m-d H:i:s', current_time('timestamp', true) + (7 * DAY_IN_SECONDS));

        // Revoke any existing pending invites for this object/email pair.
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table}
                 SET status = %s, revoked_at = %s, updated_at = %s
                 WHERE object_type = %s
                   AND object_id = %d
                   AND invitee_email = %s
                   AND status = %s",
                'revoked',
                $now,
                $now,
                $object_type,
                $object_id,
                $email,
                'pending'
            )
        );

        // plan_id is legacy + NOT NULL; keep it populated as object_id for all object types.
        $inserted = $wpdb->insert(
            $table,
            [
                'plan_id' => $object_id,
                'object_type' => $object_type,
                'object_id' => $object_id,
                'inviter_user_id' => (int) get_current_user_id(),
                'invitee_email' => $email,
                'invitee_user_id' => null,
                'role' => $role,
                'status' => 'pending',
                'token_hash' => $token_hash,
                'expires_at' => $expires_at,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                '%d',
                '%s',
                '%d',
                '%d',
                '%s',
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
            ]
        );

        if (false === $inserted) {
            return '';
        }

        return $token;
    }

    private static function send_course_invite_email(string $email, string $accept_url, int $course_id, string $invitee_name = ''): void
    {
        $course_title = $course_id > 0 ? get_the_title($course_id) : '';
        $subject = $course_title ? sprintf('Course invitation: %s', $course_title) : 'Course invitation';

        $hello = $invitee_name !== '' ? sprintf('<p>Hi %s,</p>', esc_html($invitee_name)) : '';
        $body = sprintf(
            '%s<p>You have been invited to join a course as a partner.</p><p><a href="%s">Accept invitation</a></p>',
            $hello,
            esc_url($accept_url)
        );

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        wp_mail($email, $subject, $body, $headers);
    }
}
