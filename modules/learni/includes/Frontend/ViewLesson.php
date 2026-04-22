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
        $user_id = (int) get_current_user_id();
        if ($lesson_id <= 0) {
            return '';
        }

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

        $summary = class_exists('\\Learni\\Database\\Progress') ? \Learni\Database\Progress::course_summary($user_id, $course_id) : ['percent' => 0];
        $percent = (int) ($summary['percent'] ?? 0);

        $items = class_exists('\\Learni\\Courses\\Outline') ? \Learni\Courses\Outline::get($course_id) : [];
        $lesson_ids = [];
        foreach ($items as $it) {
            if (isset($it['item_type']) && (string) $it['item_type'] === 'lesson' && isset($it['item_ref_id'])) {
                $lesson_ids[] = (int) $it['item_ref_id'];
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

        $processed = apply_filters('the_content', get_the_content(null, false, $lesson_id));

        $html = '<div id="learni-lesson" class="learni-lesson">';

        $html .= '<section class="learni-lesson-main">';
        $html .= '<header class="learni-lesson-header">';
        $html .= '<div class="learni-lesson-header-left">';
        $html .= '<a class="learni-lesson-back" href="' . esc_url((string) get_permalink($course_id)) . '"><span class="material-symbols-outlined">arrow_back</span> ' . esc_html__('Course', 'politeia-learning') . '</a>';
        $html .= '</div>';
        $html .= '<div class="learni-lesson-header-right">';
        if ($is_completed) {
            $html .= '<div class="learni-lesson-status-badge is-completed"><span class="material-symbols-outlined">check_circle</span> ' . esc_html__('Completed', 'politeia-learning') . '</div>';
        } else {
            $html .= '<form action="' . esc_url(admin_url('admin-post.php')) . '" method="POST">';
            $html .= '<input type="hidden" name="action" value="pl_learni_mark_lesson_complete">';
            $html .= '<input type="hidden" name="lesson_id" value="' . esc_attr((string) $lesson_id) . '">';
            $html .= '<input type="hidden" name="redirect_to" value="' . esc_attr((string) get_permalink($lesson_id)) . '">';
            $html .= wp_nonce_field('pl_learni_complete_lesson_' . $lesson_id, '_wpnonce', true, false);
            $html .= '<button type="submit" class="learni-btn primary">' . esc_html__('COMPLETE LESSON', 'politeia-learning') . '</button>';
            $html .= '</form>';
        }
        $html .= '</div>';
        $html .= '</header>';

        $html .= '<h1 class="learni-lesson-title">' . esc_html(get_the_title($lesson_id)) . '</h1>';
        $html .= '<div class="learni-lesson-body">';
        if ($video_html !== '') {
            $html .= '<div id="learni-lesson-video" class="learni-lesson-video"' . ($video_provider !== '' ? ' data-learni-video-provider="' . esc_attr($video_provider) . '"' : '') . ($video_provider === 'youtube' && $youtube_id !== '' ? ' data-learni-youtube-id="' . esc_attr($youtube_id) . '"' : '') . '>' . $video_html . '</div>';
        }
        $html .= $processed;
        $html .= '</div>';
        $html .= '</section>';

        $html .= '<button type="button" class="learni-outline-fab" aria-label="' . esc_attr__('Open lessons', 'politeia-learning') . '" aria-controls="learni-lesson-outline-overlay" aria-expanded="false">';
        $html .= self::outline_fab_icon_svg();
        $html .= '</button>';

        $html .= '<div id="learni-lesson-outline-overlay" class="learni-outline-overlay" aria-hidden="true">';
        $html .= '<button type="button" class="learni-outline-overlay-backdrop" aria-label="' . esc_attr__('Close lessons', 'politeia-learning') . '"></button>';
        $html .= '<div class="learni-outline-overlay-panel" role="dialog" tabindex="-1" aria-modal="true" aria-label="' . esc_attr__('Lessons', 'politeia-learning') . '">';
        $html .= '<div class="learni-outline-overlay-handle" aria-hidden="true"></div>';
        $html .= '<nav class="learni-lesson-outline learni-lesson-outline-overlay" aria-label="' . esc_attr__('Lessons', 'politeia-learning') . '">';
        if (empty($items)) {
            $html .= '<div class="learni-lesson-outline-empty">' . esc_html__('No lessons yet.', 'politeia-learning') . '</div>';
        } else {
            $html .= '<div class="learni-lesson-outline-head"><h3>' . esc_html__('Lessons', 'politeia-learning') . '</h3></div>';
            $html .= '<ul>';
            foreach ($items as $it) {
                $type = (string) ($it['item_type'] ?? '');
                $ref_id = (int) ($it['item_ref_id'] ?? 0);
                if ($type !== 'lesson' || $ref_id <= 0) {
                    continue;
                }

                $is_cur_completed = isset($completed[$ref_id]);
                $pos_cur = isset($lesson_index[$ref_id]) ? (int) $lesson_index[$ref_id] : -1;
                $is_cur_locked = ($linear_order && $pos_cur > $max_unlocked) && !$has_access;
                $url = get_permalink($ref_id);

                $html .= '<li class="learni-lesson-outline-item' . ($is_cur_completed ? ' is-completed' : '') . ($is_cur_locked ? ' is-locked' : '') . ($ref_id === $lesson_id ? ' is-active' : '') . '">';
                if ($is_cur_locked) {
                    $html .= '<div class="learni-lesson-outline-link">';
                } else {
                    $html .= '<a class="learni-lesson-outline-link" href="' . esc_url((string) $url) . '">';
                }

                $html .= '<span class="material-symbols-outlined">' . ($is_cur_completed ? 'check_circle' : ($is_cur_locked ? 'lock' : 'play_circle')) . '</span>';
                $html .= '<span>' . esc_html(get_the_title($ref_id)) . '</span>';

                if ($is_cur_locked) {
                    $html .= '</div>';
                } else {
                    $html .= '</a>';
                }
                $html .= '</li>';
            }
            $html .= '</ul>';
        }
        $html .= '</nav>';
        $html .= '</div>';
        $html .= '</div>';

        $html .= '</div>'; // #learni-lesson

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
