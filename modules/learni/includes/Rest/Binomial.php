<?php

namespace Learni\Rest;

use Learni\Access\Access;
use Learni\Database\Progress;
use Learni\Database\Enrollments;
use Learni\PostTypes\Course;
use WP_Error;
use WP_REST_Request;

if (!defined('ABSPATH')) {
    exit;
}

final class Binomial
{
    private const FINAL_RETRY_COOLDOWN_DAYS = 7;
    private const RESTART_CYCLE_META_PREFIX = 'learni_binomial_cycle_after_attempt_';

    private static function restart_cycle_meta_key(int $course_id, int $quiz_id): string
    {
        return self::RESTART_CYCLE_META_PREFIX . $course_id . '_' . $quiz_id;
    }

    private static function restart_cycle_cutoff_attempt_id(int $user_id, int $course_id, int $quiz_id): int
    {
        if ($user_id <= 0 || $course_id <= 0 || $quiz_id <= 0) {
            return 0;
        }
        return (int) get_user_meta($user_id, self::restart_cycle_meta_key($course_id, $quiz_id), true);
    }

    private static function filter_attempt_series_after_cutoff(array $series, int $cutoff_attempt_id): array
    {
        if ($cutoff_attempt_id <= 0 || empty($series)) {
            return $series;
        }
        $out = [];
        foreach ($series as $a) {
            $aid = is_array($a) ? (int) ($a['attemptId'] ?? 0) : 0;
            if ($aid > $cutoff_attempt_id) {
                $out[] = $a;
            }
        }
        return $out;
    }

    private static function parse_mysql_timestamp(string $submitted_at): int
    {
        $submitted_at = trim($submitted_at);
        if ($submitted_at === '') {
            return 0;
        }
        $dt = date_create_immutable_from_format('Y-m-d H:i:s', $submitted_at, wp_timezone());
        if ($dt instanceof \DateTimeImmutable) {
            return (int) $dt->getTimestamp();
        }
        return (int) strtotime($submitted_at);
    }

    /**
     * @return array<int,array{attemptId:int,score:int,total:int,percent:int,submittedAt:string,phase:string}>
     */
    public static function attempt_series_for_user(int $quiz_id, int $user_id): array
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
     * @return array{
     *   initial:?array,
     *   latestFinal:?array,
     *   eligibleFinal:bool,
     *   finalFailed:bool,
     *   cooldownUntil:string,
     *   cooldownDaysRemaining:int,
     *   canTakeFinalNow:bool,
     *   restartCooldownUntil:string,
     *   restartCooldownDaysRemaining:int,
     *   canRestartNow:bool
     * }
     */
    public static function gate_state_for_user(int $course_id, int $quiz_id, int $user_id): array
    {
        $out = [
            'initial' => null,
            'latestFinal' => null,
            'eligibleFinal' => false,
            'finalFailed' => false,
            'cooldownUntil' => '',
            'cooldownDaysRemaining' => 0,
            'canTakeFinalNow' => false,
            'restartCooldownUntil' => '',
            'restartCooldownDaysRemaining' => 0,
            'canRestartNow' => false,
        ];

        if ($course_id <= 0 || $quiz_id <= 0 || $user_id <= 0) {
            return $out;
        }

        $cutoff_attempt_id = self::restart_cycle_cutoff_attempt_id($user_id, $course_id, $quiz_id);
        $series = self::attempt_series_for_user($quiz_id, $user_id);
        $series = self::filter_attempt_series_after_cutoff($series, $cutoff_attempt_id);
        if (empty($series)) {
            return $out;
        }

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
                $ts = self::parse_mysql_timestamp($submitted_at);
                if ($ts > 0) {
                    $cool_ts = $ts + (self::FINAL_RETRY_COOLDOWN_DAYS * DAY_IN_SECONDS);
                    $out['cooldownUntil'] = wp_date('Y-m-d H:i:s', $cool_ts, wp_timezone());
                    $now = (int) current_time('timestamp');
                    $diff = $cool_ts - $now;
                    if ($diff > 0) {
                        $out['cooldownDaysRemaining'] = (int) max(1, (int) ceil($diff / DAY_IN_SECONDS));
                    }
                }
            }
        }

        $out['canRestartNow'] = !empty($out['eligibleFinal']);
        if (!empty($out['eligibleFinal']) && is_array($latest_final)) {
            $quiz = self::quiz_for_course($course_id);
            $settings = is_array($quiz['settings'] ?? null) ? (array) ($quiz['settings'] ?? []) : [];
            $restart_days = max(0, (int) ($settings['restartCooldownDays'] ?? 0));
            if ($restart_days > 0) {
                $ts = self::parse_mysql_timestamp((string) ($latest_final['submittedAt'] ?? ''));
                if ($ts > 0) {
                    $cool_ts = $ts + ($restart_days * DAY_IN_SECONDS);
                    $out['restartCooldownUntil'] = wp_date('Y-m-d H:i:s', $cool_ts, wp_timezone());
                    $now = (int) current_time('timestamp');
                    $diff = $cool_ts - $now;
                    if ($diff > 0) {
                        $days_since = intdiv(max(0, $now - $ts), DAY_IN_SECONDS);
                        $out['restartCooldownDaysRemaining'] = (int) max(1, $restart_days - $days_since);
                        $out['canRestartNow'] = false;
                    }
                }
            }
        }

        if ($baseline !== null) {
            $summary = Progress::course_summary($user_id, $course_id);
            $lesson_percent = isset($summary['percent']) ? (int) $summary['percent'] : 0;
            if ($lesson_percent >= 100 && Access::user_can_access_course($user_id, $course_id) && !$out['eligibleFinal']) {
                $out['canTakeFinalNow'] = ($out['cooldownDaysRemaining'] <= 0);
            }
        }

        return $out;
    }

    public static function user_has_eligible_final(int $course_id, int $quiz_id, int $user_id): bool
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

        $gate = self::gate_state_for_user($course_id, $quiz_id, $user_id);
        return !empty($gate['eligibleFinal']);
    }

    public static function get_course_status(WP_REST_Request $request)
    {
        $course_id = (int) $request['id'];
        if ($course_id <= 0 || get_post_type($course_id) !== Course::POST_TYPE) {
            return new WP_Error('learni_invalid_course', 'Invalid course.', ['status' => 404]);
        }

        $user_id = (int) get_current_user_id();
        if ($user_id <= 0) {
            return new WP_Error('learni_login_required', 'Login required.', ['status' => 401]);
        }

        $partner_info = Routes::course_partner_info($course_id);

        $quiz = self::quiz_for_course($course_id);
        $quiz_id = (int) ($quiz['id'] ?? 0);

        $summary = Progress::course_summary($user_id, $course_id);
        $lesson_percent = isset($summary['percent']) ? (int) $summary['percent'] : 0;

        $certificate_available = Routes::course_certificate_eligible($course_id, $user_id);

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

        $gate = self::gate_state_for_user($course_id, $quiz_id, $user_id);
        $initial = is_array($gate['initial']) ? $gate['initial'] : null;
        $final = is_array($gate['latestFinal']) ? $gate['latestFinal'] : null;

        $cutoff_attempt_id = self::restart_cycle_cutoff_attempt_id($user_id, $course_id, $quiz_id);
        $series = self::attempt_series_for_user($quiz_id, $user_id);
        $series = self::filter_attempt_series_after_cutoff($series, $cutoff_attempt_id);
        $submitted_count = count($series);

        $needs_initial = !is_array($initial);
        $needs_final = is_array($initial) && !$gate['eligibleFinal'];
        $can_take_final = $needs_final && $lesson_percent >= 100 && (bool) ($gate['canTakeFinalNow'] ?? false);
        $can_restart = (bool) ($gate['eligibleFinal'] ?? false)
            && Access::user_can_access_course($user_id, $course_id)
            && (bool) ($gate['canRestartNow'] ?? false);

        if (!empty($partner_info['hasPartner']) && !empty($partner_info['otherUserId'])) {
            $other_user_id = (int) ($partner_info['otherUserId'] ?? 0);
            $other_lessons = (int) ($partner_info['otherLessonsPercent'] ?? 0);
            if ($other_user_id > 0) {
                $other_gate = self::gate_state_for_user($course_id, $quiz_id, $other_user_id);
                $other_initial = is_array($other_gate['initial']) ? $other_gate['initial'] : null;
                $other_final = is_array($other_gate['latestFinal']) ? $other_gate['latestFinal'] : null;

                $other_cutoff = self::restart_cycle_cutoff_attempt_id($other_user_id, $course_id, $quiz_id);
                $other_series = self::attempt_series_for_user($quiz_id, $other_user_id);
                $other_series = self::filter_attempt_series_after_cutoff($other_series, $other_cutoff);
                $partner_info['otherSubmittedCount'] = count($other_series);
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
                'restartCooldownUntil' => (string) ($gate['restartCooldownUntil'] ?? ''),
                'restartCooldownDaysRemaining' => (int) ($gate['restartCooldownDaysRemaining'] ?? 0),
            ],
        ];
    }

    public static function post_start(WP_REST_Request $request)
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
            $partner_info = Routes::course_partner_info($course_id);
            if (!empty($partner_info['hasPartner'])) {
                return new WP_Error('learni_cross_eval_required', 'Final quiz must be taken as Test Partner for courses with a partner.', ['status' => 400]);
            }
        }

        $quiz = self::quiz_for_course($course_id);
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

        $gate = self::gate_state_for_user($course_id, $quiz_id, $user_id);

        if ($phase === 'initial') {
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

    public static function post_restart(WP_REST_Request $request)
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

        $quiz = self::quiz_for_course($course_id);
        $quiz_id = (int) ($quiz['id'] ?? 0);
        if ($quiz_id <= 0) {
            return new WP_Error('learni_no_binomial', 'No binomial quiz configured for this course.', ['status' => 404]);
        }

        $gate = self::gate_state_for_user($course_id, $quiz_id, $user_id);
        $days_remaining = (int) ($gate['restartCooldownDaysRemaining'] ?? 0);
        $can_restart = !empty($gate['eligibleFinal']) && (bool) ($gate['canRestartNow'] ?? false) && $days_remaining <= 0;
        if (!$can_restart) {
            if ($days_remaining > 0) {
                return new WP_Error(
                    'learni_restart_cooldown',
                    'Restart is in cooldown.',
                    [
                        'status' => 400,
                        'cooldownDaysRemaining' => $days_remaining,
                        'cooldownUntil' => (string) ($gate['restartCooldownUntil'] ?? ''),
                    ]
                );
            }
            return new WP_Error('learni_restart_unavailable', 'Restart is only available after completing the Initial + Final quiz cycle.', ['status' => 400]);
        }

        $attempts_table = $wpdb ? ($wpdb->prefix . 'learni_quiz_attempts') : '';
        $last_attempt_id = 0;
        if ($attempts_table !== '') {
            $last_attempt_id = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT MAX(id)
                     FROM {$attempts_table}
                     WHERE quiz_id = %d AND user_id = %d AND status = %s",
                    $quiz_id,
                    $user_id,
                    'submitted'
                )
            );
        }

        $ok = Progress::reset_course($user_id, $course_id);
        if (!$ok) {
            return new WP_Error('learni_restart_failed', 'Failed to restart course.', ['status' => 500]);
        }

        if ($last_attempt_id > 0) {
            update_user_meta($user_id, self::restart_cycle_meta_key($course_id, $quiz_id), $last_attempt_id);
        }

        return [
            'ok' => true,
            'courseId' => $course_id,
        ];
    }

    /**
     * @return array{id:int,title:string,passingScore:int,settings:array<string,mixed>}
     */
    public static function quiz_for_course(int $course_id): array
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
            if (empty($fallback) && empty($row['lesson_post_id'])) {
                $fallback = [
                    'id' => (int) ($row['id'] ?? 0),
                    'title' => (string) ($row['title'] ?? ''),
                    'passingScore' => (int) ($row['passing_score'] ?? 0),
                    'settings' => $settings,
                ];
            }
        }

        return !empty($fallback) ? $fallback : ['id' => 0, 'title' => '', 'passingScore' => 0, 'settings' => []];
    }

    public static function questions_payload(int $quiz_id, array $question_ids, array $answer_order): array
    {
        global $wpdb;
        if (empty($question_ids)) {
            return [];
        }

        $in_q = implode(',', array_fill(0, count($question_ids), '%d'));
        $q_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, prompt, meta_json
                 FROM {$wpdb->prefix}learni_quiz_questions
                 WHERE id IN ($in_q)",
                $question_ids
            ),
            ARRAY_A
        );

        $q_by_id = [];
        $q_meta_by_id = [];
        foreach ($q_rows as $q) {
            $qid = (int) ($q['id'] ?? 0);
            if ($qid > 0) {
                $q_by_id[$qid] = (string) ($q['prompt'] ?? '');
                $meta_json = isset($q['meta_json']) ? (string) $q['meta_json'] : '';
                $decoded = $meta_json !== '' ? json_decode($meta_json, true) : null;
                $image_id = 0;
                if (is_array($decoded)) {
                    $image_id = (int) ($decoded['image_id'] ?? 0);
                }
                $q_meta_by_id[$qid] = [
                    'imageId' => $image_id,
                    'imageUrl' => $image_id > 0 ? (string) wp_get_attachment_image_url($image_id, 'large') : '',
                ];
            }
        }

        $a_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, question_id, answer_text, meta_json
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
                $meta_json = isset($a['meta_json']) ? (string) $a['meta_json'] : '';
                $decoded = $meta_json !== '' ? json_decode($meta_json, true) : null;
                $image_id = 0;
                if (is_array($decoded)) {
                    $image_id = (int) ($decoded['image_id'] ?? 0);
                }
                $answers_by_id[$aid] = [
                    'id' => $aid,
                    'questionId' => $qid,
                    'text' => (string) ($a['answer_text'] ?? ''),
                    'imageId' => $image_id,
                    'imageUrl' => $image_id > 0 ? (string) wp_get_attachment_image_url($image_id, 'medium') : '',
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
                            'imageId' => (int) ($answers_by_id[$aid]['imageId'] ?? 0),
                            'imageUrl' => (string) ($answers_by_id[$aid]['imageUrl'] ?? ''),
                        ];
                    }
                }
            } else {
                foreach ($answers_by_id as $aid => $a) {
                    if ((int) ($a['questionId'] ?? 0) === (int) $qid) {
                        $answers[] = [
                            'id' => (int) $aid,
                            'text' => (string) ($a['text'] ?? ''),
                            'imageId' => (int) ($a['imageId'] ?? 0),
                            'imageUrl' => (string) ($a['imageUrl'] ?? ''),
                        ];
                    }
                }
            }

            $out[] = [
                'id' => (int) $qid,
                'prompt' => isset($q_by_id[$qid]) ? (string) $q_by_id[$qid] : '',
                'imageId' => isset($q_meta_by_id[$qid]) ? (int) ($q_meta_by_id[$qid]['imageId'] ?? 0) : 0,
                'imageUrl' => isset($q_meta_by_id[$qid]) ? (string) ($q_meta_by_id[$qid]['imageUrl'] ?? '') : '',
                'answers' => $answers,
            ];
        }

        return $out;
    }

    public static function attempt_public_payload(array $row): array
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
