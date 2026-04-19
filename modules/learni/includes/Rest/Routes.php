<?php

namespace Learni\Rest;

use Learni\Access\Access;
use Learni\Database\Progress;
use Learni\Database\Enrollments;
use Learni\PostTypes\Course;
use WP_Error;
use WP_REST_Request;

final class Routes
{
    public const REST_NAMESPACE = 'learni/v1';
    private const FINAL_RETRY_COOLDOWN_DAYS = 7;

    /**
     * Return submitted binomial attempts for a user with a best-effort phase.
     *
     * New attempts store `answers_json.phase` ("initial"|"final"). For legacy attempts
     * without phase, we infer it from the historical ordering (odd=initial, even=final).
     *
     * @return array<int,array{attemptId:int,score:int,total:int,percent:int,submittedAt:string,phase:string}>
     */
    private static function binomial_attempt_series_for_user(int $quiz_id, int $user_id): array
    {
        global $wpdb;
        if ($quiz_id <= 0 || $user_id <= 0 || !$wpdb) {
            return [];
        }

        $attempts_table = $wpdb->prefix . 'learni_quiz_attempts';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, score, submitted_at, answers_json
                 FROM {$attempts_table}
                 WHERE quiz_id = %d AND user_id = %d AND status = %s
                 ORDER BY submitted_at ASC, id ASC
                 LIMIT 200",
                $quiz_id,
                $user_id,
                'submitted'
            ),
            ARRAY_A
        );

        $series = [];
        $idx = 0;
        foreach ((array) $rows as $r) {
            if (!is_array($r)) {
                continue;
            }
            $idx++;
            $payload = [];
            if (!empty($r['answers_json'])) {
                $decoded = json_decode((string) $r['answers_json'], true);
                if (is_array($decoded)) {
                    $payload = $decoded;
                }
            }

            $phase = '';
            if (isset($payload['phase']) && is_string($payload['phase'])) {
                $p = sanitize_key($payload['phase']);
                if (in_array($p, ['initial', 'final'], true)) {
                    $phase = $p;
                }
            }
            if ($phase === '') {
                $phase = ($idx % 2 === 1) ? 'initial' : 'final';
            }

            $row_payload = self::attempt_public_payload($r);
            $row_payload['phase'] = $phase;
            $series[] = $row_payload;
        }

        return $series;
    }

    /**
     * Compute certificate/cooldown gate state for a user.
     *
     * @return array{
     *   initial:?array,
     *   latestFinal:?array,
     *   eligibleFinal:bool,
     *   finalFailed:bool,
     *   cooldownUntil:string,
     *   cooldownDaysRemaining:int,
     *   canTakeFinalNow:bool
     * }
     */
    private static function binomial_gate_state_for_user(int $course_id, int $quiz_id, int $user_id): array
    {
        $out = [
            'initial' => null,
            'latestFinal' => null,
            'eligibleFinal' => false,
            'finalFailed' => false,
            'cooldownUntil' => '',
            'cooldownDaysRemaining' => 0,
            'canTakeFinalNow' => false,
        ];

        if ($course_id <= 0 || $quiz_id <= 0 || $user_id <= 0) {
            return $out;
        }

        $series = self::binomial_attempt_series_for_user($quiz_id, $user_id);
        if (empty($series)) {
            return $out;
        }

        // Treat the most recent "initial" as the baseline for the current cycle, and
        // consider only finals submitted after that baseline.
        $initial = null;
        $finals_after_initial = [];
        foreach ($series as $a) {
            if (!is_array($a)) {
                continue;
            }
            $phase = (string) ($a['phase'] ?? '');
            if ($phase === 'initial') {
                $initial = $a;
                $finals_after_initial = [];
                continue;
            }
            if ($phase === 'final' && is_array($initial)) {
                $finals_after_initial[] = $a;
            }
        }

        $latest_final = !empty($finals_after_initial) ? $finals_after_initial[count($finals_after_initial) - 1] : null;

        $out['initial'] = $initial;
        $out['latestFinal'] = $latest_final;

        $baseline = is_array($initial) ? (int) ($initial['percent'] ?? 0) : null;
        if ($baseline !== null) {
            foreach ($finals_after_initial as $f) {
                $fp = (int) ($f['percent'] ?? 0);
                if ($fp >= $baseline) {
                    $out['eligibleFinal'] = true;
                    break;
                }
            }
        }

        if (!$out['eligibleFinal'] && is_array($latest_final) && $baseline !== null) {
            $fp = (int) ($latest_final['percent'] ?? 0);
            if ($fp < $baseline) {
                $out['finalFailed'] = true;
                $submitted_at = (string) ($latest_final['submittedAt'] ?? '');
                $ts = $submitted_at !== '' ? (int) strtotime($submitted_at) : 0;
                if ($ts > 0) {
                    $cool_ts = $ts + (self::FINAL_RETRY_COOLDOWN_DAYS * 86400);
                    $out['cooldownUntil'] = date('Y-m-d H:i:s', $cool_ts);
                    $now = (int) current_time('timestamp');
                    $diff = $cool_ts - $now;
                    if ($diff > 0) {
                        $out['cooldownDaysRemaining'] = (int) max(1, (int) ceil($diff / 86400));
                    }
                }
            }
        }

        // Allow final now only if:
        // - baseline exists
        // - user completed lessons & can access
        // - certificate is not already eligible
        // - cooldown expired (or never triggered)
        if ($baseline !== null) {
            $summary = Progress::course_summary($user_id, $course_id);
            $lesson_percent = isset($summary['percent']) ? (int) $summary['percent'] : 0;
            if ($lesson_percent >= 100 && Access::user_can_access_course($user_id, $course_id) && !$out['eligibleFinal']) {
                $out['canTakeFinalNow'] = ($out['cooldownDaysRemaining'] <= 0);
            }
        }

        return $out;
    }

    /**
     * Return the active (owner, partner) user ids for a course.
     *
     * Owner can be missing in the partnership row for legacy data; in that case we
     * infer it from the enrollments table (first non-partner_invite active enrollment).
     *
     * @return array{owner:int,partner:int}
     */
    private static function course_partner_users(int $course_id): array
    {
        $out = ['owner' => 0, 'partner' => 0];
        if ($course_id <= 0) {
            return $out;
        }

        if (!class_exists('PL_Partnerships_Repository') || !method_exists('PL_Partnerships_Repository', 'get_single_partner')) {
            return $out;
        }

        $partner = null;
        try {
            $partner = \PL_Partnerships_Repository::get_single_partner('course', $course_id, 'partner');
        } catch (\Throwable $e) {
            $partner = null;
        }

        if (!is_array($partner) || empty($partner['partner_user_id'])) {
            return $out;
        }

        $out['partner'] = (int) ($partner['partner_user_id'] ?? 0);
        $out['owner'] = (int) ($partner['owner_user_id'] ?? 0);

        if ($out['owner'] <= 0 && $out['partner'] > 0) {
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
                        $out['owner'] = $candidate_id;
                        break;
                    }
                }
            }
        }

        return $out;
    }

    public static function register(): void
    {
        register_rest_route(
            self::REST_NAMESPACE,
            '/courses/(?P<id>\\d+)/binomial',
            [
                'methods' => 'GET',
                'permission_callback' => static function () {
                    return is_user_logged_in();
                },
                'callback' => [__CLASS__, 'get_course_binomial_status'],
            ]
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/courses/(?P<id>\\d+)/binomial/start',
            [
                'methods' => 'POST',
                'permission_callback' => static function () {
                    return is_user_logged_in();
                },
                'callback' => [__CLASS__, 'post_course_binomial_start'],
                'args' => [
                    'phase' => [
                        'type' => 'string',
                        'required' => true,
                        'sanitize_callback' => static function ($value) {
                            $value = is_string($value) ? sanitize_key($value) : '';
                            return in_array($value, ['initial', 'final'], true) ? $value : 'initial';
                        },
                    ],
                ],
            ]
        );

        // Cross evaluation ("Test Partner") handshake.
        register_rest_route(
            self::REST_NAMESPACE,
            '/courses/(?P<id>\\d+)/cross-eval/create',
            [
                'methods' => 'POST',
                'permission_callback' => static function () {
                    return is_user_logged_in();
                },
                'callback' => [__CLASS__, 'post_cross_eval_create'],
            ]
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/cross-eval/pending',
            [
                'methods' => 'GET',
                'permission_callback' => static function () {
                    return is_user_logged_in();
                },
                'callback' => [__CLASS__, 'get_cross_eval_pending'],
            ]
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/cross-eval/sessions/(?P<id>\\d+)',
            [
                'methods' => 'GET',
                'permission_callback' => static function () {
                    return is_user_logged_in();
                },
                'callback' => [__CLASS__, 'get_cross_eval_session'],
            ]
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/cross-eval/sessions/(?P<id>\\d+)/respond',
            [
                'methods' => 'POST',
                'permission_callback' => static function () {
                    return is_user_logged_in();
                },
                'callback' => [__CLASS__, 'post_cross_eval_respond'],
                'args' => [
                    'decision' => [
                        'type' => 'string',
                        'required' => true,
                        'sanitize_callback' => static function ($value) {
                            $value = is_string($value) ? sanitize_key($value) : '';
                            return in_array($value, ['accept', 'decline'], true) ? $value : 'decline';
                        },
                    ],
                ],
            ]
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/cross-eval/sessions/(?P<id>\\d+)/cancel',
            [
                'methods' => 'POST',
                'permission_callback' => static function () {
                    return is_user_logged_in();
                },
                'callback' => [__CLASS__, 'post_cross_eval_cancel'],
            ]
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/courses/(?P<id>\\d+)/cross-eval/binomial/start',
            [
                'methods' => 'POST',
                'permission_callback' => static function () {
                    return is_user_logged_in();
                },
                'callback' => [__CLASS__, 'post_cross_eval_binomial_start'],
                'args' => [
                    'sessionId' => [
                        'type' => 'integer',
                        'required' => true,
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ]
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/attempts/(?P<id>\\d+)/submit',
            [
                'methods' => 'POST',
                'permission_callback' => static function () {
                    return is_user_logged_in();
                },
                'callback' => [__CLASS__, 'post_attempt_submit'],
                'args' => [
                    'answers' => [
                        'type' => 'object',
                        'required' => true,
                    ],
                ],
            ]
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/attempts/(?P<id>\\d+)/cross-eval/submit',
            [
                'methods' => 'POST',
                'permission_callback' => static function () {
                    return is_user_logged_in();
                },
                'callback' => [__CLASS__, 'post_cross_eval_attempt_submit'],
                'args' => [
                    'sessionId' => [
                        'type' => 'integer',
                        'required' => true,
                        'sanitize_callback' => 'absint',
                    ],
                    'answers' => [
                        'type' => 'object',
                        'required' => true,
                    ],
                ],
            ]
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/courses/(?P<id>\\d+)/restart',
            [
                'methods' => 'POST',
                'permission_callback' => static function () {
                    return is_user_logged_in();
                },
                'callback' => [__CLASS__, 'post_course_restart'],
            ]
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/courses/(?P<id>\\d+)/certificate',
            [
                'methods' => 'GET',
                'permission_callback' => static function () {
                    return is_user_logged_in();
                },
                'callback' => [__CLASS__, 'get_course_certificate'],
            ]
        );
    }

    public static function get_course_binomial_status(WP_REST_Request $request)
    {
        global $wpdb;

        $course_id = (int) $request['id'];
        if ($course_id <= 0 || get_post_type($course_id) !== Course::POST_TYPE) {
            return new WP_Error('learni_invalid_course', 'Invalid course.', ['status' => 404]);
        }

        $user_id = (int) get_current_user_id();
        if ($user_id <= 0) {
            return new WP_Error('learni_login_required', 'Login required.', ['status' => 401]);
        }

        $partner_info = self::course_partner_info($course_id);

        $quiz = self::binomial_quiz_for_course($course_id);
        $quiz_id = (int) ($quiz['id'] ?? 0);

        $summary = Progress::course_summary($user_id, $course_id);
        $lesson_percent = isset($summary['percent']) ? (int) $summary['percent'] : 0;

        $certificate_available = self::course_certificate_eligible($course_id, $user_id);

        if ($quiz_id <= 0) {
            return [
                'courseId' => $course_id,
                'quizId' => 0,
                'partner' => $partner_info,
                'certificateAvailable' => $certificate_available,
                'progress' => [
                    'lessonsPercent' => $lesson_percent,
                ],
                'attempts' => [
                    'submittedCount' => 0,
                    'initial' => null,
                    'final' => null,
                ],
                'ui' => [
                    'needsInitial' => false,
                    'needsFinal' => false,
                    'canTakeFinal' => false,
                    'canRestart' => false,
                ],
            ];
        }

        $gate = self::binomial_gate_state_for_user($course_id, $quiz_id, $user_id);
        $initial = is_array($gate['initial']) ? $gate['initial'] : null;
        $final = is_array($gate['latestFinal']) ? $gate['latestFinal'] : null;

        $submitted_count = 0;
        $series = self::binomial_attempt_series_for_user($quiz_id, $user_id);
        $submitted_count = count($series);

        $needs_initial = !is_array($initial);
        $needs_final = is_array($initial) && !$gate['eligibleFinal'];
        $can_take_final = $needs_final && $lesson_percent >= 100 && (bool) ($gate['canTakeFinalNow'] ?? false);
        $can_restart = (bool) ($gate['eligibleFinal'] ?? false) && $lesson_percent >= 100 && Access::user_can_access_course($user_id, $course_id);

        // For partner courses, compute "other user" eligibility for triggering the test.
        if (!empty($partner_info['hasPartner']) && !empty($partner_info['otherUserId'])) {
            $other_user_id = (int) ($partner_info['otherUserId'] ?? 0);
            $other_lessons = (int) ($partner_info['otherLessonsPercent'] ?? 0);
            if ($other_user_id > 0) {
                $other_gate = self::binomial_gate_state_for_user($course_id, $quiz_id, $other_user_id);
                $other_initial = is_array($other_gate['initial']) ? $other_gate['initial'] : null;
                $other_final = is_array($other_gate['latestFinal']) ? $other_gate['latestFinal'] : null;

                $partner_info['otherSubmittedCount'] = count(self::binomial_attempt_series_for_user($quiz_id, $other_user_id));
                $partner_info['otherNeedsFinal'] = is_array($other_initial) && !(bool) ($other_gate['eligibleFinal'] ?? false);
                $partner_info['otherCanTakeFinal'] = (bool) ($other_gate['canTakeFinalNow'] ?? false) && $other_lessons >= 100 && Access::user_can_access_course($other_user_id, $course_id);
                $partner_info['otherFinalEligible'] = (bool) ($other_gate['eligibleFinal'] ?? false);
                $partner_info['otherFinalFailed'] = (bool) ($other_gate['finalFailed'] ?? false);
                $partner_info['otherFinalCooldownUntil'] = (string) ($other_gate['cooldownUntil'] ?? '');
                $partner_info['otherFinalCooldownDaysRemaining'] = (int) ($other_gate['cooldownDaysRemaining'] ?? 0);

                $partner_info['otherAttempts'] = [
                    'initial' => $other_initial,
                    'final' => $other_final,
                ];
            }
        }

        return [
            'courseId' => $course_id,
            'quizId' => $quiz_id,
            'partner' => $partner_info,
            'certificateAvailable' => $certificate_available,
            'progress' => [
                'lessonsPercent' => $lesson_percent,
            ],
            'attempts' => [
                'submittedCount' => $submitted_count,
                'initial' => $initial,
                'final' => $final,
            ],
            'ui' => [
                'needsInitial' => $needs_initial,
                'needsFinal' => $needs_final,
                'canTakeFinal' => $can_take_final,
                'canRestart' => $can_restart,
                'finalEligible' => (bool) ($gate['eligibleFinal'] ?? false),
                'finalFailed' => (bool) ($gate['finalFailed'] ?? false),
                'finalCooldownUntil' => (string) ($gate['cooldownUntil'] ?? ''),
                'finalCooldownDaysRemaining' => (int) ($gate['cooldownDaysRemaining'] ?? 0),
            ],
        ];
    }

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

        if (!self::certificate_template_exists($course_id)) {
            return new WP_Error('learni_no_certificate', 'No certificate template.', ['status' => 404]);
        }

        $summary = Progress::course_summary($user_id, $course_id);
        $percent = isset($summary['percent']) ? (int) $summary['percent'] : 0;
        $eligible = self::course_certificate_eligible($course_id, $user_id);

        $title = (string) get_post_meta($course_id, Course::META_CERTIFICATE_TITLE, true);
        $title = $title !== '' ? $title : __('Certificado de Finalización', 'politeia-learning');

        $paragraph = (string) get_post_meta($course_id, Course::META_CERTIFICATE_CONGRATS, true);
        $issued_label = wp_date(get_option('date_format'));
        $paragraph = self::certificate_paragraph_with_replacements($paragraph, $course_id, $user_id, $issued_label);

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
            $quiz = self::binomial_quiz_for_course($course_id);
            $quiz_id = (int) ($quiz['id'] ?? 0);
            if ($quiz_id > 0) {
                $gate = self::binomial_gate_state_for_user($course_id, $quiz_id, $user_id);
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

    public static function post_course_binomial_start(WP_REST_Request $request)
    {
        global $wpdb;

        $course_id = (int) $request['id'];
        if ($course_id <= 0 || get_post_type($course_id) !== Course::POST_TYPE) {
            return new WP_Error('learni_invalid_course', 'Invalid course.', ['status' => 404]);
        }

        $user_id = (int) get_current_user_id();
        if ($user_id <= 0) {
            return new WP_Error('learni_login_required', 'Login required.', ['status' => 401]);
        }

        $phase = (string) $request->get_param('phase');
        if ($phase === 'final') {
            $partner_info = self::course_partner_info($course_id);
            if (!empty($partner_info['hasPartner'])) {
                return new WP_Error('learni_cross_eval_required', 'Final quiz must be taken as Test Partner for courses with a partner.', ['status' => 400]);
            }
        }

        $quiz = self::binomial_quiz_for_course($course_id);
        $quiz_id = (int) ($quiz['id'] ?? 0);
        if ($quiz_id <= 0) {
            return new WP_Error('learni_no_binomial', 'No binomial quiz configured for this course.', ['status' => 404]);
        }

        $summary = Progress::course_summary($user_id, $course_id);
        $lesson_percent = isset($summary['percent']) ? (int) $summary['percent'] : 0;

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

        $gate = self::binomial_gate_state_for_user($course_id, $quiz_id, $user_id);

        if ($phase === 'initial') {
            // Initial quiz can be taken only once per cycle (until a qualifying final is achieved).
            if (is_array($gate['initial']) && empty($gate['eligibleFinal'])) {
                return new WP_Error('learni_final_pending', 'Final quiz is pending; complete it before starting again.', ['status' => 400]);
            }
            if ($submitted_count > 0 && $lesson_percent >= 100 && Access::user_can_access_course($user_id, $course_id)) {
                return new WP_Error('learni_restart_required', 'Restart the course to take the initial quiz again.', ['status' => 400]);
            }
        } else {
            if (!Access::user_can_access_course($user_id, $course_id)) {
                return new WP_Error('learni_forbidden', 'No access.', ['status' => 403]);
            }
            if ($lesson_percent < 100) {
                return new WP_Error('learni_final_unavailable', 'Final quiz is only available after completing all lessons.', ['status' => 400]);
            }
            if (!is_array($gate['initial'])) {
                return new WP_Error('learni_initial_required', 'Initial quiz must be completed first.', ['status' => 400]);
            }
            if (!empty($gate['eligibleFinal'])) {
                return new WP_Error('learni_final_already_eligible', 'Final quiz already meets the certificate requirement.', ['status' => 400]);
            }
            $days_remaining = (int) ($gate['cooldownDaysRemaining'] ?? 0);
            if ($days_remaining > 0) {
                return new WP_Error(
                    'learni_final_cooldown',
                    'Final quiz is in cooldown.',
                    [
                        'status' => 400,
                        'cooldownDaysRemaining' => $days_remaining,
                        'cooldownUntil' => (string) ($gate['cooldownUntil'] ?? ''),
                    ]
                );
            }
        }

        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, answers_json
                 FROM {$attempts_table}
                 WHERE quiz_id = %d AND user_id = %d AND status = %s AND started_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                 ORDER BY started_at DESC, id DESC
                 LIMIT 1",
                $quiz_id,
                $user_id,
                'started'
            ),
            ARRAY_A
        );

        $attempt_id = 0;
        $attempt_meta = [];
        if ($existing && is_array($existing)) {
            $decoded = [];
            if (!empty($existing['answers_json'])) {
                $d = json_decode((string) $existing['answers_json'], true);
                if (is_array($d)) {
                    $decoded = $d;
                }
            }
            $existing_phase = isset($decoded['phase']) ? (string) $decoded['phase'] : '';
            if ($existing_phase === $phase) {
                $attempt_id = (int) $existing['id'];
                $attempt_meta = $decoded;
            } else {
                return new WP_Error('learni_attempt_in_progress', 'You already have an in-progress quiz attempt.', ['status' => 400]);
            }
        }

        $question_ids = [];
        $answer_order = [];
        $quiz_settings = is_array($quiz['settings'] ?? null) ? (array) $quiz['settings'] : [];

        if ($attempt_id > 0) {
            $question_ids = isset($attempt_meta['questionIds']) && is_array($attempt_meta['questionIds']) ? array_map('intval', $attempt_meta['questionIds']) : [];
            $answer_order = isset($attempt_meta['answerOrder']) && is_array($attempt_meta['answerOrder']) ? $attempt_meta['answerOrder'] : [];
        } else {
            $questions = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, sort_order
                     FROM {$wpdb->prefix}learni_quiz_questions
                     WHERE quiz_id = %d
                     ORDER BY sort_order ASC, id ASC",
                    $quiz_id
                ),
                ARRAY_A
            );

            $ids = [];
            foreach ($questions as $q) {
                $ids[] = (int) $q['id'];
            }

            $order_mode = isset($quiz_settings['questionOrder']) ? (string) $quiz_settings['questionOrder'] : '';
            if ($order_mode === '') {
                $order_mode = !empty($quiz_settings['random_questions']) ? 'random' : 'in_order';
            }

            if ($order_mode === 'random') {
                shuffle($ids);
            }

            $question_ids = $ids;

            // Subset selection: allow rendering only a portion of the total questions.
            $per_attempt = isset($quiz_settings['questions_per_attempt']) ? (int) $quiz_settings['questions_per_attempt'] : 0;
            $subset_random = !empty($quiz_settings['questions_subset_random']);
            if ($per_attempt > 0 && $per_attempt < count($question_ids)) {
                if ($subset_random && $order_mode !== 'random') {
                    shuffle($question_ids);
                }
                $question_ids = array_slice($question_ids, 0, $per_attempt);
            }
            if (empty($question_ids)) {
                return new WP_Error('learni_quiz_empty', 'Quiz has no questions.', ['status' => 400]);
            }

            $in = implode(',', array_fill(0, count($question_ids), '%d'));
            $answers = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, question_id, sort_order
                     FROM {$wpdb->prefix}learni_quiz_answers
                     WHERE question_id IN ($in)
                     ORDER BY sort_order ASC, id ASC",
                    $question_ids
                ),
                ARRAY_A
            );

            $by_q = [];
            foreach ($answers as $a) {
                $qid = (int) $a['question_id'];
                if (!isset($by_q[$qid])) {
                    $by_q[$qid] = [];
                }
                $by_q[$qid][] = (int) $a['id'];
            }

            // Answers are always shuffled (not configurable).
            $shuffle_answers = true;
            foreach ($question_ids as $qid) {
                $list = isset($by_q[$qid]) ? $by_q[$qid] : [];
                if ($shuffle_answers) {
                    shuffle($list);
                }
                $answer_order[(string) $qid] = $list;
            }

            $attempt_meta = [
                'phase' => $phase,
                'courseId' => $course_id,
                'quizId' => $quiz_id,
                'questionIds' => $question_ids,
                'answerOrder' => $answer_order,
                'total' => count($question_ids),
            ];

            $ok = $wpdb->insert(
                $attempts_table,
                [
                    'quiz_id' => $quiz_id,
                    'user_id' => $user_id,
                    'status' => 'started',
                    'started_at' => current_time('mysql'),
                    'submitted_at' => null,
                    'answers_json' => wp_json_encode($attempt_meta),
                ],
                ['%d', '%d', '%s', '%s', '%s', '%s']
            );

            if (!$ok) {
                return new WP_Error('learni_attempt_failed', 'Failed to start attempt.', ['status' => 500]);
            }

            $attempt_id = (int) $wpdb->insert_id;
        }

        $questions_payload = self::questions_payload($quiz_id, $question_ids, $answer_order);

        return [
            'courseId' => $course_id,
            'quiz' => [
                'id' => $quiz_id,
                'title' => (string) ($quiz['title'] ?? ''),
                'passingScore' => (int) ($quiz['passingScore'] ?? 0),
                'introText' => (string) ($quiz_settings['introText'] ?? ''),
            ],
            'attempt' => [
                'id' => $attempt_id,
            ],
            'questions' => $questions_payload,
        ];
    }

    private static function cross_eval_table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'learni_cross_eval_sessions';
    }

    /**
     * Returns partner info for the course (for UI gating).
     *
     * @return array<string,mixed>
     */
    private static function course_partner_info(int $course_id): array
    {
        $out = [
            'hasPartner' => false,
            'partnerUserId' => 0,
            'ownerUserId' => 0,
            'isPartner' => false,
            'isOwner' => false,
            'otherUserId' => 0,
            'otherLessonsPercent' => 0,
        ];

        if ($course_id <= 0) {
            return $out;
        }

        $ids = self::course_partner_users($course_id);
        $owner_user_id = (int) ($ids['owner'] ?? 0);
        $partner_user_id = (int) ($ids['partner'] ?? 0);
        if ($partner_user_id <= 0) {
            return $out;
        }

        $current_user_id = (int) get_current_user_id();
        $out['hasPartner'] = true;
        $out['partnerUserId'] = $partner_user_id;
        $out['ownerUserId'] = $owner_user_id;
        $out['isPartner'] = ($partner_user_id === $current_user_id);
        $out['isOwner'] = ($owner_user_id > 0 && $owner_user_id === $current_user_id);

        $other_user_id = 0;
        if ($partner_user_id > 0) {
            $other_user_id = ($partner_user_id === $current_user_id) ? $owner_user_id : $partner_user_id;
        }
        $out['otherUserId'] = $other_user_id > 0 ? $other_user_id : 0;
        if ($other_user_id > 0) {
            $sum = Progress::course_summary($other_user_id, $course_id);
            $out['otherLessonsPercent'] = isset($sum['percent']) ? (int) $sum['percent'] : 0;
        }

        return $out;
    }

    private static function user_has_eligible_final(int $course_id, int $quiz_id, int $user_id): bool
    {
        if ($course_id <= 0 || $quiz_id <= 0 || $user_id <= 0) {
            return false;
        }

        $summary = Progress::course_summary($user_id, $course_id);
        $lesson_percent = isset($summary['percent']) ? (int) $summary['percent'] : 0;
        if ($lesson_percent < 100) {
            return false;
        }
        if (!Access::user_can_access_course($user_id, $course_id)) {
            return false;
        }

        $gate = self::binomial_gate_state_for_user($course_id, $quiz_id, $user_id);
        return !empty($gate['eligibleFinal']);
    }

    private static function course_certificate_eligible(int $course_id, int $user_id): bool
    {
        if ($course_id <= 0 || $user_id <= 0) {
            return false;
        }
        if (!Access::user_can_access_course($user_id, $course_id)) {
            return false;
        }
        if (!self::certificate_template_exists($course_id)) {
            return false;
        }

        $quiz = self::binomial_quiz_for_course($course_id);
        $quiz_id = (int) ($quiz['id'] ?? 0);
        if ($quiz_id <= 0) {
            return false;
        }

        if (!self::user_has_eligible_final($course_id, $quiz_id, $user_id)) {
            return false;
        }

        $ids = self::course_partner_users($course_id);
        $partner_user_id = (int) ($ids['partner'] ?? 0);
        if ($partner_user_id <= 0) {
            return true;
        }

        $owner_user_id = (int) ($ids['owner'] ?? 0);
        $other_user_id = ($partner_user_id === $user_id) ? $owner_user_id : $partner_user_id;
        if ($other_user_id <= 0) {
            return false;
        }

        return self::user_has_eligible_final($course_id, $quiz_id, $other_user_id);
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function cross_eval_get_session(int $session_id): ?array
    {
        global $wpdb;
        if ($session_id <= 0 || !$wpdb) {
            return null;
        }
        $table = self::cross_eval_table();
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT *
                 FROM {$table}
                 WHERE id = %d
                 LIMIT 1",
                $session_id
            ),
            ARRAY_A
        );
        return (is_array($row) && !empty($row)) ? $row : null;
    }

    private static function cross_eval_maybe_expire(array $row): array
    {
        global $wpdb;
        $status = (string) ($row['status'] ?? '');
        $expires_at = (string) ($row['expires_at'] ?? '');
        $id = (int) ($row['id'] ?? 0);
        if ($id > 0 && $status === 'pending' && $expires_at !== '') {
            $now_ts = (int) current_time('timestamp');
            $exp_ts = (int) strtotime($expires_at);
            if ($exp_ts > 0 && $now_ts > $exp_ts) {
                $table = self::cross_eval_table();
                $wpdb->update(
                    $table,
                    [
                        'status' => 'expired',
                        'updated_at' => current_time('mysql'),
                    ],
                    ['id' => $id],
                    ['%s', '%s'],
                    ['%d']
                );
                $row['status'] = 'expired';
            }
        }
        return $row;
    }

    public static function post_cross_eval_create(WP_REST_Request $request)
    {
        global $wpdb;

        $course_id = (int) $request['id'];
        if ($course_id <= 0 || get_post_type($course_id) !== Course::POST_TYPE) {
            return new WP_Error('learni_invalid_course', 'Invalid course.', ['status' => 404]);
        }

        $initiator_user_id = (int) get_current_user_id();
        if ($initiator_user_id <= 0) {
            return new WP_Error('learni_login_required', 'Login required.', ['status' => 401]);
        }

        $ids = self::course_partner_users($course_id);
        $owner_user_id = (int) ($ids['owner'] ?? 0);
        $partner_user_id = (int) ($ids['partner'] ?? 0);

        if ($partner_user_id <= 0) {
            return new WP_Error('learni_no_partner', 'No partner configured for this course.', ['status' => 400]);
        }

        // Either owner or partner can initiate (mutual testing).
        $is_owner = ($owner_user_id > 0 && $initiator_user_id === $owner_user_id)
            || (class_exists(Enrollments::class) && method_exists(Enrollments::class, 'user_is_owner') && Enrollments::user_is_owner($initiator_user_id, $course_id));
        $is_partner = ($initiator_user_id === $partner_user_id);
        if (!current_user_can('manage_options') && !$is_owner && !$is_partner) {
            return new WP_Error('learni_forbidden', 'Not allowed.', ['status' => 403]);
        }

        if (!Access::user_can_access_course($initiator_user_id, $course_id)) {
            return new WP_Error('learni_forbidden', 'No access.', ['status' => 403]);
        }

        $target_user_id = $is_partner ? $owner_user_id : $partner_user_id;
        if ($target_user_id <= 0 || $target_user_id === $initiator_user_id) {
            return new WP_Error('learni_invalid_partner', 'Invalid partner.', ['status' => 400]);
        }

        if (!Access::user_can_access_course($target_user_id, $course_id)) {
            return new WP_Error('learni_forbidden', 'Partner has no access.', ['status' => 403]);
        }

        $quiz = self::binomial_quiz_for_course($course_id);
        $quiz_id = (int) ($quiz['id'] ?? 0);
        if ($quiz_id <= 0) {
            return new WP_Error('learni_no_binomial', 'No binomial quiz configured for this course.', ['status' => 404]);
        }

        // Only allow starting a cross-eval when the target user is eligible for final.
        $target_summary = Progress::course_summary($target_user_id, $course_id);
        $target_lessons = isset($target_summary['percent']) ? (int) $target_summary['percent'] : 0;
        if ($target_lessons < 100) {
            return new WP_Error('learni_cross_eval_unavailable', 'Target has not completed all lessons.', ['status' => 400]);
        }

        $target_gate = self::binomial_gate_state_for_user($course_id, $quiz_id, $target_user_id);
        if (!is_array($target_gate['initial'])) {
            return new WP_Error('learni_cross_eval_unavailable', 'Target must complete the initial quiz first.', ['status' => 400]);
        }
        if (!empty($target_gate['eligibleFinal'])) {
            return new WP_Error('learni_cross_eval_unavailable', 'Target already meets the certificate requirement.', ['status' => 400]);
        }
        $days_remaining = (int) ($target_gate['cooldownDaysRemaining'] ?? 0);
        if ($days_remaining > 0) {
            return new WP_Error(
                'learni_cross_eval_cooldown',
                'Target final quiz is in cooldown.',
                [
                    'status' => 400,
                    'cooldownDaysRemaining' => $days_remaining,
                    'cooldownUntil' => (string) ($target_gate['cooldownUntil'] ?? ''),
                ]
            );
        }

        $table = self::cross_eval_table();

        // Cancel any still-active pending sessions for the same pair.
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table}
                 SET status = %s, updated_at = %s
                 WHERE course_id = %d
                   AND initiator_user_id = %d
                   AND target_user_id = %d
                   AND status IN (%s, %s)",
                'canceled',
                current_time('mysql'),
                $course_id,
                $initiator_user_id,
                $target_user_id,
                'pending',
                'accepted'
            )
        );

        $expires_at = date('Y-m-d H:i:s', (int) current_time('timestamp') + 120);
        $ok = $wpdb->insert(
            $table,
            [
                'course_id' => $course_id,
                'quiz_id' => $quiz_id,
                'initiator_user_id' => $initiator_user_id,
                'target_user_id' => $target_user_id,
                'status' => 'pending',
                'expires_at' => $expires_at,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s']
        );

        if (!$ok) {
            return new WP_Error('learni_cross_eval_failed', 'Failed to create session.', ['status' => 500]);
        }

        return [
            'ok' => true,
            'sessionId' => (int) $wpdb->insert_id,
            'status' => 'pending',
            'expiresAt' => $expires_at,
        ];
    }

    public static function get_cross_eval_pending(WP_REST_Request $request)
    {
        global $wpdb;

        $user_id = (int) get_current_user_id();
        if ($user_id <= 0) {
            return new WP_Error('learni_login_required', 'Login required.', ['status' => 401]);
        }

        $table = self::cross_eval_table();
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, course_id, initiator_user_id, status, expires_at
                 FROM {$table}
                 WHERE target_user_id = %d
                   AND status = %s
                 ORDER BY created_at DESC, id DESC
                 LIMIT 3",
                $user_id,
                'pending'
            ),
            ARRAY_A
        );

        $out = [];
        foreach ((array) $rows as $r) {
            if (!is_array($r)) {
                continue;
            }
            $r_full = self::cross_eval_get_session((int) ($r['id'] ?? 0));
            if (!$r_full) {
                continue;
            }
            $r_full = self::cross_eval_maybe_expire($r_full);
            if ((string) ($r_full['status'] ?? '') !== 'pending') {
                continue;
            }
            $course_id = (int) ($r_full['course_id'] ?? 0);
            $initiator_id = (int) ($r_full['initiator_user_id'] ?? 0);
            $iu = $initiator_id > 0 ? get_userdata($initiator_id) : null;
            $avatar_url = '';
            if ($initiator_id > 0) {
                if (function_exists('pl_get_user_profile_avatar_custom_url')) {
                    $avatar_url = (string) pl_get_user_profile_avatar_custom_url($initiator_id, 96);
                }
                if (!$avatar_url) {
                    $avatar_url = (string) get_avatar_url($initiator_id, ['size' => 96]);
                }
            }
            $out[] = [
                'id' => (int) ($r_full['id'] ?? 0),
                'courseId' => $course_id,
                'courseTitle' => $course_id > 0 ? (string) get_the_title($course_id) : '',
                'initiatorUserId' => $initiator_id,
                'initiatorName' => ($iu instanceof \WP_User) ? (string) ($iu->display_name ?? '') : '',
                'initiatorAvatarUrl' => $avatar_url,
                'expiresAt' => (string) ($r_full['expires_at'] ?? ''),
            ];
        }

        return [
            'ok' => true,
            'sessions' => $out,
        ];
    }

    public static function get_cross_eval_session(WP_REST_Request $request)
    {
        $session_id = (int) $request['id'];
        $row = self::cross_eval_get_session($session_id);
        if (!$row) {
            return new WP_Error('learni_cross_eval_not_found', 'Not found.', ['status' => 404]);
        }

        $row = self::cross_eval_maybe_expire($row);

        $user_id = (int) get_current_user_id();
        $initiator_id = (int) ($row['initiator_user_id'] ?? 0);
        $target_id = (int) ($row['target_user_id'] ?? 0);
        if ($user_id !== $initiator_id && $user_id !== $target_id && !current_user_can('manage_options')) {
            return new WP_Error('learni_forbidden', 'No access.', ['status' => 403]);
        }

        return [
            'ok' => true,
            'session' => [
                'id' => (int) ($row['id'] ?? 0),
                'courseId' => (int) ($row['course_id'] ?? 0),
                'quizId' => (int) ($row['quiz_id'] ?? 0),
                'status' => (string) ($row['status'] ?? ''),
                'expiresAt' => (string) ($row['expires_at'] ?? ''),
            ],
        ];
    }

    public static function post_cross_eval_respond(WP_REST_Request $request)
    {
        global $wpdb;

        $session_id = (int) $request['id'];
        $decision = (string) $request->get_param('decision');

        $row = self::cross_eval_get_session($session_id);
        if (!$row) {
            return new WP_Error('learni_cross_eval_not_found', 'Not found.', ['status' => 404]);
        }
        $row = self::cross_eval_maybe_expire($row);

        if ((string) ($row['status'] ?? '') !== 'pending') {
            return new WP_Error('learni_cross_eval_closed', 'Session is not pending.', ['status' => 400]);
        }

        $user_id = (int) get_current_user_id();
        $target_id = (int) ($row['target_user_id'] ?? 0);
        if ($user_id !== $target_id) {
            return new WP_Error('learni_forbidden', 'No access.', ['status' => 403]);
        }

        $new_status = ($decision === 'accept') ? 'accepted' : 'declined';
        $table = self::cross_eval_table();
        $ok = $wpdb->update(
            $table,
            [
                'status' => $new_status,
                'responded_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $session_id],
            ['%s', '%s', '%s'],
            ['%d']
        );
        if ($ok === false) {
            return new WP_Error('learni_cross_eval_failed', 'Failed to update session.', ['status' => 500]);
        }

        return [
            'ok' => true,
            'status' => $new_status,
        ];
    }

    public static function post_cross_eval_cancel(WP_REST_Request $request)
    {
        global $wpdb;

        $session_id = (int) $request['id'];
        $row = self::cross_eval_get_session($session_id);
        if (!$row) {
            return new WP_Error('learni_cross_eval_not_found', 'Not found.', ['status' => 404]);
        }
        $row = self::cross_eval_maybe_expire($row);

        $user_id = (int) get_current_user_id();
        $initiator_id = (int) ($row['initiator_user_id'] ?? 0);
        if ($user_id !== $initiator_id && !current_user_can('manage_options')) {
            return new WP_Error('learni_forbidden', 'No access.', ['status' => 403]);
        }

        $status = (string) ($row['status'] ?? '');
        if (!in_array($status, ['pending', 'accepted'], true)) {
            return new WP_Error('learni_cross_eval_closed', 'Session is not cancelable.', ['status' => 400]);
        }

        $table = self::cross_eval_table();
        $ok = $wpdb->update(
            $table,
            [
                'status' => 'canceled',
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $session_id],
            ['%s', '%s'],
            ['%d']
        );
        if ($ok === false) {
            return new WP_Error('learni_cross_eval_failed', 'Failed to cancel session.', ['status' => 500]);
        }

        return [
            'ok' => true,
            'status' => 'canceled',
        ];
    }

    public static function post_cross_eval_binomial_start(WP_REST_Request $request)
    {
        global $wpdb;

        $course_id = (int) $request['id'];
        if ($course_id <= 0 || get_post_type($course_id) !== Course::POST_TYPE) {
            return new WP_Error('learni_invalid_course', 'Invalid course.', ['status' => 404]);
        }

        $initiator_user_id = (int) get_current_user_id();
        if ($initiator_user_id <= 0) {
            return new WP_Error('learni_login_required', 'Login required.', ['status' => 401]);
        }

        $session_id = (int) $request->get_param('sessionId');
        if ($session_id <= 0) {
            return new WP_Error('learni_invalid_session', 'Invalid session.', ['status' => 404]);
        }

        $row = self::cross_eval_get_session($session_id);
        if (!$row) {
            return new WP_Error('learni_cross_eval_not_found', 'Not found.', ['status' => 404]);
        }
        $row = self::cross_eval_maybe_expire($row);

        if ((int) ($row['course_id'] ?? 0) !== $course_id) {
            return new WP_Error('learni_invalid_session', 'Invalid session.', ['status' => 400]);
        }

        if ((int) ($row['initiator_user_id'] ?? 0) !== $initiator_user_id && !current_user_can('manage_options')) {
            return new WP_Error('learni_forbidden', 'No access.', ['status' => 403]);
        }

        $sess_status = (string) ($row['status'] ?? '');
        if (!in_array($sess_status, ['accepted', 'started'], true)) {
            return new WP_Error('learni_cross_eval_not_ready', 'Session not accepted.', ['status' => 400]);
        }

        $target_user_id = (int) ($row['target_user_id'] ?? 0);
        if ($target_user_id <= 0) {
            return new WP_Error('learni_invalid_partner', 'Invalid partner.', ['status' => 400]);
        }

        $quiz = self::binomial_quiz_for_course($course_id);
        $quiz_id = (int) ($quiz['id'] ?? 0);
        if ($quiz_id <= 0) {
            return new WP_Error('learni_no_binomial', 'No binomial quiz configured for this course.', ['status' => 404]);
        }

        $summary = Progress::course_summary($target_user_id, $course_id);
        $lesson_percent = isset($summary['percent']) ? (int) $summary['percent'] : 0;
        if (!Access::user_can_access_course($target_user_id, $course_id)) {
            return new WP_Error('learni_forbidden', 'Partner has no access.', ['status' => 403]);
        }
        if ($lesson_percent < 100) {
            return new WP_Error('learni_final_unavailable', 'Final quiz is only available after completing all lessons.', ['status' => 400]);
        }

        $gate = self::binomial_gate_state_for_user($course_id, $quiz_id, $target_user_id);
        if (!is_array($gate['initial'])) {
            return new WP_Error('learni_initial_required', 'Initial quiz must be completed first.', ['status' => 400]);
        }
        if (!empty($gate['eligibleFinal'])) {
            return new WP_Error('learni_final_already_eligible', 'Final quiz already meets the certificate requirement.', ['status' => 400]);
        }
        $days_remaining = (int) ($gate['cooldownDaysRemaining'] ?? 0);
        if ($days_remaining > 0) {
            return new WP_Error(
                'learni_final_cooldown',
                'Final quiz is in cooldown.',
                [
                    'status' => 400,
                    'cooldownDaysRemaining' => $days_remaining,
                    'cooldownUntil' => (string) ($gate['cooldownUntil'] ?? ''),
                ]
            );
        }

        $attempts_table = $wpdb->prefix . 'learni_quiz_attempts';

        $phase = 'final';
        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, answers_json
                 FROM {$attempts_table}
                 WHERE quiz_id = %d AND user_id = %d AND status = %s AND started_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                 ORDER BY started_at DESC, id DESC
                 LIMIT 1",
                $quiz_id,
                $target_user_id,
                'started'
            ),
            ARRAY_A
        );

        $attempt_id = 0;
        $attempt_meta = [];
        if ($existing && is_array($existing)) {
            $decoded = [];
            if (!empty($existing['answers_json'])) {
                $d = json_decode((string) $existing['answers_json'], true);
                if (is_array($d)) {
                    $decoded = $d;
                }
            }
            $existing_phase = isset($decoded['phase']) ? (string) $decoded['phase'] : '';
            if ($existing_phase === $phase) {
                $cross = isset($decoded['crossEval']) && is_array($decoded['crossEval']) ? (array) $decoded['crossEval'] : [];
                $existing_session_id = isset($cross['sessionId']) ? (int) $cross['sessionId'] : 0;
                if ($existing_session_id !== $session_id) {
                    return new WP_Error('learni_attempt_in_progress', 'Partner already has an in-progress quiz attempt.', ['status' => 400]);
                }
                $attempt_id = (int) $existing['id'];
                $attempt_meta = $decoded;
            } else {
                return new WP_Error('learni_attempt_in_progress', 'Partner already has an in-progress quiz attempt.', ['status' => 400]);
            }
        }

        $question_ids = [];
        $answer_order = [];
        $quiz_settings = is_array($quiz['settings'] ?? null) ? (array) $quiz['settings'] : [];

        if ($attempt_id > 0) {
            $question_ids = isset($attempt_meta['questionIds']) && is_array($attempt_meta['questionIds']) ? array_map('intval', $attempt_meta['questionIds']) : [];
            $answer_order = isset($attempt_meta['answerOrder']) && is_array($attempt_meta['answerOrder']) ? $attempt_meta['answerOrder'] : [];
        } else {
            $questions = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, sort_order
                     FROM {$wpdb->prefix}learni_quiz_questions
                     WHERE quiz_id = %d
                     ORDER BY sort_order ASC, id ASC",
                    $quiz_id
                ),
                ARRAY_A
            );

            $ids = [];
            foreach ($questions as $q) {
                $ids[] = (int) $q['id'];
            }

            $order_mode = isset($quiz_settings['questionOrder']) ? (string) $quiz_settings['questionOrder'] : '';
            if ($order_mode === '') {
                $order_mode = !empty($quiz_settings['random_questions']) ? 'random' : 'in_order';
            }
            if ($order_mode === 'random') {
                shuffle($ids);
            }
            $question_ids = $ids;

            $per_attempt = isset($quiz_settings['questions_per_attempt']) ? (int) $quiz_settings['questions_per_attempt'] : 0;
            $subset_random = !empty($quiz_settings['questions_subset_random']);
            if ($per_attempt > 0 && $per_attempt < count($question_ids)) {
                if ($subset_random && $order_mode !== 'random') {
                    shuffle($question_ids);
                }
                $question_ids = array_slice($question_ids, 0, $per_attempt);
            }
            if (empty($question_ids)) {
                return new WP_Error('learni_quiz_empty', 'Quiz has no questions.', ['status' => 400]);
            }

            $in = implode(',', array_fill(0, count($question_ids), '%d'));
            $answers = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, question_id, sort_order
                     FROM {$wpdb->prefix}learni_quiz_answers
                     WHERE question_id IN ($in)
                     ORDER BY sort_order ASC, id ASC",
                    $question_ids
                ),
                ARRAY_A
            );

            $by_q = [];
            foreach ($answers as $a) {
                $qid = (int) $a['question_id'];
                if (!isset($by_q[$qid])) {
                    $by_q[$qid] = [];
                }
                $by_q[$qid][] = (int) $a['id'];
            }

            foreach ($question_ids as $qid) {
                $list = isset($by_q[$qid]) ? $by_q[$qid] : [];
                shuffle($list);
                $answer_order[(string) $qid] = $list;
            }

            $attempt_meta = [
                'phase' => $phase,
                'courseId' => $course_id,
                'quizId' => $quiz_id,
                'questionIds' => $question_ids,
                'answerOrder' => $answer_order,
                'total' => count($question_ids),
                'crossEval' => [
                    'sessionId' => $session_id,
                    'initiatorUserId' => $initiator_user_id,
                ],
            ];

            $ok = $wpdb->insert(
                $attempts_table,
                [
                    'quiz_id' => $quiz_id,
                    'user_id' => $target_user_id,
                    'status' => 'started',
                    'started_at' => current_time('mysql'),
                    'submitted_at' => null,
                    'answers_json' => wp_json_encode($attempt_meta),
                ],
                ['%d', '%d', '%s', '%s', '%s', '%s']
            );

            if (!$ok) {
                return new WP_Error('learni_attempt_failed', 'Failed to start attempt.', ['status' => 500]);
            }

            $attempt_id = (int) $wpdb->insert_id;
        }

        // Mark session started.
        $table = self::cross_eval_table();
        $wpdb->update(
            $table,
            [
                'status' => 'started',
                'started_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $session_id],
            ['%s', '%s', '%s'],
            ['%d']
        );

        $questions_payload = self::questions_payload($quiz_id, $question_ids, $answer_order);

        return [
            'courseId' => $course_id,
            'quiz' => [
                'id' => $quiz_id,
                'title' => (string) ($quiz['title'] ?? ''),
                'passingScore' => (int) ($quiz['passingScore'] ?? 0),
                'introText' => (string) ($quiz_settings['introText'] ?? ''),
            ],
            'attempt' => [
                'id' => $attempt_id,
            ],
            'questions' => $questions_payload,
        ];
    }

    public static function post_cross_eval_attempt_submit(WP_REST_Request $request)
    {
        global $wpdb;

        $attempt_id = (int) $request['id'];
        if ($attempt_id <= 0) {
            return new WP_Error('learni_invalid_attempt', 'Invalid attempt.', ['status' => 404]);
        }

        $initiator_user_id = (int) get_current_user_id();
        if ($initiator_user_id <= 0) {
            return new WP_Error('learni_login_required', 'Login required.', ['status' => 401]);
        }

        $session_id = (int) $request->get_param('sessionId');
        if ($session_id <= 0) {
            return new WP_Error('learni_invalid_session', 'Invalid session.', ['status' => 404]);
        }

        $session = self::cross_eval_get_session($session_id);
        if (!$session) {
            return new WP_Error('learni_cross_eval_not_found', 'Not found.', ['status' => 404]);
        }
        $session = self::cross_eval_maybe_expire($session);

        if ((int) ($session['initiator_user_id'] ?? 0) !== $initiator_user_id && !current_user_can('manage_options')) {
            return new WP_Error('learni_forbidden', 'No access.', ['status' => 403]);
        }

        $attempts_table = $wpdb->prefix . 'learni_quiz_attempts';
        $attempt = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, quiz_id, user_id, status, answers_json
                 FROM {$attempts_table}
                 WHERE id = %d
                 LIMIT 1",
                $attempt_id
            ),
            ARRAY_A
        );
        if (!$attempt || !is_array($attempt)) {
            return new WP_Error('learni_invalid_attempt', 'Attempt not found.', ['status' => 404]);
        }
        if ((string) ($attempt['status'] ?? '') !== 'started') {
            return new WP_Error('learni_attempt_closed', 'Attempt is not in progress.', ['status' => 400]);
        }

        $meta = [];
        if (!empty($attempt['answers_json'])) {
            $decoded = json_decode((string) $attempt['answers_json'], true);
            if (is_array($decoded)) {
                $meta = $decoded;
            }
        }

        $cross = isset($meta['crossEval']) && is_array($meta['crossEval']) ? (array) $meta['crossEval'] : [];
        $attempt_session_id = isset($cross['sessionId']) ? (int) $cross['sessionId'] : 0;
        if ($attempt_session_id !== $session_id) {
            return new WP_Error('learni_invalid_session', 'Invalid session.', ['status' => 400]);
        }

        $question_ids = isset($meta['questionIds']) && is_array($meta['questionIds']) ? array_map('intval', $meta['questionIds']) : [];
        if (empty($question_ids)) {
            return new WP_Error('learni_attempt_invalid', 'Attempt has no questions.', ['status' => 500]);
        }

        $answers = (array) $request->get_param('answers');
        $given = [];
        foreach ($answers as $qid => $aid) {
            $qid = (int) $qid;
            $aid = (int) $aid;
            if ($qid > 0 && $aid > 0) {
                $given[$qid] = $aid;
            }
        }

        $correct_rows = self::correct_answer_ids_by_question($question_ids);
        $score = 0;
        $total = count($question_ids);

        foreach ($question_ids as $qid) {
            $correct_ids = $correct_rows[$qid] ?? [];
            $picked = $given[$qid] ?? 0;
            if ($picked > 0 && in_array($picked, $correct_ids, true)) {
                $score++;
            }
        }

        $percent = $total > 0 ? (int) round(($score / $total) * 100) : 0;
        $quiz_id = (int) ($attempt['quiz_id'] ?? 0);
        $passing_score = $quiz_id > 0 ? self::quiz_passing_score($quiz_id) : 0;
        $passed = $passing_score > 0 ? ($percent >= $passing_score) : true;

        $meta['answers'] = $given;
        $meta['score'] = $score;
        $meta['total'] = $total;
        $meta['percent'] = $percent;

        // Store a deterministic label mapping for auditing (A/B/C...) based on the server order.
        $label_map = [];
        $order = isset($meta['answerOrder']) && is_array($meta['answerOrder']) ? (array) $meta['answerOrder'] : [];
        foreach ($order as $qid_str => $ids) {
            if (!is_array($ids)) {
                continue;
            }
            $qid = (int) $qid_str;
            if ($qid <= 0) {
                continue;
            }
            $label_map[$qid] = [];
            $idx = 0;
            foreach ($ids as $aid) {
                $aid = (int) $aid;
                if ($aid <= 0) {
                    continue;
                }
                $label_map[$qid][$aid] = chr(ord('A') + $idx);
                $idx++;
            }
        }
        if (!empty($label_map)) {
            $meta['answerLabels'] = $label_map;
        }

        $ok = $wpdb->update(
            $attempts_table,
            [
                'status' => 'submitted',
                'score' => $score,
                'passed' => $passed ? 1 : 0,
                'submitted_at' => current_time('mysql'),
                'answers_json' => wp_json_encode($meta),
            ],
            ['id' => $attempt_id],
            ['%s', '%d', '%d', '%s', '%s'],
            ['%d']
        );

        if ($ok === false) {
            return new WP_Error('learni_submit_failed', 'Failed to submit attempt.', ['status' => 500]);
        }

        // Mark session completed.
        $table = self::cross_eval_table();
        $wpdb->update(
            $table,
            [
                'status' => 'completed',
                'completed_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $session_id],
            ['%s', '%s', '%s'],
            ['%d']
        );

        // Notify both users via email (cross evaluation).
        $course_id = (int) ($session['course_id'] ?? 0);
        $target_user_id = (int) ($session['target_user_id'] ?? 0);
        $quiz_id = (int) ($attempt['quiz_id'] ?? 0);

        if ($course_id > 0 && $target_user_id > 0 && $quiz_id > 0 && class_exists('\\PL_Email')) {
            $gate = self::binomial_gate_state_for_user($course_id, $quiz_id, $target_user_id);
            $initial_payload = is_array($gate['initial']) ? $gate['initial'] : ['percent' => 0, 'submittedAt' => ''];
            $final_payload = is_array($gate['latestFinal']) ? $gate['latestFinal'] : ['percent' => $percent, 'submittedAt' => current_time('mysql')];

            $first_ts = !empty($initial_payload['submittedAt']) ? (int) strtotime((string) $initial_payload['submittedAt']) : 0;
            $final_ts = !empty($final_payload['submittedAt']) ? (int) strtotime((string) $final_payload['submittedAt']) : 0;
            $duration_days = ($first_ts > 0 && $final_ts > 0) ? (int) max(0, round(($final_ts - $first_ts) / 86400)) : 0;

            $cooldown_days = (int) ($gate['cooldownDaysRemaining'] ?? 0);
            $cooldown_until = (string) ($gate['cooldownUntil'] ?? '');
            $retry_date_label = '';
            if ($cooldown_until !== '') {
                $retry_ts = (int) strtotime($cooldown_until);
                if ($retry_ts > 0) {
                    $retry_date_label = (string) date_i18n('d M Y', $retry_ts);
                }
            }

            $course_name = (string) get_the_title($course_id);
            $course_url = (string) get_permalink($course_id);
            $tester = $initiator_user_id > 0 ? get_userdata($initiator_user_id) : null;
            $tested = $target_user_id > 0 ? get_userdata($target_user_id) : null;
            $tester_name = ($tester instanceof \WP_User) ? (string) ($tester->display_name ?? '') : '';
            $tested_name = ($tested instanceof \WP_User) ? (string) ($tested->display_name ?? '') : '';

            $subject = sprintf(__('Evaluación Final completada: %s', 'politeia-learning'), $course_name !== '' ? $course_name : (string) $course_id);

            foreach ([
                ['id' => $target_user_id, 'role' => 'tested'],
                ['id' => $initiator_user_id, 'role' => 'tester'],
            ] as $recipient) {
                $rid = (int) ($recipient['id'] ?? 0);
                if ($rid <= 0) {
                    continue;
                }
                $u = get_userdata($rid);
                if (!$u || empty($u->user_email) || !is_email($u->user_email)) {
                    continue;
                }

                $eligible = self::course_certificate_eligible($course_id, $rid);
                $cta_url = $course_url !== '' ? ($eligible ? add_query_arg('learni_open_cert', '1', $course_url) : $course_url) : '';
                $cta_label = $eligible
                    ? __('VER CERTIFICADO', 'politeia-learning')
                    : ($cooldown_days > 0 ? __('REVISAR LECCIONES', 'politeia-learning') : __('VER CURSO', 'politeia-learning'));

                $html = \PL_Email::render('learni_cross_eval_completed', [
                    'course_name' => $course_name,
                    'tester_name' => $tester_name,
                    'tested_name' => $tested_name,
                    'recipient_role' => (string) ($recipient['role'] ?? 'tested'),
                    'percentage_first' => (int) ($initial_payload['percent'] ?? 0),
                    'percentage_final' => (int) ($final_payload['percent'] ?? 0),
                    'first_date_label' => $first_ts > 0 ? (string) date_i18n('d M Y', $first_ts) : '',
                    'final_date_label' => $final_ts > 0 ? (string) date_i18n('d M Y', $final_ts) : '',
                    'duration_days' => $duration_days,
                    'cooldown_days_remaining' => $cooldown_days,
                    'retry_date_label' => $retry_date_label,
                    'cta_url' => $cta_url,
                    'cta_label' => $cta_label,
                ]);

                if (trim($html) === '') {
                    continue;
                }

                wp_mail((string) $u->user_email, $subject, $html, ['Content-Type: text/html; charset=UTF-8']);
            }
        }

        return [
            'ok' => true,
            'attemptId' => $attempt_id,
            'score' => $score,
            'total' => $total,
            'percent' => $percent,
            'passed' => $passed,
        ];
    }

    public static function post_attempt_submit(WP_REST_Request $request)
    {
        global $wpdb;

        $attempt_id = (int) $request['id'];
        if ($attempt_id <= 0) {
            return new WP_Error('learni_invalid_attempt', 'Invalid attempt.', ['status' => 404]);
        }

        $user_id = (int) get_current_user_id();
        if ($user_id <= 0) {
            return new WP_Error('learni_login_required', 'Login required.', ['status' => 401]);
        }

        $attempts_table = $wpdb->prefix . 'learni_quiz_attempts';
        $attempt = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, quiz_id, user_id, status, answers_json
                 FROM {$attempts_table}
                 WHERE id = %d
                 LIMIT 1",
                $attempt_id
            ),
            ARRAY_A
        );

        if (!$attempt || !is_array($attempt)) {
            return new WP_Error('learni_invalid_attempt', 'Attempt not found.', ['status' => 404]);
        }

        if ((int) ($attempt['user_id'] ?? 0) !== $user_id) {
            return new WP_Error('learni_forbidden', 'No access.', ['status' => 403]);
        }

        if ((string) ($attempt['status'] ?? '') !== 'started') {
            return new WP_Error('learni_attempt_closed', 'Attempt is not in progress.', ['status' => 400]);
        }

        $quiz_id = (int) ($attempt['quiz_id'] ?? 0);
        if ($quiz_id <= 0) {
            return new WP_Error('learni_invalid_quiz', 'Invalid quiz.', ['status' => 500]);
        }

        $meta = [];
        if (!empty($attempt['answers_json'])) {
            $decoded = json_decode((string) $attempt['answers_json'], true);
            if (is_array($decoded)) {
                $meta = $decoded;
            }
        }

        $question_ids = isset($meta['questionIds']) && is_array($meta['questionIds']) ? array_map('intval', $meta['questionIds']) : [];
        if (empty($question_ids)) {
            return new WP_Error('learni_attempt_invalid', 'Attempt has no questions.', ['status' => 500]);
        }

        $answers = (array) $request->get_param('answers');
        $given = [];
        foreach ($answers as $qid => $aid) {
            $qid = (int) $qid;
            $aid = (int) $aid;
            if ($qid > 0 && $aid > 0) {
                $given[$qid] = $aid;
            }
        }

        $correct_rows = self::correct_answer_ids_by_question($question_ids);
        $score = 0;
        $total = count($question_ids);

        foreach ($question_ids as $qid) {
            $correct_ids = $correct_rows[$qid] ?? [];
            $picked = $given[$qid] ?? 0;
            if ($picked > 0 && in_array($picked, $correct_ids, true)) {
                $score++;
            }
        }

        $percent = $total > 0 ? (int) round(($score / $total) * 100) : 0;
        $passing_score = self::quiz_passing_score($quiz_id);
        $passed = $passing_score > 0 ? ($percent >= $passing_score) : true;

        $meta['answers'] = $given;
        $meta['score'] = $score;
        $meta['total'] = $total;
        $meta['percent'] = $percent;

        $ok = $wpdb->update(
            $attempts_table,
            [
                'status' => 'submitted',
                'score' => $score,
                'passed' => $passed ? 1 : 0,
                'submitted_at' => current_time('mysql'),
                'answers_json' => wp_json_encode($meta),
            ],
            ['id' => $attempt_id],
            ['%s', '%d', '%d', '%s', '%s'],
            ['%d']
        );

        if ($ok === false) {
            return new WP_Error('learni_submit_failed', 'Failed to submit attempt.', ['status' => 500]);
        }

        // Progress email for final quiz (single-user courses).
        $phase = isset($meta['phase']) && is_string($meta['phase']) ? sanitize_key((string) $meta['phase']) : '';
        $course_id = isset($meta['courseId']) ? (int) $meta['courseId'] : 0;
        if ($phase === 'final' && $course_id > 0 && get_post_type($course_id) === Course::POST_TYPE && class_exists('\\PL_Email')) {
            $u = get_userdata($user_id);
            if ($u && !empty($u->user_email) && is_email($u->user_email)) {
                $gate = self::binomial_gate_state_for_user($course_id, $quiz_id, $user_id);
                $initial = is_array($gate['initial']) ? $gate['initial'] : null;
                $latest_final = is_array($gate['latestFinal']) ? $gate['latestFinal'] : null;

                $first_pct = is_array($initial) ? (int) ($initial['percent'] ?? 0) : 0;
                $final_pct = is_array($latest_final) ? (int) ($latest_final['percent'] ?? 0) : $percent;

                $first_ts = is_array($initial) && !empty($initial['submittedAt']) ? (int) strtotime((string) $initial['submittedAt']) : 0;
                $final_ts = is_array($latest_final) && !empty($latest_final['submittedAt']) ? (int) strtotime((string) $latest_final['submittedAt']) : (int) current_time('timestamp');
                $duration_days = ($first_ts > 0 && $final_ts > 0) ? (int) max(0, round(($final_ts - $first_ts) / 86400)) : 0;

                $cooldown_days = (int) ($gate['cooldownDaysRemaining'] ?? 0);
                $cooldown_until = (string) ($gate['cooldownUntil'] ?? '');
                $retry_date_label = '';
                if ($cooldown_until !== '') {
                    $retry_ts = (int) strtotime($cooldown_until);
                    if ($retry_ts > 0) {
                        $retry_date_label = (string) date_i18n('d M Y', $retry_ts);
                    }
                }

                $course_name = (string) get_the_title($course_id);
                $course_url = (string) get_permalink($course_id);
                $eligible = self::course_certificate_eligible($course_id, $user_id);
                $cta_url = $course_url !== '' ? ($eligible ? add_query_arg('learni_open_cert', '1', $course_url) : $course_url) : '';
                $cta_label = $eligible ? __('VER CERTIFICADO', 'politeia-learning') : __('REVISAR LECCIONES', 'politeia-learning');

                $html = \PL_Email::render('learni_final_quiz_completed', [
                    'percentage_first' => $first_pct,
                    'percentage_final' => $final_pct,
                    'first_date_label' => $first_ts > 0 ? (string) date_i18n('d M Y', $first_ts) : '',
                    'final_date_label' => $final_ts > 0 ? (string) date_i18n('d M Y', $final_ts) : '',
                    'duration_days' => $duration_days,
                    'lessons_url' => $course_url,
                    'cooldown_days_remaining' => $cooldown_days,
                    'retry_date_label' => $retry_date_label,
                    'cta_url' => $cta_url,
                    'cta_label' => $cta_label,
                ]);

                if (trim($html) !== '') {
                    $subject = sprintf(__('Evaluación Final completada: %s', 'politeia-learning'), $course_name !== '' ? $course_name : (string) $course_id);
                    wp_mail((string) $u->user_email, $subject, $html, ['Content-Type: text/html; charset=UTF-8']);
                }
            }
        }

        return [
            'ok' => true,
            'attemptId' => $attempt_id,
            'score' => $score,
            'total' => $total,
            'percent' => $percent,
            'passed' => $passed,
        ];
    }

    public static function post_course_restart(WP_REST_Request $request)
    {
        global $wpdb;

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

        $quiz = self::binomial_quiz_for_course($course_id);
        $quiz_id = (int) ($quiz['id'] ?? 0);
        if ($quiz_id <= 0) {
            return new WP_Error('learni_no_binomial', 'No binomial quiz configured for this course.', ['status' => 404]);
        }

        $summary = Progress::course_summary($user_id, $course_id);
        $lesson_percent = isset($summary['percent']) ? (int) $summary['percent'] : 0;

        $gate = self::binomial_gate_state_for_user($course_id, $quiz_id, $user_id);
        $can_restart = !empty($gate['eligibleFinal']) && $lesson_percent >= 100;
        if (!$can_restart) {
            return new WP_Error('learni_restart_unavailable', 'Restart is only available after completing the course and finishing the final quiz.', ['status' => 400]);
        }

        $ok = Progress::reset_course($user_id, $course_id);
        if (!$ok) {
            return new WP_Error('learni_restart_failed', 'Failed to restart course.', ['status' => 500]);
        }

        return [
            'ok' => true,
            'courseId' => $course_id,
        ];
    }

    private static function quiz_passing_score(int $quiz_id): int
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT passing_score
                 FROM {$wpdb->prefix}learni_quizzes
                 WHERE id = %d
                 LIMIT 1",
                $quiz_id
            ),
            ARRAY_A
        );
        return isset($row['passing_score']) ? (int) $row['passing_score'] : 0;
    }

    /**
     * @param array<int,int> $question_ids
     * @return array<int,array<int,int>> question_id => [answer_id,...]
     */
    private static function correct_answer_ids_by_question(array $question_ids): array
    {
        global $wpdb;
        if (empty($question_ids)) {
            return [];
        }
        $in = implode(',', array_fill(0, count($question_ids), '%d'));
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, question_id
                 FROM {$wpdb->prefix}learni_quiz_answers
                 WHERE question_id IN ($in) AND is_correct = 1",
                $question_ids
            ),
            ARRAY_A
        );

        $out = [];
        foreach ($rows as $r) {
            $qid = (int) ($r['question_id'] ?? 0);
            $aid = (int) ($r['id'] ?? 0);
            if ($qid <= 0 || $aid <= 0) {
                continue;
            }
            if (!isset($out[$qid])) {
                $out[$qid] = [];
            }
            $out[$qid][] = $aid;
        }
        return $out;
    }

    /**
     * @return array{id:int,title:string,passingScore:int,settings:array<string,mixed>}
     */
    private static function binomial_quiz_for_course(int $course_id): array
    {
        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, lesson_post_id, title, passing_score, settings_json
                 FROM {$wpdb->prefix}learni_quizzes
                 WHERE course_post_id = %d
                 ORDER BY id DESC",
                $course_id
            ),
            ARRAY_A
        );

        $fallback = [];
        foreach ($rows as $row) {
            $settings = [];
            if (!empty($row['settings_json'])) {
                $decoded = json_decode((string) $row['settings_json'], true);
                if (is_array($decoded)) {
                    $settings = $decoded;
                }
            }
            if (isset($settings['role']) && (string) $settings['role'] === 'binomial') {
                return [
                    'id' => (int) ($row['id'] ?? 0),
                    'title' => (string) ($row['title'] ?? ''),
                    'passingScore' => (int) ($row['passing_score'] ?? 0),
                    'settings' => $settings,
                ];
            }
            // Fallback: most recent course-level quiz.
            if (empty($fallback) && empty($row['lesson_post_id'])) {
                $fallback = [
                    'id' => (int) ($row['id'] ?? 0),
                    'title' => (string) ($row['title'] ?? ''),
                    'passingScore' => (int) ($row['passing_score'] ?? 0),
                    'settings' => $settings,
                ];
            }
        }

        // If no explicit binomial role exists, use the most recent course-level quiz.
        if (!empty($fallback)) {
            return $fallback;
        }

        return ['id' => 0, 'title' => '', 'passingScore' => 0, 'settings' => []];
    }

    /**
     * @param array<int,int> $question_ids
     * @param array<string,mixed> $answer_order question_id => [answer_id,...]
     * @return array<int,array<string,mixed>>
     */
    private static function questions_payload(int $quiz_id, array $question_ids, array $answer_order): array
    {
        global $wpdb;
        if (empty($question_ids)) {
            return [];
        }

        $in_q = implode(',', array_fill(0, count($question_ids), '%d'));
        $q_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, prompt
                 FROM {$wpdb->prefix}learni_quiz_questions
                 WHERE id IN ($in_q)",
                $question_ids
            ),
            ARRAY_A
        );

        $q_by_id = [];
        foreach ($q_rows as $q) {
            $qid = (int) ($q['id'] ?? 0);
            if ($qid > 0) {
                $q_by_id[$qid] = (string) ($q['prompt'] ?? '');
            }
        }

        $a_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, question_id, answer_text
                 FROM {$wpdb->prefix}learni_quiz_answers
                 WHERE question_id IN ($in_q)",
                $question_ids
            ),
            ARRAY_A
        );

        $answers_by_id = [];
        foreach ($a_rows as $a) {
            $aid = (int) ($a['id'] ?? 0);
            $qid = (int) ($a['question_id'] ?? 0);
            if ($aid > 0 && $qid > 0) {
                $answers_by_id[$aid] = [
                    'id' => $aid,
                    'questionId' => $qid,
                    'text' => (string) ($a['answer_text'] ?? ''),
                ];
            }
        }

        $out = [];
        foreach ($question_ids as $qid) {
            $ordered_ids = [];
            if (isset($answer_order[(string) $qid]) && is_array($answer_order[(string) $qid])) {
                $ordered_ids = array_map('intval', (array) $answer_order[(string) $qid]);
            }

            $answers = [];
            if (!empty($ordered_ids)) {
                foreach ($ordered_ids as $aid) {
                    if (isset($answers_by_id[$aid])) {
                        $answers[] = [
                            'id' => (int) $answers_by_id[$aid]['id'],
                            'text' => (string) $answers_by_id[$aid]['text'],
                        ];
                    }
                }
            } else {
                foreach ($answers_by_id as $aid => $a) {
                    if ((int) ($a['questionId'] ?? 0) === (int) $qid) {
                        $answers[] = [
                            'id' => (int) $aid,
                            'text' => (string) ($a['text'] ?? ''),
                        ];
                    }
                }
            }

            $out[] = [
                'id' => (int) $qid,
                'prompt' => isset($q_by_id[$qid]) ? (string) $q_by_id[$qid] : '',
                'answers' => $answers,
            ];
        }

        return $out;
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

    private static function certificate_template_exists(int $course_id): bool
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

    private static function certificate_paragraph_with_replacements(string $paragraph, int $course_id, int $user_id, string $issued_label): string
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
