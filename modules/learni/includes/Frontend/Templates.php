<?php
/**
 * Frontend template wiring for internal Learni module.
 *
 * Loads Learni templates for learni_course + learni_lesson without enabling
 * the full Learni frontend routing layer yet.
 */

if (!defined('ABSPATH')) {
    exit;
}

final class PL_Learni_Frontend_Templates
{
    private static bool $did_register_content_filter = false;

    public static function init(): void
    {
        add_filter('template_include', [__CLASS__, 'template_include'], 50);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets'], 20);
        add_filter('render_block', [__CLASS__, 'filter_theme_blocks'], 10, 2);
        add_filter('body_class', [__CLASS__, 'filter_body_class'], 20);
        add_action('admin_post_pl_learni_enroll_course', [__CLASS__, 'handle_enroll_course']);
        add_action('admin_post_nopriv_pl_learni_enroll_course', [__CLASS__, 'handle_enroll_course_nopriv']);
        add_action('admin_post_pl_learni_mark_lesson_complete', [__CLASS__, 'handle_mark_lesson_complete']);
        add_action('admin_post_nopriv_pl_learni_mark_lesson_complete', [__CLASS__, 'handle_mark_lesson_complete_nopriv']);

        // For block themes, we want the theme's header/footer to stay identical to the rest
        // of the site, so we render the Learni screens inside the normal singular template.
        if (!self::$did_register_content_filter) {
            add_filter('the_content', [__CLASS__, 'render_block_theme_content'], 99);
            self::$did_register_content_filter = true;
        }
    }

    private static function is_block_theme(): bool
    {
        return function_exists('wp_is_block_theme') && wp_is_block_theme();
    }

    /**
     * Remove theme blocks that don't make sense on Learni screens.
     *
     * Example: a "Written by {author} on {date}" row can become "Escrito por  en" if author/date blocks
     * are hidden or empty. Remove the whole group in that case.
     *
     * @param string $block_content
     * @param array  $block
     */
    public static function filter_theme_blocks(string $block_content, array $block): string
    {
        if (!self::is_block_theme()) {
            return $block_content;
        }

        if (!is_singular('learni_course') && !is_singular('learni_lesson')) {
            return $block_content;
        }

        $name = (string) ($block['blockName'] ?? '');
        // Remove theme-rendered featured image on Learni screens since we render it inside the hero card.
        if ($name === 'core/post-featured-image') {
            return '';
        }

        // Remove the site footer on single lesson screens (Learni layout is full-height).
        if (is_singular('learni_lesson') && $name === 'core/template-part') {
            $attrs = (array) ($block['attrs'] ?? []);
            $slug = (string) ($attrs['slug'] ?? '');
            if ($slug === 'footer') {
                return '';
            }
        }

        if ($name === 'core/group') {
            // Spanish pattern from the active theme: "Escrito por {author} en {date}".
            if (preg_match('/<p>\\s*Escrito\\s+por\\s*<\\/p>/i', $block_content) && preg_match('/<p>\\s*en\\s*<\\/p>/i', $block_content)) {
                return '';
            }
        }

        return $block_content;
    }

    /**
     * Add body classes for Learni screens so we can scope theme overrides safely.
     *
     * @param array $classes
     * @return array
     */
    public static function filter_body_class(array $classes): array
    {
        if (is_singular('learni_lesson')) {
            $classes[] = 'pl-learni-lesson';
        } elseif (is_singular('learni_course')) {
            $classes[] = 'pl-learni-course';
        }
        return $classes;
    }

    public static function enqueue_assets(): void
    {
        if (!is_singular('learni_course') && !is_singular('learni_lesson')) {
            return;
        }

        // Keep this self-contained (no dependency on separate Learni plugin).
        wp_enqueue_style(
            'pl-learni-material-symbols',
            'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&icon_names=auto_stories&display=swap',
            [],
            null
        );

        wp_enqueue_style(
            'pl-learni-learner',
            PL_LEARNI_URL . 'assets/learner.css',
            ['pl-learni-material-symbols'],
            defined('LEARNI_VERSION') ? (string) LEARNI_VERSION : '0.0.0'
        );

        if (is_singular('learni_course') || is_singular('learni_lesson')) {
            wp_enqueue_script(
                'pl-learni-quiz',
                PL_LEARNI_URL . 'assets/learner-quiz.js',
                [],
                defined('LEARNI_VERSION') ? (string) LEARNI_VERSION : '0.0.0',
                true
            );
            wp_add_inline_script(
                'pl-learni-quiz',
                'window.Learni = Object.assign({}, window.Learni || {}, ' . wp_json_encode([
                    'restUrl' => esc_url_raw(rest_url()),
                    'restNonce' => wp_create_nonce('wp_rest'),
                ]) . ');',
                'before'
            );
        }

        if (is_singular('learni_lesson')) {
            wp_enqueue_script(
                'pl-learni-lesson-outline',
                PL_LEARNI_URL . 'assets/learner-lesson-outline.js',
                [],
                defined('LEARNI_VERSION') ? (string) LEARNI_VERSION : '0.0.0',
                true
            );
        }

        if (self::is_block_theme() && is_singular('learni_course')) {
            wp_enqueue_script(
                'pl-learni-tabs',
                PL_LEARNI_URL . 'assets/learner-tabs.js',
                [],
                defined('LEARNI_VERSION') ? (string) LEARNI_VERSION : '0.0.0',
                true
            );
        }
    }

    public static function render_block_theme_content(string $content): string
    {
        if (!self::is_block_theme()) {
            return $content;
        }

        if (!is_singular('learni_course') && !is_singular('learni_lesson')) {
            return $content;
        }

        // Avoid recursion when rendering post content.
        remove_filter('the_content', [__CLASS__, __FUNCTION__], 99);

        $out = $content;
        try {
            if (is_singular('learni_course')) {
                $out = self::render_course_block_theme();
            } elseif (is_singular('learni_lesson')) {
                $out = self::render_lesson_block_theme();
            }
        } catch (\Throwable $e) {
            $out = $content;
        }

        add_filter('the_content', [__CLASS__, __FUNCTION__], 99);

        return $out;
    }

    private static function render_course_block_theme(): string
    {
        $course_id = (int) get_the_ID();
        if ($course_id <= 0) {
            return '<div class="learni-learner"><p>' . esc_html__('Course not found.', 'politeia-learning') . '</p></div>';
        }

        $user_id = (int) get_current_user_id();

        $title = (string) get_the_title($course_id);
        $excerpt = (string) get_post_field('post_excerpt', $course_id);
        if ($excerpt === '') {
            $excerpt = wp_trim_words(wp_strip_all_tags((string) get_post_field('post_content', $course_id)), 40, '…');
        }

        if ($user_id <= 0) {
            return '<div class="learni-learner"><p>' . esc_html__('Please log in to view this course.', 'politeia-learning') . '</p></div>';
        }

        if (class_exists('\\Learni\\Access\\Access') && !\Learni\Access\Access::user_can_access_course($user_id, $course_id)) {
            return '<div class="learni-learner"><p>' . esc_html__('You do not have access to this course.', 'politeia-learning') . '</p></div>';
        }

        $items = class_exists('\\Learni\\Courses\\Outline') ? \Learni\Courses\Outline::get_items($course_id) : [];
        $lesson_ids = class_exists('\\Learni\\Courses\\Outline') ? \Learni\Courses\Outline::lesson_ids($course_id) : [];
        $summary = class_exists('\\Learni\\Database\\Progress') ? \Learni\Database\Progress::course_summary($user_id, $course_id) : ['total' => 0, 'completed' => 0, 'percent' => 0];
        $completed = class_exists('\\Learni\\Database\\Progress') ? array_flip(\Learni\Database\Progress::completed_lesson_ids($user_id, $course_id)) : [];
        $linear_order = self::course_linear_order_enabled($course_id);
        $lesson_index = self::lesson_index_map($lesson_ids);
        $max_unlocked = self::max_unlocked_lesson_index($lesson_ids, $completed, $linear_order);

        $percent = (int) ($summary['percent'] ?? 0);
        $total = (int) ($summary['total'] ?? 0);
        $done = (int) ($summary['completed'] ?? 0);
        $progress_text = sprintf(__('COMPLETADO %1$d DE %2$d LECCIONES', 'politeia-learning'), $done, $total);
        $price = (float) get_post_meta($course_id, 'learni_price', true);
        $price_label = $price > 0 ? '$' . number_format((float) $price, 0, '.', ',') : __('FREE', 'politeia-learning');
        $is_free = $price <= 0;
        $thumb_url = (string) get_the_post_thumbnail_url($course_id, 'large');
        $certificate_attachment_id = class_exists('\\Learni\\PostTypes\\Course') ? (int) get_post_meta($course_id, \Learni\PostTypes\Course::META_CERTIFICATE_ATTACHMENT_ID, true) : 0;
        $certificate_url = $certificate_attachment_id > 0 ? (string) wp_get_attachment_url($certificate_attachment_id) : '';

        $author_id = (int) get_post_field('post_author', $course_id);
        $author = $author_id > 0 ? get_userdata($author_id) : null;
        $author_name = $author ? (string) ($author->display_name ?? '') : '';
        if ($author_name === '') {
            $author_name = (string) $author_id;
        }

        // Match Learni structure: the main wrapper is both `#learni-course` and `.learni-learner`
        // (needed for full-bleed hero math + consistent alignment with the site header).
        $html = '<div id="learni-course" class="learni-learner alignwide">';

        // Hero (banner + aside card).
        $html .= '<section class="learni-course-hero"><div class="learni-course-hero-content"><div class="learni-course-hero-inner">';
        $html .= '<div class="learni-course-hero-left">';
        $html .= '<h1 id="learni-course-title">' . esc_html($title) . '</h1>';
        if ($excerpt !== '') {
            $html .= '<p class="learni-course-description">' . esc_html($excerpt) . '</p>';
        }
        $html .= '<p class="learni-course-author">' . esc_html(sprintf(__('Written by %s', 'politeia-learning'), $author_name)) . '</p>';

        $html .= '<div class="learni-progress">';
        $html .= '<div class="learni-progress-head">';
        $html .= '<div class="learni-progress-text">' . esc_html($progress_text) . '</div>';
        $html .= '<div class="learni-progress-percent">' . esc_html((string) $percent) . '%</div>';
        $html .= '</div>';
        $html .= '<div class="learni-progress-bar" role="progressbar" aria-valuenow="' . esc_attr((string) $percent) . '" aria-valuemin="0" aria-valuemax="100">';
        $html .= '<span class="learni-progress-bar-fill" style="width:' . esc_attr((string) $percent) . '%"><span class="learni-progress-shimmer" aria-hidden="true"></span></span>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>'; // left

        $html .= '<aside class="learni-course-hero-card" aria-label="' . esc_attr__('Course details', 'politeia-learning') . '">';
        if ($thumb_url !== '') {
            $html .= '<div class="learni-course-card-thumbnail-wrap"><img class="learni-course-card-thumbnail" src="' . esc_url($thumb_url) . '" alt=""></div>';
        }
        $html .= '<div class="learni-course-hero-card-body">';
        $html .= '<div class="learni-course-price-row">';
        $html .= '<div class="learni-course-price">' . esc_html($price_label) . '</div>';
        if ($certificate_url !== '' && $percent >= 100) {
            $html .= '<a class="learni-course-cert-trigger" href="' . esc_url($certificate_url) . '" target="_blank" rel="noopener">';
            $html .= '<span class="learni-course-cert-icon" aria-hidden="true"></span>';
            $html .= '<span class="learni-course-cert-text">' . esc_html__('CERTIFICADO', 'politeia-learning') . '</span>';
            $html .= '</a>';
        }
        $html .= '</div>';
        $html .= '<div class="learni-course-card-actions">';

        // Binomial quiz aside controls (ported subset).
        $binomial = self::binomial_course_state($course_id, $user_id, $percent);
        $binomial_quiz_id = (int) ($binomial['quizId'] ?? 0);
        if ($binomial_quiz_id > 0) {
            if (is_array($binomial['initial'] ?? null)) {
                $ip = (int) ($binomial['initial']['percent'] ?? 0);
                $html .= '<div class="learni-eval">';
                $html .= '<div class="learni-eval-head"><span class="learni-eval-title">' . esc_html__('EVALUACIÓN INICIAL', 'politeia-learning') . '</span><span class="learni-eval-percent">' . esc_html((string) $ip) . '%</span></div>';
                $html .= '<div class="learni-eval-track"><div class="learni-eval-bar" style="width:' . esc_attr((string) $ip) . '%"></div></div>';
                $html .= '</div>';
            }
            if (is_array($binomial['final'] ?? null)) {
                $fp = (int) ($binomial['final']['percent'] ?? 0);
                $html .= '<div class="learni-eval">';
                $html .= '<div class="learni-eval-head"><span class="learni-eval-title">' . esc_html__('EVALUACIÓN FINAL', 'politeia-learning') . '</span><span class="learni-eval-percent">' . esc_html((string) $fp) . '%</span></div>';
                $html .= '<div class="learni-eval-track"><div class="learni-eval-bar" style="width:' . esc_attr((string) $fp) . '%"></div></div>';
                $html .= '</div>';
            }

            if (!empty($binomial['needsInitial'])) {
                $html .= '<button id="learni-course-first-quiz" class="learni-btn learni-btn-quiz" type="button" data-course-id="' . esc_attr((string) $course_id) . '" data-phase="initial">' . esc_html__('TAKE FIRST QUIZ', 'politeia-learning') . '</button>';
            }
            if (!empty($binomial['needsFinal']) && $percent >= 100 && empty($binomial['final'])) {
                $disabled = !empty($binomial['canTakeFinal']) ? '' : ' disabled';
                $html .= '<button id="learni-course-final-quiz" class="learni-btn learni-btn-quiz" type="button" data-course-id="' . esc_attr((string) $course_id) . '" data-phase="final"' . $disabled . '>' . esc_html__('TAKE FINAL QUIZ', 'politeia-learning') . '</button>';
            }
        }

        $is_enrolled = class_exists('\\Learni\\Database\\Enrollments') ? \Learni\Database\Enrollments::user_has_active($user_id, $course_id) : false;
        $course_permalink = (string) get_permalink($course_id);
        $first_lesson_url = '';
        if (!empty($lesson_ids)) {
            $first_slug = (string) get_post_field('post_name', (int) $lesson_ids[0]);
            $first_lesson_url = $first_slug !== '' ? home_url('/?learni_lesson=' . rawurlencode($first_slug)) : '';
        }

        $continue_lesson_url = $first_lesson_url;
        if (!empty($lesson_ids)) {
            if ($linear_order) {
                $continue_id = ($max_unlocked >= 0 && isset($lesson_ids[$max_unlocked])) ? (int) $lesson_ids[$max_unlocked] : (int) $lesson_ids[0];
                $continue_slug = (string) get_post_field('post_name', $continue_id);
                $continue_lesson_url = $continue_slug !== '' ? home_url('/?learni_lesson=' . rawurlencode($continue_slug)) : $first_lesson_url;
            } else {
                $continue_id = 0;
                foreach ($lesson_ids as $lid) {
                    $lid = (int) $lid;
                    if ($lid > 0 && !isset($completed[$lid])) {
                        $continue_id = $lid;
                        break;
                    }
                }
                if ($continue_id <= 0) {
                    $continue_id = (int) $lesson_ids[0];
                }
                $continue_slug = (string) get_post_field('post_name', $continue_id);
                $continue_lesson_url = $continue_slug !== '' ? home_url('/?learni_lesson=' . rawurlencode($continue_slug)) : $first_lesson_url;
            }
        }

        if (!empty($binomial['canRestart'])) {
            $html .= '<button id="learni-course-restart" class="learni-btn learni-course-primary-btn" type="button" data-course-id="' . esc_attr((string) $course_id) . '">' . esc_html__('REINICIAR CURSO', 'politeia-learning') . '</button>';
        } elseif ($is_free && !$is_enrolled) {
            $redirect_to = $first_lesson_url !== '' ? $first_lesson_url : $course_permalink;
            $html .= '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            $html .= '<input type="hidden" name="action" value="pl_learni_enroll_course">';
            $html .= '<input type="hidden" name="course_id" value="' . esc_attr((string) $course_id) . '">';
            $html .= '<input type="hidden" name="redirect_to" value="' . esc_attr($redirect_to) . '">';
            $html .= wp_nonce_field('pl_learni_enroll_course_' . $course_id, '_wpnonce', true, false);
            $html .= '<button type="submit" class="learni-btn learni-course-primary-btn">' . esc_html__('START COURSE', 'politeia-learning') . '</button>';
            $html .= '</form>';
        } elseif ($continue_lesson_url !== '') {
            $html .= '<a class="learni-btn learni-course-primary-btn" href="' . esc_url($continue_lesson_url) . '">' . esc_html__($is_enrolled ? 'CONTINUE' : 'START COURSE', 'politeia-learning') . '</a>';
        } else {
            $html .= '<button type="button" class="learni-btn learni-course-primary-btn" disabled>' . esc_html__('START COURSE', 'politeia-learning') . '</button>';
        }
        $html .= '</div>';
        $html .= '<div class="learni-course-includes">';
        $html .= '<div class="learni-course-includes-title">' . esc_html__('CURSO INCLUYE', 'politeia-learning') . '</div>';
        $html .= '<div class="learni-course-includes-row">';
        $html .= '<span class="learni-course-includes-icon"><span class="material-symbols-outlined learni-ms-icon" aria-hidden="true">auto_stories</span></span>';
        $html .= '<span class="learni-course-includes-text">' . esc_html(sprintf(_n('%d Lección', '%d Lecciones', $total, 'politeia-learning'), $total)) . '</span>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</aside>';

        $html .= '</div></div></section>';

        // Render course content using the normal WP content pipeline (minus our own wrapper filter).
        $raw = (string) get_post_field('post_content', $course_id);
        $processed = apply_filters('the_content', $raw);
        $html .= '<div class="learni-course-body">';
        $html .= '<div class="learni-tabs" role="tablist" aria-label="' . esc_attr__('Course tabs', 'politeia-learning') . '">';
        $html .= '<button type="button" class="learni-tab is-active" role="tab" aria-selected="true" data-learni-tab="content">' . esc_html__('CONTENT', 'politeia-learning') . '</button>';
        $html .= '<button type="button" class="learni-tab" role="tab" aria-selected="false" data-learni-tab="lessons">' . esc_html__('LESSONS', 'politeia-learning') . '</button>';
        $html .= '</div>';

        $html .= '<div class="learni-tabpanel is-active" role="tabpanel" data-learni-panel="content">';
        $html .= '<section class="learni-course-content">' . $processed . '</section>';
        $html .= '</div>';

        $html .= '<div class="learni-tabpanel" role="tabpanel" data-learni-panel="lessons">';
        $html .= '<section class="learni-outline">';

        if (empty($items)) {
            $html .= '<p>' . esc_html__('No lessons yet.', 'politeia-learning') . '</p>';
        } else {
            $html .= '<ul class="learni-outline-list">';
            foreach ($items as $item) {
                $type = (string) ($item['type'] ?? '');
                if ($type === 'header') {
                    $html .= '<li class="learni-outline-header">' . esc_html((string) ($item['label'] ?? '')) . '</li>';
                    continue;
                }
                if ($type !== 'lesson') {
                    continue;
                }

                $lesson_id = (int) ($item['refId'] ?? 0);
                if ($lesson_id <= 0) {
                    continue;
                }

                $lesson_slug = (string) get_post_field('post_name', $lesson_id);
                $url = $lesson_slug !== '' ? home_url('/?learni_lesson=' . rawurlencode($lesson_slug)) : '';
                $is_done = isset($completed[$lesson_id]);
                $label = (string) get_the_title($lesson_id);
                $pos = isset($lesson_index[$lesson_id]) ? (int) $lesson_index[$lesson_id] : -1;
                $is_locked = $linear_order && $pos >= 0 && $max_unlocked >= 0 && $pos > $max_unlocked;

                $html .= '<li class="learni-outline-lesson' . ($is_done ? ' is-complete' : '') . ($is_locked ? ' is-locked' : '') . '"' . ($is_locked ? ' title="' . esc_attr__('Completa las lecciones anteriores para desbloquear.', 'politeia-learning') . '"' : '') . '>';
                if ($url !== '' && !$is_locked) {
                    $html .= '<a href="' . esc_url($url) . '">';
                } else {
                    $html .= '<span>';
                }
                $html .= '<span class="learni-check" aria-hidden="true">' . ($is_done ? '✓' : '•') . '</span>';
                $html .= '<span class="learni-label">' . esc_html($label) . '</span>';
                $html .= ($url !== '' && !$is_locked) ? '</a>' : '</span>';
                $html .= '</li>';
            }
            $html .= '</ul>';
        }

        $html .= '</section>';
        $html .= '</div>'; // lessons panel

        $html .= '</div>'; // course body
        $html .= '</div>'; // #learni-course

        return $html;
    }

    private static function render_lesson_block_theme(): string
    {
        $lesson_id = (int) get_the_ID();
        if ($lesson_id <= 0) {
            return '<div class="learni-learner"><p>' . esc_html__('Lesson not found.', 'politeia-learning') . '</p></div>';
        }

        $user_id = (int) get_current_user_id();

        $course_id = 0;
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

        $items = ($course_id > 0 && class_exists('\\Learni\\Courses\\Outline')) ? \Learni\Courses\Outline::get_items($course_id) : [];
        $lesson_ids = ($course_id > 0 && class_exists('\\Learni\\Courses\\Outline')) ? \Learni\Courses\Outline::lesson_ids($course_id) : [];
        $index = array_search($lesson_id, $lesson_ids, true);
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

        $summary = ($user_id > 0 && $course_id > 0 && class_exists('\\Learni\\Database\\Progress'))
            ? \Learni\Database\Progress::course_summary($user_id, $course_id)
            : ['total' => count($lesson_ids), 'completed' => 0, 'percent' => 0];

        $completed = ($user_id > 0 && $course_id > 0 && class_exists('\\Learni\\Database\\Progress'))
            ? array_flip(\Learni\Database\Progress::completed_lesson_ids($user_id, $course_id))
            : [];

        $linear_order = $course_id > 0 ? self::course_linear_order_enabled($course_id) : true;
        $max_unlocked = self::max_unlocked_lesson_index($lesson_ids, $completed, $linear_order);
        $lesson_index = self::lesson_index_map($lesson_ids);
        $is_locked = $linear_order && $index >= 0 && $max_unlocked >= 0 && $index > $max_unlocked;

        $is_complete = isset($completed[$lesson_id]);
        $total = (int) ($summary['total'] ?? 0);
        $step = $index >= 0 ? $index + 1 : 0;
        $percent = (int) ($summary['percent'] ?? 0);

        $raw = (string) get_post_field('post_content', $lesson_id);
        $processed = apply_filters('the_content', $raw);

        $video_url = (string) get_post_meta($lesson_id, \Learni\PostTypes\Lesson::META_VIDEO_URL, true);
        $video_html = '';
        $video_provider = '';
        $youtube_id = '';
        if ($video_url !== '') {
            $youtube_id = self::parse_youtube_id($video_url);
            if ($youtube_id !== '') {
                $video_provider = 'youtube';
                $embed_url = self::youtube_embed_url($youtube_id);
                $video_html = $embed_url !== '' ? '<iframe id="learni-youtube-player" src="' . esc_url($embed_url) . '" title="" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe>' : '';
            } else {
                $video_html = (string) wp_oembed_get($video_url);
            }
        }

        $course_url = $course_id > 0 ? (string) get_permalink($course_id) : '';
        $course_title = $course_id > 0 ? (string) get_the_title($course_id) : '';

        if ($is_locked) {
            $unlocked_id = ($max_unlocked >= 0 && isset($lesson_ids[$max_unlocked])) ? (int) $lesson_ids[$max_unlocked] : 0;
            $unlocked_slug = $unlocked_id > 0 ? (string) get_post_field('post_name', $unlocked_id) : '';
            $unlocked_url = $unlocked_slug !== '' ? home_url('/?learni_lesson=' . rawurlencode($unlocked_slug)) : '';
            $prev_url = $unlocked_url !== '' ? $unlocked_url : $prev_url;
            $processed = '<div class="learni-lesson-locked"><div class="learni-lesson-locked-title">' . esc_html__('Lección bloqueada', 'politeia-learning') . '</div><div class="learni-lesson-locked-text">' . esc_html__('Debes finalizar las lecciones anteriores para continuar.', 'politeia-learning') . '</div>' . ($unlocked_url !== '' ? '<a class="learni-lesson-locked-link" href="' . esc_url($unlocked_url) . '">' . esc_html__('Ir a la siguiente lección disponible', 'politeia-learning') . '</a>' : '') . '</div>';
            $video_html = '';
        }

        $html = '<div class="learni-learner learni-lesson-layout alignwide" data-learni-course-id="' . esc_attr((string) $course_id) . '">';
        $html .= '<div class="learni-lesson-shell">';

        $html .= '<aside class="learni-lesson-aside" aria-label="' . esc_attr__('Course navigation', 'politeia-learning') . '">';
        if ($course_url !== '') {
            $html .= '<a class="learni-lesson-back" href="' . esc_url($course_url) . '"><span class="learni-lesson-back-label">' . esc_html__('VOLVER A CURSO', 'politeia-learning') . '</span></a>';
        }
        if ($course_title !== '') {
            $html .= '<h2 id="learni-lesson-course-title" class="learni-lesson-course-title">' . esc_html($course_title) . '</h2>';
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
        if (empty($items)) {
            $html .= '<div class="learni-lesson-outline-empty">' . esc_html__('No lessons yet.', 'politeia-learning') . '</div>';
        } else {
            $html .= '<ul class="learni-lesson-outline-list">';
            foreach ($items as $item) {
                $type = (string) ($item['type'] ?? '');
                if ($type === 'header') {
                    $label = (string) ($item['label'] ?? '');
                    if ($label !== '') {
                        $html .= '<li class="learni-lesson-outline-header">' . esc_html($label) . '</li>';
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

                $html .= '<li class="' . esc_attr($classes) . '">';
                if ($url !== '' && !$item_is_locked) {
                    $html .= '<a href="' . esc_url($url) . '">';
                } else {
                    $html .= '<span>';
                }
                $html .= '<span class="learni-lesson-outline-label">' . esc_html($label) . '</span>';
                $html .= '<span class="learni-lesson-outline-status" aria-hidden="true">' . ($item_is_complete ? '✓' : '') . '</span>';
                $html .= ($url !== '') ? '</a>' : '</span>';
                $html .= '</li>';
            }
            $html .= '</ul>';
        }
        $html .= '</nav>';
        $html .= '</aside>';

        $html .= '<section class="learni-lesson-main" aria-label="' . esc_attr__('Lesson content', 'politeia-learning') . '">';
        $html .= '<div class="learni-lesson-top">';
        $html .= '<div class="learni-lesson-step">' . esc_html(sprintf(__('LECCIÓN %1$d DE %2$d', 'politeia-learning'), (int) $step, (int) $total)) . '</div>';
        $html .= '<div class="learni-lesson-top-actions">';

        $btn_label = $is_complete ? __('FINALIZADO', 'politeia-learning') : __('FINALIZAR', 'politeia-learning');
        $requires_video_gate = (!$is_complete && !$is_locked && $video_provider === 'youtube' && $video_html !== '');
        $btn_disabled = ($user_id <= 0) || $is_complete || ($course_id <= 0) || $is_locked || $requires_video_gate;
        $html .= '<form class="learni-lesson-complete-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        $html .= '<input type="hidden" name="action" value="pl_learni_mark_lesson_complete">';
        $html .= '<input type="hidden" name="lesson_id" value="' . esc_attr((string) $lesson_id) . '">';
        $html .= '<input type="hidden" name="redirect_to" value="' . esc_attr((string) (wp_get_raw_referer() ?: '')) . '">';
        $html .= wp_nonce_field('pl_learni_complete_lesson_' . $lesson_id, '_wpnonce', true, false);
        $html .= '<button type="submit" class="learni-lesson-complete-btn' . ($is_complete ? ' is-complete' : '') . '"' . ($requires_video_gate ? ' data-learni-requires-video="1"' : '') . ($btn_disabled ? ' disabled' : '') . '>';
        $html .= '<span class="learni-lesson-complete-icon" aria-hidden="true"></span>';
        $html .= '<span class="learni-lesson-complete-text">' . esc_html($btn_label) . '</span>';
        $html .= '</button>';
        $html .= '</form>';

        $next_pos = ($next_id > 0 && isset($lesson_index[$next_id])) ? (int) $lesson_index[$next_id] : -1;
        $next_unlocked = (!$linear_order) || ($next_pos >= 0 && $max_unlocked >= 0 && $next_pos <= $max_unlocked);
        if ($next_url !== '' && !$is_locked && (!$linear_order || $is_complete) && $next_unlocked) {
            $html .= '<a class="learni-lesson-next-btn" href="' . esc_url($next_url) . '" aria-label="' . esc_attr__('Next lesson', 'politeia-learning') . '">→</a>';
        }
        $html .= '</div>'; // actions
        $html .= '</div>'; // top

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
            $html .= '<ul class="learni-lesson-outline-list">';
            foreach ($items as $item) {
                $type = (string) ($item['type'] ?? '');
                if ($type === 'header') {
                    $label = (string) ($item['label'] ?? '');
                    if ($label !== '') {
                        $html .= '<li class="learni-lesson-outline-header">' . esc_html($label) . '</li>';
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

                $html .= '<li class="' . esc_attr($classes) . '">';
                if ($url !== '' && !$item_is_locked) {
                    $html .= '<a href="' . esc_url($url) . '">';
                } else {
                    $html .= '<span>';
                }
                $html .= '<span class="learni-lesson-outline-label">' . esc_html($label) . '</span>';
                $html .= '<span class="learni-lesson-outline-status" aria-hidden="true">' . ($item_is_complete ? '✓' : '') . '</span>';
                $html .= ($url !== '') ? '</a>' : '</span>';
                $html .= '</li>';
            }
            $html .= '</ul>';
        }
        $html .= '</nav>';
        $html .= '</div>';
        $html .= '</div>';

        $html .= '</div></div>';

        return $html;
    }

    private static function outline_fab_icon_svg(): string
    {
        return '<svg class="learni-outline-fab-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M19 2H8c-1.1 0-2 .9-2 2v15c0 .55.45 1 1 1h12v-2H8V4h11v16h2V4c0-1.1-.9-2-2-2zM3 6c-.55 0-1 .45-1 1v14c0 .55.45 1 1 1h14v-2H4V7c0-.55-.45-1-1-1z"></path></svg>';
    }

    public static function handle_mark_lesson_complete_nopriv(): void
    {
        $redirect = isset($_POST['redirect_to']) ? (string) wp_unslash($_POST['redirect_to']) : '';
        if ($redirect === '') {
            $redirect = home_url('/');
        }
        wp_safe_redirect(wp_login_url($redirect));
        exit;
    }

    public static function handle_enroll_course_nopriv(): void
    {
        $redirect = isset($_POST['redirect_to']) ? (string) wp_unslash($_POST['redirect_to']) : '';
        if ($redirect === '') {
            $redirect = home_url('/');
        }
        wp_safe_redirect(wp_login_url($redirect));
        exit;
    }

    public static function handle_enroll_course(): void
    {
        $user_id = (int) get_current_user_id();
        $course_id = isset($_POST['course_id']) ? (int) $_POST['course_id'] : 0;
        $redirect = isset($_POST['redirect_to']) ? (string) wp_unslash($_POST['redirect_to']) : '';

        if ($redirect === '') {
            $redirect = wp_get_referer() ?: home_url('/');
        }

        if ($user_id <= 0) {
            wp_safe_redirect(wp_login_url($redirect));
            exit;
        }

        if ($course_id <= 0) {
            wp_safe_redirect($redirect);
            exit;
        }

        check_admin_referer('pl_learni_enroll_course_' . $course_id);

        $price = (float) get_post_meta($course_id, 'learni_price', true);
        if ($price > 0) {
            // Paid enrollments are handled via WooCommerce for now.
            wp_safe_redirect($redirect);
            exit;
        }

        if (class_exists('\\Learni\\Database\\Enrollments')) {
            \Learni\Database\Enrollments::upsert(
                $user_id,
                $course_id,
                [
                    'status' => \Learni\Database\Enrollments::STATUS_ACTIVE,
                    'source' => \Learni\Database\Enrollments::SOURCE_DIRECT,
                ]
            );
        }

        wp_safe_redirect($redirect);
        exit;
    }

    public static function handle_mark_lesson_complete(): void
    {
        $user_id = (int) get_current_user_id();
        $lesson_id = isset($_POST['lesson_id']) ? (int) $_POST['lesson_id'] : 0;
        $redirect = isset($_POST['redirect_to']) ? (string) wp_unslash($_POST['redirect_to']) : '';

        if ($redirect === '') {
            $redirect = wp_get_referer() ?: home_url('/');
        }

        if ($user_id <= 0) {
            wp_safe_redirect(wp_login_url($redirect));
            exit;
        }

        if ($lesson_id <= 0) {
            wp_safe_redirect($redirect);
            exit;
        }

        check_admin_referer('pl_learni_complete_lesson_' . $lesson_id);

        $course_id = 0;
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

        if ($course_id <= 0) {
            wp_safe_redirect($redirect);
            exit;
        }

        if (class_exists('\\Learni\\Access\\Access') && !\Learni\Access\Access::user_can_access_course($user_id, $course_id)) {
            wp_safe_redirect($redirect);
            exit;
        }

        $linear_order = self::course_linear_order_enabled($course_id);
        if ($linear_order && class_exists('\\Learni\\Courses\\Outline') && class_exists('\\Learni\\Database\\Progress')) {
            $lesson_ids = \Learni\Courses\Outline::lesson_ids($course_id);
            $lesson_index = self::lesson_index_map($lesson_ids);
            $pos = isset($lesson_index[$lesson_id]) ? (int) $lesson_index[$lesson_id] : -1;
            $completed = array_flip(\Learni\Database\Progress::completed_lesson_ids($user_id, $course_id));
            $max_unlocked = self::max_unlocked_lesson_index($lesson_ids, $completed, true);
            if ($pos >= 0 && $max_unlocked >= 0 && $pos > $max_unlocked) {
                wp_safe_redirect($redirect);
                exit;
            }
        }

        $now = current_time('mysql');
        $table = $wpdb->prefix . 'learni_progress';

        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$table} (user_id, course_post_id, lesson_post_id, status, completed_at, updated_at)
                 VALUES (%d, %d, %d, %s, %s, %s)
                 ON DUPLICATE KEY UPDATE course_post_id = VALUES(course_post_id), status = VALUES(status), completed_at = VALUES(completed_at), updated_at = VALUES(updated_at)",
                $user_id,
                $course_id,
                $lesson_id,
                \Learni\Database\Progress::STATUS_COMPLETE,
                $now,
                $now
            )
        );

        wp_safe_redirect($redirect);
        exit;
    }

    private static function course_linear_order_enabled(int $course_id): bool
    {
        if ($course_id <= 0 || !class_exists('\\Learni\\PostTypes\\Course')) {
            return true;
        }
        $raw = get_post_meta($course_id, \Learni\PostTypes\Course::META_LINEAR_ORDER, true);
        if ($raw === '') {
            return true;
        }
        return (bool) (int) $raw;
    }

    /**
     * @param array<int,int> $lesson_ids
     * @return array<int,int> map lesson_id => index
     */
    private static function lesson_index_map(array $lesson_ids): array
    {
        $map = [];
        foreach ($lesson_ids as $i => $id) {
            $id = (int) $id;
            if ($id > 0) {
                $map[$id] = (int) $i;
            }
        }
        return $map;
    }

    /**
     * Computes the highest lesson index the user may open when linear order is enabled.
     *
     * @param array<int,int> $lesson_ids
     * @param array<int,true> $completed_map
     */
    private static function max_unlocked_lesson_index(array $lesson_ids, array $completed_map, bool $linear_order): int
    {
        $n = count($lesson_ids);
        if ($n <= 0) {
            return -1;
        }
        if (!$linear_order) {
            return $n - 1;
        }
        $i = 0;
        while ($i < $n) {
            $lesson_id = (int) $lesson_ids[$i];
            if (!isset($completed_map[$lesson_id])) {
                break;
            }
            $i++;
        }
        return min($i, $n - 1);
    }

    private static function parse_youtube_id(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        $parts = wp_parse_url($url);
        if (!is_array($parts)) {
            return '';
        }

        $host = isset($parts['host']) ? strtolower((string) $parts['host']) : '';
        $path = isset($parts['path']) ? (string) $parts['path'] : '';
        $query = [];
        if (isset($parts['query'])) {
            wp_parse_str((string) $parts['query'], $query);
        }

        if (str_contains($host, 'youtube.com')) {
            $id = isset($query['v']) ? (string) $query['v'] : '';
            $id = preg_replace('/[^a-zA-Z0-9_-]/', '', $id);
            return $id ?: '';
        }

        if ($host === 'youtu.be') {
            $id = trim($path, '/');
            $id = preg_replace('/[^a-zA-Z0-9_-]/', '', $id);
            return $id ?: '';
        }

        return '';
    }

    private static function youtube_embed_url(string $video_id): string
    {
        $video_id = preg_replace('/[^a-zA-Z0-9_-]/', '', $video_id);
        if ($video_id === '') {
            return '';
        }

        $origin_parts = wp_parse_url(home_url('/'));
        $origin = '';
        if (is_array($origin_parts)) {
            $scheme = isset($origin_parts['scheme']) ? (string) $origin_parts['scheme'] : 'https';
            $host = isset($origin_parts['host']) ? (string) $origin_parts['host'] : '';
            $port = isset($origin_parts['port']) ? (int) $origin_parts['port'] : 0;
            if ($host !== '') {
                $origin = $scheme . '://' . $host . ($port ? ':' . $port : '');
            }
        }

        $args = [
            'enablejsapi' => '1',
            'rel' => '0',
            'modestbranding' => '1',
        ];
        if ($origin !== '') {
            $args['origin'] = $origin;
        }

        return (string) add_query_arg($args, 'https://www.youtube.com/embed/' . $video_id);
    }

    public static function template_include(string $template): string
    {
        // For block themes, keep the theme's header/footer/template structure.
        if (self::is_block_theme()) {
            return $template;
        }

        if (is_singular('learni_course')) {
            $t = PL_LEARNI_PATH . 'templates/single-learni_course.php';
            if (file_exists($t)) {
                return $t;
            }
        }

        if (is_singular('learni_lesson')) {
            $t = PL_LEARNI_PATH . 'templates/single-learni_lesson.php';
            if (file_exists($t)) {
                return $t;
            }
        }

        return $template;
    }

    private static function binomial_course_state(int $course_id, int $user_id, int $lesson_percent): array
    {
        if ($course_id <= 0 || $user_id <= 0 || !class_exists('\\Learni\\Database\\Progress')) {
            return [
                'quizId' => 0,
                'needsInitial' => false,
                'needsFinal' => false,
                'canTakeFinal' => false,
                'initial' => null,
                'final' => null,
            ];
        }

        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, lesson_post_id, settings_json
                 FROM {$wpdb->prefix}learni_quizzes
                 WHERE course_post_id = %d
                 ORDER BY id ASC",
                $course_id
            ),
            ARRAY_A
        );

        $quiz_id = 0;
        $fallback_id = 0;
        $fallback_count = 0;
        foreach ($rows as $row) {
            $settings = [];
            if (!empty($row['settings_json'])) {
                $decoded = json_decode((string) $row['settings_json'], true);
                if (is_array($decoded)) {
                    $settings = $decoded;
                }
            }
            if (isset($settings['role']) && (string) $settings['role'] === 'binomial') {
                $quiz_id = (int) ($row['id'] ?? 0);
                break;
            }
            if (empty($row['lesson_post_id'])) {
                $fallback_id = (int) ($row['id'] ?? 0);
                $fallback_count++;
            }
        }
        if ($quiz_id <= 0 && $fallback_count === 1) {
            $quiz_id = $fallback_id;
        }

        if ($quiz_id <= 0) {
            return [
                'quizId' => 0,
                'needsInitial' => false,
                'needsFinal' => false,
                'canTakeFinal' => false,
                'initial' => null,
                'final' => null,
            ];
        }

        $attempts_table = $wpdb->prefix . 'learni_quiz_attempts';
        $submitted_count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*)
                 FROM {$attempts_table}
                 WHERE quiz_id = %d AND user_id = %d AND status = %s",
                $quiz_id,
                $user_id,
                'submitted'
            )
        );

        $last_two = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, score, submitted_at, answers_json
                 FROM {$attempts_table}
                 WHERE quiz_id = %d AND user_id = %d AND status = %s
                 ORDER BY submitted_at DESC, id DESC
                 LIMIT 2",
                $quiz_id,
                $user_id,
                'submitted'
            ),
            ARRAY_A
        );

        $initial = null;
        $final = null;
        if ($submitted_count % 2 === 1) {
            $last = isset($last_two[0]) ? $last_two[0] : null;
            if (is_array($last)) {
                $initial = self::attempt_public_payload($last);
            }
        } elseif ($submitted_count >= 2) {
            $last_final = isset($last_two[0]) ? $last_two[0] : null;
            $last_initial = isset($last_two[1]) ? $last_two[1] : null;
            if (is_array($last_initial)) {
                $initial = self::attempt_public_payload($last_initial);
            }
            if (is_array($last_final)) {
                $final = self::attempt_public_payload($last_final);
            }
        }

        $needs_initial = $submitted_count === 0 || ($submitted_count % 2 === 0 && $lesson_percent < 100);
        $needs_final = $submitted_count % 2 === 1;
        $can_take_final = $needs_final && $lesson_percent >= 100 && class_exists('\\Learni\\Access\\Access') && \Learni\Access\Access::user_can_access_course($user_id, $course_id);

        return [
            'quizId' => $quiz_id,
            'submittedCount' => $submitted_count,
            'needsInitial' => $needs_initial,
            'needsFinal' => $needs_final,
            'canTakeFinal' => $can_take_final,
            'canRestart' => $submitted_count > 0 && $submitted_count % 2 === 0 && $lesson_percent >= 100 && class_exists('\\Learni\\Access\\Access') && \Learni\Access\Access::user_can_access_course($user_id, $course_id),
            'initial' => $initial,
            'final' => $final,
        ];
    }

    private static function attempt_public_payload(array $row): array
    {
        $payload = [];
        if (!empty($row['answers_json'])) {
            $decoded = json_decode((string) $row['answers_json'], true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }
        $total = isset($payload['total']) ? (int) $payload['total'] : 0;
        $score = isset($row['score']) ? (int) $row['score'] : 0;
        $percent = isset($payload['percent']) ? (int) $payload['percent'] : ($total > 0 ? (int) round(($score / $total) * 100) : 0);
        return [
            'attemptId' => (int) ($row['id'] ?? 0),
            'score' => $score,
            'total' => $total,
            'percent' => $percent,
            'submittedAt' => (string) ($row['submitted_at'] ?? ''),
        ];
    }
}
