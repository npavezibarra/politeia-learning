<?php
/**
 * Centralized email rendering/sending helpers.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PL_Email
{
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

        return (bool) wp_mail($email, __('Confirm your Politeia account', 'politeia-learning'), $html, [
            'Content-Type: text/html; charset=UTF-8',
        ]);
    }
}
