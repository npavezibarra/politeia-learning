<?php

namespace Learni\Auth;

use Learni\Auth\Handlers\LoginHandler;
use Learni\Auth\Handlers\RegisterHandler;
use Learni\Auth\Handlers\VerificationHandler;
use Learni\Auth\Handlers\PasswordHandler;
use Learni\Auth\UI\Renderer;
use Learni\Auth\Utilities\AuthUtils;
use Learni\Auth\PasswordPage;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main Orchestrator for Authentication module.
 */
final class AuthOrchestrator
{
    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // Core hooks
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets'], 20);
        add_action('wp_body_open', [$this, 'render_auth_modal'], 5);
        add_action('wp_footer', [$this, 'render_auth_modal'], 5);
        add_action('wp_footer', [$this, 'render_unverified_popup'], 30);
        
        // Form submissions
        add_action('admin_post_nopriv_pl_auth_submit', [$this, 'handle_submit']);
        add_action('admin_post_pl_auth_submit', [$this, 'handle_submit']);
        
        // Verification / Resend
        add_action('admin_post_pl_auth_resend_confirmation', [$this, 'handle_resend_confirmation']);
        add_action('admin_post_nopriv_pl_auth_resend_confirmation', [$this, 'handle_resend_confirmation_nopriv']);
        add_action('template_redirect', [$this, 'handle_confirmation_link'], 1);
        
        // AJAX
        add_action('wp_ajax_nopriv_pl_auth_forgot_password_probe', [PasswordHandler::class, 'ajax_probe']);
        add_action('wp_ajax_pl_auth_forgot_password_probe', [PasswordHandler::class, 'ajax_probe']);
        
        // Filters
        add_filter('login_url', [$this, 'filter_login_url'], 10, 3);
        add_filter('register_url', [$this, 'filter_register_url'], 10);
        
        // Shortcodes
        add_shortcode('pl_auth_links', [$this, 'render_auth_links_shortcode']);

        // Sub-modules
        PasswordPage::init();
    }

    public function enqueue_assets(): void
    {
        if (is_admin() || is_user_logged_in()) {
            return;
        }

        wp_enqueue_style('pl-auth-poppins', 'https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap', [], null);
        
        wp_enqueue_style(
            'pl-auth-modal-css',
            PL_AUTH_URL . 'assets/css/auth-modal.css',
            ['pl-auth-poppins'],
            filemtime(PL_AUTH_PATH . 'assets/css/auth-modal.css')
        );

        wp_enqueue_script(
            'pl-auth-modal-js',
            PL_AUTH_URL . 'assets/js/auth-modal.js',
            ['jquery'],
            filemtime(PL_AUTH_PATH . 'assets/js/auth-modal.js'),
            true
        );

        $is_spanish = strpos(get_locale(), 'es') === 0;
        wp_localize_script('pl-auth-modal-js', 'plAuthData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'forgotNonce' => wp_create_nonce('pl_auth_forgot_password'),
            'isSpanish' => $is_spanish,
            'loginUrl' => AuthUtils::build_modal_url('login'),
            'registerUrl' => AuthUtils::build_modal_url('register'),
            'labels' => $this->get_localization_labels($is_spanish),
            'messages' => [
                'email_mismatch' => __('The email addresses do not match.', 'politeia-learning'),
                'password_mismatch' => __('The passwords do not match.', 'politeia-learning'),
            ]
        ]);

        // Unverified popup assets (only for logged in users)
        if (is_user_logged_in()) {
            $user_id = (int) get_current_user_id();
            if (!VerificationHandler::is_verified($user_id) && VerificationHandler::requires_verification($user_id)) {
                wp_enqueue_style(
                    'pl-auth-unverified-css',
                    PL_AUTH_URL . 'assets/css/unverified-popup.css',
                    [],
                    filemtime(PL_AUTH_PATH . 'assets/css/unverified-popup.css')
                );
                wp_enqueue_script(
                    'pl-auth-unverified-js',
                    PL_AUTH_URL . 'assets/js/unverified-popup.js',
                    [],
                    filemtime(PL_AUTH_PATH . 'assets/js/unverified-popup.js'),
                    true
                );
            }
        }
    }

    private function get_localization_labels(bool $is_spanish): array
    {
        return [
            'welcome' => $is_spanish ? 'Bienvenido de nuevo' : 'Welcome back',
            'register_title' => $is_spanish ? 'Crea tu cuenta' : 'Create your account',
            'forgot_title' => $is_spanish ? 'Olvidé contraseña' : 'Forgot password',
            'login_copy' => $is_spanish ? 'Inicia sesión para continuar o crea una nueva cuenta.' : 'Log in to continue or create a new account.',
            'register_copy' => $is_spanish ? 'Crea una cuenta para recibir tu email de confirmación.' : 'Create an account and we will send you a confirmation email.',
            'forgot_copy' => $is_spanish ? 'Ingresa tu correo electrónico para restablecer tu contraseña.' : 'Enter your email to reset your password.',
            'login' => $is_spanish ? 'Ingresar' : 'Login',
            'register' => $is_spanish ? 'Registrarse' : 'Register',
            'email' => $is_spanish ? 'Correo electrónico' : 'Email',
            'login_identifier' => $is_spanish ? 'Correo o usuario' : 'Email or username',
            'already_account' => $is_spanish ? '¿Ya tienes cuenta?' : 'Already have an account?',
            'new_here' => $is_spanish ? '¿No tienes cuenta?' : 'New here?',
            'create_account_link' => $is_spanish ? 'Crea una cuenta' : 'Create an account',
            'back_to_login' => $is_spanish ? 'Inicia sesión' : 'Back to login',
            'forgot_link' => $is_spanish ? 'Olvidé mi contraseña' : 'Forgot password',
            'email_not_registered' => $is_spanish ? 'Este email no está registrado' : 'This email is not registered',
            'reset_sent' => $is_spanish ? 'Hemos enviado un correo para reestablecer contraseña' : 'We sent a password reset email',
            'create_account' => $is_spanish ? 'Crear cuenta' : 'Create account',
        ];
    }

    public function render_auth_modal(): void
    {
        if (class_exists(Renderer::class)) {
            echo Renderer::get_auth_modal_markup();
        }
    }

    public function render_unverified_popup(): void
    {
        if (class_exists(Renderer::class)) {
            echo Renderer::get_unverified_popup_markup();
        }
    }

    public function handle_submit(): void
    {
        if (!isset($_POST['pl_auth_nonce']) || !wp_verify_nonce((string) wp_unslash($_POST['pl_auth_nonce']), 'pl_auth_submit')) {
            wp_safe_redirect(AuthUtils::build_modal_url('login', home_url('/'), ['pl_auth_error' => 'invalid_nonce']));
            exit;
        }

        $mode = AuthUtils::sanitize_view((string) wp_unslash($_POST['mode'] ?? 'login'));
        $redirect_to = AuthUtils::resolve_redirect_to((string) wp_unslash($_POST['redirect_to'] ?? ''));

        if ($mode === 'register') {
            RegisterHandler::handle($redirect_to);
        }

        LoginHandler::handle($redirect_to);
    }

    public function handle_confirmation_link(): void
    {
        $action = isset($_GET['pl_auth_action']) ? sanitize_key((string) wp_unslash($_GET['pl_auth_action'])) : '';
        if ($action !== 'confirm') {
            return;
        }

        $email = isset($_GET['email']) ? sanitize_email((string) wp_unslash($_GET['email'])) : '';
        $token = isset($_GET['token']) ? trim(sanitize_text_field((string) wp_unslash($_GET['token']))) : '';
        $redirect_to = AuthUtils::resolve_redirect_to((string) wp_unslash($_GET['redirect_to'] ?? ''));

        $result = VerificationHandler::confirm_user_email($email, $token);
        if (is_wp_error($result)) {
            wp_safe_redirect(AuthUtils::build_modal_url('login', $redirect_to, ['pl_auth_error' => $result->get_error_code()]));
            exit;
        }

        wp_safe_redirect(AuthUtils::build_modal_url('login', $redirect_to, ['pl_auth_notice' => 'verified']));
        exit;
    }

    public function handle_resend_confirmation_nopriv(): void
    {
        $redirect_to = AuthUtils::resolve_redirect_to((string) wp_unslash($_REQUEST['redirect_to'] ?? ''));
        wp_safe_redirect(AuthUtils::build_modal_url('login', $redirect_to));
        exit;
    }

    public function handle_resend_confirmation(): void
    {
        if (!is_user_logged_in()) {
            $this->handle_resend_confirmation_nopriv();
        }

        if (!isset($_POST['pl_auth_resend_nonce']) || !wp_verify_nonce((string) wp_unslash($_POST['pl_auth_resend_nonce']), 'pl_auth_resend_confirmation')) {
            $redirect_to = AuthUtils::resolve_redirect_to((string) wp_unslash($_POST['redirect_to'] ?? ''));
            wp_safe_redirect(add_query_arg(['pl_auth_error' => 'invalid_nonce'], $redirect_to));
            exit;
        }

        $user_id = (int) get_current_user_id();
        $redirect_to = AuthUtils::resolve_redirect_to((string) wp_unslash($_POST['redirect_to'] ?? ''));
        
        if (get_transient('pl_auth_resend_' . $user_id)) {
            wp_safe_redirect(add_query_arg(['pl_auth_error' => 'resend_throttled'], $redirect_to));
            exit;
        }
        set_transient('pl_auth_resend_' . $user_id, 1, 60);

        $user = get_userdata($user_id);
        if (!$user || !VerificationHandler::requires_verification($user_id) || VerificationHandler::is_verified($user_id)) {
            wp_safe_redirect($redirect_to);
            exit;
        }

        $token = VerificationHandler::issue_token($user_id);
        VerificationHandler::send_confirmation($user_id, (string) $user->user_email, (string) $user->display_name, $redirect_to, $token);

        wp_safe_redirect(add_query_arg(['pl_auth_notice' => 'verification_sent', 'pl_auth_unverified' => '1'], $redirect_to));
        exit;
    }

    public function filter_login_url(string $login_url, string $redirect): string
    {
        return AuthUtils::build_modal_url('login', $redirect);
    }

    public function filter_register_url(string $register_url): string
    {
        return AuthUtils::build_modal_url('register');
    }

    public function render_auth_links_shortcode(): string
    {
        if (is_user_logged_in()) {
            return '';
        }

        $login_url = esc_url(AuthUtils::build_modal_url('login'));
        $register_url = esc_url(AuthUtils::build_modal_url('register'));

        return '<div class="pl-auth-links"><a class="pl-auth-link pl-auth-link-login" href="' . $login_url . '">' . esc_html__('Login', 'politeia-learning') . '</a><a class="pl-auth-link pl-auth-link-register" href="' . $register_url . '">' . esc_html__('Register', 'politeia-learning') . '</a></div>';
    }
}
