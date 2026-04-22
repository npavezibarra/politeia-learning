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
        $user_id = (int) get_current_user_id();
        $is_logged_in = $user_id > 0;
        $course_permalink = (string) get_permalink($course_id);

        if ($course_id <= 0) {
            return '';
        }

        $summary = class_exists('\\Learni\\Database\\Progress') ? \Learni\Database\Progress::course_summary($user_id, $course_id) : ['percent' => 0];
        $percent = (int) ($summary['percent'] ?? 0);
        $is_enrolled = class_exists('\\Learni\\Access\\Access') && \Learni\Access\Access::is_enrolled($user_id, $course_id);
        $has_access = class_exists('\\Learni\\Access\\Access') && \Learni\Access\Access::user_can_access_course($user_id, $course_id);
        $is_free = (float) get_post_meta($course_id, 'learni_price', true) <= 0;
        $product_id = (int) get_post_meta($course_id, 'learni_wc_product_id', true);

        // Course partner logic.
        $has_course_partner = (bool) get_post_meta($course_id, 'learni_has_partner', true);
        $partner_user_id = 0;
        $owner_user_id = 0;
        if ($has_course_partner) {
            global $wpdb;
            if ($wpdb) {
                $partner_user_id = (int) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT user_id
                         FROM {$wpdb->prefix}learni_enrollments
                         WHERE course_post_id = %d AND source = %s AND payment_provider = %s
                         LIMIT 1",
                        $course_id,
                        \Learni\Database\Enrollments::SOURCE_MANUAL,
                        'partner_invite'
                    )
                );

                $rows = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT user_id, source, payment_provider
                         FROM {$wpdb->prefix}learni_enrollments
                         WHERE course_post_id = %d
                         ORDER BY id ASC",
                        $course_id
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

	        // Only show the certificate CTA once the user has completed the final evaluation.
	        // For partner courses, both users must have completed their final evaluation (mutual testing).
	        $certificate_available = $has_access && ($percent >= 100) && PL_Learni_Frontend_Certificates::template_exists($course_id);
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
        $author_first = ($author instanceof \WP_User) ? trim((string) ($author->first_name ?? '')) : '';
        $author_last = ($author instanceof \WP_User) ? trim((string) ($author->last_name ?? '')) : '';
        $author_full_name = trim($author_first . ' ' . $author_last);
        if ($author_full_name === '' && $author instanceof \WP_User) {
            $author_full_name = trim((string) ($author->display_name ?? ''));
        }
        if ($author_full_name === '') {
            $author_full_name = __('Educator', 'politeia-learning');
        }

        $items = class_exists('\\Learni\\Courses\\Outline') ? \Learni\Courses\Outline::get($course_id) : [];
        $lesson_ids = [];
        foreach ($items as $it) {
            if (isset($it['item_type']) && (string) $it['item_type'] === 'lesson' && isset($it['item_ref_id'])) {
                $lesson_ids[] = (int) $it['item_ref_id'];
            }
        }
        $completed = array_flip(class_exists('\\Learni\\Database\\Progress') ? \Learni\Database\Progress::completed_lesson_ids($user_id, $course_id) : []);

        $linear_order = PL_Learni_Frontend_Templates::course_linear_order_enabled($course_id);
        $lesson_index = PL_Learni_Frontend_Templates::lesson_index_map($lesson_ids);
        $max_unlocked = PL_Learni_Frontend_Templates::max_unlocked_lesson_index($lesson_ids, $completed, $linear_order);

        $continue_lesson_url = '';
        if ($is_enrolled) {
            $last_lesson_id = 0;
            if ($max_unlocked >= 0 && isset($lesson_ids[$max_unlocked])) {
                $last_lesson_id = $lesson_ids[$max_unlocked];
            }
            if ($last_lesson_id > 0) {
                $continue_lesson_url = (string) get_permalink($last_lesson_id);
            }
        }

        $html = '<div id="learni-course" class="learni-course' . ($is_enrolled ? ' is-enrolled' : ' is-visitor') . '">';

        $html .= '<section class="learni-course-hero">';
        $html .= '<div class="learni-course-hero-content">';

        $feat_url = (string) get_the_post_thumbnail_url($course_id, 'large');
        if ($feat_url !== '') {
            $html .= '<div class="learni-course-hero-media"><img src="' . esc_url($feat_url) . '" alt=""></div>';
        }

        $html .= '<div class="learni-course-hero-meta">';
        $html .= '<div class="learni-course-badge">' . esc_html__('COURSE', 'politeia-learning') . '</div>';
        $html .= '<h1 class="learni-course-title">' . esc_html(get_the_title($course_id)) . '</h1>';
        $html .= '<div class="learni-course-author">' . sprintf(esc_html__('By %s', 'politeia-learning'), '<strong>' . esc_html($author_full_name) . '</strong>') . '</div>';

        $html .= '<div class="learni-course-cta">';
        if (!$is_enrolled && $is_free) {
            $redirect_to = $continue_lesson_url ?: $course_permalink;
            $html .= '<form action="' . esc_url(admin_url('admin-post.php')) . '" method="POST">';
            $html .= '<input type="hidden" name="action" value="pl_learni_enroll_course">';
            $html .= '<input type="hidden" name="course_id" value="' . esc_attr((string) $course_id) . '">';
            $html .= '<input type="hidden" name="redirect_to" value="' . esc_attr($redirect_to) . '">';
            $html .= wp_nonce_field('pl_learni_enroll_course_' . $course_id, '_wpnonce', true, false);
            $html .= '<button type="submit" class="learni-btn learni-course-primary-btn">' . esc_html__('START COURSE', 'politeia-learning') . '</button>';
            $html .= '</form>';
        } elseif ($has_access && $continue_lesson_url !== '') {
            $html .= '<a class="learni-btn learni-course-primary-btn" href="' . esc_url($continue_lesson_url) . '">' . esc_html__($is_enrolled ? 'CONTINUE' : 'START COURSE', 'politeia-learning') . '</a>';
        } else {
            $html .= '<button type="button" class="learni-btn learni-course-primary-btn" disabled>' . esc_html__('START COURSE', 'politeia-learning') . '</button>';
        }

        // Pending partner invite (place it right under the main CTA).
        if ($has_course_partner && $is_logged_in && !$is_enrolled) {
            global $wpdb;
            $invite = $wpdb ? $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}learni_enrollments
                     WHERE course_post_id = %d AND user_id = %d AND source = %s AND payment_provider = %s AND status = %s
                     LIMIT 1",
                    $course_id,
                    $user_id,
                    \Learni\Database\Enrollments::SOURCE_MANUAL,
                    'partner_invite',
                    'pending'
                )
            ) : null;

            if ($invite) {
                $html .= '<div class="learni-course-partner-invite">';
                $html .= '<p>' . esc_html__('Has sido invitado como Test Partner para este curso.', 'politeia-learning') . '</p>';
                $html .= '<button type="button" class="learni-btn secondary" data-learni-accept-partner-invite="' . esc_attr((string) $course_id) . '">' . esc_html__('ACEPTAR INVITACIÓN', 'politeia-learning') . '</button>';
                $html .= '</div>';
            }
        }

        $html .= '</div>'; // learni-course-cta
        $html .= '</div>'; // learni-course-hero-meta
        $html .= '</div>'; // learni-course-hero-content
        $html .= '</section>';

        $html .= '<div class="learni-course-layout">';
        $html .= '<div class="learni-course-aside">';

        // Enrollment & Progress.
        if ($is_enrolled) {
            $html .= '<div class="learni-course-card progress-card">';
            $html .= '<div class="learni-course-card-head">';
            $html .= '<span class="material-symbols-outlined">analytics</span>';
            $html .= '<h3>' . esc_html__('Your Progress', 'politeia-learning') . '</h3>';
            $html .= '</div>';
            $html .= '<div class="learni-progress-stat">' . sprintf(esc_html__('%d%% Complete', 'politeia-learning'), $percent) . '</div>';
            $html .= '<div class="learni-progress-bar"><div class="learni-progress-bar-fill" style="width:' . esc_attr((string) $percent) . '%"></div></div>';
            $html .= '</div>';
        }

        // Binomial quiz aside controls (ported subset).
        $binomial = $is_logged_in ? PL_Learni_Frontend_Assessment::binomial_course_state($course_id, $user_id, $percent) : [];
        $binomial_quiz_id = (int) ($binomial['quizId'] ?? 0);
        if ($binomial_quiz_id <= 0) {
            $binomial_quiz_id = PL_Learni_Frontend_Assessment::binomial_quiz_id_for_course($course_id);
        }
        if ($binomial_quiz_id > 0) {
            if (is_array($binomial['initial'] ?? null)) {
                $html .= '<div class="learni-course-card binomial-card initial-completed">';
                $html .= '<div class="learni-course-card-head"><span class="material-symbols-outlined">check_circle</span><h3>' . esc_html__('EVALUACIÓN INICIAL', 'politeia-learning') . '</h3></div>';
                $html .= '<div class="learni-binomial-score"><strong>' . (int) ($binomial['initial']['percent'] ?? 0) . '%</strong></div>';
                $html .= '</div>';
            } else {
                $html .= '<div class="learni-course-card binomial-card initial-pending">';
                $html .= '<div class="learni-course-card-head"><span class="material-symbols-outlined">quiz</span><h3>' . esc_html__('EVALUACIÓN INICIAL', 'politeia-learning') . '</h3></div>';
                $html .= '<p>' . esc_html__('Realiza el quiz inicial para medir tu conocimiento base.', 'politeia-learning') . '</p>';
                if ($has_access) {
                    $html .= '<button class="learni-btn secondary full-width" type="button" data-learni-quiz-trigger="' . esc_attr((string) $binomial_quiz_id) . '" data-quiz-phase="initial">' . esc_html__('TOMAR EVALUACIÓN', 'politeia-learning') . '</button>';
                } else {
                    $html .= '<button class="learni-btn secondary full-width" type="button" disabled>' . esc_html__('TOMAR EVALUACIÓN', 'politeia-learning') . '</button>';
                }
                $html .= '</div>';
            }

            if (is_array($binomial['final'] ?? null)) {
                $html .= '<div class="learni-course-card binomial-card final-completed">';
                $html .= '<div class="learni-course-card-head"><span class="material-symbols-outlined">verified</span><h3>' . esc_html__('EVALUACIÓN FINAL', 'politeia-learning') . '</h3></div>';
                $html .= '<div class="learni-binomial-score"><strong>' . (int) ($binomial['final']['percent'] ?? 0) . '%</strong></div>';
                $html .= '</div>';
            } else {
                $html .= '<div class="learni-course-card binomial-card final-pending' . (!empty($binomial['canTakeFinal']) ? ' is-ready' : ' is-locked') . '">';
                $html .= '<div class="learni-course-card-head"><span class="material-symbols-outlined">assignment_turned_in</span><h3>' . esc_html__('EVALUACIÓN FINAL', 'politeia-learning') . '</h3></div>';
                $html .= '<p>' . esc_html__('Disponible al completar el 100% de las lecciones.', 'politeia-learning') . '</p>';
                if (!empty($binomial['canTakeFinal'])) {
                    $html .= '<button class="learni-btn secondary full-width" type="button" data-learni-quiz-trigger="' . esc_attr((string) $binomial_quiz_id) . '" data-quiz-phase="final">' . esc_html__('TOMAR EVALUACIÓN', 'politeia-learning') . '</button>';
                } else {
                    if (!empty($binomial['finalFailed']) && !empty($binomial['cooldownDaysRemaining'])) {
                        $html .= '<div class="learni-binomial-cooldown">' . sprintf(esc_html__('Debes esperar %d días para reintentar.', 'politeia-learning'), (int) $binomial['cooldownDaysRemaining']) . '</div>';
                    }
                    $html .= '<button class="learni-btn secondary full-width" type="button" disabled>' . esc_html__('BLOQUEADO', 'politeia-learning') . '</button>';
                }
                $html .= '</div>';
            }
        }

        // Test Partner card.
        if ($has_course_partner) {
            $show_test_partner = false;
            $other_user_id = 0;
            $is_in_pair = false;
            if ($is_logged_in && $wpdb) {
                $is_in_pair = (bool) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}learni_enrollments WHERE course_post_id = %d AND user_id = %d AND status = %s LIMIT 1", $course_id, $user_id, 'active'));
                if ($is_in_pair) {
                    $other_user_id = ($partner_user_id === $user_id) ? $owner_user_id : $partner_user_id;
                    if ($is_logged_in && $has_access && $is_in_pair && $other_user_id > 0) {
	                    $other_summary = class_exists('\\Learni\\Database\\Progress') ? \Learni\Database\Progress::course_summary($other_user_id, $course_id) : ['percent' => 0];
	                    $other_percent = (int) ($other_summary['percent'] ?? 0);
	                    $other_binomial = PL_Learni_Frontend_Assessment::binomial_course_state($course_id, $other_user_id, $other_percent);
	                    if (!empty($other_binomial['needsFinal']) && $other_percent >= 100 && empty($other_binomial['eligibleFinal'])) {
	                        $show_test_partner = true;
	                        if (empty($other_binomial['canTakeFinal']) || !empty($other_binomial['cooldownDaysRemaining'])) {
	                            $show_test_partner = false;
	                        }
	                    }
                    }
                }
            }

            if ($show_test_partner && $other_user_id > 0) {
                $other_data = get_userdata($other_user_id);
                $other_name = $other_data ? (string) $other_data->display_name : __('compañero', 'politeia-learning');
                $html .= '<div class="learni-course-card partner-card">';
                $html .= '<div class="learni-course-card-head"><span class="material-symbols-outlined">groups</span><h3>' . esc_html__('TEST PARTNER', 'politeia-learning') . '</h3></div>';
                $html .= '<p>' . sprintf(esc_html__('%s ha finalizado sus lecciones y necesita tu evaluación.', 'politeia-learning'), '<strong>' . esc_html($other_name) . '</strong>') . '</p>';
                $html .= '<button class="learni-btn secondary full-width" type="button" data-learni-cross-eval-trigger="' . esc_attr((string) $course_id) . '" data-target-user-id="' . esc_attr((string) $other_user_id) . '">' . esc_html__('EVALUAR AHORA', 'politeia-learning') . '</button>';
                $html .= '</div>';
            }
        }

        // Certificate card.
        if ($certificate_available) {
            $html .= '<div class="learni-course-card certificate-card">';
            $html .= '<div class="learni-course-card-head"><span class="material-symbols-outlined">emoji_events</span><h3>' . esc_html__('CERTIFICADO', 'politeia-learning') . '</h3></div>';
            $html .= '<p>' . esc_html__('¡Felicidades! Has completado el curso y la evaluación final.', 'politeia-learning') . '</p>';
            $html .= '<button class="learni-btn primary full-width" type="button" data-learni-cert-open="' . esc_attr((string) $course_id) . '">' . esc_html__('VER CERTIFICADO', 'politeia-learning') . '</button>';
            $html .= '</div>';
        }

        // Buy card.
        if (!$is_enrolled && !$is_free) {
            $checkout_url = $product_id > 0 ? PL_Learni_Frontend_Actions::checkout_course_url($course_id) : '#';
            $product_url = ($user_id <= 0 && $checkout_url !== '#') ? wp_login_url($checkout_url) : $checkout_url;
            if ($product_url === '' || $product_url === '#') {
                $product_url = $course_permalink;
            }

            $html .= '<div class="learni-course-card purchase-card">';
            $html .= '<div class="learni-course-card-head"><span class="material-symbols-outlined">shopping_cart</span><h3>' . esc_html__('UNIRSE AL CURSO', 'politeia-learning') . '</h3></div>';
            $price_html = class_exists('WooCommerce') && $product_id > 0 ? get_woocommerce_currency_symbol() . ' ' . get_post_meta($product_id, '_price', true) : '';
            if ($price_html !== '') {
                $html .= '<div class="learni-purchase-price">' . esc_html($price_html) . '</div>';
            }
            $html .= '<p>' . esc_html__('Obtén acceso completo a todas las lecciones y tu certificado de finalización.', 'politeia-learning') . '</p>';
            $html .= '<a class="learni-btn primary full-width" href="' . esc_url($product_url) . '">' . esc_html__('COMPRAR AHORA', 'politeia-learning') . '</a>';
            $html .= '</div>';
        }

        $html .= '</div>'; // learni-course-aside

        $html .= '<div class="learni-course-body">';
        $html .= '<section class="learni-course-section">';
        $html .= '<div class="learni-course-section-head"><h2>' . esc_html__('Overview', 'politeia-learning') . '</h2></div>';
        $html .= '<div class="learni-course-content-text">' . apply_filters('the_content', get_the_content(null, false, $course_id)) . '</div>';
        $html .= '</section>';

        $html .= '<section class="learni-course-section">';
        $html .= '<div class="learni-course-section-head"><h2>' . esc_html__('Curriculum', 'politeia-learning') . '</h2></div>';

        if (empty($items)) {
            $html .= '<div class="learni-course-empty">' . esc_html__('No curriculum items found.', 'politeia-learning') . '</div>';
        } else {
            $html .= '<ul class="learni-curriculum-list">';
            foreach ($items as $it) {
                $type = (string) ($it['item_type'] ?? '');
                $ref_id = (int) ($it['item_ref_id'] ?? 0);
                if ($type !== 'lesson' || $ref_id <= 0) {
                    continue;
                }

                $is_completed = isset($completed[$ref_id]);
                $pos = isset($lesson_index[$ref_id]) ? (int) $lesson_index[$ref_id] : -1;
                $is_locked = ($linear_order && $pos > $max_unlocked) && !$has_access;
                $url = get_permalink($ref_id);

                $html .= '<li class="learni-curriculum-item' . ($is_completed ? ' is-completed' : '') . ($is_locked ? ' is-locked' : '') . '">';
                if ($is_locked) {
                    $html .= '<div class="learni-curriculum-link">';
                } else {
                    $html .= '<a class="learni-curriculum-link" href="' . esc_url((string) $url) . '">';
                }

                $html .= '<span class="material-symbols-outlined learni-curriculum-icon">' . ($is_completed ? 'check_circle' : ($is_locked ? 'lock' : 'play_circle')) . '</span>';
                $html .= '<span class="learni-curriculum-title">' . esc_html(get_the_title($ref_id)) . '</span>';

                if ($is_locked) {
                    $html .= '</div>';
                } else {
                    $html .= '</a>';
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
