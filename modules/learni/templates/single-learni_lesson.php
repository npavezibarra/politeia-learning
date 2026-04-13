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

$lesson_id = isset($post->ID) ? (int) $post->ID : 0;
$user_id = (int) get_current_user_id();

$course_id = 0;
if ($lesson_id > 0) {
    global $wpdb;
    if ($wpdb) {
        $course_id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT course_post_id
                 FROM {$wpdb->prefix}learni_course_items
                 WHERE item_type = %s AND item_ref_id = %d
                 ORDER BY id DESC
                 LIMIT 1",
                'lesson',
                $lesson_id
            )
        );
    }
}

$lesson_ids = $course_id > 0 ? Outline::lesson_ids($course_id) : [];
$index = $lesson_id > 0 ? array_search($lesson_id, $lesson_ids, true) : false;
$index = is_int($index) ? $index : -1;

$prev_id = $index > 0 ? (int) $lesson_ids[$index - 1] : 0;
$next_id = ($index >= 0 && $index < count($lesson_ids) - 1) ? (int) $lesson_ids[$index + 1] : 0;

$prev_url = '';
$next_url = '';
if ($prev_id > 0) {
    $prev_slug = (string) get_post_field('post_name', $prev_id);
    $prev_url = $prev_slug !== '' ? home_url('/?learni_lesson=' . rawurlencode($prev_slug)) : '';
}
if ($next_id > 0) {
    $next_slug = (string) get_post_field('post_name', $next_id);
    $next_url = $next_slug !== '' ? home_url('/?learni_lesson=' . rawurlencode($next_slug)) : '';
}

$summary = ($user_id > 0 && $course_id > 0) ? Progress::course_summary($user_id, $course_id) : ['total' => 0, 'completed' => 0, 'percent' => 0];
$completed = ($user_id > 0 && $course_id > 0) ? array_flip(Progress::completed_lesson_ids($user_id, $course_id)) : [];
$items = $course_id > 0 ? Outline::get_items($course_id) : [];
$is_complete = $lesson_id > 0 && isset($completed[$lesson_id]);
$total = (int) ($summary['total'] ?? 0);
$step = $index >= 0 ? $index + 1 : 0;
$percent = (int) ($summary['percent'] ?? 0);
$course_url = $course_id > 0 ? (string) get_permalink($course_id) : '';
$course_title = $course_id > 0 ? (string) get_the_title($course_id) : '';

$linear_raw = ($course_id > 0 && class_exists('\\Learni\\PostTypes\\Course')) ? get_post_meta($course_id, \Learni\PostTypes\Course::META_LINEAR_ORDER, true) : '';
$linear_order = $linear_raw === '' ? true : (bool) (int) $linear_raw;
$max_unlocked = -1;
if (!empty($lesson_ids)) {
    if (!$linear_order) {
        $max_unlocked = count($lesson_ids) - 1;
    } else {
        $i = 0;
        $n = count($lesson_ids);
        while ($i < $n) {
            $lid = (int) $lesson_ids[$i];
            if (!isset($completed[$lid])) {
                break;
            }
            $i++;
        }
        $max_unlocked = min($i, $n - 1);
    }
}

$is_locked = $linear_order && $index >= 0 && $max_unlocked >= 0 && $index > $max_unlocked;
if ($is_locked) {
    $unlocked_id = isset($lesson_ids[$max_unlocked]) ? (int) $lesson_ids[$max_unlocked] : 0;
    $unlocked_slug = $unlocked_id > 0 ? (string) get_post_field('post_name', $unlocked_id) : '';
    $unlocked_url = $unlocked_slug !== '' ? home_url('/?learni_lesson=' . rawurlencode($unlocked_slug)) : $course_url;
    if ($unlocked_url !== '') {
        wp_safe_redirect($unlocked_url);
        exit;
    }
}

get_header();

$video_url = $lesson_id > 0 ? (string) get_post_meta($lesson_id, \Learni\PostTypes\Lesson::META_VIDEO_URL, true) : '';
$video_html = $video_url !== '' ? (string) wp_oembed_get($video_url) : '';

// Build outline HTML once (used for desktop + mobile overlay).
$outline_html = '';
$lesson_index = [];
foreach ($lesson_ids as $i => $lid) {
    $lid = (int) $lid;
    if ($lid > 0) {
        $lesson_index[$lid] = (int) $i;
    }
}
if (empty($items)) {
    $outline_html = '<div class="learni-lesson-outline-empty">' . esc_html__('No lessons yet.', 'politeia-learning') . '</div>';
} else {
    $outline_html .= '<ul class="learni-lesson-outline-list">';
    foreach ($items as $item) {
        $type = (string) ($item['type'] ?? '');
        if ($type === 'header') {
            $label = (string) ($item['label'] ?? '');
            if ($label !== '') {
                $outline_html .= '<li class="learni-lesson-outline-header">' . esc_html($label) . '</li>';
            }
            continue;
        }
        if ($type !== 'lesson') {
            continue;
        }
        $item_lesson_id = (int) ($item['refId'] ?? 0);
        if ($item_lesson_id <= 0) {
            continue;
        }

        $label = (string) get_the_title($item_lesson_id);
        $slug = (string) get_post_field('post_name', $item_lesson_id);
        $url = $slug !== '' ? home_url('/?learni_lesson=' . rawurlencode($slug)) : '';
        $item_is_complete = isset($completed[$item_lesson_id]);
        $pos = isset($lesson_index[$item_lesson_id]) ? (int) $lesson_index[$item_lesson_id] : -1;
        $item_is_locked = $linear_order && $pos >= 0 && $max_unlocked >= 0 && $pos > $max_unlocked;
        $classes = 'learni-lesson-outline-item';
        if ($item_lesson_id === $lesson_id) {
            $classes .= ' is-active';
        }
        if ($item_is_complete) {
            $classes .= ' is-complete';
        }
        if ($item_is_locked) {
            $classes .= ' is-locked';
        }

        $outline_html .= '<li class="' . esc_attr($classes) . '">';
        $outline_html .= ($url !== '' && !$item_is_locked) ? '<a href="' . esc_url($url) . '">' : '<span>';
        $outline_html .= '<span class="learni-lesson-outline-label">' . esc_html($label) . '</span>';
        $outline_html .= '<span class="learni-lesson-outline-status" aria-hidden="true">' . ($item_is_complete ? '✓' : '') . '</span>';
        $outline_html .= ($url !== '' && !$item_is_locked) ? '</a>' : '</span>';
        $outline_html .= '</li>';
    }
    $outline_html .= '</ul>';
}

echo '<main class="learni-learner learni-lesson-layout">';
echo '<div class="learni-lesson-shell">';

echo '<aside class="learni-lesson-aside" aria-label="' . esc_attr__('Course navigation', 'politeia-learning') . '">';
if ($course_url !== '') {
    echo '<a class="learni-lesson-back" href="' . esc_url($course_url) . '"><span class="learni-lesson-back-label">' . esc_html__('VOLVER A CURSO', 'politeia-learning') . '</span></a>';
}
if ($course_title !== '') {
    echo '<h2 class="learni-lesson-course-title">' . esc_html($course_title) . '</h2>';
}
if ($course_id > 0) {
    echo '<div class="learni-lesson-course-progress" aria-label="' . esc_attr__('Course progress', 'politeia-learning') . '">';
    echo '<div class="learni-lesson-course-progress-label">' . esc_html(sprintf(__('%d%% COMPLETO', 'politeia-learning'), $percent)) . '</div>';
    echo '<div class="learni-lesson-course-progress-bar" role="progressbar" aria-valuenow="' . esc_attr((string) $percent) . '" aria-valuemin="0" aria-valuemax="100">';
    echo '<span class="learni-lesson-course-progress-fill" style="width:' . esc_attr((string) $percent) . '%"></span>';
    echo '</div>';
    echo '</div>';
}

echo '<nav id="learni-lesson-outline" class="learni-lesson-outline" aria-label="' . esc_attr__('Lessons', 'politeia-learning') . '">';
echo $outline_html;
echo '</nav>';
echo '</aside>';

echo '<section class="learni-lesson-main" aria-label="' . esc_attr__('Lesson content', 'politeia-learning') . '">';
echo '<div class="learni-lesson-top">';
echo '<div class="learni-lesson-step">' . esc_html(sprintf(__('LECCIÓN %1$d DE %2$d', 'politeia-learning'), (int) $step, (int) $total)) . '</div>';
echo '<div class="learni-lesson-top-actions">';

$btn_label = $is_complete ? __('FINALIZADO', 'politeia-learning') : __('FINALIZAR', 'politeia-learning');
$btn_disabled = ($user_id <= 0) || $is_complete || ($course_id <= 0) || $is_locked;
echo '<form class="learni-lesson-complete-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
echo '<input type="hidden" name="action" value="pl_learni_mark_lesson_complete">';
echo '<input type="hidden" name="lesson_id" value="' . esc_attr((string) $lesson_id) . '">';
echo '<input type="hidden" name="redirect_to" value="' . esc_attr((string) (wp_get_raw_referer() ?: '')) . '">';
wp_nonce_field('pl_learni_complete_lesson_' . $lesson_id);
echo '<button type="submit" class="learni-lesson-complete-btn' . ($is_complete ? ' is-complete' : '') . '"' . ($btn_disabled ? ' disabled' : '') . '>';
echo '<span class="learni-lesson-complete-icon" aria-hidden="true"></span>';
echo '<span class="learni-lesson-complete-text">' . esc_html($btn_label) . '</span>';
echo '</button>';
echo '</form>';

if ($next_url !== '' && !$is_locked && (!$linear_order || $is_complete)) {
    echo '<a class="learni-lesson-next-btn" href="' . esc_url($next_url) . '" aria-label="' . esc_attr__('Next lesson', 'politeia-learning') . '">→</a>';
}
echo '</div>';
echo '</div>';

echo '<h1 class="learni-lesson-title">' . esc_html(get_the_title($lesson_id)) . '</h1>';
echo '<div class="learni-lesson-body">';
if ($video_html !== '') {
    echo '<div class="learni-lesson-video">' . $video_html . '</div>';
}
echo apply_filters('the_content', (string) ($post->post_content ?? ''));
echo '</div>';
echo '</section>';

echo '<button type="button" class="learni-outline-fab" aria-label="' . esc_attr__('Open lessons', 'politeia-learning') . '" aria-controls="learni-lesson-outline-overlay" aria-expanded="false">';
echo '<svg class="learni-outline-fab-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M19 2H8c-1.1 0-2 .9-2 2v15c0 .55.45 1 1 1h12v-2H8V4h11v16h2V4c0-1.1-.9-2-2-2zM3 6c-.55 0-1 .45-1 1v14c0 .55.45 1 1 1h14v-2H4V7c0-.55-.45-1-1-1z"></path></svg>';
echo '</button>';

echo '<div id="learni-lesson-outline-overlay" class="learni-outline-overlay" aria-hidden="true">';
echo '<button type="button" class="learni-outline-overlay-backdrop" aria-label="' . esc_attr__('Close lessons', 'politeia-learning') . '"></button>';
echo '<div class="learni-outline-overlay-panel" role="dialog" tabindex="-1" aria-modal="true" aria-label="' . esc_attr__('Lessons', 'politeia-learning') . '">';
echo '<div class="learni-outline-overlay-handle" aria-hidden="true"></div>';
echo '<nav class="learni-lesson-outline learni-lesson-outline-overlay" aria-label="' . esc_attr__('Lessons', 'politeia-learning') . '">';
echo $outline_html;
echo '</nav>';
echo '</div>';
echo '</div>';

echo '</div>';
echo '</main>';

get_footer();
