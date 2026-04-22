<?php

namespace Learni\QuizEditor;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * High-level orchestration for Learni Quiz Editor.
 */
final class QuizEditor
{
    public static function get_quiz_data(int $quiz_id): ?array
    {
        $quiz = QuizRepository::get_quiz_raw($quiz_id);
        if (!is_array($quiz)) {
            return null;
        }

        $settings = [
            'random_questions' => 0,
            'random_answers' => 1,
            'run_once' => 0,
            'force_solve' => 0,
            'show_points' => 0,
            'questions_per_attempt' => 0,
            'questions_subset_random' => 0,
            'questionOrder' => 'in_order',
        ];

        if (!empty($quiz['settings_json'])) {
            $decoded = json_decode((string) $quiz['settings_json'], true);
            if (is_array($decoded)) {
                foreach ($settings as $k => $v) {
                    if (isset($decoded[$k])) {
                        $settings[$k] = $decoded[$k];
                    }
                }
                if (isset($decoded['shuffleAnswers']) && !isset($decoded['random_answers'])) {
                    $settings['random_answers'] = !empty($decoded['shuffleAnswers']) ? 1 : 0;
                }
                if (isset($decoded['questionOrder']) && (string) $decoded['questionOrder'] === 'random' && !isset($decoded['random_questions'])) {
                    $settings['random_questions'] = 1;
                }
            }
        }
        $settings['random_answers'] = 1;

        $questions = QuizRepository::get_questions_raw($quiz_id);
        $out_questions = [];

        foreach ($questions as $q) {
            $qid = (int) $q['id'];
            $meta = json_decode((string) $q['meta_json'], true) ?: [];
            $answers = QuizRepository::get_answers_raw($qid);
            $out_answers = [];

            foreach ($answers as $a) {
                $text = trim((string) $a['answer_text']);
                if ($text === '') continue;
                $out_answers[] = [
                    'text' => $text,
                    'correct' => !empty($a['is_correct']),
                    'points' => 0,
                ];
            }

            $title = (string) ($meta['title'] ?? '');
            if ($title === '') {
                $plain = trim(wp_strip_all_tags((string) $q['prompt']));
                $plain = trim((string) preg_replace('/\s+/', ' ', $plain));
                $title = strlen($plain) > 64 ? (substr($plain, 0, 64) . '…') : $plain;
            }

            $out_questions[] = [
                'id' => $qid,
                'pro_id' => 0,
                'title' => $title,
                'question_text' => (string) $q['prompt'],
                'answers' => $out_answers,
            ];
        }

        return [
            'id' => (int) $quiz['id'],
            'title' => (string) $quiz['title'],
            'passing_score' => (int) $quiz['passing_score'],
            'time_limit_seconds' => (int) $quiz['time_limit_seconds'],
            'settings' => $settings,
            'questions' => $out_questions,
        ];
    }

    public static function create_quiz(array $quiz_data)
    {
        global $wpdb;
        $title = sanitize_text_field((string) ($quiz_data['title'] ?? ''));
        $settings = $quiz_data['settings'] ?? [];
        $course_id = (int) ($settings['course_id'] ?? 0);

        if ($title === '' || $course_id <= 0) {
            return new WP_Error('invalid_data', __('Quiz title and course are required.', 'politeia-learning'));
        }

        $questions = $quiz_data['questions'] ?? [];
        $normalized = self::normalize_payload($questions);
        if (is_wp_error($normalized)) return $normalized;

        $existing_id = QuizRepository::get_quiz_id_by_course($course_id);
        $question_order = sanitize_text_field((string) ($settings['questionOrder'] ?? ''));
        if ($question_order !== 'random' && $question_order !== 'in_order') {
            $question_order = !empty($settings['random_questions']) ? 'random' : 'in_order';
        }

        $settings_json = wp_json_encode([
            'questionOrder' => $question_order,
            'random_questions' => $question_order === 'random' ? 1 : 0,
            'shuffleAnswers' => true,
            'random_answers' => 1,
            'run_once' => (int) ($settings['run_once'] ?? 0),
            'force_solve' => (int) ($settings['force_solve'] ?? 0),
            'show_points' => (int) ($settings['show_points'] ?? 0),
        ]);

        $passing = (int) ($settings['passing_percentage'] ?? 80);
        $time_limit = (int) ($settings['time_limit'] ?? 0);
        $quizzes_table = $wpdb->prefix . 'learni_quizzes';

        if ($existing_id > 0) {
            $wpdb->update($quizzes_table, [
                'title' => $title,
                'passing_score' => $passing,
                'time_limit_seconds' => $time_limit,
                'settings_json' => $settings_json,
            ], ['id' => $existing_id]);
            QuizRepository::delete_quiz_children($existing_id);
            $quiz_id = $existing_id;
        } else {
            $wpdb->insert($quizzes_table, [
                'course_post_id' => $course_id,
                'title' => $title,
                'passing_score' => $passing,
                'time_limit_seconds' => $time_limit,
                'settings_json' => $settings_json,
                'created_at' => current_time('mysql'),
            ]);
            $quiz_id = (int) $wpdb->insert_id;
        }

        $results = QuizRepository::insert_questions($quiz_id, $normalized);

        return [
            'quiz_post_id' => $quiz_id,
            'questions' => $results,
        ];
    }

    public static function save_changes(array $payload): bool
    {
        global $wpdb;
        $quiz_id = (int) ($payload['quiz_id'] ?? 0);
        if ($quiz_id <= 0) return false;

        $questions = $payload['questions'] ?? [];
        $question_table = $wpdb->prefix . 'learni_quiz_questions';
        $answer_table = $wpdb->prefix . 'learni_quiz_answers';

        foreach (array_values($questions) as $index => $q) {
            $qid = (int) ($q['id'] ?? 0);
            if ($qid <= 0) continue;

            $wpdb->update($question_table, [
                'prompt' => wp_kses_post((string) ($q['question_text'] ?? '')),
                'sort_order' => (int) $index,
                'meta_json' => wp_json_encode(['title' => sanitize_text_field((string) ($q['title'] ?? ''))]),
            ], ['id' => $qid, 'quiz_id' => $quiz_id]);

            $wpdb->delete($answer_table, ['question_id' => $qid]);
            $answers = $q['answers'] ?? [];
            foreach (array_values($answers) as $a_index => $a) {
                $text = sanitize_text_field((string) ($a['text'] ?? ''));
                if ($text === '') continue;
                $wpdb->insert($answer_table, [
                    'question_id' => $qid,
                    'answer_text' => $text,
                    'is_correct' => !empty($a['correct']) ? 1 : 0,
                    'sort_order' => (int) $a_index,
                ]);
            }
        }

        if (!empty($payload['title'])) {
            $wpdb->update($wpdb->prefix . 'learni_quizzes', [
                'title' => sanitize_text_field((string) $payload['title'])
            ], ['id' => $quiz_id]);
        }

        return true;
    }

    public static function replace_questions(int $quiz_id, array $questions)
    {
        if (!QuizRepository::quiz_exists($quiz_id)) return new WP_Error('not_found', 'Quiz not found');
        $normalized = self::normalize_payload($questions);
        if (is_wp_error($normalized)) return $normalized;

        QuizRepository::delete_quiz_children($quiz_id);
        $results = QuizRepository::insert_questions($quiz_id, $normalized);

        $inserted = count(array_filter($results, fn($r) => !empty($r['success'])));
        return ['total_questions' => count($normalized), 'questions_inserted' => $inserted];
    }

    public static function append_questions(int $quiz_id, array $questions)
    {
        if (!QuizRepository::quiz_exists($quiz_id)) return new WP_Error('not_found', 'Quiz not found');
        $normalized = self::normalize_payload($questions);
        if (is_wp_error($normalized)) return $normalized;

        global $wpdb;
        $offset = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}learni_quiz_questions WHERE quiz_id = %d", $quiz_id));
        $replaced = false;

        if ($offset === 1 && QuizRepository::has_demo_placeholder($quiz_id)) {
            QuizRepository::delete_quiz_children($quiz_id);
            $offset = 0;
            $replaced = true;
        }

        $results = QuizRepository::insert_questions($quiz_id, $normalized, $offset);
        $inserted = count(array_filter($results, fn($r) => !empty($r['success'])));

        return [
            'total_questions' => $offset + count($normalized),
            'questions_inserted' => $inserted,
            'go_to_index' => $replaced ? 0 : $offset,
            'replaced_placeholder' => $replaced ? 1 : 0,
        ];
    }

    public static function insert_default_question(int $quiz_id, int $after_index = -1, array $opts = [])
    {
        global $wpdb;
        $answers_per_question = max(2, min(8, (int) ($opts['answers_per_question'] ?? 4)));
        $question_table = $wpdb->prefix . 'learni_quiz_questions';
        $answer_table = $wpdb->prefix . 'learni_quiz_answers';

        $count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$question_table} WHERE quiz_id = %d", $quiz_id));
        $insert_pos = max(0, min($count, $after_index + 1));

        $wpdb->query($wpdb->prepare("UPDATE {$question_table} SET sort_order = sort_order + 1 WHERE quiz_id = %d AND sort_order >= %d", $quiz_id, $insert_pos));

        $wpdb->insert($question_table, [
            'quiz_id' => $quiz_id,
            'type' => 'single',
            'prompt' => '',
            'points' => 1,
            'sort_order' => $insert_pos,
            'meta_json' => wp_json_encode(['title' => sprintf('Pregunta %d', $insert_pos + 1)]),
        ]);

        $qid = (int) $wpdb->insert_id;
        for ($i = 0; $i < $answers_per_question; $i++) {
            $wpdb->insert($answer_table, [
                'question_id' => $qid,
                'answer_text' => sprintf('Opción %s', chr(ord('A') + $i)),
                'is_correct' => $i === 0 ? 1 : 0,
                'sort_order' => $i,
            ]);
        }

        return ['question_id' => $qid, 'total_questions' => $count + 1];
    }

    public static function update_settings(int $quiz_id, array $settings)
    {
        global $wpdb;
        $row = QuizRepository::get_quiz_raw($quiz_id);
        if (!$row) return new WP_Error('not_found', 'Examen no encontrado');

        $next = json_decode((string) ($row['settings_json'] ?? ''), true) ?: [];
        $allowed = [
            'questions_per_attempt' => 'int',
            'questions_subset_random' => 'bool',
            'questionOrder' => 'string',
            'run_once' => 'bool',
            'force_solve' => 'bool',
            'show_points' => 'bool',
        ];

        foreach ($allowed as $key => $type) {
            if (isset($settings[$key])) {
                $val = $settings[$key];
                if ($type === 'int') $val = max(0, (int) $val);
                if ($type === 'bool') $val = !empty($val) ? 1 : 0;
                if ($type === 'string') $val = sanitize_text_field((string) $val);
                $next[$key] = $val;
            }
        }

        $next['shuffleAnswers'] = true;
        $next['random_answers'] = 1;
        $next['random_questions'] = (isset($next['questionOrder']) && $next['questionOrder'] === 'random') ? 1 : 0;

        $wpdb->update($wpdb->prefix . 'learni_quizzes', ['settings_json' => wp_json_encode($next)], ['id' => $quiz_id]);
        return $next;
    }

    public static function normalize_payload(array $questions)
    {
        $out = [];
        foreach ($questions as $i => $q) {
            if (!is_array($q)) continue;
            $text = wp_kses_post((string) ($q['question_text'] ?? ($q['text'] ?? '')));
            if (trim(wp_strip_all_tags($text)) === '') continue;

            $title = sanitize_text_field((string) ($q['title'] ?? ''));
            if ($title === '') {
                $plain = trim(wp_strip_all_tags($text));
                $title = strlen($plain) > 64 ? (substr($plain, 0, 64) . '…') : ($plain ?: "Pregunta " . ($i+1));
            }

            $answers = (array) ($q['answers'] ?? []);
            $norm_answers = [];
            $has_correct = false;
            foreach ($answers as $a) {
                if (!is_array($a)) continue;
                $a_text = sanitize_text_field((string) ($a['text'] ?? ''));
                if ($a_text === '') continue;
                $correct = !empty($a['correct']);
                if ($correct) $has_correct = true;
                $norm_answers[] = ['text' => $a_text, 'correct' => $correct];
            }

            if (count($norm_answers) < 2) return new WP_Error('invalid_answers', 'Cada pregunta debe tener al menos 2 respuestas.');
            if (!$has_correct) $norm_answers[0]['correct'] = true;

            $out[] = ['title' => $title, 'prompt' => $text, 'answers' => $norm_answers];
        }
        return empty($out) ? new WP_Error('empty', 'No hay preguntas.') : $out;
    }
}
