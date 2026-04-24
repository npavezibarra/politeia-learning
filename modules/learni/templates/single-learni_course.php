<?php
/**
 * Learni course overview template (internal module copy).
 *
 * NOTE: This is intentionally minimal for rollout. Styling/assets will be aligned
 * with Politeia's learner UX in later steps.
 *
 * @var \WP_Post $post
 */

use Learni\Access\Access;
use Learni\Courses\Outline;
use Learni\Database\Progress;

if (!defined('ABSPATH')) {
    exit;
}

pl_template_open();

$course_id = isset($post->ID) ? (int) $post->ID : 0;

if ($course_id <= 0) {
    echo '<main class="learni-learner">';
    echo '<p>' . esc_html__('Course not found.', 'politeia-learning') . '</p>';
    echo '</main>';
} else {
    try {
        if (class_exists('PL_Learni_Frontend_ViewCourse')) {
            echo PL_Learni_Frontend_ViewCourse::render();
        } else {
            // Fallback if renderer is missing.
            echo '<main class="learni-learner">';
            echo '<h1>' . esc_html(get_the_title($course_id)) . '</h1>';
            echo apply_filters('the_content', (string) get_post_field('post_content', $course_id));
            echo '</main>';
        }
    } catch (\Throwable $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            echo '<p>Error: ' . esc_html($e->getMessage()) . '</p>';
        }
    }
}

pl_template_close();
