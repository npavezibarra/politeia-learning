<?php
/**
 * Frontend View Lesson logic for Learni.
 */

if (!defined('ABSPATH')) {
    exit;
}

final class PL_Learni_Frontend_ViewLesson
{
    public static function render(): string
    {
        $lesson_id = (int) get_the_ID();
        if ($lesson_id <= 0) {
            return '';
        }

        $user_id = (int) get_current_user_id();
        $course_id = 0;
        global $wpdb;
        if ($wpdb) {
            $course_id = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT course_post_id FROM {$wpdb->prefix}learni_course_items WHERE item_type = %s AND item_ref_id = %d LIMIT 1",
                    'lesson',
                    $lesson_id
                )
            );
        }

        if ($course_id <= 0) {
            return '';
        }

        $is_logged_in = $user_id > 0;
        $has_access = class_exists('\\Learni\\Access\\Access') && \Learni\Access\Access::user_can_access_course($user_id, $course_id);

        $summary = class_exists('\\Learni\\Database\\Progress') ? \Learni\Database\Progress::course_summary($user_id, $course_id) : ['total' => 0, 'completed' => 0, 'percent' => 0];
        $percent = (int) ($summary['percent'] ?? 0);

        $price = (float) get_post_meta($course_id, 'learni_price', true);
        $product_id = (int) get_post_meta($course_id, 'learni_wc_product_id', true);
        $is_free = $price <= 0 && $product_id <= 0;
        $can_view = $has_access || $is_free;

        if (!$can_view) {
            return '<div class="learni-lesson-restricted">' . esc_html__('Compra el curso para acceder a esta lección.', 'politeia-learning') . '</div>';
        }

        $items = class_exists('\\Learni\\Courses\\Outline') ? \Learni\Courses\Outline::get_items($course_id) : [];
        $lesson_ids = [];
        foreach ($items as $it) {
            if (isset($it['type']) && (string) $it['type'] === 'lesson' && isset($it['refId'])) {
                $lesson_ids[] = (int) $it['refId'];
            }
        }
        $completed = array_flip(class_exists('\\Learni\\Database\\Progress') ? \Learni\Database\Progress::completed_lesson_ids($user_id, $course_id) : []);
        $is_completed = isset($completed[$lesson_id]);

        $linear_order = $course_id > 0 ? PL_Learni_Frontend_Templates::course_linear_order_enabled($course_id) : true;
        $max_unlocked = PL_Learni_Frontend_Templates::max_unlocked_lesson_index($lesson_ids, $completed, $linear_order);
        $lesson_index = PL_Learni_Frontend_Templates::lesson_index_map($lesson_ids);
        $pos = isset($lesson_index[$lesson_id]) ? (int) $lesson_index[$lesson_id] : -1;

        if ($linear_order && $pos >= 0 && $max_unlocked >= 0 && $pos > $max_unlocked && !$has_access) {
            return '<div class="learni-lesson-locked">' . esc_html__('This lesson is locked.', 'politeia-learning') . '</div>';
        }

        $video_url = (string) get_post_meta($lesson_id, 'learni_video_url', true);
        $video_html = '';
        $video_provider = '';
        $youtube_id = '';
        if ($video_url !== '') {
            if (str_contains($video_url, 'youtube.com') || str_contains($video_url, 'youtu.be')) {
                $video_provider = 'youtube';
                $youtube_id = self::parse_youtube_id($video_url);
                if ($youtube_id !== '') {
                    $embed_url = self::youtube_embed_url($youtube_id);
                    $video_html = '<iframe src="' . esc_url($embed_url) . '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>';
                }
            } else {
                $video_html = wp_oembed_get($video_url);
            }
        }

        // Sourcing content from linked "Escrito" post if available.
        $src_meta_key = class_exists('\\Learni\\PostTypes\\Lesson') ? \Learni\PostTypes\Lesson::META_SOURCE_POST_ID : 'learni_source_post_id';
        $source_post_id = (int) get_post_meta($lesson_id, $src_meta_key, true);
        $content_to_process = (string) get_post_field('post_content', $lesson_id);
        if ($source_post_id > 0) {
            $content_to_process = (string) get_post_field('post_content', $source_post_id);
        }
        $processed = apply_filters('the_content', $content_to_process);

        $course_url = (string) get_permalink($course_id);
        $course_title = (string) get_the_title($course_id);
        $total = count($lesson_ids);
        $step = $pos >= 0 ? $pos + 1 : 0;

        $html = '<main class="learni-learner learni-lesson-layout alignwide" data-learni-course-id="' . esc_attr((string) $course_id) . '">';
        $html .= '<div class="learni-lesson-shell">';
        
        // Sidebar
        $html .= '<aside class="learni-lesson-aside" aria-label="' . esc_attr__('Course navigation', 'politeia-learning') . '">';
        if ($course_url !== '') {
            $html .= '<a class="learni-lesson-back" href="' . esc_url($course_url) . '"><span class="learni-lesson-back-label">' . esc_html__('VOLVER A CURSO', 'politeia-learning') . '</span></a>';
        }
        if ($course_title !== '') {
            $html .= '<h2 class="learni-lesson-course-title">' . esc_html($course_title) . '</h2>';
        }
        if ($course_id > 0) {
            $html .= '<div class="learni-lesson-course-progress" aria-label="' . esc_attr__('Course progress', 'politeia-learning') . '">';
            $html .= '<div class="learni-lesson-course-progress-label">' . esc_html(sprintf(__('%d%% COMPLETO', 'politeia-learning'), $percent)) . '</div>';
            $html .= '<div class="learni-lesson-course-progress-bar" role="progressbar" aria-valuenow="' . esc_attr((string) $percent) . '" aria-valuemin="0" aria-valuemax="100">';
            $html .= '<span class="learni-lesson-course-progress-fill" style="width:' . esc_attr((string) $percent) . '%"></span>';
            $html .= '</div>';
            $html .= '</div>';
        }

        $html .= '<nav id="learni-lesson-outline" class="learni-lesson-outline" aria-label="' . esc_attr__('Lessons', 'politeia-learning') . '">';
        $html .= '<ul class="learni-lesson-outline-list">';
        foreach ($items as $it) {
            $type = (string) ($it['type'] ?? '');
            if ($type === 'header') {
                $html .= '<li class="learni-lesson-outline-header">' . esc_html((string) ($it['label'] ?? '')) . '</li>';
                continue;
            }
            if ($type !== 'lesson') continue;
            
            $rid = (int) ($it['refId'] ?? 0);
            if ($rid <= 0) continue;
            
            $it_is_done = isset($completed[$rid]);
            $it_pos = isset($lesson_index[$rid]) ? (int) $lesson_index[$rid] : -1;
            $it_locked = ($linear_order && $it_pos >= 0 && $max_unlocked >= 0 && $it_pos > $max_unlocked) || !$can_view;
            $it_active = ($rid === $lesson_id);
            
            $classes = 'learni-lesson-outline-item';
            if ($it_active) $classes .= ' is-active';
            if ($it_is_done) $classes .= ' is-complete';
            if ($it_locked) $classes .= ' is-locked';
            
            $it_url = get_permalink($rid);
            
            $html .= '<li class="' . esc_attr($classes) . '">';
            if ($it_url && !$it_locked) {
                $html .= '<a href="' . esc_url($it_url) . '">';
            } else {
                $html .= '<span>';
            }
            $html .= '<span class="learni-lesson-outline-label">' . esc_html(get_the_title($rid)) . '</span>';
            $html .= '<span class="learni-lesson-outline-status" aria-hidden="true">' . ($it_is_done ? '✓' : '') . '</span>';
            if ($it_url && !$it_locked) {
                $html .= '</a>';
            } else {
                $html .= '</span>';
            }
            $html .= '</li>';
        }
        $html .= '</ul>';
        $html .= '</nav>';
        $html .= '</aside>';

        // Main Content
        $html .= '<section class="learni-lesson-main" aria-label="' . esc_attr__('Lesson content', 'politeia-learning') . '">';
        $html .= '<div class="learni-lesson-top">';
        $html .= '<div class="learni-lesson-step">' . esc_html(sprintf(__('LECCIÓN %1$d DE %2$d', 'politeia-learning'), $step, $total)) . '</div>';
        $html .= '<div class="learni-lesson-top-actions">';
        
        $btn_label = __('FINALIZADO', 'politeia-learning');
        $btn_disabled = ($user_id <= 0) || $is_completed || $is_locked;
        $requires_video_gate = (!$btn_disabled && $video_provider === 'youtube' && $video_html !== '');
        
        $html .= '<form class="learni-lesson-complete-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        $html .= '<input type="hidden" name="action" value="pl_learni_mark_lesson_complete">';
        $html .= '<input type="hidden" name="lesson_id" value="' . esc_attr((string) $lesson_id) . '">';
        $html .= '<input type="hidden" name="redirect_to" value="' . esc_attr((string) get_permalink($lesson_id)) . '">';
        $html .= wp_nonce_field('pl_learni_complete_lesson_' . $lesson_id, '_wpnonce', true, false);
        
        if ($requires_video_gate) {
            $html .= '<span class="learni-lesson-complete-wrap" data-learni-tooltip="' . esc_attr__('Finaliza el video para declarar la lección como finalizada.', 'politeia-learning') . '">';
        }
        
        $html .= '<button type="submit" class="learni-lesson-complete-btn' . ($is_completed ? ' is-complete' : '') . '"' . ($requires_video_gate ? ' data-learni-video-gate="1"' : '') . ($btn_disabled ? ' disabled' : '') . '>';
        $html .= '<span class="learni-lesson-complete-icon" aria-hidden="true"></span>';
        $html .= '<span class="learni-lesson-complete-text">' . esc_html($btn_label) . '</span>';
        $html .= '</button>';
        
        if ($requires_video_gate) {
            $html .= '<span class="learni-tooltip" role="tooltip">' . esc_html__('Finaliza el video para declarar la lección como finalizada.', 'politeia-learning') . '</span>';
            $html .= '</span>';
        }
        $html .= '</form>';

        // Next Link
        $next_idx = $pos + 1;
        if (isset($lesson_ids[$next_idx])) {
            $next_id = $lesson_ids[$next_idx];
            $next_locked = ($linear_order && $next_idx > $max_unlocked) || !$can_view;
            if (!$next_locked && (!$linear_order || $is_completed)) {
                $html .= '<a class="learni-lesson-next-btn" href="' . esc_url(get_permalink($next_id)) . '" aria-label="' . esc_attr__('Next lesson', 'politeia-learning') . '">→</a>';
            }
        }
        $html .= '</div>'; // top actions
        $html .= '</div>'; // lesson top

        $html .= '<h1 class="learni-lesson-title">' . esc_html(get_the_title($lesson_id)) . '</h1>';
        $html .= '<div class="learni-lesson-body">';
        if ($video_html !== '') {
            $html .= '<div id="learni-lesson-video" class="learni-lesson-video"' . ($video_provider !== '' ? ' data-learni-video-provider="' . esc_attr($video_provider) . '"' : '') . ($video_provider === 'youtube' && $youtube_id !== '' ? ' data-learni-youtube-id="' . esc_attr($youtube_id) . '"' : '') . '>' . $video_html . '</div>';
        }
        $html .= $processed;
        $html .= '</div>';
        $html .= '</section>';

        // FAB + Overlay
        $html .= '<button type="button" class="learni-outline-fab" aria-label="' . esc_attr__('Open lessons', 'politeia-learning') . '" aria-controls="learni-lesson-outline-overlay" aria-expanded="false">';
        $html .= '<svg class="learni-outline-fab-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M19 2H8c-1.1 0-2 .9-2 2v15c0 .55.45 1 1 1h12v-2H8V4h11v16h2V4c0-1.1-.9-2-2-2zM3 6c-.55 0-1 .45-1 1v14c0 .55.45 1 1 1h14v-2H4V7c0-.55-.45-1-1-1z"></path></svg>';
        $html .= '</button>';

        $html .= '<div id="learni-lesson-outline-overlay" class="learni-outline-overlay" aria-hidden="true">';
        $html .= '<button type="button" class="learni-outline-overlay-backdrop" aria-label="' . esc_attr__('Close lessons', 'politeia-learning') . '"></button>';
        $html .= '<div class="learni-outline-overlay-panel" role="dialog" tabindex="-1" aria-modal="true" aria-label="' . esc_attr__('Lessons', 'politeia-learning') . '">';
        $html .= '<div class="learni-outline-overlay-handle" aria-hidden="true"></div>';
        $html .= '<nav class="learni-lesson-outline learni-lesson-outline-overlay" aria-label="' . esc_attr__('Lessons', 'politeia-learning') . '">';
        $html .= '<ul class="learni-lesson-outline-list">';
        // Repeat outline for mobile (same structure)
        foreach ($items as $it) {
            $type = (string) ($it['type'] ?? '');
            if ($type === 'header') {
                $html .= '<li class="learni-lesson-outline-header">' . esc_html((string) ($it['label'] ?? '')) . '</li>';
                continue;
            }
            if ($type !== 'lesson') continue;
            $rid = (int) ($it['refId'] ?? 0);
            if ($rid <= 0) continue;
            $it_is_done = isset($completed[$rid]);
            $it_pos = isset($lesson_index[$rid]) ? (int) $lesson_index[$rid] : -1;
            $it_locked = ($linear_order && $it_pos >= 0 && $max_unlocked >= 0 && $it_pos > $max_unlocked) || !$can_view;
            $it_active = ($rid === $lesson_id);
            $classes = 'learni-lesson-outline-item';
            if ($it_active) $classes .= ' is-active';
            if ($it_is_done) $classes .= ' is-complete';
            if ($it_locked) $classes .= ' is-locked';
            $it_url = get_permalink($rid);
            $html .= '<li class="' . esc_attr($classes) . '">';
            if ($it_url && !$it_locked) $html .= '<a href="' . esc_url($it_url) . '">'; else $html .= '<span>';
            $html .= '<span class="learni-lesson-outline-label">' . esc_html(get_the_title($rid)) . '</span>';
            $html .= '<span class="learni-lesson-outline-status" aria-hidden="true">' . ($it_is_done ? '✓' : '') . '</span>';
            if ($it_url && !$it_locked) $html .= '</a>'; else $html .= '</span>';
            $html .= '</li>';
        }
        $html .= '</ul>';
        $html .= '</nav>';
        $html .= '</div>';
        $html .= '</div>';

        $html .= '</div>'; // learni-lesson-shell
        $html .= '</main>';

        return $html;
    }

    private static function outline_fab_icon_svg(): string
    {
        return '<svg class="learni-outline-fab-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M19 2H8c-1.1 0-2 .9-2 2v15c0 .55.45 1 1 1h12v-2H8V4h11v16h2V4c0-1.1-.9-2-2-2zM3 6c-.55 0-1 .45-1 1v14c0 .55.45 1 1 1h14v-2H4V7c0-.55-.45-1-1-1z"></path></svg>';
    }

    private static function parse_youtube_id(string $url): string
    {
        $pattern = '/(?:youtube\\.com\\/(?:[^\\/]+\\/.+\\/|(?:v|e(?:mbed)?)\\/|.*[?&]v=)|youtu\\.be\\/)([^"&?\\/ ]{11})/i';
        if (preg_match($pattern, $url, $matches)) {
            return (string) $matches[1];
        }
        return '';
    }

    private static function youtube_embed_url(string $video_id): string
    {
        return 'https://www.youtube.com/embed/' . $video_id . '?rel=0&showinfo=0&modestbranding=1';
    }
}
