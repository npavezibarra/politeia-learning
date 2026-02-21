<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Permission helpers for the Quiz Creator module.
 *
 * The course creator UI allows "customer"-like users to author LearnDash courses
 * without granting them broad WP capabilities like `edit_posts`.
 * These helpers allow course authors (and assigned course roles) to access
 * the Quiz Creator only for their own content.
 */

function pqc_user_owns_post(int $post_id, int $user_id): bool
{
    $post = get_post($post_id);
    if (!$post) {
        return false;
    }
    return ((int) $post->post_author) === $user_id;
}

function pqc_user_has_course_role(int $course_id, int $user_id): bool
{
    global $wpdb;
    if (!$wpdb) {
        return false;
    }

    $table = $wpdb->prefix . 'politeia_course_roles';
    // If the table doesn't exist, treat as no role.
    // (wpdb->get_var will return null/false if it errors.)
    $found = $wpdb->get_var($wpdb->prepare(
        "SELECT 1 FROM {$table} WHERE object_type = %s AND object_id = %d AND user_id = %d LIMIT 1",
        'course',
        $course_id,
        $user_id
    ));

    return !empty($found);
}

/**
 * Returns whether the current user can access the Quiz Creator for a given course/quiz.
 *
 * - Admins / editors with `edit_posts` are allowed (legacy behavior).
 * - Otherwise, the user must own the course/quiz (or be assigned a role on the course).
 */
function pqc_can_access_quiz_creator(int $course_id = 0, int $quiz_id = 0): bool
{
    if (!is_user_logged_in()) {
        return false;
    }

    if (current_user_can('manage_options') || current_user_can('edit_posts')) {
        return true;
    }

    $user_id = get_current_user_id();

    if ($quiz_id > 0 && pqc_user_owns_post($quiz_id, $user_id)) {
        return true;
    }

    if ($course_id > 0) {
        if (pqc_user_owns_post($course_id, $user_id)) {
            return true;
        }
        if (pqc_user_has_course_role($course_id, $user_id)) {
            return true;
        }
    }

    return false;
}
