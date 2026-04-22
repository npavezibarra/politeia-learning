<?php

namespace Learni\Rest;

use Learni\Access\Access;
use Learni\Database\Progress;
use Learni\Database\Enrollments;
use Learni\PostTypes\Course;
use WP_Error;
use WP_REST_Request;

final class CrossEval
{
    public static function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'learni_cross_eval_sessions';
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function get_session_row(int $session_id): ?array
    {
        global $wpdb;
        if ($session_id <= 0 || !$wpdb) {
            return null;
        }
        $table = self::table();
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

    public static function maybe_expire(array $row): array
    {
        global $wpdb;
        $status = (string) ($row['status'] ?? '');
        $expires_at = (string) ($row['expires_at'] ?? '');
        $id = (int) ($row['id'] ?? 0);
        if ($id > 0 && $status === 'pending' && $expires_at !== '') {
            $now_ts = (int) current_time('timestamp');
            $exp_ts = (int) strtotime($expires_at);
            if ($exp_ts > 0 && $now_ts > $exp_ts) {
                $table = self::table();
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

    public static function post_create(WP_REST_Request $request)
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

        $ids = Routes::course_partner_users($course_id);
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

        $quiz = Binomial::quiz_for_course($course_id);
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

        $target_gate = Binomial::gate_state_for_user($course_id, $quiz_id, $target_user_id);
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

        $table = self::table();

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

    public static function get_pending(WP_REST_Request $request)
    {
        global $wpdb;

        $user_id = (int) get_current_user_id();
        if ($user_id <= 0) {
            return new WP_Error('learni_login_required', 'Login required.', ['status' => 401]);
        }

        $table = self::table();
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
            $r_full = self::get_session_row((int) ($r['id'] ?? 0));
            if (!$r_full) {
                continue;
            }
            $r_full = self::maybe_expire($r_full);
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

    public static function get_session(WP_REST_Request $request)
    {
        $session_id = (int) $request['id'];
        $row = self::get_session_row($session_id);
        if (!$row) {
            return new WP_Error('learni_cross_eval_not_found', 'Not found.', ['status' => 404]);
        }

        $row = self::maybe_expire($row);

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

    public static function post_respond(WP_REST_Request $request)
    {
        global $wpdb;

        $session_id = (int) $request['id'];
        $decision = (string) $request->get_param('decision');

        $row = self::get_session_row($session_id);
        if (!$row) {
            return new WP_Error('learni_cross_eval_not_found', 'Not found.', ['status' => 404]);
        }
        $row = self::maybe_expire($row);

        if ((string) ($row['status'] ?? '') !== 'pending') {
            return new WP_Error('learni_cross_eval_closed', 'Session is not pending.', ['status' => 400]);
        }

        $user_id = (int) get_current_user_id();
        $target_id = (int) ($row['target_user_id'] ?? 0);
        if ($user_id !== $target_id) {
            return new WP_Error('learni_forbidden', 'No access.', ['status' => 403]);
        }

        $new_status = ($decision === 'accept') ? 'accepted' : 'declined';
        $table = self::table();
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

    public static function post_cancel(WP_REST_Request $request)
    {
        global $wpdb;

        $session_id = (int) $request['id'];
        $row = self::get_session_row($session_id);
        if (!$row) {
            return new WP_Error('learni_cross_eval_not_found', 'Not found.', ['status' => 404]);
        }
        $row = self::maybe_expire($row);

        $user_id = (int) get_current_user_id();
        $initiator_id = (int) ($row['initiator_user_id'] ?? 0);
        if ($user_id !== $initiator_id && !current_user_can('manage_options')) {
            return new WP_Error('learni_forbidden', 'No access.', ['status' => 403]);
        }

        $status = (string) ($row['status'] ?? '');
        if (!in_array($status, ['pending', 'accepted'], true)) {
            return new WP_Error('learni_cross_eval_closed', 'Session is not cancelable.', ['status' => 400]);
        }

        $table = self::table();
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

    public static function post_binomial_start(WP_REST_Request $request)
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

        $row = self::get_session_row($session_id);
        if (!$row) {
            return new WP_Error('learni_cross_eval_not_found', 'Not found.', ['status' => 404]);
        }
        $row = self::maybe_expire($row);

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
        $quiz = Binomial::quiz_for_course($course_id);
        $quiz_id = (int) ($quiz['id'] ?? 0);
        if ($quiz_id <= 0) {
            return new WP_Error('learni_no_binomial', 'No binomial quiz configured for this course.', ['status' => 404]);
        }

        $summary = Progress::course_summary($target_user_id, $course_id);
        $lesson_percent = isset($summary['percent']) ? (int) $summary['percent'] : 0;
        if ($lesson_percent < 100) {
            return new WP_Error('learni_cross_eval_unavailable', 'Target has not completed all lessons.', ['status' => 400]);
        }

        $target_gate = Binomial::gate_state_for_user($course_id, $quiz_id, $target_user_id);

        if (!is_array($target_gate['initial'])) {
            return new WP_Error('learni_initial_required', 'Initial quiz must be completed first.', ['status' => 400]);
        }
        if (!empty($target_gate['eligibleFinal'])) {
            return new WP_Error('learni_final_already_eligible', 'Final quiz already meets the certificate requirement.', ['status' => 400]);
        }
        $days_remaining = (int) ($target_gate['cooldownDaysRemaining'] ?? 0);
        if ($days_remaining > 0) {
            return new WP_Error(
                'learni_final_cooldown',
                'Final quiz is in cooldown.',
                [
                    'status' => 400,
                    'cooldownDaysRemaining' => $days_remaining,
                    'cooldownUntil' => (string) ($target_gate['cooldownUntil'] ?? ''),
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
        $table = self::table();
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

        $questions_payload = Binomial::questions_payload($quiz_id, $question_ids, $answer_order);

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

    public static function post_submit(WP_REST_Request $request)
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

        $session = self::get_session_row($session_id);
        if (!$session) {
            return new WP_Error('learni_cross_eval_not_found', 'Not found.', ['status' => 404]);
        }
        $session = self::maybe_expire($session);

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

        $correct_rows = Routes::correct_answer_ids_by_question($question_ids);
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
        $passing_score = $quiz_id > 0 ? Routes::quiz_passing_score($quiz_id) : 0;
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
        $table = self::table();
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
            $gate = Binomial::gate_state_for_user($course_id, $quiz_id, $target_user_id);
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

                $eligible = Routes::course_certificate_eligible($course_id, $rid);
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
}
