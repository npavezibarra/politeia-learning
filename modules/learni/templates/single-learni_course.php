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

get_header();

$course_id = isset($post->ID) ? (int) $post->ID : 0;
$user_id = (int) get_current_user_id();

echo '<main class="learni-learner">';

if ($course_id <= 0) {
    echo '<p>' . esc_html__('Course not found.', 'politeia-learning') . '</p>';
    echo '</main>';
    get_footer();
    return;
}

echo '<header class="learni-course-header">';
echo '<h1>' . esc_html(get_the_title($course_id)) . '</h1>';
echo '</header>';

if ($user_id <= 0) {
    echo '<p>' . esc_html__('Please log in to view this course.', 'politeia-learning') . '</p>';
    echo '</main>';
    get_footer();
    return;
}

if (!Access::user_can_access_course($user_id, $course_id)) {
    echo '<p>' . esc_html__('You do not have access to this course.', 'politeia-learning') . '</p>';
    echo '</main>';
    get_footer();
    return;
}

$items = Outline::get_items($course_id);
$completed = array_flip(Progress::completed_lesson_ids($user_id, $course_id));
$summary = Progress::course_summary($user_id, $course_id);
$certificate_attachment_id = (int) get_post_meta($course_id, \Learni\PostTypes\Course::META_CERTIFICATE_ATTACHMENT_ID, true);
$certificate_url = $certificate_attachment_id > 0 ? (string) wp_get_attachment_url($certificate_attachment_id) : '';

echo '<div class="learni-progress">';
echo '<span>' . esc_html(sprintf(__('%1$d/%2$d complete', 'politeia-learning'), (int) $summary['completed'], (int) $summary['total'])) . '</span>';
echo '<span class="learni-progress-bar" role="progressbar" aria-valuenow="' . esc_attr((string) $summary['percent']) . '" aria-valuemin="0" aria-valuemax="100">';
echo '<span style="width:' . esc_attr((string) $summary['percent']) . '%"></span>';
echo '</span>';
echo '</div>';

if ($certificate_url !== '' && (int) ($summary['percent'] ?? 0) >= 100) {
    echo '<p><a class="learni-course-cert-trigger" href="' . esc_url($certificate_url) . '" target="_blank" rel="noopener">';
    echo '<span class="learni-course-cert-icon" aria-hidden="true"></span>';
    echo '<span class="learni-course-cert-text">' . esc_html__('CERTIFICADO', 'politeia-learning') . '</span>';
    echo '</a></p>';
}

echo '<section class="learni-course-content">';
echo apply_filters('the_content', (string) get_post_field('post_content', $course_id));
echo '</section>';

echo '<section class="learni-outline">';

if (empty($items)) {
    echo '<p>' . esc_html__('No lessons yet.', 'politeia-learning') . '</p>';
} else {
    echo '<ul class="learni-outline-list">';
    foreach ($items as $item) {
        if (($item['type'] ?? '') === 'header') {
            echo '<li class="learni-outline-header">' . esc_html((string) ($item['label'] ?? '')) . '</li>';
            continue;
        }

        if (($item['type'] ?? '') !== 'lesson') {
            continue;
        }

        $lesson_id = (int) ($item['refId'] ?? 0);
        if ($lesson_id <= 0) {
            continue;
        }

        $lesson_slug = (string) get_post_field('post_name', $lesson_id);
        $url = $lesson_slug !== '' ? home_url('/?learni_lesson=' . rawurlencode($lesson_slug)) : '';

        $done = isset($completed[$lesson_id]);
        $label = (string) get_the_title($lesson_id);

        echo '<li class="learni-outline-lesson' . ($done ? ' is-complete' : '') . '">';
        if ($url !== '') {
            echo '<a href="' . esc_url($url) . '">';
        }
        echo '<span class="learni-check" aria-hidden="true">' . ($done ? '✓' : '•') . '</span>';
        echo '<span class="learni-label">' . esc_html($label) . '</span>';
        if ($url !== '') {
            echo '</a>';
        }
        echo '</li>';
    }
    echo '</ul>';
}

echo '</section>';
echo '</main>';

get_footer();
