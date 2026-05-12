<?php

namespace Learni\Auth\UI;

use Learni\Auth\Utilities\AuthUtils;
use Learni\Auth\Handlers\VerificationHandler;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles rendering of Authentication UI components.
 */
class Renderer
{
    /**
     * Returns the markup for the authentication modal.
     */
    public static function get_auth_modal_markup(): string
    {
        static $rendered = false;
        if ($rendered || is_admin() || is_user_logged_in()) {
            return '';
        }
        $rendered = true;

        $view = AuthUtils::sanitize_view((string) wp_unslash($_GET['pl_auth_view'] ?? 'login'));
        $notice = (string) sanitize_key((string) wp_unslash($_GET['pl_auth_notice'] ?? ''));
        $error = (string) sanitize_key((string) wp_unslash($_GET['pl_auth_error'] ?? ''));
        $redirect_to = AuthUtils::resolve_redirect_to((string) wp_unslash($_GET['redirect_to'] ?? ''));
        $auto_open = isset($_GET['pl_auth_view']) || isset($_GET['pl_auth_notice']) || isset($_GET['pl_auth_error']);
        $action_url = admin_url('admin-post.php');
        $nonce = wp_create_nonce('pl_auth_submit');

        ob_start();
        $template = PL_AUTH_PATH . 'templates/auth-modal.php';
        if (file_exists($template)) {
            include $template;
        }
        return (string) ob_get_clean();
    }

    /**
     * Returns the markup for the unverified account popup.
     */
    public static function get_unverified_popup_markup(): string
    {
        static $rendered = false;
        if ($rendered || is_admin() || !is_user_logged_in()) {
            return '';
        }

        $user_id = (int) get_current_user_id();
        if ($user_id <= 0 || VerificationHandler::is_verified($user_id) || !VerificationHandler::requires_verification($user_id)) {
            return '';
        }
        $rendered = true;

        $user = wp_get_current_user();
        $user_email = ($user && isset($user->user_email)) ? sanitize_email((string) $user->user_email) : '';

        $notice_code = (string) sanitize_key((string) wp_unslash($_GET['pl_auth_notice'] ?? ''));
        $error_code = (string) sanitize_key((string) wp_unslash($_GET['pl_auth_error'] ?? ''));
        $force_open = isset($_GET['pl_auth_unverified']) && sanitize_key((string) wp_unslash($_GET['pl_auth_unverified'])) === '1';
        $open_after_quiz = isset($_GET['pl_auth_unverified_after_quiz']) && sanitize_key((string) wp_unslash($_GET['pl_auth_unverified_after_quiz'])) === '1';
        $action_url = admin_url('admin-post.php');
        $nonce = wp_create_nonce('pl_auth_resend_confirmation');
        $confirm_nonce = wp_create_nonce('pl_auth_confirm_token');
        
        $redirect_to = AuthUtils::resolve_redirect_to((string) wp_unslash($_GET['redirect_to'] ?? ''));
        $redirect_to_for_form = remove_query_arg([
            'pl_auth_unverified_after_quiz',
            'pl_auth_unverified',
            'pl_auth_notice',
            'pl_auth_error',
        ], $redirect_to);

        $is_spanish = strpos(get_locale(), 'es') === 0;

        $dismissed = isset($_COOKIE['pl_auth_unverified_dismissed']) && !$force_open;

        $message = '';
        $message_type = '';
        if ($notice_code === 'verification_sent') {
            if ($is_spanish) {
                $message = $user_email !== ''
                    ? 'Te enviamos un correo de confirmación a ' . $user_email . '. Por favor revisa tu bandeja de entrada.'
                    : 'Te enviamos un correo de confirmación. Por favor revisa tu bandeja de entrada.';
            } else {
                $message = $user_email !== ''
                    ? 'We sent a confirmation email to ' . $user_email . '. Please check your inbox.'
                    : 'We sent a confirmation email. Please check your inbox.';
            }
            $message_type = 'success';
        } elseif ($error_code === 'resend_throttled') {
            $message = $is_spanish
                ? 'Espera un momento antes de reenviar el correo.'
                : 'Please wait a moment before resending.';
            $message_type = 'warning';
        } elseif ($error_code === 'verification_send_failed') {
            $message = $is_spanish
                ? 'No pudimos enviar el correo de confirmación en este momento. Por favor revisa tu carpeta de Spam/No deseado o inténtalo de nuevo más tarde.'
                : 'We could not send the confirmation email right now. Please check your Spam/Junk folder or try again later.';
            $message_type = 'error';
        } elseif ($error_code !== '') {
            $message = $is_spanish
                ? 'Algo salió mal. Por favor, inténtalo de nuevo.'
                : 'Something went wrong. Please try again.';
            $message_type = 'error';
        }
        
        $should_open = !$dismissed
            || $force_open
            || $open_after_quiz
            || $notice_code === 'verification_sent'
            || $error_code !== '';

        $show_token_form = $notice_code === 'verification_sent';

        $token_label = $is_spanish
            ? 'Si tu proveedor de correo bloquea el botón, pega el token aquí:'
            : 'If your email provider blocks the button, paste the token here:';
        $token_placeholder = $is_spanish ? 'Pegar token' : 'Paste token';
        $token_confirm = $is_spanish ? 'Confirmar' : 'Confirm';
        $token_help = $is_spanish
            ? 'Esto verificará: %1$s'
            : 'This will verify: %1$s';

        // Data for the template
        $data = [
            'title' => $is_spanish ? 'Aún no has verificado tu cuenta' : 'Your account is not verified yet',
            'body' => $is_spanish ? 'Para mayor seguridad revisa tu correo y verifica la creación de tu cuenta en Politeia.' : 'For your security, please check your email and verify the creation of your Politeia account.',
            'cta' => $is_spanish ? 'Reenviar confirmación' : 'Resend confirmation',
            'action_url' => $action_url,
            'nonce' => $nonce,
            'confirm_nonce' => $confirm_nonce,
            'redirect_to' => $redirect_to_for_form,
            'message' => $message,
            'message_type' => $message_type,
            'should_open' => $should_open,
            'user_email' => $user_email,
            'show_token_form' => $show_token_form,
            'token_label' => $token_label,
            'token_placeholder' => $token_placeholder,
            'token_confirm' => $token_confirm,
            'token_help' => $token_help,
        ];

        ob_start();
        $template = PL_AUTH_PATH . 'templates/auth/unverified-popup.php';
        if (file_exists($template)) {
            include $template;
        }
        return (string) ob_get_clean();
    }
}
