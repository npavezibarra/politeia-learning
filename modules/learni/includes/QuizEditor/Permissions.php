<?php

namespace Learni\QuizEditor;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Permission helpers for the Quiz Editor.
 */
final class Permissions
{
    public static function can_access(int $course_id = 0, int $quiz_id = 0): bool
    {
        if (!is_user_logged_in()) return false;
        if (current_user_can('manage_options') || current_user_can('edit_posts')) return true;

        $user_id = get_current_user_id();

        if ($quiz_id > 0) {
            $resolved_course_id = QuizRepository::get_course_id_by_quiz_id($quiz_id);
            if ($resolved_course_id > 0) {
                $course_id = $course_id > 0 ? $course_id : $resolved_course_id;
            }
        }

        if ($course_id > 0) {
            if (self::user_owns_post($course_id, $user_id)) return true;
            if (self::user_has_course_role($course_id, $user_id)) return true;
        }

        return false;
    }

    private static function user_owns_post(int $post_id, int $user_id): bool
    {
        $author_id = (int) get_post_field('post_author', $post_id);
        return $author_id === $user_id;
    }

    private static function user_has_course_role(int $course_id, int $user_id): bool
    {
        global $wpdb;
        
        // Partnerships check
        if (class_exists('Learni\Rest\Routes') && method_exists('Learni\Rest\Routes', 'course_partner_users')) {
            $partners = \Learni\Rest\Routes::course_partner_users($course_id);
            if (in_array($user_id, $partners)) return true;
        }

        // Legacy table check
        $table = $wpdb->prefix . 'politeia_course_roles';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'")) {
            $found = $wpdb->get_var($wpdb->prepare(
                "SELECT 1 FROM {$table} WHERE object_type = %s AND object_id = %d AND user_id = %d LIMIT 1",
                'course',
                $course_id,
                $user_id
            ));
            return !empty($found);
        }

        return false;
    }
}
