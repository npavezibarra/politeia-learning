<?php

namespace Learni\Rest;

use Learni\Access\Access;
use Learni\Database\Progress;
use Learni\PostTypes\Course;
use WP_Error;
use WP_REST_Request;

if (!defined('ABSPATH')) {
    exit;
}

final class Certificates
{
    public static function get_course_certificate(WP_REST_Request $request)
    {
        $course_id = (int) $request['id'];
        if ($course_id <= 0 || get_post_type($course_id) !== Course::POST_TYPE) {
            return new WP_Error('learni_invalid_course', 'Invalid course.', ['status' => 404]);
        }

        $user_id = (int) get_current_user_id();
        if ($user_id <= 0) {
            return new WP_Error('learni_login_required', 'Login required.', ['status' => 401]);
        }

        if (!Access::user_can_access_course($user_id, $course_id)) {
            return new WP_Error('learni_forbidden', 'No access.', ['status' => 403]);
        }

        if (!self::template_exists($course_id)) {
            return new WP_Error('learni_no_certificate', 'No certificate template.', ['status' => 404]);
        }

        $eligible = Routes::course_certificate_eligible($course_id, $user_id);

        $title = (string) get_post_meta($course_id, Course::META_CERTIFICATE_TITLE, true);
        $title = $title !== '' ? $title : __('Certificado de Finalización', 'politeia-learning');

        $paragraph = (string) get_post_meta($course_id, Course::META_CERTIFICATE_CONGRATS, true);
        $issued_label = wp_date(get_option('date_format'));
        $paragraph = self::paragraph_with_replacements($paragraph, $course_id, $user_id, $issued_label);

        $logo_id = (int) get_post_meta($course_id, Course::META_CERTIFICATE_LOGO_ATTACHMENT_ID, true);
        $logo_url = $logo_id > 0 ? (string) wp_get_attachment_image_url($logo_id, 'medium') : '';
        $logo_align = (string) get_post_meta($course_id, Course::META_CERTIFICATE_LOGO_ALIGN, true);
        $logo_align = in_array($logo_align, ['left', 'center', 'right'], true) ? $logo_align : 'left';

        $sig_id = (int) get_post_meta($course_id, Course::META_CERTIFICATE_SIGNATURE_ATTACHMENT_ID, true);
        $sig_url = $sig_id > 0 ? (string) wp_get_attachment_image_url($sig_id, 'medium') : '';
        $sig_label = (string) get_post_meta($course_id, Course::META_CERTIFICATE_SIGNATURE_LABEL, true);
        $sig_label = $sig_label !== '' ? $sig_label : __('Firma', 'politeia-learning');

        $first_pct = null;
        $final_pct = null;
        $variation = null;

        global $wpdb;
        if ($wpdb) {
            $quiz = Binomial::quiz_for_course($course_id);
            $quiz_id = (int) ($quiz['id'] ?? 0);
            if ($quiz_id > 0) {
                $gate = Binomial::gate_state_for_user($course_id, $quiz_id, $user_id);
                $initial = is_array($gate['initial']) ? $gate['initial'] : null;
                $final = is_array($gate['latestFinal']) ? $gate['latestFinal'] : null;

                if (is_array($initial) && isset($initial['percent'])) {
                    $first_pct = (int) $initial['percent'];
                }
                if (is_array($final) && isset($final['percent'])) {
                    $final_pct = (int) $final['percent'];
                }
                if (is_int($first_pct) && is_int($final_pct)) {
                    $variation = (int) $final_pct - (int) $first_pct;
                }
            }
        }

        $show_first = (bool) get_post_meta($course_id, Course::META_CERTIFICATE_CLAIM_FIRST, true);
        $show_final = (bool) get_post_meta($course_id, Course::META_CERTIFICATE_CLAIM_FINAL, true);
        $show_variation = (bool) get_post_meta($course_id, Course::META_CERTIFICATE_CLAIM_VARIATION, true);

        $claims = [];
        if ($show_first && is_int($first_pct)) {
            $claims[] = esc_html(sprintf(__('First quiz score: %d%%', 'politeia-learning'), $first_pct));
        }
        if ($show_final && is_int($final_pct)) {
            $claims[] = esc_html(sprintf(__('Final quiz score: %d%%', 'politeia-learning'), $final_pct));
        }
        if ($show_variation && is_int($variation)) {
            $sign = $variation > 0 ? '+' : '';
            $claims[] = esc_html(sprintf(__('Variation: %s%d%%', 'politeia-learning'), $sign, $variation));
        }

        $student_name = '';
        if ($user_id > 0) {
            $u = get_userdata($user_id);
            if ($u) {
                $student_name = (string) ($u->display_name ?? '');
            }
        }
        $student_name = $student_name !== '' ? $student_name : __('Student', 'politeia-learning');
        $course_title = (string) get_the_title($course_id);

        $sheet = '<div class="learni-cert-stage">';
        $sheet .= '<div class="learni-cert-sheet" data-learni-cert-sheet="1">';
        $sheet .= '<div class="learni-cert-sheet__inner">';
        $sheet .= '<div class="learni-cert-sheet__top learni-align-' . esc_attr($logo_align) . '">';
        if ($logo_url !== '') {
            $sheet .= '<img class="learni-cert-sheet__logo" src="' . esc_url($logo_url) . '" alt="" loading="lazy" />';
        }
        $sheet .= '</div>';
        $sheet .= '<div class="learni-cert-sheet__title">' . esc_html($title) . '</div>';
        $sheet .= '<div class="learni-cert-sheet__kicker">' . esc_html__('This certifies that', 'politeia-learning') . '</div>';
        $sheet .= '<div class="learni-cert-sheet__name">' . esc_html($student_name) . '</div>';
        $sheet .= '<div class="learni-cert-sheet__kicker">' . esc_html__('has successfully completed', 'politeia-learning') . '</div>';
        $sheet .= '<div class="learni-cert-sheet__course">' . esc_html($course_title) . '</div>';
        if ($paragraph !== '') {
            $sheet .= '<div class="learni-cert-sheet__paragraph">' . wp_kses_post($paragraph) . '</div>';
        }
        if (!empty($claims)) {
            $sheet .= '<div class="learni-cert-sheet__claims">';
            $sheet .= '<div class="learni-cert-sheet__claims-title">' . esc_html__('Assessment claims', 'politeia-learning') . '</div>';
            foreach ($claims as $c) {
                $sheet .= '<div class="learni-cert-sheet__claim">' . $c . '</div>';
            }
            $sheet .= '</div>';
        }

        $sheet .= '<div class="learni-cert-sheet__bottom">';
        $sheet .= '<div class="learni-cert-sheet__meta">';
        $sheet .= '<div class="learni-cert-sheet__meta-row"><span class="learni-cert-sheet__meta-label">' . esc_html__('Issued', 'politeia-learning') . '</span><span class="learni-cert-sheet__meta-value">' . esc_html($issued_label) . '</span></div>';
        $sheet .= '</div>';
        $sheet .= '<div class="learni-cert-sheet__sig">';
        if ($sig_url !== '') {
            $sheet .= '<img class="learni-cert-sheet__sigimg" src="' . esc_url($sig_url) . '" alt="" loading="lazy" />';
        }
        $sheet .= '<div class="learni-cert-sheet__sigline"></div>';
        $sheet .= '<div class="learni-cert-sheet__siglabel">' . esc_html($sig_label) . '</div>';
        $sheet .= '</div>';
        $sheet .= '</div>'; // bottom

        $sheet .= '</div>'; // inner
        $sheet .= '</div>'; // sheet
        $sheet .= '</div>'; // stage

        return [
            'courseId' => $course_id,
            'eligible' => $eligible,
            'html' => $sheet,
        ];
    }

    public static function template_exists(int $course_id): bool
    {
        if ($course_id <= 0) {
            return false;
        }

        $title = (string) get_post_meta($course_id, Course::META_CERTIFICATE_TITLE, true);
        $paragraph = (string) get_post_meta($course_id, Course::META_CERTIFICATE_CONGRATS, true);
        $logo_id = (int) get_post_meta($course_id, Course::META_CERTIFICATE_LOGO_ATTACHMENT_ID, true);
        $sig_id = (int) get_post_meta($course_id, Course::META_CERTIFICATE_SIGNATURE_ATTACHMENT_ID, true);
        $show_first = (bool) get_post_meta($course_id, Course::META_CERTIFICATE_CLAIM_FIRST, true);
        $show_final = (bool) get_post_meta($course_id, Course::META_CERTIFICATE_CLAIM_FINAL, true);
        $show_variation = (bool) get_post_meta($course_id, Course::META_CERTIFICATE_CLAIM_VARIATION, true);

        return ($title !== '') || ($paragraph !== '') || ($logo_id > 0) || ($sig_id > 0) || $show_first || $show_final || $show_variation;
    }

    public static function paragraph_with_replacements(string $paragraph, int $course_id, int $user_id, string $issued_label): string
    {
        if ($paragraph === '') {
            return '';
        }

        $student_name = '';
        $first_name = '';
        if ($user_id > 0) {
            $u = get_userdata($user_id);
            if ($u) {
                $first_name = (string) ($u->first_name ?? '');
                $student_name = (string) ($u->display_name ?? '');
            }
        }
        if ($student_name === '') {
            $student_name = __('Student', 'politeia-learning');
        }
        if ($first_name === '') {
            $first_name = $student_name;
        }

        $course_title = $course_id > 0 ? (string) get_the_title($course_id) : '';
        $date_start = '';
        if ($course_id > 0 && $user_id > 0) {
            global $wpdb;
            if ($wpdb) {
                $started_at = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT started_at FROM {$wpdb->prefix}learni_enrollments WHERE user_id = %d AND course_post_id = %d LIMIT 1",
                        $user_id,
                        $course_id
                    )
                );
                if (is_string($started_at) && $started_at !== '') {
                    $date_start = wp_date(get_option('date_format'), strtotime($started_at));
                }
            }
        }

        $replacements = [
            '[display_full_name]' => $student_name,
            '[first_name]' => $first_name,
            '[course_name]' => $course_title,
            '[date_start]' => $date_start,
            '[date_end]' => $issued_label,
            '{{display_full_name}}' => $student_name,
            '{{first_name}}' => $first_name,
            '{{course_name}}' => $course_title,
            '{{date_start}}' => $date_start,
            '{{date_end}}' => $issued_label,
        ];

        foreach ($replacements as $key => $val) {
            $paragraph = str_replace($key, (string) $val, $paragraph);
        }

        return $paragraph;
    }
}
