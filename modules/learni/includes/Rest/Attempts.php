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

final class Attempts
{
    public static function post_submit(WP_REST_Request $request)
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
        $passing_score = Routes::quiz_passing_score($quiz_id);
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
                $gate = Binomial::gate_state_for_user($course_id, $quiz_id, $user_id);
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
                $eligible = Routes::course_certificate_eligible($course_id, $user_id);
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
}
