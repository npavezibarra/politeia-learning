<?php
/**
 * REST endpoints for partnerships.
 * (Refactored: Logic moved to Partnerships/ Manager, Handlers, and Utils)
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
        add_action('init', ['PL_Partnership_Handlers', 'handle_invite_accept']);
        add_action('admin_post_nopriv_' . self::REGISTER_FORM_ACTION, ['PL_Partnership_Handlers', 'handle_invite_register_submit']);
        add_action('admin_post_' . self::REGISTER_FORM_ACTION, ['PL_Partnership_Handlers', 'handle_invite_register_submit']);
        add_action('admin_post_' . self::INVITE_RESPOND_ACTION, ['PL_Partnership_Handlers', 'handle_course_partner_invite_respond']);
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

        if (!PL_Partnership_Manager::user_can_manage_course($current_user, $object_id)) {
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
        if (!PL_Partnership_Manager::user_can_manage_course($current_user, $object_id)) {
            return new WP_Error('forbidden', 'Not allowed', ['status' => 403]);
        }

        $token = PL_Partnership_Manager::create_invite($object_type, $object_id, $email, 'partner');
        if ('' === $token) {
            return new WP_Error('server_error', 'Failed to create invite', ['status' => 500]);
        }

        $accept_url = add_query_arg(['token' => $token], home_url(self::ACCEPT_INVITE_PATH));

        $existing_invitee = get_user_by('email', $email);
        if (!($existing_invitee instanceof WP_User)) {
            $accept_url = add_query_arg([
                self::REGISTER_QUERY_MODE => self::REGISTER_QUERY_VALUE,
                'invite_first_name' => $first_name,
                'invite_last_name' => $last_name,
                'invite_email' => $email,
            ], $accept_url);
        }

        $mail_sent = true;
        if (class_exists('PL_Email') && method_exists('PL_Email', 'send_course_invite')) {
            $mail_sent = (bool) PL_Email::send_course_invite($email, $accept_url, $object_id, $invitee_name);
        } else {
            PL_Partnership_Utils::send_course_invite_email($email, $accept_url, $object_id, $invitee_name);
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
        if (!PL_Partnership_Manager::user_can_manage_course($current_user, $object_id)) {
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

        $query_args = [
            'search' => '*' . $term . '*',
            'search_columns' => ['display_name', 'user_login', 'user_email'],
            'number' => 3,
            'orderby' => 'display_name',
            'order' => 'ASC',
            'exclude' => [$current_user],
        ];

        $users = get_users($query_args);

        $out = array_map(static function ($u) {
            $avatar = function_exists('get_avatar_url') ? (string) get_avatar_url((int) $u->ID, ['size' => 48]) : '';
            return [
                'id' => (int) $u->ID,
                'name' => (string) $u->display_name,
                'email' => (string) $u->user_email,
                'avatar' => $avatar,
            ];
        }, (array) $users);

        return rest_ensure_response($out);
    }

    // --- Legacy Method Wrappers (delegating to modular classes) ---

    public static function handle_invite_accept(): void { PL_Partnership_Handlers::handle_invite_accept(); }
    public static function handle_invite_register_submit(): void { PL_Partnership_Handlers::handle_invite_register_submit(); }
    public static function handle_course_partner_invite_respond(): void { PL_Partnership_Handlers::handle_course_partner_invite_respond(); }
    private static function render_invite_register_page(string $token, array $invite, string $f, string $l): void { PL_Partnership_Handlers::render_invite_register_page($token, $invite, $f, $l); }
    private static function render_invite_success_page(): void { PL_Partnership_Handlers::render_invite_success_page(); }
    private static function accept_course_partner_invite_by_id(string $s, int $i, int $u): bool { return PL_Partnership_Manager::accept_course_partner_invite_by_id($s, $i, $u); }
    private static function decline_course_partner_invite_by_id(string $s, int $i, int $u): bool { return PL_Partnership_Manager::decline_course_partner_invite_by_id($s, $i, $u); }
    private static function generate_username_from_email(string $e): string { return PL_Partnership_Utils::generate_username_from_email($e); }
    private static function normalize_email(string $e): string { return PL_Partnership_Utils::normalize_email($e); }
    private static function invites_table_column_exists(string $t, string $c): bool { return PL_Partnership_Utils::invites_table_column_exists($t, $c); }
    private static function create_invite(string $o_t, int $o_i, string $e, string $r = 'observer'): string { return PL_Partnership_Manager::create_invite($o_t, $o_i, $e, $r); }
    private static function create_invite_legacy(string $o_t, int $o_i, string $e, string $r): string { return PL_Partnership_Manager::create_invite_legacy($o_t, $o_i, $e, $r); }
    private static function send_course_invite_email(string $e, string $a, int $c, string $n = ''): void { PL_Partnership_Utils::send_course_invite_email($e, $a, $c, $n); }
    private static function accept_invite_for_user(string $t, int $u): bool { return PL_Partnership_Manager::accept_invite_for_user($t, $u); }
    private static function ensure_course_enrollment_for_partner(int $u, int $c): void { PL_Partnership_Manager::ensure_course_enrollment_for_partner($u, $c); }
    private static function mark_invite_expired(array $i): void { PL_Partnership_Manager::mark_invite_expired($i); }
    private static function get_pending_invite_by_raw_token(string $t): ?array { return PL_Partnership_Manager::get_pending_invite_by_raw_token($t); }
    private static function get_invite_by_raw_token(string $t): ?array { return PL_Partnership_Manager::get_invite_by_raw_token($t); }
    private static function user_can_manage_course(int $u, int $c): bool { return PL_Partnership_Manager::user_can_manage_course($u, $c); }
}
