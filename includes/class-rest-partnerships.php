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
    private const REGISTER_QUERY_MODE = 'mode';
    private const REGISTER_QUERY_VALUE = 'register';
    private const REGISTER_QUERY_STATUS = 'status';
    private const REGISTER_STATUS_CREATED = 'created';
    private const REGISTER_FORM_ACTION = 'pl_accept_invite_register';

    public static function init(): void
    {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
        add_action('init', [__CLASS__, 'handle_invite_accept']);
        add_action('admin_post_nopriv_' . self::REGISTER_FORM_ACTION, [__CLASS__, 'handle_invite_register_submit']);
        add_action('admin_post_' . self::REGISTER_FORM_ACTION, [__CLASS__, 'handle_invite_register_submit']);
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
                'user_id' => [
                    'required' => false,
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
                    // Required for non-member invites. For member invites we accept user_id instead.
                    'required' => false,
                    'sanitize_callback' => 'sanitize_email',
                ],
            ],
        ]);

        register_rest_route('politeia/v1', '/partnerships/revoke', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'revoke_partner'],
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
                    'required' => false,
                    'sanitize_callback' => 'absint',
                ],
                'role' => [
                    'required' => false,
                    'sanitize_callback' => 'sanitize_key',
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
        $user_id = (int) ($request['user_id'] ?? 0);
        $first_name = sanitize_text_field((string) ($request['first_name'] ?? ''));
        $last_name = sanitize_text_field((string) ($request['last_name'] ?? ''));
        $email = sanitize_email((string) ($request['email'] ?? ''));
        $invitee_name = trim($first_name . ' ' . $last_name);

        if ($object_type !== 'course') {
            return new WP_Error('bad_request', 'Unsupported object type', ['status' => 400]);
        }

        if ($object_id <= 0) {
            return new WP_Error('bad_request', 'Invalid parameters', ['status' => 400]);
        }

        // Member invite: allow passing a user_id and resolve email/name from the user.
        if ($user_id > 0) {
            $user = get_userdata($user_id);
            if (!($user instanceof WP_User)) {
                return new WP_Error('bad_request', 'User not found', ['status' => 400]);
            }
            $email = sanitize_email((string) ($user->user_email ?? ''));
            $invitee_name = trim((string) ($user->display_name ?? ''));
        }

        if (!$email || !is_email($email)) {
            return new WP_Error('bad_request', 'Invalid parameters', ['status' => 400]);
        }

        $current_user = (int) get_current_user_id();
        if (!self::user_can_manage_course($current_user, $object_id)) {
            return new WP_Error('forbidden', 'Not allowed', ['status' => 403]);
        }

        // Allow inviting even if a partner already exists. When the invite is accepted,
        // PL_Partnerships_Repository::add_partner() will overwrite the single partner slot.
        // (We still revoke any existing pending invites for the same course/email in create_invite().)

        $token = self::create_invite($object_type, $object_id, $email, 'partner');
        if ('' === $token) {
            return new WP_Error('server_error', 'Failed to create invite', ['status' => 500]);
        }

        $accept_url = add_query_arg(
            ['token' => $token],
            home_url(self::ACCEPT_INVITE_PATH)
        );

        // Non-members go to a dedicated registration screen (still keyed by the same invite token).
        $existing_invitee = get_user_by('email', $email);
        if (!($existing_invitee instanceof WP_User)) {
            $accept_url = add_query_arg(
                [
                    self::REGISTER_QUERY_MODE => self::REGISTER_QUERY_VALUE,
                    'invite_first_name' => $first_name,
                    'invite_last_name' => $last_name,
                    'invite_email' => $email,
                ],
                $accept_url
            );
        }

        $mail_sent = true;
        if (class_exists('PL_Email') && method_exists('PL_Email', 'send_course_invite')) {
            $mail_sent = (bool) PL_Email::send_course_invite($email, $accept_url, $object_id, $invitee_name);
        } else {
            self::send_course_invite_email($email, $accept_url, $object_id, $invitee_name);
        }

        return rest_ensure_response([
            'success' => true,
            'accept_url' => $accept_url,
            'mail_sent' => $mail_sent,
        ]);
    }

    public static function revoke_partner(WP_REST_Request $request)
    {
        $object_type = (string) ($request['object_type'] ?? '');
        $object_id = (int) ($request['object_id'] ?? 0);
        $user_id = (int) ($request['user_id'] ?? 0);
        $role = sanitize_key((string) ($request['role'] ?? 'partner'));

        if ($object_type !== 'course') {
            return new WP_Error('bad_request', 'Unsupported object type', ['status' => 400]);
        }

        if ($object_id <= 0) {
            return new WP_Error('bad_request', 'Invalid parameters', ['status' => 400]);
        }

        $current_user = (int) get_current_user_id();
        if (!self::user_can_manage_course($current_user, $object_id)) {
            return new WP_Error('forbidden', 'Not allowed', ['status' => 403]);
        }

        if (!class_exists('PL_Partnerships_Repository') || !method_exists('PL_Partnerships_Repository', 'revoke_partner')) {
            return new WP_Error('server_error', 'Partnerships repository not available', ['status' => 500]);
        }

        $ok = (bool) PL_Partnerships_Repository::revoke_partner($object_type, $object_id, $user_id, $role);

        return rest_ensure_response([
            'success' => $ok ? true : false,
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

        // BuddyBoss/BuddyPress friends integration is optional. If it's not present (or user has no friends),
        // fall back to searching all platform users by display name / login.
        $query_args = [
            'search' => '*' . $term . '*',
            'search_columns' => ['display_name', 'user_login', 'user_email'],
            'number' => 3,
            'orderby' => 'display_name',
            'order' => 'ASC',
            'exclude' => [$current_user],
        ];

        if (function_exists('friends_get_friend_user_ids')) {
            $friend_ids = friends_get_friend_user_ids($current_user);
            $friend_ids = array_values(array_unique(array_filter(array_map('absint', (array) $friend_ids))));
            if (!empty($friend_ids)) {
                $query_args['include'] = $friend_ids;
            }
        }

        $users = get_users($query_args);

        $out = array_map(static function ($u) {
            $avatar = function_exists('get_avatar_url') ? (string) get_avatar_url((int) $u->ID, ['size' => 48]) : '';
            return [
                'id' => (int) $u->ID,
                'name' => (string) $u->display_name,
                'avatar_url' => $avatar,
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

        // Final confirmation screen after registration+acceptance.
        $status = isset($_GET[self::REGISTER_QUERY_STATUS]) ? sanitize_key((string) wp_unslash($_GET[self::REGISTER_QUERY_STATUS])) : '';
        if ($status === self::REGISTER_STATUS_CREATED) {
            self::render_invite_success_page();
            exit;
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
            $invite_email = self::normalize_email((string) ($invite['invitee_email'] ?? ''));
            $existing = $invite_email !== '' ? get_user_by('email', $invite_email) : null;

            $mode = isset($_GET[self::REGISTER_QUERY_MODE]) ? sanitize_key((string) wp_unslash($_GET[self::REGISTER_QUERY_MODE])) : '';
            if ($mode === self::REGISTER_QUERY_VALUE && !($existing instanceof WP_User)) {
                $first_name = isset($_GET['invite_first_name']) ? sanitize_text_field((string) wp_unslash($_GET['invite_first_name'])) : '';
                $last_name = isset($_GET['invite_last_name']) ? sanitize_text_field((string) wp_unslash($_GET['invite_last_name'])) : '';
                self::render_invite_register_page($raw_token, $invite, $first_name, $last_name);
                exit;
            }

            $redirect_back = add_query_arg(['token' => $raw_token], home_url(self::ACCEPT_INVITE_PATH));

            if (class_exists('PL_Auth_Login_Register') && method_exists('PL_Auth_Login_Register', 'build_modal_url')) {
                $args = [];
                if ($invite_email !== '') {
                    $args['invite_email'] = $invite_email;
                }
                wp_safe_redirect(PL_Auth_Login_Register::build_modal_url('login', $redirect_back, $args));
                exit;
            }

            wp_redirect(wp_login_url($redirect_back));
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

    public static function handle_invite_register_submit(): void
    {
        $token = isset($_POST['token']) ? strtolower(trim((string) wp_unslash($_POST['token']))) : '';
        $first_name = isset($_POST['first_name']) ? sanitize_text_field((string) wp_unslash($_POST['first_name'])) : '';
        $last_name = isset($_POST['last_name']) ? sanitize_text_field((string) wp_unslash($_POST['last_name'])) : '';
        $password = isset($_POST['password']) ? (string) wp_unslash($_POST['password']) : '';
        $password_confirm = isset($_POST['password_confirm']) ? (string) wp_unslash($_POST['password_confirm']) : '';
        $nonce = isset($_POST['nonce']) ? (string) wp_unslash($_POST['nonce']) : '';

        if (!wp_verify_nonce($nonce, self::REGISTER_FORM_ACTION)) {
            wp_safe_redirect(home_url(self::ACCEPT_INVITE_PATH));
            exit;
        }

        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            wp_safe_redirect(home_url(self::ACCEPT_INVITE_PATH));
            exit;
        }

        if ($password === '' || strlen($password) < 8 || $password !== $password_confirm) {
            $back = add_query_arg(
                [
                    'token' => $token,
                    self::REGISTER_QUERY_MODE => self::REGISTER_QUERY_VALUE,
                    'invite_first_name' => $first_name,
                    'invite_last_name' => $last_name,
                    'err' => 'password',
                ],
                home_url(self::ACCEPT_INVITE_PATH)
            );
            wp_safe_redirect($back);
            exit;
        }

        $invite = self::get_pending_invite_by_raw_token($token);
        if (!$invite) {
            wp_safe_redirect(home_url(self::ACCEPT_INVITE_PATH));
            exit;
        }

        $email = sanitize_email((string) ($invite['invitee_email'] ?? ''));
        if ($email === '' || !is_email($email)) {
            wp_safe_redirect(home_url(self::ACCEPT_INVITE_PATH));
            exit;
        }

        $existing = get_user_by('email', $email);
        if ($existing instanceof WP_User) {
            // If user exists, route them to login (invite acceptance will happen after login).
            wp_safe_redirect(wp_login_url(add_query_arg(['token' => $token], home_url(self::ACCEPT_INVITE_PATH))));
            exit;
        }

        $username = self::generate_username_from_email($email);
        $user_id = wp_create_user($username, $password, $email);
        if (is_wp_error($user_id)) {
            wp_safe_redirect(home_url(self::ACCEPT_INVITE_PATH));
            exit;
        }

        if ($first_name !== '') {
            update_user_meta((int) $user_id, 'first_name', $first_name);
        }
        if ($last_name !== '') {
            update_user_meta((int) $user_id, 'last_name', $last_name);
        }

        // Mark verified (skip email confirmation flow for invite-based onboarding).
        update_user_meta((int) $user_id, 'pl_auth_email_verified', 1);
        delete_user_meta((int) $user_id, 'pl_auth_verification_token_hash');
        delete_user_meta((int) $user_id, 'pl_auth_verification_token_expires');

        // Log the user in.
        wp_set_current_user((int) $user_id);
        wp_set_auth_cookie((int) $user_id, true);

        // Accept the invite now that the user exists and is logged in.
        $ok = self::accept_invite_for_user($token, (int) $user_id);
        if (!$ok) {
            wp_safe_redirect(home_url(self::ACCEPT_INVITE_PATH));
            exit;
        }

        $redirect = add_query_arg(
            [
                self::REGISTER_QUERY_STATUS => self::REGISTER_STATUS_CREATED,
                'token' => $token,
            ],
            home_url(self::ACCEPT_INVITE_PATH)
        );

        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function get_pending_invite_by_raw_token(string $raw_token): ?array
    {
        $raw_token = strtolower(trim($raw_token));
        if (!preg_match('/^[a-f0-9]{64}$/', $raw_token)) {
            return null;
        }

        $token_hash = hash('sha256', $raw_token);

        global $wpdb;
        if (!$wpdb) {
            return null;
        }

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

        return is_array($invite) ? $invite : null;
    }

    private static function accept_invite_for_user(string $raw_token, int $user_id): bool
    {
        $invite = self::get_pending_invite_by_raw_token($raw_token);
        if (!$invite) {
            return false;
        }

        // Expiry check.
        global $wpdb;
        if (!$wpdb) {
            return false;
        }
        $invites_table = $wpdb->prefix . self::INVITES_TABLE_SLUG;

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
            return false;
        }

        $user = get_userdata($user_id);
        if (!($user instanceof WP_User)) {
            return false;
        }

        $current_email = self::normalize_email((string) ($user->user_email ?? ''));
        $invite_email = self::normalize_email((string) ($invite['invitee_email'] ?? ''));
        if ('' === $current_email || '' === $invite_email || $current_email !== $invite_email) {
            return false;
        }

        $object_type = (string) ($invite['object_type'] ?? 'reading_plan');
        $object_id = (int) ($invite['object_id'] ?? 0);
        $role = sanitize_key((string) ($invite['role'] ?? 'observer'));

        if ($object_id <= 0) {
            return false;
        }

        if (!class_exists('PL_Partnerships_Repository') || !method_exists('PL_Partnerships_Repository', 'add_partner')) {
            return false;
        }

        $ok = PL_Partnerships_Repository::add_partner($object_type, $object_id, $user_id, $role);
        if (!$ok) {
            return false;
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

        return true;
    }

    private static function generate_username_from_email(string $email): string
    {
        $base = sanitize_user(strstr($email, '@', true) ?: $email, true);
        if ($base === '') {
            $base = 'user';
        }

        $username = $base;
        $i = 1;
        while (username_exists($username)) {
            $username = $base . $i;
            $i++;
        }

        return $username;
    }

    private static function render_invite_register_page(string $raw_token, array $invite, string $first_name, string $last_name): void
    {
        $email = sanitize_email((string) ($invite['invitee_email'] ?? ''));
        $first_name = sanitize_text_field($first_name);
        $last_name = sanitize_text_field($last_name);
        $err = isset($_GET['err']) ? sanitize_key((string) wp_unslash($_GET['err'])) : '';

        if (function_exists('pl_template_open')) {
            pl_template_open();
        }

        $nonce = wp_create_nonce(self::REGISTER_FORM_ACTION);
        include PL_PATH . 'templates/accept-invite-register.php';

        if (function_exists('pl_template_close')) {
            pl_template_close();
        }
    }

    private static function render_invite_success_page(): void
    {
        $user_id = (int) get_current_user_id();
        $course_id = 0;

        $token = isset($_GET['token']) ? strtolower(trim((string) wp_unslash($_GET['token']))) : '';
        if (preg_match('/^[a-f0-9]{64}$/', $token)) {
            // When redirecting here, the invite is already accepted, so it is no longer "pending".
            $invite = self::get_invite_by_raw_token($token);
            if (is_array($invite)) {
                $course_id = (int) ($invite['object_id'] ?? 0);
            }
        }

        $course_url = $course_id > 0 ? (string) get_permalink($course_id) : home_url('/dashboard');

        $u = $user_id > 0 ? get_userdata($user_id) : null;
        $user_login = ($u instanceof WP_User) ? (string) $u->user_login : '';
        $profile_url = $user_login !== '' ? home_url('/members/' . rawurlencode($user_login) . '/center-2/?section=profile') : home_url('/members/');

        if (function_exists('pl_template_open')) {
            pl_template_open();
        }

        include PL_PATH . 'templates/accept-invite-success.php';

        if (function_exists('pl_template_close')) {
            pl_template_close();
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function get_invite_by_raw_token(string $raw_token): ?array
    {
        $raw_token = strtolower(trim($raw_token));
        if (!preg_match('/^[a-f0-9]{64}$/', $raw_token)) {
            return null;
        }

        $token_hash = hash('sha256', $raw_token);

        global $wpdb;
        if (!$wpdb) {
            return null;
        }

        $invites_table = $wpdb->prefix . self::INVITES_TABLE_SLUG;
        $invite = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT *
                 FROM {$invites_table}
                 WHERE token_hash = %s
                 LIMIT 1",
                $token_hash
            ),
            ARRAY_A
        );

        return is_array($invite) ? $invite : null;
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
            // Best-effort: the invites table is owned by Politeia Bookshelf (Reading Planner).
            if (class_exists('\\Politeia\\ReadingPlanner\\Installer')) {
                try {
                    \Politeia\ReadingPlanner\Installer::install();
                } catch (\Throwable $e) {
                    // ignore
                }
            }

            $table_exists = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table);
            if (!$table_exists) {
                return '';
            }
        }

        // Determine user id if the invitee already exists.
        $invitee_user = get_user_by('email', $email);
        $invitee_user_id = $invitee_user instanceof WP_User ? (int) $invitee_user->ID : 0;

        // If the table has invitee_user_id, populate it when possible.
        $has_invitee_user_id = self::invites_table_column_exists($table, 'invitee_user_id');

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
        $has_revoked_at = self::invites_table_column_exists($table, 'revoked_at');
        if ($has_revoked_at) {
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table}
                     SET status = %s, revoked_at = %s, updated_at = %s
                     WHERE object_type = %s
                       AND object_id = %d
                       AND role = %s
                       AND status = %s",
                    'revoked',
                    $now,
                    $now,
                    $object_type,
                    $object_id,
                    $role,
                    'pending'
                )
            );
        } else {
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table}
                     SET status = %s, updated_at = %s
                     WHERE object_type = %s
                       AND object_id = %d
                       AND role = %s
                       AND status = %s",
                    'revoked',
                    $now,
                    $object_type,
                    $object_id,
                    $role,
                    'pending'
                )
            );
        }

        // plan_id is legacy + NOT NULL; keep it populated as object_id for all object types.
        $inserted = $wpdb->insert(
            $table,
            [
                'plan_id' => $object_id,
                'object_type' => $object_type,
                'object_id' => $object_id,
                'inviter_user_id' => (int) get_current_user_id(),
                'invitee_email' => $email,
                'invitee_user_id' => ($has_invitee_user_id && $invitee_user_id > 0) ? $invitee_user_id : null,
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
