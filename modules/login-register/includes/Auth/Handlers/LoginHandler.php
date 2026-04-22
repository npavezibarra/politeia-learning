<?php

namespace Learni\Auth\Handlers;

use Learni\Auth\Utilities\AuthUtils;
use WP_User;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles login requests.
 */
class LoginHandler
{
    /**
     * Processes a login request.
     */
    public static function handle(string $redirect_to): void
    {
        $login_or_email = '';
        if (isset($_POST['user_login'])) {
            $login_or_email = sanitize_text_field((string) wp_unslash($_POST['user_login']));
        } elseif (isset($_POST['email'])) {
            $login_or_email = sanitize_text_field((string) wp_unslash($_POST['email']));
        }
        
        $password = isset($_POST['password']) ? (string) wp_unslash($_POST['password']) : '';
        $remember = !empty($_POST['remember']);

        if ($login_or_email === '' || $password === '') {
            wp_safe_redirect(AuthUtils::build_modal_url('login', $redirect_to, ['pl_auth_error' => 'missing_login']));
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
            wp_safe_redirect(AuthUtils::build_modal_url('login', $redirect_to, ['pl_auth_error' => $code]));
            exit;
        }

        wp_safe_redirect($redirect_to);
        exit;
    }
}
