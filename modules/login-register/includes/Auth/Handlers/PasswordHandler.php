<?php

namespace Learni\Auth\Handlers;

use WP_User;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles password reset AJAX requests.
 */
class PasswordHandler
{
    /**
     * Responds to the forgot password probe AJAX call.
     */
    public static function ajax_probe(): void
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

        $html = class_exists('\PL_Email')
            ? (string) \PL_Email::render('password-reset', [
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
}
