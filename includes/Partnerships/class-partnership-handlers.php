<?php
/**
 * Handlers class for partnership-related HTTP requests and UI rendering.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PL_Partnership_Handlers
{
    private const ACCEPT_INVITE_PATH = '/accept-invite';
    private const REGISTER_QUERY_MODE = 'mode';
    private const REGISTER_QUERY_VALUE = 'register';
    private const REGISTER_QUERY_STATUS = 'status';
    private const REGISTER_STATUS_CREATED = 'created';
    private const REGISTER_FORM_ACTION = 'pl_accept_invite_register';
    private const INVITE_RESPOND_ACTION = 'pl_course_partner_invite_respond';
    private const INVITE_RESPOND_NONCE_ACTION = 'pl_course_partner_invite_respond';

    /**
     * Handle accepting an invite token via /accept-invite?token=...
     */
    public static function handle_invite_accept(): void
    {
        $path = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
        $path = (string) parse_url($path, PHP_URL_PATH);
        $path = rtrim($path, '/');

        if ($path !== rtrim(self::ACCEPT_INVITE_PATH, '/')) {
            return;
        }

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

        $invite = PL_Partnership_Manager::get_pending_invite_by_raw_token($raw_token);
        if (!$invite) {
            return;
        }

        $now_ts = current_time('timestamp', true);
        $expires_ts = strtotime((string) ($invite['expires_at'] ?? '') . ' UTC');
        if ($expires_ts && $expires_ts < $now_ts) {
            PL_Partnership_Manager::mark_invite_expired($invite);
            return;
        }

        $user_id = (int) get_current_user_id();
        if ($user_id <= 0) {
            $invite_email = PL_Partnership_Utils::normalize_email((string) ($invite['invitee_email'] ?? ''));
            $existing = $invite_email !== '' ? get_user_by('email', $invite_email) : null;

            $mode = isset($_GET[self::REGISTER_QUERY_MODE]) ? sanitize_key((string) wp_unslash($_GET[self::REGISTER_QUERY_MODE])) : '';
            if ($mode === self::REGISTER_QUERY_VALUE && !($existing instanceof WP_User)) {
                $first_name = isset($_GET['invite_first_name']) ? sanitize_text_field((string) wp_unslash($_GET['invite_first_name'])) : '';
                $last_name = isset($_GET['invite_last_name']) ? sanitize_text_field((string) wp_unslash($_GET['invite_last_name'])) : '';
                self::render_invite_register_page($raw_token, $invite, $first_name, $last_name);
                exit;
            }

            $redirect_back = add_query_arg(['token' => $raw_token], home_url(self::ACCEPT_INVITE_PATH));

            if (class_exists('\Learni\Auth\Utilities\AuthUtils')) {
                $args = [];
                if ($invite_email !== '') {
                    $args['invite_email'] = $invite_email;
                }
                wp_safe_redirect(\Learni\Auth\Utilities\AuthUtils::build_modal_url('login', $redirect_back, $args));
                exit;
            }

            wp_redirect(wp_login_url($redirect_back));
            exit;
        }

        $current_user = wp_get_current_user();
        $current_email = PL_Partnership_Utils::normalize_email((string) ($current_user->user_email ?? ''));
        $invite_email = PL_Partnership_Utils::normalize_email((string) ($invite['invitee_email'] ?? ''));
        if ('' === $current_email || '' === $invite_email || $current_email !== $invite_email) {
            return;
        }

        $object_type = (string) ($invite['object_type'] ?? 'reading_plan');
        $object_id = (int) ($invite['object_id'] ?? 0);

        if ($object_id <= 0) {
            return;
        }

        $ok = PL_Partnership_Manager::accept_invite_for_user($raw_token, $user_id);
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

    /**
     * Handle registration form submission for invitees.
     */
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

        $invite = PL_Partnership_Manager::get_pending_invite_by_raw_token($token);
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
            wp_safe_redirect(wp_login_url(add_query_arg(['token' => $token], home_url(self::ACCEPT_INVITE_PATH))));
            exit;
        }

        $username = PL_Partnership_Utils::generate_username_from_email($email);
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

        update_user_meta((int) $user_id, 'pl_auth_email_verified', 1);
        delete_user_meta((int) $user_id, 'pl_auth_verification_token_hash');
        delete_user_meta((int) $user_id, 'pl_auth_verification_token_expires');

        wp_set_current_user((int) $user_id);
        wp_set_auth_cookie((int) $user_id, true);

        $ok = PL_Partnership_Manager::accept_invite_for_user($token, (int) $user_id);
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
     * Handle direct response to course partner invite (Accept/Reject).
     */
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
            $ok = PL_Partnership_Manager::accept_course_partner_invite_by_id($source, $invite_id, $user_id);
        } else {
            $ok = PL_Partnership_Manager::decline_course_partner_invite_by_id($source, $invite_id, $user_id);
        }

        $args = [
            'pl_cp_invite' => $ok ? ($decision === 'accept' ? 'accepted' : 'declined') : 'failed',
        ];
        if ($ok && $decision === 'accept') {
            $args['pl_cp_invite_id'] = $invite_id;
            $args['pl_cp_invite_source'] = $source;
        }
        $args['tab'] = 'requests';

        wp_safe_redirect(add_query_arg($args, $redirect_to));
        exit;
    }

    /**
     * Render the registration page for invitees.
     */
    public static function render_invite_register_page(string $raw_token, array $invite, string $first_name, string $last_name): void
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

    /**
     * Render the success page after accepting an invite.
     */
    public static function render_invite_success_page(): void
    {
        $user_id = (int) get_current_user_id();
        $course_id = 0;

        $token = isset($_GET['token']) ? strtolower(trim((string) wp_unslash($_GET['token']))) : '';
        if (preg_match('/^[a-f0-9]{64}$/', $token)) {
            $invite = PL_Partnership_Manager::get_invite_by_raw_token($token);
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
}
