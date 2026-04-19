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
    private const RESEND_NONCE_ACTION = 'pl_auth_resend_confirmation';
    private const RESEND_NONCE_FIELD = 'pl_auth_resend_nonce';
    private const QUERY_VIEW = 'pl_auth_view';
    private const QUERY_NOTICE = 'pl_auth_notice';
    private const QUERY_ERROR = 'pl_auth_error';
    private const QUERY_ACTION = 'pl_auth_action';

    public static function init(): void
    {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets'], 20);
        add_action('wp_body_open', [__CLASS__, 'render_auth_modal'], 20);
        add_action('wp_footer', [__CLASS__, 'render_auth_modal'], 20);
        add_action('wp_footer', [__CLASS__, 'render_unverified_popup'], 30);
        add_action('admin_post_nopriv_pl_auth_submit', [__CLASS__, 'handle_submit']);
        add_action('admin_post_pl_auth_submit', [__CLASS__, 'handle_submit']);
        add_action('admin_post_pl_auth_resend_confirmation', [__CLASS__, 'handle_resend_confirmation']);
        add_action('admin_post_nopriv_pl_auth_resend_confirmation', [__CLASS__, 'handle_resend_confirmation_nopriv']);
        add_action('template_redirect', [__CLASS__, 'handle_confirmation_link'], 1);
        add_action('wp_ajax_nopriv_pl_auth_forgot_password_probe', [__CLASS__, 'ajax_forgot_password_probe']);
        add_action('wp_ajax_pl_auth_forgot_password_probe', [__CLASS__, 'ajax_forgot_password_probe']);
        add_filter('login_url', [__CLASS__, 'filter_login_url'], 10, 3);
        add_filter('register_url', [__CLASS__, 'filter_register_url'], 10);
        add_filter('wp_authenticate_user', [__CLASS__, 'ensure_verified_before_login'], 20, 2);
        add_shortcode('pl_auth_links', [__CLASS__, 'render_auth_links_shortcode']);

        if (class_exists('PL_Auth_Reset_Password_Page')) {
            PL_Auth_Reset_Password_Page::init();
        }
    }

    public static function ajax_forgot_password_probe(): void
    {
        check_ajax_referer('pl_auth_forgot_password', 'nonce');

        $email = isset($_GET['email']) ? sanitize_email((string) wp_unslash($_GET['email'])) : '';
        if ($email === '' || !is_email($email)) {
            wp_send_json_success(['exists' => false, 'sent' => false, 'invalid' => true]);
        }

        $user = get_user_by('email', $email);
        if (!($user instanceof WP_User)) {
            wp_send_json_success(['exists' => false, 'sent' => false]);
        }

        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
        $rate_key = 'pl_auth_pwreset_' . md5(strtolower($email) . '|' . $ip);
        if (get_transient($rate_key)) {
            wp_send_json_success(['exists' => true, 'sent' => true, 'rate_limited' => true]);
        }

        $switched = switch_to_user_locale((int) $user->ID);

        $key = get_password_reset_key($user);
        if (is_wp_error($key)) {
            if ($switched) {
                restore_previous_locale();
            }
            wp_send_json_success(['exists' => true, 'sent' => false]);
        }

        $reset_url = add_query_arg([
            'key' => $key,
            'login' => $user->user_login,
        ], home_url('/restablecer-contrasena/'));

        $html = class_exists('PL_Email')
            ? (string) PL_Email::render('password-reset', [
                'user_login' => $user->user_login,
                'reset_url' => $reset_url,
            ])
            : '';

        if ($switched) {
            restore_previous_locale();
        }

        if (trim($html) === '') {
            $html = sprintf(
                '<p>%s</p><p><a href="%s">%s</a></p>',
                esc_html__('Reset your password:', 'politeia-learning'),
                esc_url($reset_url),
                esc_html($reset_url)
            );
        }

        $subject = __('Reset Password', 'politeia-learning');
        $sent = (bool) wp_mail($email, $subject, $html, ['Content-Type: text/html; charset=UTF-8']);

        set_transient($rate_key, 1, 60);

        wp_send_json_success(['exists' => true, 'sent' => $sent]);
    }

    public static function enqueue_assets(): void
    {
        if (is_admin() || is_user_logged_in()) {
            return;
        }

        // Poppins font for auth modal UI.
        wp_enqueue_style(
            'pl-auth-poppins',
            'https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap',
            [],
            null
        );
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

    public static function render_unverified_popup(): void
    {
        echo self::get_unverified_popup_markup();
    }

    public static function get_auth_modal_markup(): string
    {
        static $rendered = false;

        if ($rendered || is_admin() || is_user_logged_in()) {
            return '';
        }

        $rendered = true;

        $view = self::sanitize_view((string) wp_unslash($_GET[self::QUERY_VIEW] ?? 'login'));
        $notice = (string) sanitize_key((string) wp_unslash($_GET[self::QUERY_NOTICE] ?? ''));
        $error = (string) sanitize_key((string) wp_unslash($_GET[self::QUERY_ERROR] ?? ''));
        $redirect_to = self::resolve_redirect_to((string) wp_unslash($_GET['redirect_to'] ?? ''));
        $auto_open = isset($_GET[self::QUERY_VIEW]) || isset($_GET[self::QUERY_NOTICE]) || isset($_GET[self::QUERY_ERROR]);
        $action_url = admin_url('admin-post.php');
        $nonce = wp_create_nonce(self::NONCE_ACTION);

        ob_start();
        include PL_AUTH_PATH . 'templates/auth-modal.php';
        return (string) ob_get_clean();
    }

    public static function get_unverified_popup_markup(): string
    {
        static $rendered = false;
        if ($rendered || is_admin() || !is_user_logged_in()) {
            return '';
        }

        $user_id = (int) get_current_user_id();
        if ($user_id <= 0 || self::is_verified($user_id) || !self::requires_verification($user_id)) {
            return '';
        }

        $rendered = true;

        $notice_code = (string) sanitize_key((string) wp_unslash($_GET[self::QUERY_NOTICE] ?? ''));
        $error_code = (string) sanitize_key((string) wp_unslash($_GET[self::QUERY_ERROR] ?? ''));
        $force_open = isset($_GET['pl_auth_unverified']) && sanitize_key((string) wp_unslash($_GET['pl_auth_unverified'])) === '1';
        $open_after_quiz = isset($_GET['pl_auth_unverified_after_quiz']) && sanitize_key((string) wp_unslash($_GET['pl_auth_unverified_after_quiz'])) === '1';
        $action_url = admin_url('admin-post.php');
        $nonce = wp_create_nonce(self::RESEND_NONCE_ACTION);
        $redirect_to = self::resolve_redirect_to((string) wp_unslash($_GET['redirect_to'] ?? ''));
        $redirect_to_for_form = remove_query_arg([
            'pl_auth_unverified_after_quiz',
            'pl_auth_unverified',
            self::QUERY_NOTICE,
            self::QUERY_ERROR,
        ], $redirect_to);

        $is_spanish = strpos(get_locale(), 'es') === 0;
        $title = $is_spanish ? 'Aún no has verificado tu cuenta' : 'Your account is not verified yet';
        $body = $is_spanish
            ? 'Para mayor seguridad revisa tu correo y verifica la creación de tu cuenta en Politeia.'
            : 'For your security, please check your email and verify the creation of your Politeia account.';
        $cta = $is_spanish ? 'Enviar correo de confirmación' : 'Send confirmation email';
        $sent = $is_spanish ? 'Te enviamos un correo de confirmación. Por favor revisa tu bandeja de entrada.' : 'We sent a confirmation email. Please check your inbox.';
        $throttled = $is_spanish ? 'Espera un momento antes de reenviar el correo.' : 'Please wait a moment before resending.';
        $generic_err = $is_spanish ? 'Algo salió mal. Por favor, inténtalo de nuevo.' : 'Something went wrong. Please try again.';

        $message_html = '';
        if ($notice_code === 'verification_sent') {
            $message_html = '<div class="pl-auth-unverified__msg is-ok">' . esc_html($sent) . '</div>';
        } elseif ($error_code === 'resend_throttled') {
            $message_html = '<div class="pl-auth-unverified__msg is-err">' . esc_html($throttled) . '</div>';
        } elseif ($error_code !== '') {
            $message_html = '<div class="pl-auth-unverified__msg is-err">' . esc_html($generic_err) . '</div>';
        }

        // Do not interrupt the post-register flow (we auto-start the quiz after registration).
        // The popup is opened after completing the quiz (via explicit query param), or when a resend action errors.
        $should_open = $force_open || $open_after_quiz || ($error_code === 'resend_throttled' || $error_code === 'invalid_nonce');

        ob_start();
        ?>
        <style>
            #pl-auth-unverified {
                position: fixed;
                inset: 0;
                z-index: 10001;
                display: none;
                align-items: center;
                justify-content: center;
                padding: 24px;
                background: rgba(15, 23, 42, 0.68);
                backdrop-filter: blur(10px);
                box-sizing: border-box;
                font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            }
            #pl-auth-unverified.is-open { display: flex; }
            #pl-auth-unverified * { box-sizing: border-box; }
            .pl-auth-unverified__card {
                width: min(100%, 520px);
                background: #fff;
                border: 1px solid rgba(15, 23, 42, 0.08);
                border-radius: 24px;
                box-shadow: 0 30px 80px rgba(15, 23, 42, 0.28);
                overflow: hidden;
            }
            .pl-auth-unverified__inner { padding: 28px 28px 26px; position: relative; }
            .pl-auth-unverified__close {
                position: absolute;
                right: 14px;
                top: 12px;
                width: 42px;
                height: 42px;
                border-radius: 999px;
                border: 0;
                background: rgba(15, 23, 42, 0.06);
                cursor: pointer;
                font-size: 18px;
                line-height: 1;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }
            .pl-auth-unverified__title { margin: 0 0 10px; font-size: 20px; line-height: 1.15; color: #0f172a; }
            .pl-auth-unverified__text { margin: 0 0 18px; font-size: 15px; line-height: 1.5; color: #475569; }
            .pl-auth-unverified__msg { margin: 0 0 14px; padding: 12px 14px; border-radius: 14px; font-size: 14px; line-height: 1.4; }
            .pl-auth-unverified__msg.is-ok { background: rgba(16, 185, 129, 0.10); border: 1px solid rgba(16, 185, 129, 0.22); color: #065f46; }
            .pl-auth-unverified__msg.is-err { background: rgba(239, 68, 68, 0.10); border: 1px solid rgba(239, 68, 68, 0.22); color: #7f1d1d; }
            .pl-auth-unverified__actions { display: flex; justify-content: center; }
            .pl-auth-unverified__btn {
                appearance: none;
                border: 1px solid rgba(17, 24, 39, 0.12);
                background: #111827;
                color: #fff;
                border-radius: 12px;
                height: 44px;
                padding: 0 16px;
                font-weight: 700;
                letter-spacing: 0.12em;
                text-transform: uppercase;
                cursor: pointer;
            }
        </style>
        <div id="pl-auth-unverified" class="pl-auth-unverified<?php echo $should_open ? ' is-open' : ''; ?>" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr($title); ?>">
            <div class="pl-auth-unverified__card">
                <div class="pl-auth-unverified__inner">
                    <button type="button" class="pl-auth-unverified__close" aria-label="<?php echo esc_attr__('Close', 'politeia-learning'); ?>" data-pl-auth-unverified-close>×</button>
                    <h3 class="pl-auth-unverified__title"><?php echo esc_html($title); ?></h3>
                    <p class="pl-auth-unverified__text"><?php echo esc_html($body); ?></p>
                    <?php echo $message_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <form method="post" action="<?php echo esc_url($action_url); ?>" class="pl-auth-unverified__actions">
                        <input type="hidden" name="action" value="pl_auth_resend_confirmation">
                        <input type="hidden" name="<?php echo esc_attr(self::RESEND_NONCE_FIELD); ?>" value="<?php echo esc_attr($nonce); ?>">
                        <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to_for_form); ?>">
                        <button type="submit" class="pl-auth-unverified__btn"><?php echo esc_html($cta); ?></button>
                    </form>
                </div>
            </div>
        </div>
        <script>
        (function(){
            var overlay = document.getElementById('pl-auth-unverified');
            if (!overlay) return;
            try {
                var shouldAutoOpen = <?php echo $should_open ? 'true' : 'false'; ?>;
                if (overlay.classList.contains('is-open') && window.history && window.history.replaceState) {
                    var url = new URL(window.location.href);
                    url.searchParams.delete('pl_auth_unverified_after_quiz');
                    url.searchParams.delete('pl_auth_unverified');
                    url.searchParams.delete('<?php echo esc_js(self::QUERY_NOTICE); ?>');
                    url.searchParams.delete('<?php echo esc_js(self::QUERY_ERROR); ?>');
                    window.history.replaceState({}, document.title, url.toString());
                }
            } catch (e) {}
            var btn = overlay.querySelector('[data-pl-auth-unverified-close]');
            if (btn) btn.addEventListener('click', function(){ overlay.classList.remove('is-open'); });
        })();
        </script>
        <?php
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

        // Allow login even when not verified; verification can be enforced by feature gating
        // (e.g. checkout, final quiz, certificate) while still letting users take the first quiz.
        return $user;
    }

    public static function handle_resend_confirmation_nopriv(): void
    {
        $redirect_to = self::resolve_redirect_to((string) wp_unslash($_REQUEST['redirect_to'] ?? ''));
        wp_safe_redirect(self::build_modal_url('login', $redirect_to));
        exit;
    }

    public static function handle_resend_confirmation(): void
    {
        if (!is_user_logged_in()) {
            self::handle_resend_confirmation_nopriv();
        }

        if (!isset($_POST[self::RESEND_NONCE_FIELD]) || !wp_verify_nonce((string) wp_unslash($_POST[self::RESEND_NONCE_FIELD]), self::RESEND_NONCE_ACTION)) {
            $redirect_to = self::resolve_redirect_to((string) wp_unslash($_POST['redirect_to'] ?? ''));
            wp_safe_redirect(add_query_arg([self::QUERY_ERROR => 'invalid_nonce'], $redirect_to));
            exit;
        }

        $user_id = (int) get_current_user_id();
        $redirect_to = self::resolve_redirect_to((string) wp_unslash($_POST['redirect_to'] ?? ''));
        if ($user_id <= 0) {
            wp_safe_redirect($redirect_to);
            exit;
        }

        if (get_transient('pl_auth_resend_' . $user_id)) {
            wp_safe_redirect(add_query_arg([self::QUERY_ERROR => 'resend_throttled'], $redirect_to));
            exit;
        }
        set_transient('pl_auth_resend_' . $user_id, 1, 60);

        $user = get_userdata($user_id);
        if (!$user || empty($user->user_email)) {
            wp_safe_redirect(add_query_arg([self::QUERY_ERROR => 'create_failed'], $redirect_to));
            exit;
        }

        if (!self::requires_verification($user_id)) {
            // If the account doesn't require verification, do nothing.
            wp_safe_redirect($redirect_to);
            exit;
        }

        if (self::is_verified($user_id)) {
            wp_safe_redirect(add_query_arg([self::QUERY_NOTICE => 'verified'], $redirect_to));
            exit;
        }

        $token = self::issue_confirmation_token($user_id);
        $display_name = (string) ($user->display_name ?? '');
        if ($display_name === '') {
            $display_name = (string) $user_id;
        }
        self::send_confirmation_for_user($user_id, (string) $user->user_email, $display_name, $redirect_to, $token);

        wp_safe_redirect(add_query_arg([self::QUERY_NOTICE => 'verification_sent', 'pl_auth_unverified' => '1'], $redirect_to));
        exit;
    }

    private static function handle_login_request(string $redirect_to): void
    {
        $login_or_email = '';
        if (isset($_POST['user_login'])) {
            $login_or_email = sanitize_text_field((string) wp_unslash($_POST['user_login']));
        } elseif (isset($_POST['email'])) {
            // Back-compat: some forms use `email` for the same field.
            $login_or_email = sanitize_text_field((string) wp_unslash($_POST['email']));
        }
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
        $email = '';
        if (isset($_POST['email'])) {
            $email = sanitize_email((string) wp_unslash($_POST['email']));
        } elseif (isset($_POST['user_login'])) {
            // Back-compat: modal template uses `user_login` for the email field.
            $email = sanitize_email((string) wp_unslash($_POST['user_login']));
        }
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
            $code = (string) $user_id->get_error_code();
            // Normalize common WP user creation errors to our UI codes.
            if ($code === 'existing_user_email' || $code === 'existing_user_login') {
                $code = 'account_exists';
            } elseif ($code === 'invalid_email') {
                $code = 'invalid_email';
            } elseif ($code === 'invalid_username' || $code === 'empty_username') {
                $code = 'invalid_username';
            } else {
                $code = 'create_failed';
            }
            wp_safe_redirect(self::build_modal_url('register', $redirect_to, [self::QUERY_ERROR => $code]));
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

        // Auto-login after registration (even before email confirmation) to reduce friction.
        // Unverified accounts can be prompted and feature-gated elsewhere.
        wp_set_current_user((int) $user_id);
        wp_set_auth_cookie((int) $user_id, true, is_ssl());
        $u = get_user_by('id', (int) $user_id);
        if ($u instanceof WP_User) {
            do_action('wp_login', $u->user_login, $u);
        }

        // Do not add notices here: after registering we immediately start the quiz (if requested via redirect_to).
        // The unverified popup will appear after completing the quiz.
        $redirect_to = add_query_arg(['pl_auth_registered' => '1'], $redirect_to);
        wp_safe_redirect(wp_validate_redirect($redirect_to, home_url('/')));
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
        $verification_url = self::build_confirmation_url($email, $token, $redirect_to);

        $switched_locale = switch_to_user_locale($user_id);
        PL_Email::send_auth_confirmation($email, $display_name, $verification_url, $token);
        if ($switched_locale) {
            restore_previous_locale();
        }
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
