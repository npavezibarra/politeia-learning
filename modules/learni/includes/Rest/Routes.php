<?php

namespace Learni\Rest;

use Learni\Access\Access;
use Learni\Database\Progress;
use Learni\PostTypes\Course;
use WP_Error;
use WP_REST_Request;

final class Routes
{
    public const REST_NAMESPACE = 'learni/v1';

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

        $quiz = self::binomial_quiz_for_course($course_id);
        $quiz_id = (int) ($quiz['id'] ?? 0);

        $summary = Progress::course_summary($user_id, $course_id);
        $lesson_percent = isset($summary['percent']) ? (int) $summary['percent'] : 0;

        if ($quiz_id <= 0) {
            return [
                'courseId' => $course_id,
                'quizId' => 0,
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
        $can_take_final = $needs_final && $lesson_percent >= 100 && Access::user_can_access_course($user_id, $course_id);
        $can_restart = $submitted_count > 0 && $submitted_count % 2 === 0 && $lesson_percent >= 100 && Access::user_can_access_course($user_id, $course_id);

        return [
            'courseId' => $course_id,
            'quizId' => $quiz_id,
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
            ],
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

        if ($phase === 'initial') {
            if ($submitted_count % 2 === 1) {
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
            if ($submitted_count % 2 !== 1) {
                return new WP_Error('learni_initial_required', 'Initial quiz must be completed first.', ['status' => 400]);
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

            $shuffle_answers = isset($quiz_settings['shuffleAnswers']) ? (bool) $quiz_settings['shuffleAnswers'] : !empty($quiz_settings['random_answers']);
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

        return [
            'ok' => true,
            'attemptId' => $attempt_id,
            'score' => $score,
            'total' => $total,
            'percent' => $percent,
            'passed' => $passed,
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
                 ORDER BY id ASC",
                $course_id
            ),
            ARRAY_A
        );

        $fallback = [];
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
                return [
                    'id' => (int) ($row['id'] ?? 0),
                    'title' => (string) ($row['title'] ?? ''),
                    'passingScore' => (int) ($row['passing_score'] ?? 0),
                    'settings' => $settings,
                ];
            }
            if (empty($row['lesson_post_id'])) {
                $fallback = [
                    'id' => (int) ($row['id'] ?? 0),
                    'title' => (string) ($row['title'] ?? ''),
                    'passingScore' => (int) ($row['passing_score'] ?? 0),
                    'settings' => $settings,
                ];
                $fallback_count++;
            }
        }

        // If there's exactly one course-level quiz, treat it as the binomial quiz.
        if ($fallback_count === 1) {
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
}

