<?php
/**
 * Learni lesson template (internal module copy).
 *
 * This uses standard WP singular rendering for `learni_lesson` and derives its
 * parent course by looking up `learni_course_items`.
 *
 * @var \WP_Post $post
 */

use Learni\Courses\Outline;
use Learni\Database\Progress;

if (!defined('ABSPATH')) {
    exit;
}

pl_template_open();

$lesson_id = isset($post->ID) ? (int) $post->ID : 0;

if ($lesson_id <= 0) {
    echo '<main class="learni-learner">';
    echo '<p>' . esc_html__('Lesson not found.', 'politeia-learning') . '</p>';
    echo '</main>';
} else {
    try {
        if (class_exists('PL_Learni_Frontend_ViewLesson')) {
            echo PL_Learni_Frontend_ViewLesson::render();
        } else {
            // Fallback if renderer is missing.
            echo '<main class="learni-learner">';
            echo '<h1>' . esc_html(get_the_title($lesson_id)) . '</h1>';
            echo apply_filters('the_content', (string) ($post->post_content ?? ''));
            echo '</main>';
        }
    } catch (\Throwable $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            echo '<p>Error: ' . esc_html($e->getMessage()) . '</p>';
        }
    }
}

pl_template_close();
