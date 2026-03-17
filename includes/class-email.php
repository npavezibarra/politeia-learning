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
}
