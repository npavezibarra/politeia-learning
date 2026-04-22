<?php

namespace Learni\Auth\Handlers;

use Learni\Auth\Utilities\AuthUtils;
use WP_User;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles registration requests.
 */
class RegisterHandler
{
    /**
     * Processes a registration request.
     */
    public static function handle(string $redirect_to): void
    {
        $first_name = isset($_POST['first_name']) ? sanitize_text_field((string) wp_unslash($_POST['first_name'])) : '';
        $last_name = isset($_POST['last_name']) ? sanitize_text_field((string) wp_unslash($_POST['last_name'])) : '';
        
        $email = '';
        if (isset($_POST['email'])) {
            $email = sanitize_email((string) wp_unslash($_POST['email']));
        } elseif (isset($_POST['user_login'])) {
            $email = sanitize_email((string) wp_unslash($_POST['user_login']));
        }
        
        $email_confirm = isset($_POST['email_confirm']) ? sanitize_email((string) wp_unslash($_POST['email_confirm'])) : '';
        $password = isset($_POST['password']) ? (string) wp_unslash($_POST['password']) : '';
        $password_confirm = isset($_POST['password_confirm']) ? (string) wp_unslash($_POST['password_confirm']) : '';

        if ($email === '' || !is_email($email)) {
            wp_safe_redirect(AuthUtils::build_modal_url('register', $redirect_to, ['pl_auth_error' => 'invalid_email']));
            exit;
        }

        if ($email !== $email_confirm) {
            wp_safe_redirect(AuthUtils::build_modal_url('register', $redirect_to, ['pl_auth_error' => 'email_mismatch']));
            exit;
        }

        if ($password === '' || strlen($password) < 8) {
            wp_safe_redirect(AuthUtils::build_modal_url('register', $redirect_to, ['pl_auth_error' => 'weak_password']));
            exit;
        }

        if ($password !== $password_confirm) {
            wp_safe_redirect(AuthUtils::build_modal_url('register', $redirect_to, ['pl_auth_error' => 'password_mismatch']));
            exit;
        }

        $existing_user = get_user_by('email', $email);
        if ($existing_user instanceof WP_User) {
            if (VerificationHandler::is_verified($existing_user->ID)) {
                wp_safe_redirect(AuthUtils::build_modal_url('register', $redirect_to, ['pl_auth_error' => 'account_exists']));
                exit;
            }

            $token = VerificationHandler::issue_token((int) $existing_user->ID);
            VerificationHandler::send_confirmation((int) $existing_user->ID, $email, (string) $existing_user->display_name, $redirect_to, $token);
            wp_safe_redirect(AuthUtils::build_modal_url('login', $redirect_to, ['pl_auth_notice' => 'verification_sent']));
            exit;
        }

        $username = AuthUtils::generate_username_from_email($email);
        $user_id = wp_create_user($username, $password, $email);

        if (is_wp_error($user_id)) {
            $code = self::normalize_error_code((string) $user_id->get_error_code());
            wp_safe_redirect(AuthUtils::build_modal_url('register', $redirect_to, ['pl_auth_error' => $code]));
            exit;
        }

        if ($first_name !== '') {
            update_user_meta((int) $user_id, 'first_name', $first_name);
        }
        if ($last_name !== '') {
            update_user_meta((int) $user_id, 'last_name', $last_name);
        }

        $token = VerificationHandler::issue_token((int) $user_id);
        $display_name = trim($first_name . ' ' . $last_name) ?: $username;

        VerificationHandler::send_confirmation((int) $user_id, $email, $display_name, $redirect_to, $token);
        wp_new_user_notification((int) $user_id, null, 'admin');

        // Auto-login after registration
        wp_set_current_user((int) $user_id);
        wp_set_auth_cookie((int) $user_id, true, is_ssl());
        $u = get_user_by('id', (int) $user_id);
        if ($u instanceof WP_User) {
            do_action('wp_login', $u->user_login, $u);
        }

        $redirect_to = add_query_arg(['pl_auth_registered' => '1'], $redirect_to);
        wp_safe_redirect(wp_validate_redirect($redirect_to, home_url('/')));
        exit;
    }

    /**
     * Normalizes WP user creation errors to our UI codes.
     */
    private static function normalize_error_code(string $code): string
    {
        if ($code === 'existing_user_email' || $code === 'existing_user_login') {
            return 'account_exists';
        } 
        if ($code === 'invalid_email') {
            return 'invalid_email';
        }
        if ($code === 'invalid_username' || $code === 'empty_username') {
            return 'invalid_username';
        }
        return 'create_failed';
    }
}
