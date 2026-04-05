<?php
/**
 * Login/Register module bootstrap and request handlers.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PL_Auth_Login_Register
{
    private const VERIFIED_META = 'pl_auth_email_verified';
    private const TOKEN_HASH_META = 'pl_auth_verification_token_hash';
    private const TOKEN_EXPIRES_META = 'pl_auth_verification_token_expires';
    private const NONCE_ACTION = 'pl_auth_submit';
    private const NONCE_FIELD = 'pl_auth_nonce';
    private const QUERY_VIEW = 'pl_auth_view';
    private const QUERY_NOTICE = 'pl_auth_notice';
    private const QUERY_ERROR = 'pl_auth_error';
    private const QUERY_ACTION = 'pl_auth_action';

    public static function init(): void
    {
        add_action('wp_body_open', [__CLASS__, 'render_auth_modal'], 20);
        add_action('wp_footer', [__CLASS__, 'render_auth_modal'], 20);
        add_action('admin_post_nopriv_pl_auth_submit', [__CLASS__, 'handle_submit']);
        add_action('admin_post_pl_auth_submit', [__CLASS__, 'handle_submit']);
        add_action('template_redirect', [__CLASS__, 'handle_confirmation_link'], 1);
        add_filter('login_url', [__CLASS__, 'filter_login_url'], 10, 3);
        add_filter('register_url', [__CLASS__, 'filter_register_url'], 10);
        add_filter('wp_authenticate_user', [__CLASS__, 'ensure_verified_before_login'], 20, 2);
        add_shortcode('pl_auth_links', [__CLASS__, 'render_auth_links_shortcode']);
    }

    public static function filter_login_url(string $login_url, string $redirect, bool $force_reauth): string
    {
        unset($force_reauth);

        return self::build_modal_url('login', $redirect);
    }

    public static function filter_register_url(string $register_url): string
    {
        unset($register_url);

        return self::build_modal_url('register');
    }

    public static function render_auth_links_shortcode(array $atts = []): string
    {
        unset($atts);

        if (is_user_logged_in()) {
            return '';
        }

        $login_url = esc_url(self::build_modal_url('login'));
        $register_url = esc_url(self::build_modal_url('register'));

        return '<div class="pl-auth-links"><a class="pl-auth-link pl-auth-link-login" href="' . $login_url . '">' . esc_html__('Login', 'politeia-learning') . '</a><a class="pl-auth-link pl-auth-link-register" href="' . $register_url . '">' . esc_html__('Register', 'politeia-learning') . '</a></div>';
    }

    public static function render_auth_modal(): void
    {
        echo self::get_auth_modal_markup();
    }

    public static function get_auth_modal_markup(): string
    {
        static $rendered = false;

        if ($rendered || is_admin() || is_user_logged_in()) {
            return '';
        }

        $rendered = true;

        $view = self::sanitize_view((string) wp_unslash($_GET[self::QUERY_VIEW] ?? 'login'));
        $notice = self::get_notice_message((string) wp_unslash($_GET[self::QUERY_NOTICE] ?? ''));
        $error = self::get_error_message((string) wp_unslash($_GET[self::QUERY_ERROR] ?? ''));
        $redirect_to = self::resolve_redirect_to((string) wp_unslash($_GET['redirect_to'] ?? ''));
        $auto_open = isset($_GET[self::QUERY_VIEW]) || isset($_GET[self::QUERY_NOTICE]) || isset($_GET[self::QUERY_ERROR]);
        $action_url = admin_url('admin-post.php');
        $nonce = wp_create_nonce(self::NONCE_ACTION);

        ob_start();
        include PL_AUTH_PATH . 'templates/auth-modal.php';
        return (string) ob_get_clean();
    }

    public static function handle_submit(): void
    {
        if (!isset($_POST[self::NONCE_FIELD]) || !wp_verify_nonce((string) wp_unslash($_POST[self::NONCE_FIELD]), self::NONCE_ACTION)) {
            wp_safe_redirect(self::build_modal_url('login', home_url('/'), [self::QUERY_ERROR => 'invalid_nonce']));
            exit;
        }

        $mode = self::sanitize_view((string) wp_unslash($_POST['mode'] ?? 'login'));
        $redirect_to = self::resolve_redirect_to((string) wp_unslash($_POST['redirect_to'] ?? ''));

        if ($mode === 'register') {
            self::handle_register_request($redirect_to);
        }

        self::handle_login_request($redirect_to);
    }

    public static function handle_confirmation_link(): void
    {
        $action = isset($_GET[self::QUERY_ACTION]) ? sanitize_key((string) wp_unslash($_GET[self::QUERY_ACTION])) : '';
        if ($action !== 'confirm') {
            return;
        }

        $email = isset($_GET['email']) ? sanitize_email((string) wp_unslash($_GET['email'])) : '';
        $token = isset($_GET['token']) ? trim(sanitize_text_field((string) wp_unslash($_GET['token']))) : '';
        $redirect_to = self::resolve_redirect_to((string) wp_unslash($_GET['redirect_to'] ?? ''));

        $result = self::confirm_user_email($email, $token);
        if (is_wp_error($result)) {
            wp_safe_redirect(self::build_modal_url('login', $redirect_to, [self::QUERY_ERROR => $result->get_error_code()]));
            exit;
        }

        wp_safe_redirect(self::build_modal_url('login', $redirect_to, [self::QUERY_NOTICE => 'verified']));
        exit;
    }

    public static function ensure_verified_before_login($user, $password)
    {
        unset($password);

        if (is_wp_error($user) || !($user instanceof WP_User)) {
            return $user;
        }

        if (!self::requires_verification($user->ID)) {
            return $user;
        }

        if (self::is_verified($user->ID)) {
            return $user;
        }

        return new WP_Error(
            'pl_auth_unverified',
            __('Your account is not verified yet. Please check your email and confirm your account first.', 'politeia-learning')
        );
    }

    private static function handle_login_request(string $redirect_to): void
    {
        $login_or_email = isset($_POST['user_login']) ? sanitize_text_field((string) wp_unslash($_POST['user_login'])) : '';
        $password = isset($_POST['password']) ? (string) wp_unslash($_POST['password']) : '';
        $remember = !empty($_POST['remember']);

        if ($login_or_email === '' || $password === '') {
            wp_safe_redirect(self::build_modal_url('login', $redirect_to, [self::QUERY_ERROR => 'missing_login']));
            exit;
        }

        if (is_email($login_or_email)) {
            $user = get_user_by('email', $login_or_email);
            if ($user instanceof WP_User && isset($user->user_login)) {
                $login_or_email = (string) $user->user_login;
            }
        }

        $user = wp_signon([
            'user_login' => $login_or_email,
            'user_password' => $password,
            'remember' => $remember,
        ], is_ssl());

        if (is_wp_error($user)) {
            $code = $user->get_error_code() ?: 'invalid_login';
            wp_safe_redirect(self::build_modal_url('login', $redirect_to, [self::QUERY_ERROR => $code]));
            exit;
        }

        wp_safe_redirect($redirect_to);
        exit;
    }

    private static function handle_register_request(string $redirect_to): void
    {
        $first_name = isset($_POST['first_name']) ? sanitize_text_field((string) wp_unslash($_POST['first_name'])) : '';
        $last_name = isset($_POST['last_name']) ? sanitize_text_field((string) wp_unslash($_POST['last_name'])) : '';
        $email = isset($_POST['email']) ? sanitize_email((string) wp_unslash($_POST['email'])) : '';
        $email_confirm = isset($_POST['email_confirm']) ? sanitize_email((string) wp_unslash($_POST['email_confirm'])) : '';
        $password = isset($_POST['password']) ? (string) wp_unslash($_POST['password']) : '';
        $password_confirm = isset($_POST['password_confirm']) ? (string) wp_unslash($_POST['password_confirm']) : '';

        if ($email === '' || !is_email($email)) {
            wp_safe_redirect(self::build_modal_url('register', $redirect_to, [self::QUERY_ERROR => 'invalid_email']));
            exit;
        }

        if ($email !== $email_confirm) {
            wp_safe_redirect(self::build_modal_url('register', $redirect_to, [self::QUERY_ERROR => 'email_mismatch']));
            exit;
        }

        if ($password === '' || strlen($password) < 8) {
            wp_safe_redirect(self::build_modal_url('register', $redirect_to, [self::QUERY_ERROR => 'weak_password']));
            exit;
        }

        if ($password !== $password_confirm) {
            wp_safe_redirect(self::build_modal_url('register', $redirect_to, [self::QUERY_ERROR => 'password_mismatch']));
            exit;
        }

        $existing_user = get_user_by('email', $email);
        if ($existing_user instanceof WP_User) {
            if (self::is_verified($existing_user->ID)) {
                wp_safe_redirect(self::build_modal_url('register', $redirect_to, [self::QUERY_ERROR => 'account_exists']));
                exit;
            }

            $token = self::issue_confirmation_token((int) $existing_user->ID);
            self::send_confirmation_for_user((int) $existing_user->ID, $email, $first_name !== '' ? $first_name : (string) $existing_user->display_name, $redirect_to, $token);
            wp_safe_redirect(self::build_modal_url('login', $redirect_to, [self::QUERY_NOTICE => 'verification_sent']));
            exit;
        }

        $username = self::generate_username_from_email($email);
        $user_id = wp_create_user($username, $password, $email);

        if (is_wp_error($user_id)) {
            wp_safe_redirect(self::build_modal_url('register', $redirect_to, [self::QUERY_ERROR => 'create_failed']));
            exit;
        }

        if ($first_name !== '') {
            update_user_meta((int) $user_id, 'first_name', $first_name);
        }
        if ($last_name !== '') {
            update_user_meta((int) $user_id, 'last_name', $last_name);
        }

        $token = self::issue_confirmation_token((int) $user_id);

        $display_name = trim($first_name . ' ' . $last_name);
        if ($display_name === '') {
            $display_name = $username;
        }

        self::send_confirmation_for_user((int) $user_id, $email, $display_name, $redirect_to, $token);
        wp_new_user_notification((int) $user_id, null, 'admin');

        wp_safe_redirect(self::build_modal_url('login', $redirect_to, [self::QUERY_NOTICE => 'verification_sent']));
        exit;
    }

    private static function confirm_user_email(string $email, string $token)
    {
        if ($email === '' || !is_email($email) || $token === '') {
            return new WP_Error('invalid_token', __('The confirmation link is incomplete.', 'politeia-learning'));
        }

        $user = get_user_by('email', $email);
        if (!($user instanceof WP_User)) {
            return new WP_Error('invalid_token', __('We could not find that account.', 'politeia-learning'));
        }

        $stored_hash = (string) get_user_meta($user->ID, self::TOKEN_HASH_META, true);
        $stored_expires = (int) get_user_meta($user->ID, self::TOKEN_EXPIRES_META, true);
        if ($stored_hash === '' || $stored_expires < time()) {
            return new WP_Error('token_expired', __('This confirmation token has expired. Please register again or request a new link.', 'politeia-learning'));
        }

        $provided_hash = hash_hmac('sha256', $token, wp_salt('auth'));
        if (!hash_equals($stored_hash, $provided_hash)) {
            return new WP_Error('invalid_token', __('The confirmation token is invalid.', 'politeia-learning'));
        }

        update_user_meta($user->ID, self::VERIFIED_META, 1);
        delete_user_meta($user->ID, self::TOKEN_HASH_META);
        delete_user_meta($user->ID, self::TOKEN_EXPIRES_META);

        return true;
    }

    private static function send_confirmation_for_user(int $user_id, string $email, string $display_name, string $redirect_to, string $token): void
    {
        unset($user_id);

        $verification_url = self::build_confirmation_url($email, $token, $redirect_to);

        PL_Email::send_auth_confirmation($email, $display_name, $verification_url, $token);
    }

    private static function issue_confirmation_token(int $user_id): string
    {
        $token = bin2hex(random_bytes(32));
        $hash = hash_hmac('sha256', $token, wp_salt('auth'));
        update_user_meta($user_id, self::VERIFIED_META, 0);
        update_user_meta($user_id, self::TOKEN_HASH_META, $hash);
        update_user_meta($user_id, self::TOKEN_EXPIRES_META, time() + DAY_IN_SECONDS * 2);

        return $token;
    }

    private static function build_confirmation_url(string $email, string $token, string $redirect_to): string
    {
        return add_query_arg([
            self::QUERY_ACTION => 'confirm',
            'email' => $email,
            'token' => $token,
            'redirect_to' => $redirect_to,
        ], home_url('/'));
    }

    public static function build_modal_url(string $view, string $redirect_to = '', array $args = []): string
    {
        $view = self::sanitize_view($view);
        $redirect_to = self::resolve_redirect_to($redirect_to);

        $query_args = array_merge([
            self::QUERY_VIEW => $view,
            'redirect_to' => $redirect_to,
        ], $args);

        return add_query_arg($query_args, home_url('/'));
    }

    private static function resolve_redirect_to(string $redirect_to): string
    {
        $redirect_to = trim($redirect_to);
        if ($redirect_to === '') {
            $redirect_to = self::get_current_url();
        }

        return wp_validate_redirect($redirect_to, home_url('/'));
    }

    private static function get_current_url(): string
    {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '/';
        if ($request_uri === '') {
            $request_uri = '/';
        }

        return home_url($request_uri);
    }

    private static function sanitize_view(string $view): string
    {
        $view = sanitize_key($view);
        if (!in_array($view, ['login', 'register'], true)) {
            return 'login';
        }

        return $view;
    }

    private static function get_notice_message(string $code): string
    {
        return match ($code) {
            'verification_sent' => __('We sent a confirmation email. Please check your inbox before logging in.', 'politeia-learning'),
            'verified' => __('Your email has been confirmed. You can now log in.', 'politeia-learning'),
            default => '',
        };
    }

    private static function get_error_message(string $code): string
    {
        return match ($code) {
            'invalid_nonce' => __('We could not verify your request. Please try again.', 'politeia-learning'),
            'missing_login' => __('Please enter your email and password.', 'politeia-learning'),
            'invalid_login' => __('The login details were not valid.', 'politeia-learning'),
            'pl_auth_unverified' => __('Your account is not verified yet. Please confirm your email address first.', 'politeia-learning'),
            'invalid_email' => __('Please enter a valid email address.', 'politeia-learning'),
            'email_mismatch' => __('The email addresses do not match.', 'politeia-learning'),
            'weak_password' => __('Your password must be at least 8 characters long.', 'politeia-learning'),
            'password_mismatch' => __('The passwords do not match.', 'politeia-learning'),
            'account_exists' => __('An account already exists with that email address.', 'politeia-learning'),
            'invalid_token', 'token_expired' => __('The confirmation link is invalid or expired.', 'politeia-learning'),
            default => '',
        };
    }

    private static function requires_verification(int $user_id): bool
    {
        return get_user_meta($user_id, self::VERIFIED_META, true) !== '' || get_user_meta($user_id, self::TOKEN_HASH_META, true) !== '';
    }

    private static function is_verified(int $user_id): bool
    {
        return (string) get_user_meta($user_id, self::VERIFIED_META, true) === '1';
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
}
error_log('PL Auth Init called');
