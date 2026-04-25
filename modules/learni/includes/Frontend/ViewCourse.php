<?php
/**
 * Frontend View Course logic for Learni.
 */

if (!defined('ABSPATH')) {
    exit;
}

final class PL_Learni_Frontend_ViewCourse
{
    public static function render(): string
    {
        $course_id = (int) get_the_ID();
        if ($course_id <= 0) {
            return '<div class="learni-learner"><p>' . esc_html__('Course not found.', 'politeia-learning') . '</p></div>';
        }

        $user_id = (int) get_current_user_id();
        $is_logged_in = $user_id > 0;
        $has_access = $is_logged_in && class_exists('\\Learni\\Access\\Access') && \Learni\Access\Access::user_can_access_course($user_id, $course_id);

        $title = (string) get_the_title($course_id);
        $excerpt = (string) get_post_field('post_excerpt', $course_id);
        if ($excerpt === '') {
            $excerpt = wp_trim_words(wp_strip_all_tags((string) get_post_field('post_content', $course_id)), 40, '…');
        }

        $items = class_exists('\\Learni\\Courses\\Outline') ? \Learni\Courses\Outline::get_items($course_id) : [];
        $lesson_ids = class_exists('\\Learni\\Courses\\Outline') ? \Learni\Courses\Outline::lesson_ids($course_id) : [];
        $summary = ($is_logged_in && class_exists('\\Learni\\Database\\Progress')) ? \Learni\Database\Progress::course_summary($user_id, $course_id) : ['total' => count($lesson_ids), 'completed' => 0, 'percent' => 0];
        $completed = ($has_access && class_exists('\\Learni\\Database\\Progress')) ? array_flip(\Learni\Database\Progress::completed_lesson_ids($user_id, $course_id)) : [];
        $linear_order = PL_Learni_Frontend_Templates::course_linear_order_enabled($course_id);
        $lesson_index = PL_Learni_Frontend_Templates::lesson_index_map($lesson_ids);
        $max_unlocked = PL_Learni_Frontend_Templates::max_unlocked_lesson_index($lesson_ids, $completed, $linear_order);

        $percent = (int) ($summary['percent'] ?? 0);
        $total = (int) ($summary['total'] ?? 0);
        $done = (int) ($summary['completed'] ?? 0);
        $progress_text = sprintf(__('COMPLETADO %1$d DE %2$d LECCIONES', 'politeia-learning'), $done, $total);
        $price = (float) get_post_meta($course_id, \Learni\PostTypes\Course::META_PRICE, true);
        $product_id = (int) get_post_meta($course_id, \Learni\PostTypes\Course::META_WC_PRODUCT_ID, true);
        $price_label = $price > 0 ? '$' . number_format((float) $price, 0, '.', ',') : __('FREE', 'politeia-learning');
        $is_free = $price <= 0 && $product_id <= 0;

        $thumb_id = (int) get_post_thumbnail_id($course_id);
        $thumb_url = $thumb_id > 0 ? (string) wp_get_attachment_image_url($thumb_id, 'large') : '';

        $has_course_partner = false;
        $partner_user_id = 0;
        $owner_user_id = 0;
        if (class_exists('PL_Partnerships_Repository') && method_exists('PL_Partnerships_Repository', 'get_single_partner')) {
            try {
                $p = PL_Partnerships_Repository::get_single_partner('course', $course_id, 'partner');
                $has_course_partner = is_array($p) && !empty($p['partner_user_id']);
                if (is_array($p)) {
                    $partner_user_id = !empty($p['partner_user_id']) ? (int) $p['partner_user_id'] : 0;
                    $owner_user_id = !empty($p['owner_user_id']) ? (int) $p['owner_user_id'] : 0;
                }
            } catch (\Throwable $e) {
                $has_course_partner = false;
            }
        }

        // Fallback: attempt to infer the purchaser/owner from active enrollments.
        if ($owner_user_id <= 0 && $partner_user_id > 0 && $has_access && class_exists('\\Learni\\Database\\Enrollments')) {
            global $wpdb;
            if ($wpdb) {
                $enroll_table = $wpdb->prefix . 'learni_enrollments';
                $rows = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT user_id, source, payment_provider
                         FROM {$enroll_table}
                         WHERE course_post_id = %d AND status = %s
                         ORDER BY created_at ASC
                         LIMIT 10",
                        $course_id,
                        \Learni\Database\Enrollments::STATUS_ACTIVE
                    ),
                    ARRAY_A
                );
                foreach ((array) $rows as $r) {
                    if (!is_array($r)) {
                        continue;
                    }
                    $candidate_id = (int) ($r['user_id'] ?? 0);
                    if ($candidate_id <= 0) {
                        continue;
                    }
                    $src = (string) ($r['source'] ?? '');
                    $prov = (string) ($r['payment_provider'] ?? '');
                    $is_owner_row = ($src === \Learni\Database\Enrollments::SOURCE_WOOCOMMERCE)
                        || ($src === \Learni\Database\Enrollments::SOURCE_DIRECT)
                        || ($src === \Learni\Database\Enrollments::SOURCE_MANUAL && $prov !== 'partner_invite');
                    if ($is_owner_row) {
                        $owner_user_id = $candidate_id;
                        break;
                    }
                }
            }
        }

        $certificate_available = $has_access && ($percent >= 100) && class_exists('PL_Learni_Frontend_Certificates') && PL_Learni_Frontend_Certificates::template_exists($course_id);
        if ($certificate_available) {
            $self_binomial = PL_Learni_Frontend_Assessment::binomial_course_state($course_id, $user_id, $percent);
            $certificate_available = $certificate_available && !empty($self_binomial['eligibleFinal']);

            if ($certificate_available && $has_course_partner && $partner_user_id > 0) {
                $other_user_id = ($partner_user_id === $user_id) ? $owner_user_id : $partner_user_id;
                if ($other_user_id > 0) {
                    $other_summary = class_exists('\\Learni\\Database\\Progress') ? \Learni\Database\Progress::course_summary($other_user_id, $course_id) : ['percent' => 0];
                    $other_percent = (int) ($other_summary['percent'] ?? 0);
                    $other_binomial = PL_Learni_Frontend_Assessment::binomial_course_state($course_id, $other_user_id, $other_percent);
                    $certificate_available = $certificate_available && ($other_percent >= 100) && !empty($other_binomial['eligibleFinal']);
                } else {
                    $certificate_available = false;
                }
            }
        }

        $author_id = (int) get_post_field('post_author', $course_id);
        $author = $author_id > 0 ? get_userdata($author_id) : null;
        $author_full_name = '';
        if ($author instanceof \WP_User) {
            $fname = get_user_meta($author_id, 'first_name', true);
            $lname = get_user_meta($author_id, 'last_name', true);
            if ($fname !== '' || $lname !== '') {
                $author_full_name = trim($fname . ' ' . $lname);
            } else {
                $author_full_name = (string) $author->display_name;
            }
        }
        $author_slug = ($author instanceof \WP_User) ? (string) $author->user_nicename : '';
        $author_profile_url = $author_slug !== '' ? home_url('/profile/' . rawurlencode($author_slug) . '/') : '';
        $author_avatar = ($author_id > 0 && function_exists('get_avatar_url')) ? (string) get_avatar_url($author_id, ['size' => 72]) : '';

        $html = '<div id="learni-course" class="learni-learner alignwide" data-course-id="' . esc_attr((string) $course_id) . '">';

        $cover_photo_id = (int) get_post_meta($course_id, \Learni\PostTypes\Course::META_COVER_PHOTO_ID, true);
        $cover_photo_url = $cover_photo_id > 0 ? (string) wp_get_attachment_image_url($cover_photo_id, 'full') : '';
        $hero_class = 'learni-course-hero' . ($cover_photo_url !== '' ? ' has-cover' : '');
        $hero_style = $cover_photo_url !== '' ? ' style="--learni-hero-image:url(' . esc_url($cover_photo_url) . ');"' : '';

        $html .= '<section class="' . esc_attr($hero_class) . '"' . $hero_style . '><div class="learni-course-hero-content"><div class="learni-course-hero-inner">';
        $html .= '<div class="learni-course-hero-left">';
        $html .= '<h1 id="learni-course-title">' . esc_html($title) . '</h1>';
        if ($excerpt !== '') {
            $html .= '<p class="learni-course-description">' . esc_html($excerpt) . '</p>';
        }
        $html .= '<div class="learni-course-author">';
        if ($author_avatar !== '') {
            if ($author_profile_url !== '') {
                $html .= '<a class="learni-course-author-avatar-link" href="' . esc_url($author_profile_url) . '">';
                $html .= '<img class="learni-course-author-avatar" src="' . esc_url($author_avatar) . '" alt="">';
                $html .= '</a>';
            } else {
                $html .= '<div class="learni-course-author-avatar-link" aria-hidden="true">';
                $html .= '<img class="learni-course-author-avatar" src="' . esc_url($author_avatar) . '" alt="">';
                $html .= '</div>';
            }
        }
        $html .= '<div class="learni-course-author-meta">';
        $html .= '<div class="learni-course-author-label">' . esc_html__('Profesor', 'politeia-learning') . '</div>';
        if ($author_profile_url !== '') {
            $html .= '<a class="learni-course-author-name learni-course-author-name-link" href="' . esc_url($author_profile_url) . '">';
            $html .= esc_html($author_full_name);
            $html .= '</a>';
        } else {
            $html .= '<div class="learni-course-author-name">' . esc_html($author_full_name) . '</div>';
        }
        $html .= '</div>';
        $html .= '</div>';

        if (!$has_course_partner) {
            $html .= '<div class="learni-progress">';
            $html .= '<div class="learni-progress-head">';
            $html .= '<span class="learni-progress-text">' . esc_html($progress_text) . '</span>';
            $html .= '<span class="learni-progress-percent">' . esc_html((string) $percent) . '%</span>';
            $html .= '</div>';
            $html .= '<div class="learni-progress-bar" role="progressbar" aria-valuenow="' . esc_attr((string) $percent) . '" aria-valuemin="0" aria-valuemax="100">';
            $html .= '<div class="learni-progress-bar-fill" style="width:' . esc_attr((string) $percent) . '%">';
            $html .= '<div class="learni-progress-shimmer" aria-hidden="true"></div>';
            $html .= '</div>';
            $html .= '</div>';
            $html .= '</div>';
        }
        $html .= '</div>'; // left

        $html .= '<aside class="learni-course-hero-card" aria-label="' . esc_attr__('Course details', 'politeia-learning') . '">';
        if ($thumb_id > 0) {
            $thumb_img = wp_get_attachment_image($thumb_id, 'full', false, [
                'class' => 'learni-course-card-thumbnail',
                'sizes' => '(min-width: 971px) 420px, 100vw',
                'loading' => 'lazy',
                'decoding' => 'async',
            ]);
            if ($thumb_img) {
                $html .= '<div class="learni-course-card-thumbnail-wrap">' . $thumb_img . '</div>';
            }
        } elseif ($thumb_url !== '') {
            $html .= '<div class="learni-course-card-thumbnail-wrap"><img class="learni-course-card-thumbnail" src="' . esc_url($thumb_url) . '" alt=""></div>';
        }

        $html .= '<div class="learni-course-hero-card-body">';
        $html .= '<div class="learni-course-price-row">';
        $html .= '<div class="learni-course-price">' . esc_html($price_label) . '</div>';
        if ($certificate_available) {
            $html .= '<button id="learni-course-cert-trigger" class="learni-course-cert-trigger" type="button" aria-label="' . esc_attr__('View certificate', 'politeia-learning') . '" data-learni-cert-open="1" data-course-id="' . esc_attr((string) $course_id) . '">';
            $html .= '<span class="material-symbols-outlined learni-ms-icon learni-course-cert-icon" aria-hidden="true">history_edu</span>';
            $html .= '<span class="learni-course-cert-text">' . esc_html__('CERTIFICADO', 'politeia-learning') . '</span>';
            $html .= '</button>';
        }
        $html .= '</div>';
        $html .= '<div class="learni-course-card-actions">';

        $binomial = $is_logged_in ? PL_Learni_Frontend_Assessment::binomial_course_state($course_id, $user_id, $percent) : [];
        if (is_array($binomial['initial'] ?? null)) {
            $ip = (int) ($binomial['initial']['percent'] ?? 0);
            $html .= '<div class="learni-eval" data-learni-eval="initial">';
            $html .= '<div class="learni-eval-head"><span class="learni-eval-title">' . esc_html__('EVALUACIÓN INICIAL', 'politeia-learning') . '</span><span class="learni-eval-percent">' . esc_html((string) $ip) . '%</span></div>';
            $html .= '<div class="learni-eval-track"><div class="learni-eval-bar" style="width:' . esc_attr((string) $ip) . '%"></div></div>';
            $html .= '</div>';
        }
        if (is_array($binomial['final'] ?? null)) {
            $fp = (int) ($binomial['final']['percent'] ?? 0);
            $html .= '<div class="learni-eval" data-learni-eval="final">';
            $html .= '<div class="learni-eval-head"><span class="learni-eval-title">' . esc_html__('EVALUACIÓN FINAL', 'politeia-learning') . '</span><span class="learni-eval-percent">' . esc_html((string) $fp) . '%</span></div>';
            $html .= '<div class="learni-eval-track"><div class="learni-eval-bar" style="width:' . esc_attr((string) $fp) . '%"></div></div>';
            $html .= '</div>';
        }

        $binomial_quiz_id = (int) ($binomial['quizId'] ?? 0);
        if ($binomial_quiz_id <= 0) {
            $binomial_quiz_id = PL_Learni_Frontend_Assessment::binomial_quiz_id_for_course($course_id);
        }

        if ($binomial_quiz_id > 0) {
            if (!$is_logged_in || (!empty($binomial['needsInitial']) && $has_access)) {
                $html .= '<button id="learni-course-first-quiz" class="learni-btn learni-btn-quiz" type="button" data-course-id="' . esc_attr((string) $course_id) . '" data-phase="initial">' . esc_html__('TAKE FIRST QUIZ', 'politeia-learning') . '</button>';
            } elseif ($has_access && !empty($binomial['needsFinal']) && $percent >= 100 && empty($binomial['eligibleFinal'])) {
                $disabled = (!empty($binomial['canTakeFinal'])) ? '' : ' disabled';
                $html .= '<button id="learni-course-final-quiz" class="learni-btn learni-btn-quiz" type="button" data-course-id="' . esc_attr((string) $course_id) . '" data-phase="final"' . $disabled . '>' . esc_html__('TAKE FINAL QUIZ', 'politeia-learning') . '</button>';
            }
        }

        $is_enrolled = $has_access && class_exists('\\Learni\\Database\\Enrollments') && \Learni\Database\Enrollments::user_has_active($user_id, $course_id);
        $course_permalink = (string) get_permalink($course_id);
        $first_lesson_url = '';
        if (!empty($lesson_ids)) {
            $first_slug = (string) get_post_field('post_name', (int) $lesson_ids[0]);
            $first_lesson_url = $first_slug !== '' ? home_url('/?learni_lesson=' . rawurlencode($first_slug)) : '';
        }

        if ($has_access && !empty($binomial['eligibleFinal'])) {
            $r_days = (int) ($binomial['restartCooldownDaysRemaining'] ?? 0);
            $disabled = $r_days > 0 ? ' disabled' : '';
            $title = $r_days > 0
                ? ' title="' . esc_attr(sprintf(_n('Disponible en %d día.', 'Disponible en %d días.', $r_days, 'politeia-learning'), $r_days)) . '"'
                : '';
            $label = $r_days > 0
                ? sprintf(_n('%d DÍA PARA REINICIAR', '%d DÍAS PARA REINICIAR', $r_days, 'politeia-learning'), $r_days)
                : __('REINICIAR CURSO', 'politeia-learning');
            $html .= '<button id="learni-course-restart" class="learni-btn learni-course-primary-btn" type="button" data-course-id="' . esc_attr((string) $course_id) . '"' . $disabled . $title . '>' . esc_html($label) . '</button>';
        } elseif (!$is_enrolled && !$is_free) {
            $checkout_url = $product_id > 0 ? (string) add_query_arg(['action' => 'pl_learni_checkout_course', 'course_id' => (string) $course_id], admin_url('admin-post.php')) : '#';
            $product_url = ($user_id <= 0 && $checkout_url !== '#') ? wp_login_url($checkout_url) : $checkout_url;
            $html .= '<a class="learni-btn learni-course-primary-btn" href="' . esc_url($product_url) . '">' . esc_html__('COMPRAR CURSO', 'politeia-learning') . '</a>';
        } elseif ($is_free && !$is_enrolled) {
            $redirect_to = $first_lesson_url ?: $course_permalink;
            $html .= '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            $html .= '<input type="hidden" name="action" value="pl_learni_enroll_course">';
            $html .= '<input type="hidden" name="course_id" value="' . esc_attr((string) $course_id) . '">';
            $html .= '<input type="hidden" name="redirect_to" value="' . esc_attr($redirect_to) . '">';
            $html .= wp_nonce_field('pl_learni_enroll_course_' . $course_id, '_wpnonce', true, false);
            $html .= '<button type="submit" class="learni-btn learni-course-primary-btn">' . esc_html__('START COURSE', 'politeia-learning') . '</button>';
            $html .= '</form>';
        } elseif ($has_access && !empty($lesson_ids)) {
            $continue_id = ($max_unlocked >= 0 && isset($lesson_ids[$max_unlocked])) ? (int) $lesson_ids[$max_unlocked] : (int) $lesson_ids[0];
            $continue_slug = (string) get_post_field('post_name', $continue_id);
            $continue_url = $continue_slug !== '' ? home_url('/?learni_lesson=' . rawurlencode($continue_slug)) : $first_lesson_url;
            $html .= '<a class="learni-btn learni-course-primary-btn" href="' . esc_url($continue_url) . '">' . esc_html__($is_enrolled ? 'CONTINUE' : 'START COURSE', 'politeia-learning') . '</a>';
        }

        $html .= '</div>'; // card actions

        // --- Partner Section ---
        $html .= '<div class="learni-course-partner">';
        
        // Show partner progress if enrolled and has a partner
        if ($has_access && $has_course_partner && $partner_user_id > 0) {
            $other_user_id = ($partner_user_id === $user_id) ? $owner_user_id : $partner_user_id;
            if ($other_user_id > 0 && class_exists('\\Learni\\Database\\Progress')) {
                $other_summary = \Learni\Database\Progress::course_summary($other_user_id, $course_id);
                $other_percent = (int) ($other_summary['percent'] ?? 0);
                $other_u = get_userdata($other_user_id);
                $other_name = $other_u ? $other_u->display_name : __('Partner', 'politeia-learning');
                $other_avatar = function_exists('pl_get_user_profile_avatar_custom_url') ? pl_get_user_profile_avatar_custom_url($other_user_id, 32) : get_avatar_url($other_user_id, ['size' => 32]);

                $html .= '<div class="learni-course-partner-progress">';
                $html .= '<div class="learni-course-partner-progress-item">';
                $html .= '<div class="learni-course-partner-progress-head">';
                $html .= '<div class="learni-course-partner-progress-user">';
                $html .= '<img class="learni-course-partner-progress-avatar" src="' . esc_url($other_avatar) . '" alt="">';
                $html .= '<span class="learni-course-partner-progress-name">' . esc_html($other_name) . '</span>';
                $html .= '</div>';
                $html .= '<span class="learni-course-partner-progress-percent">' . esc_html($other_percent) . '%</span>';
                $html .= '</div>';
                $html .= '<div class="learni-course-partner-progress-bar" role="progressbar" aria-valuenow="' . esc_attr((string) $other_percent) . '" aria-valuemin="0" aria-valuemax="100">';
                $html .= '<span class="learni-course-partner-progress-fill" style="width:' . esc_attr((string) $other_percent) . '%"></span>';
                $html .= '</div>';
                $html .= '</div>';
                $html .= '</div>';
            }
        }

        // Add/Replace Partner Button
        $partner_btn_label = $has_course_partner ? __('Replace Partner', 'politeia-learning') : 'AÑADIR COMPAÑERO';
        
        if (!$is_enrolled) {
            $tooltip_msg = 'Compra el curso para invitar a un compañero y completarlo juntos';
            $html .= '<div class="learni-tooltip-wrap">';
            $html .= '<button type="button" class="learni-btn secondary learni-course-partner-btn addPartnerBtn" disabled>';
            $html .= '<span class="material-symbols-outlined learni-ms-icon" aria-hidden="true">person_add</span>';
            $html .= '<span class="learni-course-partner-btn-text">' . esc_html($partner_btn_label) . '</span>';
            $html .= '</button>';
            $html .= '<div class="learni-tooltip is-left">' . esc_html($tooltip_msg) . '</div>';
            $html .= '</div>';
        } else {
            $can_manage_partner = class_exists('\\Learni\\Database\\Enrollments') && method_exists('\\Learni\\Database\\Enrollments', 'user_is_owner') && \Learni\Database\Enrollments::user_is_owner($user_id, $course_id);
            if ($can_manage_partner || current_user_can('manage_options')) {
                $html .= '<button id="pl-add-partner-btn-' . esc_attr((string) $course_id) . '" type="button" class="learni-btn secondary learni-course-partner-btn pl-add-partner addPartnerBtn">';
                $html .= '<span class="material-symbols-outlined learni-ms-icon" aria-hidden="true">person_add</span>';
                $html .= '<span class="learni-course-partner-btn-text">' . esc_html($partner_btn_label) . '</span>';
                $html .= '</button>';
            }
        }

        $html .= '</div>'; // learni-course-partner
        
        $html .= '<div class="learni-course-includes">';
        $html .= '<div class="learni-course-includes-title">' . esc_html__('CURSO INCLUYE', 'politeia-learning') . '</div>';
        $html .= '<div class="learni-course-includes-row">';
        $html .= '<span class="learni-course-includes-icon"><span class="material-symbols-outlined learni-ms-icon" aria-hidden="true">auto_stories</span></span>';
        $html .= '<span class="learni-course-includes-text">' . esc_html(sprintf(_n('%d Lección', '%d Lecciones', $total, 'politeia-learning'), $total)) . '</span>';
        $html .= '</div>';
        $html .= '</div>'; // includes
        
        $html .= '</div>'; // card body
        $html .= '</aside>';

        $html .= '</div></div></section>';

        $html .= '<div class="learni-course-body">';
        $html .= '<div class="learni-tabs" role="tablist" aria-label="' . esc_attr__('Course tabs', 'politeia-learning') . '">';
        $html .= '<button type="button" class="learni-tab is-active" role="tab" aria-selected="true" data-learni-tab="content">' . esc_html__('CONTENT', 'politeia-learning') . '</button>';
        $html .= '<button type="button" class="learni-tab" role="tab" aria-selected="false" data-learni-tab="lessons">' . esc_html__('LESSONS', 'politeia-learning') . '</button>';
        $html .= '</div>';

        $html .= '<div class="learni-tabpanel is-active" role="tabpanel" data-learni-panel="content">';
        $html .= '<section class="learni-course-content">' . apply_filters('the_content', (string) get_post_field('post_content', $course_id)) . '</section>';
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
                $src_meta_key = class_exists('\\Learni\\PostTypes\\Lesson') ? \Learni\PostTypes\Lesson::META_SOURCE_POST_ID : 'learni_source_post_id';
                $src_id = (int) get_post_meta($lesson_id, $src_meta_key, true);
                $badge_html = '';
                if ($src_id > 0) {
                    $src_title = get_the_title($src_id);
                    $badge_html = ' <span class="learni-lesson-badge-texto" title="' . esc_attr($src_title) . '">TEXTO</span>';
                }

                $can_view = $has_access || $is_free;
                $is_nav_locked = $linear_order && $pos >= 0 && $max_unlocked >= 0 && $pos > $max_unlocked;
                $is_restricted = !$can_view;

                $html .= '<li class="learni-outline-lesson' . ($is_done ? ' is-complete' : '') . (($is_nav_locked || $is_restricted) ? ' is-locked' : '') . '">';
                if ($url !== '' && !$is_nav_locked && !$is_restricted) {
                    $html .= '<a href="' . esc_url($url) . '">';
                } else {
                    $html .= '<span>';
                }
                $html .= '<span class="learni-check" aria-hidden="true">' . ($is_done ? '✓' : '•') . '</span>';
                $html .= '<span class="learni-label">' . esc_html($label) . '</span>';
                $html .= $badge_html;
                if ($url !== '' && !$is_nav_locked && !$is_restricted) {
                    $html .= '</a>';
                } else {
                    $html .= '</span>';
                }
                $html .= '</li>';
            }
            $html .= '</ul>';
        }
        $html .= '</section>';
        $html .= '</div>'; // lessons panel
        $html .= '</div>'; // course body
        $html .= '</div>'; // #learni-course

        if ($certificate_available) {
            $html .= PL_Learni_Frontend_Certificates::render_modal_html($course_id, $user_id);
        }

        return $html;
    }
}
