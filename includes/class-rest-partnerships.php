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
    private const LEARNI_COURSE_POST_TYPE = 'learni_course';
    private const COURSE_TEACHERS_META_KEY = '_pcg_course_teachers';
    private const PARTNERSHIPS_TABLE_SLUG = 'politeia_user_object_partnerships';
    private const LEGACY_INVITES_TABLE_SLUG = 'politeia_plan_participant_invites';
    private const ACCEPT_INVITE_PATH = '/accept-invite';
    private const REGISTER_QUERY_MODE = 'mode';
    private const REGISTER_QUERY_VALUE = 'register';
    private const REGISTER_QUERY_STATUS = 'status';
    private const REGISTER_STATUS_CREATED = 'created';
    private const REGISTER_FORM_ACTION = 'pl_accept_invite_register';
    private const INVITE_RESPOND_ACTION = 'pl_course_partner_invite_respond';
    private const INVITE_RESPOND_NONCE_ACTION = 'pl_course_partner_invite_respond';

    public static function init(): void
    {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
        add_action('init', [__CLASS__, 'handle_invite_accept']);
        add_action('admin_post_nopriv_' . self::REGISTER_FORM_ACTION, [__CLASS__, 'handle_invite_register_submit']);
        add_action('admin_post_' . self::REGISTER_FORM_ACTION, [__CLASS__, 'handle_invite_register_submit']);
        add_action('admin_post_' . self::INVITE_RESPOND_ACTION, [__CLASS__, 'handle_course_partner_invite_respond']);
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

        $result = PL_Partnerships_Repository::add_partner($object_type, $object_id, $user_id, 'partner', $current_user);

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

        $invite = self::get_pending_invite_by_raw_token($raw_token);
        if (!$invite) {
            return;
        }

        // Expiry check (table stores UTC-ish datetimes as plain DATETIME).
        $now_ts = current_time('timestamp', true);
        $expires_ts = strtotime((string) ($invite['expires_at'] ?? '') . ' UTC');
        if ($expires_ts && $expires_ts < $now_ts) {
            self::mark_invite_expired($invite);
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

        $ok = self::accept_invite_for_user($raw_token, $user_id);
        if (!$ok) {
            return;
        }

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

    private static function mark_invite_expired(array $invite): void
    {
        global $wpdb;
        if (!$wpdb) {
            return;
        }

        $invite_id = (int) ($invite['id'] ?? 0);
        if ($invite_id <= 0) {
            return;
        }

        $source = (string) ($invite['_pl_source'] ?? 'legacy');
        $now = current_time('mysql');

        if ($source === 'partnerships') {
            $table = $wpdb->prefix . self::PARTNERSHIPS_TABLE_SLUG;
            $wpdb->update(
                $table,
                [
                    'status' => 'expired',
                    'updated_at' => $now,
                ],
                ['id' => $invite_id],
                ['%s', '%s'],
                ['%d']
            );
            return;
        }

        $table = $wpdb->prefix . self::LEGACY_INVITES_TABLE_SLUG;
        $wpdb->update(
            $table,
            [
                'status' => 'expired',
                'updated_at' => $now,
            ],
            ['id' => $invite_id],
            ['%s', '%s'],
            ['%d']
        );
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

        $partnerships_table = $wpdb->prefix . self::PARTNERSHIPS_TABLE_SLUG;
        $partnerships_exists = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $partnerships_table)) === $partnerships_table);

        if ($partnerships_exists && self::invites_table_column_exists($partnerships_table, 'invitation_token_hash')) {
            $invite = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT *
                     FROM {$partnerships_table}
                     WHERE invitation_token_hash = %s
                       AND status = %s
                     LIMIT 1",
                    $token_hash,
                    'pending'
                ),
                ARRAY_A
            );

            if (is_array($invite)) {
                $invite['_pl_source'] = 'partnerships';
                return $invite;
            }
        }

        $invites_table = $wpdb->prefix . self::LEGACY_INVITES_TABLE_SLUG;
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

        if (is_array($invite)) {
            $invite['_pl_source'] = 'legacy';
            return $invite;
        }

        return null;
    }

    private static function accept_invite_for_user(string $raw_token, int $user_id): bool
    {
        $invite = self::get_pending_invite_by_raw_token($raw_token);
        if (!$invite) {
            return false;
        }

        $now_ts = current_time('timestamp', true);
        $expires_ts = strtotime((string) ($invite['expires_at'] ?? '') . ' UTC');
        if ($expires_ts && $expires_ts < $now_ts) {
            self::mark_invite_expired($invite);
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

        $already_partner = false;
        if (method_exists('PL_Partnerships_Repository', 'get_object_partners_by_role')) {
            $rows = PL_Partnerships_Repository::get_object_partners_by_role($object_type, $object_id, $role);
            if (is_array($rows) && !empty($rows)) {
                $first = $rows[0] ?? null;
                if (is_array($first) && (int) ($first['partner_user_id'] ?? 0) === $user_id) {
                    $already_partner = true;
                }
            }
        }

        if (!$already_partner) {
            $owner_user_id = (int) ($invite['owner_user_id'] ?? 0);
            $ok = PL_Partnerships_Repository::add_partner($object_type, $object_id, $user_id, $role, $owner_user_id);
            if (!$ok) {
                return false;
            }
        }

        // Partner invites must grant course access (Learni: active enrollment).
        if ($object_type === 'course' && $role === 'partner') {
            self::ensure_course_enrollment_for_partner($user_id, $object_id);
        }

        $now = current_time('mysql');
        global $wpdb;
        if (!$wpdb) {
            return false;
        }

        $invite_id = (int) ($invite['id'] ?? 0);
        if ($invite_id <= 0) {
            return false;
        }

        $source = (string) ($invite['_pl_source'] ?? 'legacy');
        if ($source === 'partnerships') {
            $table = $wpdb->prefix . self::PARTNERSHIPS_TABLE_SLUG;
            $updated = $wpdb->update(
                $table,
                [
                    'status' => 'accepted',
                    'accepted_at' => $now,
                    'updated_at' => $now,
                ],
                ['id' => $invite_id],
                ['%s', '%s', '%s'],
                ['%d']
            );

            return $updated !== false;
        }

        $table = $wpdb->prefix . self::LEGACY_INVITES_TABLE_SLUG;
        $updated = $wpdb->update(
            $table,
            [
                'status' => 'accepted',
                'accepted_at' => $now,
                'invitee_user_id' => $user_id,
                'updated_at' => $now,
            ],
            ['id' => $invite_id],
            ['%s', '%s', '%d', '%s'],
            ['%d']
        );

        return $updated !== false;
    }

    private static function ensure_course_enrollment_for_partner(int $user_id, int $course_id): void
    {
        if ($user_id <= 0 || $course_id <= 0) {
            return;
        }

        if (!class_exists('\\Learni\\Database\\Enrollments')) {
            return;
        }

        // Avoid rewriting an existing active enrollment unless it's a previously-misclassified partner enrollment.
        if (method_exists('\\Learni\\Database\\Enrollments', 'get_enrollment')) {
            $row = \Learni\Database\Enrollments::get_enrollment($user_id, $course_id);
            if (is_array($row) && (($row['status'] ?? '') === \Learni\Database\Enrollments::STATUS_ACTIVE)) {
                $source = (string) ($row['source'] ?? '');
                $provider = (string) ($row['payment_provider'] ?? '');
                $ref = (string) ($row['payment_reference'] ?? '');

                // If it was a partner enrollment but missing the provider marker, normalize it.
                if ($source === \Learni\Database\Enrollments::SOURCE_MANUAL && $ref === 'course_partner' && $provider !== 'partner_invite') {
                    \Learni\Database\Enrollments::upsert($user_id, $course_id, [
                        'status' => \Learni\Database\Enrollments::STATUS_ACTIVE,
                        'source' => \Learni\Database\Enrollments::SOURCE_MANUAL,
                        'payment_provider' => 'partner_invite',
                        'payment_reference' => 'course_partner',
                    ]);
                }
                return;
            }
        }

        \Learni\Database\Enrollments::upsert($user_id, $course_id, [
            'status' => \Learni\Database\Enrollments::STATUS_ACTIVE,
            'source' => \Learni\Database\Enrollments::SOURCE_MANUAL,
            'payment_provider' => 'partner_invite',
            'payment_reference' => 'course_partner',
        ]);
    }

    public static function handle_course_partner_invite_respond(): void
    {
        if (!is_user_logged_in()) {
            auth_redirect();
            exit;
        }

        $redirect_to = isset($_POST['redirect_to']) ? (string) wp_unslash($_POST['redirect_to']) : '';
        $redirect_to = $redirect_to !== '' ? esc_url_raw($redirect_to) : home_url('/');

        $nonce = isset($_POST['_wpnonce']) ? (string) wp_unslash($_POST['_wpnonce']) : '';
        if ($nonce === '' || !wp_verify_nonce($nonce, self::INVITE_RESPOND_NONCE_ACTION)) {
            wp_safe_redirect(add_query_arg(['pl_cp_invite' => 'invalid_nonce'], $redirect_to));
            exit;
        }

        $invite_id = isset($_POST['invite_id']) ? absint((string) wp_unslash($_POST['invite_id'])) : 0;
        $source = isset($_POST['source']) ? sanitize_key((string) wp_unslash($_POST['source'])) : 'partnerships';
        $decision = isset($_POST['decision']) ? sanitize_key((string) wp_unslash($_POST['decision'])) : '';

        if ($invite_id <= 0 || ($decision !== 'accept' && $decision !== 'reject')) {
            wp_safe_redirect(add_query_arg(['pl_cp_invite' => 'bad_request'], $redirect_to));
            exit;
        }

        $user_id = (int) get_current_user_id();
        $ok = false;
        if ($decision === 'accept') {
            $ok = self::accept_course_partner_invite_by_id($source, $invite_id, $user_id);
        } else {
            $ok = self::decline_course_partner_invite_by_id($source, $invite_id, $user_id);
        }

        $args = [
            'pl_cp_invite' => $ok ? ($decision === 'accept' ? 'accepted' : 'declined') : 'failed',
        ];
        if ($ok && $decision === 'accept') {
            // Preserve context so the profile page can show a "just accepted" dropdown.
            $args['pl_cp_invite_id'] = $invite_id;
            $args['pl_cp_invite_source'] = $source;
        }
        // Keep the user on the Requests tab in the profile UI.
        $args['tab'] = 'requests';

        wp_safe_redirect(add_query_arg($args, $redirect_to));
        exit;
    }

    private static function accept_course_partner_invite_by_id(string $source, int $invite_id, int $user_id): bool
    {
        global $wpdb;
        if (!$wpdb || $invite_id <= 0 || $user_id <= 0) {
            return false;
        }

        $user = get_userdata($user_id);
        if (!($user instanceof \WP_User)) {
            return false;
        }
        $current_email = self::normalize_email((string) ($user->user_email ?? ''));
        if ($current_email === '') {
            return false;
        }

        $now_ts = current_time('timestamp', true);
        $now = current_time('mysql');

        if ($source === 'legacy') {
            $table = $wpdb->prefix . self::LEGACY_INVITES_TABLE_SLUG;
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id, object_type, object_id, role, status, invitee_email, expires_at
                     FROM {$table}
                     WHERE id = %d
                     LIMIT 1",
                    $invite_id
                ),
                ARRAY_A
            );
            if (!is_array($row)) {
                return false;
            }

            if (sanitize_key((string) ($row['object_type'] ?? '')) !== 'course') {
                return false;
            }
            if (sanitize_key((string) ($row['role'] ?? '')) !== 'partner') {
                return false;
            }
            if (sanitize_key((string) ($row['status'] ?? '')) !== 'pending') {
                return false;
            }
            $invite_email = self::normalize_email((string) ($row['invitee_email'] ?? ''));
            if ($invite_email === '' || $invite_email !== $current_email) {
                return false;
            }

            $expires_ts = strtotime((string) ($row['expires_at'] ?? '') . ' UTC');
            if ($expires_ts && $expires_ts < $now_ts) {
                return false;
            }

            $object_id = (int) ($row['object_id'] ?? 0);
            if ($object_id <= 0) {
                return false;
            }
            if (!class_exists('PL_Partnerships_Repository') || !method_exists('PL_Partnerships_Repository', 'add_partner')) {
                return false;
            }
            if (!PL_Partnerships_Repository::add_partner('course', $object_id, $user_id, 'partner', 0)) {
                return false;
            }

            self::ensure_course_enrollment_for_partner($user_id, $object_id);

            $updated = $wpdb->update(
                $table,
                [
                    'status' => 'accepted',
                    'accepted_at' => $now,
                    'invitee_user_id' => $user_id,
                    'updated_at' => $now,
                ],
                ['id' => $invite_id],
                ['%s', '%s', '%d', '%s'],
                ['%d']
            );

            return $updated !== false;
        }

        // Default: partnerships table.
        $table = $wpdb->prefix . self::PARTNERSHIPS_TABLE_SLUG;
        $row = $wpdb->get_row(
            $wpdb->prepare(
            "SELECT id, object_type, object_id, role, status, invitee_email, expires_at, owner_user_id
                 FROM {$table}
                 WHERE id = %d
                 LIMIT 1",
            $invite_id
        ),
            ARRAY_A
        );
        if (!is_array($row)) {
            return false;
        }

        if (sanitize_key((string) ($row['object_type'] ?? '')) !== 'course') {
            return false;
        }
        if (sanitize_key((string) ($row['role'] ?? '')) !== 'partner') {
            return false;
        }
        if (sanitize_key((string) ($row['status'] ?? '')) !== 'pending') {
            return false;
        }
        $invite_email = self::normalize_email((string) ($row['invitee_email'] ?? ''));
        if ($invite_email === '' || $invite_email !== $current_email) {
            return false;
        }

        $expires_ts = strtotime((string) ($row['expires_at'] ?? '') . ' UTC');
        if ($expires_ts && $expires_ts < $now_ts) {
            return false;
        }

        $object_id = (int) ($row['object_id'] ?? 0);
        if ($object_id <= 0) {
            return false;
        }
        if (!class_exists('PL_Partnerships_Repository') || !method_exists('PL_Partnerships_Repository', 'add_partner')) {
            return false;
        }
        $owner_user_id = (int) ($row['owner_user_id'] ?? 0);
        if (!PL_Partnerships_Repository::add_partner('course', $object_id, $user_id, 'partner', $owner_user_id)) {
            return false;
        }

        self::ensure_course_enrollment_for_partner($user_id, $object_id);

        $updated = $wpdb->update(
            $table,
            [
                'status' => 'accepted',
                'accepted_at' => $now,
                'updated_at' => $now,
            ],
            ['id' => $invite_id],
            ['%s', '%s', '%s'],
            ['%d']
        );

        return $updated !== false;
    }

    private static function decline_course_partner_invite_by_id(string $source, int $invite_id, int $user_id): bool
    {
        global $wpdb;
        if (!$wpdb || $invite_id <= 0 || $user_id <= 0) {
            return false;
        }

        $user = get_userdata($user_id);
        if (!($user instanceof \WP_User)) {
            return false;
        }
        $current_email = self::normalize_email((string) ($user->user_email ?? ''));
        if ($current_email === '') {
            return false;
        }

        $now = current_time('mysql');
        $table = $wpdb->prefix . (($source === 'legacy') ? self::LEGACY_INVITES_TABLE_SLUG : self::PARTNERSHIPS_TABLE_SLUG);

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, object_type, object_id, role, status, invitee_email
                 FROM {$table}
                 WHERE id = %d
                 LIMIT 1",
                $invite_id
            ),
            ARRAY_A
        );
        if (!is_array($row)) {
            return false;
        }

        if (sanitize_key((string) ($row['object_type'] ?? '')) !== 'course') {
            return false;
        }
        if (sanitize_key((string) ($row['role'] ?? '')) !== 'partner') {
            return false;
        }
        if (sanitize_key((string) ($row['status'] ?? '')) !== 'pending') {
            return false;
        }
        $invite_email = self::normalize_email((string) ($row['invitee_email'] ?? ''));
        if ($invite_email === '' || $invite_email !== $current_email) {
            return false;
        }

        $data = [
            'status' => 'declined',
            'updated_at' => $now,
        ];
        $formats = ['%s', '%s'];

        if ($source === 'legacy') {
            if (self::invites_table_column_exists($table, 'declined_at')) {
                $data['declined_at'] = $now;
                $formats[] = '%s';
            }
            $updated = $wpdb->update(
                $table,
                $data,
                ['id' => $invite_id],
                $formats,
                ['%d']
            );
            return $updated !== false;
        }

        $data['declined_at'] = $now;
        $formats[] = '%s';
        $updated = $wpdb->update(
            $table,
            $data,
            ['id' => $invite_id],
            $formats,
            ['%d']
        );
        return $updated !== false;
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

        $partnerships_table = $wpdb->prefix . self::PARTNERSHIPS_TABLE_SLUG;
        $partnerships_exists = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $partnerships_table)) === $partnerships_table);

        if ($partnerships_exists && self::invites_table_column_exists($partnerships_table, 'invitation_token_hash')) {
            $invite = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT *
                     FROM {$partnerships_table}
                     WHERE invitation_token_hash = %s
                     LIMIT 1",
                    $token_hash
                ),
                ARRAY_A
            );

            if (is_array($invite)) {
                $invite['_pl_source'] = 'partnerships';
                return $invite;
            }
        }

        $invites_table = $wpdb->prefix . self::LEGACY_INVITES_TABLE_SLUG;
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

        if (is_array($invite)) {
            $invite['_pl_source'] = 'legacy';
            return $invite;
        }

        return null;
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
        $post_type = $post ? (string) ($post->post_type ?? '') : '';
        if (!$post || !in_array($post_type, [self::COURSE_POST_TYPE, self::LEARNI_COURSE_POST_TYPE], true)) {
            return false;
        }

        // Invited course partners must never be allowed to replace/remove the partner.
        if ($post_type === self::LEARNI_COURSE_POST_TYPE && class_exists('PL_Partnerships_Repository') && method_exists('PL_Partnerships_Repository', 'get_object_partners_by_role')) {
            try {
                $rows = PL_Partnerships_Repository::get_object_partners_by_role('course', $course_id, 'partner');
                foreach ((array) $rows as $row) {
                    if (is_array($row) && (int) ($row['partner_user_id'] ?? 0) === $user_id && ($row['status'] ?? '') === 'active') {
                        return false;
                    }
                }
            } catch (\Throwable $e) {
                // ignore
            }
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

        // Learner access (Learni): only the purchaser/owner (not the invited partner) can manage partners.
        if ($post_type === self::LEARNI_COURSE_POST_TYPE && class_exists('\\Learni\\Database\\Enrollments') && method_exists('\\Learni\\Database\\Enrollments', 'user_is_owner')) {
            try {
                if ((bool) \Learni\Database\Enrollments::user_is_owner($user_id, $course_id)) {
                    return true;
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        // Learner access (LearnDash legacy): if the user is enrolled and has access to the course, allow managing their partner.
        if ($post_type === self::COURSE_POST_TYPE && function_exists('sfwd_lms_has_access') && (bool) sfwd_lms_has_access($course_id, $user_id)) {
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

        $partnerships_table = $wpdb->prefix . self::PARTNERSHIPS_TABLE_SLUG;
        $partnerships_exists = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $partnerships_table)) === $partnerships_table);

        $has_invite_cols = $partnerships_exists
            && self::invites_table_column_exists($partnerships_table, 'invitation_token_hash')
            && self::invites_table_column_exists($partnerships_table, 'invitee_email')
            && self::invites_table_column_exists($partnerships_table, 'invited_at')
            && self::invites_table_column_exists($partnerships_table, 'expires_at')
            && self::invites_table_column_exists($partnerships_table, 'accepted_at')
            && self::invites_table_column_exists($partnerships_table, 'revoked_at');

        if ($has_invite_cols) {
            $token = bin2hex(random_bytes(32));
            $token_hash = hash('sha256', $token);
            $now = current_time('mysql');
            $expires_at = gmdate('Y-m-d H:i:s', current_time('timestamp', true) + (7 * DAY_IN_SECONDS));

            // Single-slot: revoke existing pending invites for this object+role.
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$partnerships_table}
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

            // Upsert by (object_type, object_id, invitee_email, role) due to unique key.
            $existing = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id
                     FROM {$partnerships_table}
                     WHERE object_type = %s
                       AND object_id = %d
                       AND invitee_email = %s
                       AND role = %s
                     LIMIT 1",
                    $object_type,
                    $object_id,
                    $email,
                    $role
                ),
                ARRAY_A
            );

            if (is_array($existing) && !empty($existing['id'])) {
                $updated = $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$partnerships_table}
                         SET owner_user_id = %d,
                             partner_user_id = NULL,
                             status = %s,
                             invitation_token_hash = %s,
                             invited_at = %s,
                             expires_at = %s,
                             accepted_at = NULL,
                             declined_at = NULL,
                             revoked_at = NULL,
                             updated_at = %s
                         WHERE id = %d",
                        (int) get_current_user_id(),
                        'pending',
                        $token_hash,
                        $now,
                        $expires_at,
                        $now,
                        (int) $existing['id']
                    )
                );

                return $updated !== false ? $token : '';
            }

            $inserted = $wpdb->query(
                $wpdb->prepare(
                    "INSERT INTO {$partnerships_table}
                        (object_type, object_id, owner_user_id, partner_user_id, invitee_email, role, status, invitation_token_hash, invited_at, expires_at, created_at, updated_at)
                     VALUES
                        (%s, %d, %d, NULL, %s, %s, %s, %s, %s, %s, %s, %s)",
                    $object_type,
                    $object_id,
                    (int) get_current_user_id(),
                    $email,
                    $role,
                    'pending',
                    $token_hash,
                    $now,
                    $expires_at,
                    $now,
                    $now
                )
            );

            return $inserted ? $token : '';
        }

        return self::create_invite_legacy($object_type, $object_id, $email, $role);
    }

    private static function create_invite_legacy(string $object_type, int $object_id, string $email, string $role): string
    {
        global $wpdb;
        if (!$wpdb) {
            return '';
        }

        $table = $wpdb->prefix . self::LEGACY_INVITES_TABLE_SLUG;
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
