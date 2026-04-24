<?php
/**
 * Utility methods for partnerships.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PL_Partnership_Utils
{
    /**
     * Generate a username from an email address.
     */
    public static function generate_username_from_email(string $email): string
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

    /**
     * Normalize email address (lowercase and trimmed).
     */
    public static function normalize_email(string $email): string
    {
        return strtolower(trim($email));
    }

    /**
     * Check if a column exists in a table.
     */
    public static function invites_table_column_exists(string $table, string $column): bool
    {
        global $wpdb;
        return (bool) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s LIMIT 1',
                $table,
                $column
            )
        );
    }

    /**
     * Send a course invitation email.
     */
    public static function send_course_invite_email(string $email, string $accept_url, int $course_id, string $invitee_name = ''): void
    {
        $course_title = $course_id > 0 ? get_the_title($course_id) : '';
        $subject = $course_title ? sprintf('Course invitation: %s', $course_title) : 'Course invitation';

        $hello = $invitee_name !== '' ? sprintf('<p>Hi %s,</p>', esc_html($invitee_name)) : '';
        $body = sprintf(
            '%s<p>You have been invited to join a course as a partner.</p><p><a href="%s">Accept invitation</a></p>',
            $hello,
            esc_url($accept_url)
        );

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        wp_mail($email, $subject, $body, $headers);
    }
}
