<?php
/**
 * Centralized email rendering/sending helpers.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PL_Email
{
    private const PASSWORD_RESET_MARKER = '<!--pl_password_reset-->';

    public static function init(): void
    {
        add_filter('retrieve_password_title', [__CLASS__, 'filter_retrieve_password_title'], 10, 3);
        add_filter('retrieve_password_message', [__CLASS__, 'filter_retrieve_password_message'], 10, 4);
        add_filter('wp_mail', [__CLASS__, 'filter_wp_mail_force_html_for_password_reset'], 10, 1);
    }

    public static function filter_retrieve_password_title(string $title, string $user_login, WP_User $user_data): string
    {
        unset($user_login, $user_data);
        return __('Reset Password', 'politeia-learning');
    }

    public static function filter_retrieve_password_message(string $message, string $key, string $user_login, WP_User $user_data): string
    {
        unset($user_data);

        $reset_url = add_query_arg([
            'key' => $key,
            'login' => $user_login,
        ], home_url('/restablecer-contrasena/'));

        $html = self::render('password-reset', [
            'user_login' => $user_login,
            'reset_url' => $reset_url,
        ]);

        if ('' === trim($html)) {
            return self::PASSWORD_RESET_MARKER . $message;
        }

        return self::PASSWORD_RESET_MARKER . $html;
    }

    public static function filter_wp_mail_force_html_for_password_reset(array $args): array
    {
        if (!isset($args['message']) || !is_string($args['message'])) {
            return $args;
        }

        if (strpos($args['message'], self::PASSWORD_RESET_MARKER) === false) {
            return $args;
        }

        $args['message'] = str_replace(self::PASSWORD_RESET_MARKER, '', $args['message']);

        $headers = $args['headers'] ?? [];
        if (is_string($headers)) {
            $headers = [$headers];
        }
        if (!is_array($headers)) {
            $headers = [];
        }

        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $args['headers'] = $headers;

        return $args;
    }

    /**
     * Render an email template from templates/emails/{template}.php.
     *
     * @param string               $template Template slug (without extension).
     * @param array<string,mixed>  $vars     Variables extracted into template scope.
     */
    public static function render(string $template, array $vars = []): string
    {
        $template = trim($template);
        if ($template === '') {
            return '';
        }

        $path = PL_PATH . 'templates/emails/' . $template . '.php';
        if (!file_exists($path)) {
            return '';
        }

        // If Email Log module is enabled, store the template path for better attribution.
        if (class_exists('PL_Email_Log_Manager')) {
            PL_Email_Log_Manager::set_last_template_file($path);
        }

        if (!empty($vars)) {
            // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
            extract($vars, EXTR_SKIP);
        }

        ob_start();
        include $path;
        return (string) ob_get_clean();
    }

    public static function send_course_invite(string $email, string $accept_url, int $course_id, string $invitee_name = ''): bool
    {
        $email = sanitize_email($email);
        if (!$email || !is_email($email)) {
            return false;
        }

        $invitee_name = trim(sanitize_text_field($invitee_name));

        $inviter = wp_get_current_user();
        $inviter_name = $inviter && !empty($inviter->display_name) ? (string) $inviter->display_name : 'Politeia';
        $course_name = $course_id > 0 ? (string) get_the_title($course_id) : '';

        $html = self::render('course-partner-invite', [
            'invitee_name' => $invitee_name,
            'inviter_name' => $inviter_name,
            'course_name' => $course_name,
            'accept_url' => $accept_url,
        ]);

        if ('' === trim($html)) {
            $html = sprintf(
                '<p>%s invited you to join a course as a partner.</p><p><a href="%s">Accept invitation</a></p>',
                esc_html($inviter_name),
                esc_url($accept_url)
            );
        }

        return (bool) wp_mail($email, 'Course Invitation', $html, [
            'Content-Type: text/html; charset=UTF-8',
        ]);
    }

    public static function send_auth_confirmation(string $email, string $user_name, string $verification_url, string $token): bool
    {
        $email = sanitize_email($email);
        if (!$email || !is_email($email)) {
            return false;
        }

        $user_name = trim(sanitize_text_field($user_name));
        $verification_url = esc_url_raw($verification_url);
        $token = trim(sanitize_text_field($token));

        $html = self::render('auth-confirmation', [
            'user_name' => $user_name,
            'verification_url' => $verification_url,
            'token' => $token,
        ]);

        if ('' === trim($html)) {
            $html = sprintf(
                '<p>%s</p><p><a href="%s">%s</a></p><p>%s: <code>%s</code></p>',
                esc_html(sprintf(__('Hi %s, please confirm your account.', 'politeia-learning'), $user_name !== '' ? $user_name : __('there', 'politeia-learning'))),
                esc_url($verification_url),
                esc_html__('Confirm account', 'politeia-learning'),
                esc_html__('Token', 'politeia-learning'),
                esc_html($token)
            );
        }

        $site_name = wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES);
        $host = (string) wp_parse_url(home_url('/'), PHP_URL_HOST);
        $from_email = $host !== '' ? 'no-reply@' . preg_replace('/^www\\./', '', $host) : '';

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
        ];
        if ($from_email !== '' && is_email($from_email)) {
            $headers[] = 'From: ' . $site_name . ' <' . $from_email . '>';
            $headers[] = 'Reply-To: ' . $site_name . ' <' . $from_email . '>';
        }

        return (bool) wp_mail($email, __('Confirm your Politeia account', 'politeia-learning'), $html, $headers);
    }
}

if (function_exists('add_action')) {
    add_action('plugins_loaded', [PL_Email::class, 'init'], 20);
}
