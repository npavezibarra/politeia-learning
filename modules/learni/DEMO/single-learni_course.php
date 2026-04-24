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
$has_access = ($user_id > 0) && Access::user_can_access_course($user_id, $course_id);
$is_enrolled = ($user_id > 0) && class_exists('\\Learni\\Database\\Enrollments') && \Learni\Database\Enrollments::user_has_active($user_id, $course_id);
$can_manage_partner = ($user_id > 0) && (current_user_can('manage_options') || (class_exists('\\Learni\\Database\\Enrollments') && method_exists('\\Learni\\Database\\Enrollments', 'user_is_owner') && \Learni\Database\Enrollments::user_is_owner($user_id, $course_id)));

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

$items = Outline::get_items($course_id);
$completed = $has_access ? array_flip(Progress::completed_lesson_ids($user_id, $course_id)) : [];
$summary = $has_access ? Progress::course_summary($user_id, $course_id) : ['total' => count(Outline::lesson_ids($course_id)), 'completed' => 0, 'percent' => 0];
$certificate_template_title = (string) get_post_meta($course_id, \Learni\PostTypes\Course::META_CERTIFICATE_TITLE, true);
$certificate_template_paragraph = (string) get_post_meta($course_id, \Learni\PostTypes\Course::META_CERTIFICATE_CONGRATS, true);
$certificate_logo_id = (int) get_post_meta($course_id, \Learni\PostTypes\Course::META_CERTIFICATE_LOGO_ATTACHMENT_ID, true);
$certificate_sig_id = (int) get_post_meta($course_id, \Learni\PostTypes\Course::META_CERTIFICATE_SIGNATURE_ATTACHMENT_ID, true);
$certificate_has_template = ($certificate_template_title !== '') || ($certificate_template_paragraph !== '') || ($certificate_logo_id > 0) || ($certificate_sig_id > 0);
$certificate_available = $certificate_has_template && ((int) ($summary['percent'] ?? 0) >= 100);
$has_course_partner = false;
if (class_exists('PL_Partnerships_Repository') && method_exists('PL_Partnerships_Repository', 'get_single_partner')) {
    try {
        $p = PL_Partnerships_Repository::get_single_partner('course', $course_id, 'partner');
        $has_course_partner = is_array($p) && !empty($p['partner_user_id']);
    } catch (\Throwable $e) {
        $has_course_partner = false;
    }
}

// Hide the main progress bar when a course partner is accepted; the competitive comparison lives in the aside.
if (!$has_course_partner) {
    echo '<div class="learni-progress">';
    echo '<span>' . esc_html(sprintf(__('%1$d/%2$d complete', 'politeia-learning'), (int) $summary['completed'], (int) $summary['total'])) . '</span>';
    echo '<span class="learni-progress-bar" role="progressbar" aria-valuenow="' . esc_attr((string) $summary['percent']) . '" aria-valuemin="0" aria-valuemax="100">';
    echo '<span style="width:' . esc_attr((string) $summary['percent']) . '%"></span>';
    echo '</span>';
    echo '</div>';
}

// Course partner UI (Learni course pages): show info to enrolled users; only owner/admin can manage partners.
if ($user_id > 0 && $is_enrolled) {
    $partner = null;
    if (class_exists('PL_Partnerships_Repository') && method_exists('PL_Partnerships_Repository', 'get_single_partner')) {
        try {
            $partner = PL_Partnerships_Repository::get_single_partner('course', $course_id, 'partner');
        } catch (\Throwable $e) {
            $partner = null;
        }
    }

    echo '<div class="pl-course-partner-block" style="margin:14px 0 18px;">';

    if (is_array($partner) && !empty($partner['partner_user_id'])) {
        $partner_user_id = (int) $partner['partner_user_id'];
        $partner_user = $partner_user_id > 0 ? get_userdata($partner_user_id) : null;
        $partner_name = ($partner_user instanceof \WP_User) ? (string) $partner_user->display_name : '';
        $owner_user_id = (is_array($partner) && !empty($partner['owner_user_id'])) ? (int) $partner['owner_user_id'] : 0;
        $other_user_id = ($partner_user_id > 0) ? (($partner_user_id === $user_id) ? $owner_user_id : $partner_user_id) : 0;
        if ($partner_user_id > 0 && $partner_user_id === $user_id) {
            $can_manage_partner = false;
        }

        if (class_exists('\\Learni\\Database\\Progress')) {
            $progress_users = array_values(array_unique(array_filter(array_map('absint', [$other_user_id, $user_id]))));

            if (!empty($progress_users)) {
                echo '<div class="learni-course-partner-title-row">';
                echo '<div class="learni-course-partner-title">' . esc_html__('Partner', 'politeia-learning') . '</div>';
                if ($can_manage_partner && $partner_user_id > 0) {
                    echo '<button type="button" class="pl-partner-remove learni-course-partner-remove" data-object-type="course" data-object-id="' . esc_attr((string) $course_id) . '" data-user-id="' . esc_attr((string) $partner_user_id) . '" aria-label="' . esc_attr__('Eliminar partner', 'politeia-learning') . '" title="' . esc_attr__('Eliminar partner', 'politeia-learning') . '">×</button>';
                }
                echo '</div>';

                echo '<div class="learni-course-partner-progress" style="margin:0 0 12px;">';
                foreach ($progress_users as $pid) {
                    $u = get_userdata($pid);
                    $display = ($u instanceof \WP_User) ? (string) $u->display_name : (string) $pid;
                    $avatar = function_exists('get_avatar_url') ? (string) get_avatar_url((int) $pid, ['size' => 48]) : '';
                    $p_summary = \Learni\Database\Progress::course_summary($pid, $course_id);
                    $p_percent = (int) ($p_summary['percent'] ?? 0);
                    if ($p_percent < 0) {
                        $p_percent = 0;
                    } elseif ($p_percent > 100) {
                        $p_percent = 100;
                    }

                    echo '<div class="learni-course-partner-progress-item" style="margin-bottom:10px;">';
                    echo '<div class="learni-course-partner-progress-head" style="display:flex;align-items:center;justify-content:space-between;gap:8px;">';
                    echo '<div class="learni-course-partner-progress-user" style="display:flex;align-items:center;gap:8px;min-width:0;">';
                    if ($avatar !== '') {
                        echo '<img class="learni-course-partner-progress-avatar" src="' . esc_url($avatar) . '" alt="" style="width:22px;height:22px;border-radius:999px;object-fit:cover;border:1px solid rgba(226, 232, 240, 0.95);background:#fff;flex:0 0 auto;">';
                    }
                    echo '<span class="learni-course-partner-progress-name" style="font-size:12px;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' . esc_html($display) . '</span>';
                    echo '</div>';
                    echo '<span class="learni-course-partner-progress-percent" style="font-size:11px;font-weight:800;opacity:.7;flex:0 0 auto;">' . esc_html((string) $p_percent) . '%</span>';
                    echo '</div>';
                    echo '<div class="learni-course-partner-progress-bar" role="progressbar" aria-valuenow="' . esc_attr((string) $p_percent) . '" aria-valuemin="0" aria-valuemax="100" style="height:8px;border-radius:999px;background:rgba(15, 23, 42, 0.06);overflow:hidden;border:1px solid rgba(15, 23, 42, 0.08);">';
                    echo '<span class="learni-course-partner-progress-fill" style="display:block;height:100%;border-radius:999px;background:#000;width:' . esc_attr((string) $p_percent) . '%;"></span>';
                    echo '</div>';
                    echo '</div>';
                }
                echo '</div>';
            }
        }
    }

    $pending_invite = function_exists('pl_get_pending_course_partner_invite') ? pl_get_pending_course_partner_invite((int) $course_id) : null;
    if (is_array($pending_invite) && !empty($pending_invite['label'])) {
        echo '<div class="pl-course-partner-pending" style="font-size:13px;color:#b45309;margin-bottom:8px;">' . esc_html(sprintf(__('Esperando a %s', 'politeia-learning'), (string) $pending_invite['label'])) . '</div>';
    }

    if ($can_manage_partner) {
        echo '<button id="pl-add-partner-btn-' . esc_attr((string) $course_id) . '" type="button" class="pl-add-partner addPartnerBtn" style="display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border-radius:999px;border:1px solid #000;background:#fff;color:#000;font-weight:800;letter-spacing:1px;text-transform:uppercase;font-size:11px;cursor:pointer;">';
        echo '<span class="material-symbols-outlined learni-ms-icon" aria-hidden="true">person_add</span>';
        echo '<span>' . esc_html(is_array($partner) && !empty($partner) ? __('Replace Partner', 'politeia-learning') : __('Add Partner', 'politeia-learning')) . '</span>';
        echo '</button>';
    }

    echo '</div>';
}

if ($certificate_available) {
    echo '<p><button id="learni-course-cert-trigger" class="learni-course-cert-trigger" type="button" aria-label="' . esc_attr__('View certificate', 'politeia-learning') . '" data-learni-cert-open="1" data-course-id="' . esc_attr((string) $course_id) . '">';
    echo '<span class="material-symbols-outlined learni-ms-icon learni-course-cert-icon" aria-hidden="true">history_edu</span>';
    echo '<span class="learni-course-cert-text">' . esc_html__('CERTIFICADO', 'politeia-learning') . '</span>';
    echo '</button></p>';
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
        if ($url !== '' && $has_access) {
            echo '<a href="' . esc_url($url) . '">';
        }
        echo '<span class="learni-check" aria-hidden="true">' . ($done ? '✓' : '•') . '</span>';
        echo '<span class="learni-label">' . esc_html($label) . '</span>';
        if ($url !== '' && $has_access) {
            echo '</a>';
        }
        echo '</li>';
    }
    echo '</ul>';
}

echo '</section>';
echo '</main>';

if ($certificate_available) {
    echo \PL_Learni_Frontend_Templates::render_certificate_modal_html($course_id, $user_id);
}

get_footer();
