<?php

namespace Learni\QuizEditor;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Low-level database operations for Learni Quizzes.
 */
final class QuizRepository
{
    private const TABLE_QUIZZES = 'learni_quizzes';
    private const TABLE_QUESTIONS = 'learni_quiz_questions';
    private const TABLE_ANSWERS = 'learni_quiz_answers';

    public static function get_quiz_id_by_course(int $course_id): int
    {
        if ($course_id <= 0) {
            return 0;
        }

        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_QUIZZES;
        $question_table = $wpdb->prefix . self::TABLE_QUESTIONS;
        
        $rows = $wpdb->get_results(
            "SELECT q.id, q.title, COUNT(qq.id) AS question_count, q.course_post_id
             FROM {$table} q
             LEFT JOIN {$question_table} qq ON qq.quiz_id = q.id
             WHERE (q.lesson_post_id IS NULL OR q.lesson_post_id = 0)
             GROUP BY q.id
             ORDER BY question_count DESC, q.id DESC",
            ARRAY_A
        );

        if (empty($rows)) {
            return 0;
        }

        $course_title = trim((string) get_the_title($course_id));
        $course_slug = function_exists('sanitize_title') ? sanitize_title($course_title) : strtolower(trim($course_title));
        $best_id = 0;
        $best_score = -1;

        foreach ((array) $rows as $row) {
            $candidate_id = (int) ($row['id'] ?? 0);
            if ($candidate_id <= 0) {
                continue;
            }

            $question_count = (int) ($row['question_count'] ?? 0);
            $candidate_title = trim((string) ($row['title'] ?? ''));
            $candidate_slug = function_exists('sanitize_title') ? sanitize_title($candidate_title) : strtolower($candidate_title);

            $score = $question_count;

            if ((int) ($row['course_post_id'] ?? 0) === $course_id) {
                $score += 10;
            }

            if ($course_slug !== '' && $candidate_slug !== '' && $candidate_slug === $course_slug) {
                $score += 5;
            }

            if ($question_count <= 1 && $course_slug !== '' && $candidate_slug !== '' && $candidate_slug === $course_slug) {
                $score -= 5;
            }

            if ($score > $best_score || ($score === $best_score && $candidate_id > $best_id)) {
                $best_id = $candidate_id;
                $best_score = $score;
            }
        }

        return $best_id > 0 ? $best_id : 0;
    }

    public static function get_course_id_by_quiz_id(int $quiz_id): int
    {
        if ($quiz_id <= 0) {
            return 0;
        }

        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_QUIZZES;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT course_post_id FROM {$table} WHERE id = %d LIMIT 1",
                $quiz_id
            )
        );
    }

    public static function quiz_exists(int $quiz_id): bool
    {
        if ($quiz_id <= 0) {
            return false;
        }

        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_QUIZZES;
        return (int) $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM {$table} WHERE id = %d LIMIT 1", $quiz_id)
        ) > 0;
    }

    public static function delete_quiz(int $quiz_id): bool
    {
        if ($quiz_id <= 0) {
            return false;
        }

        self::delete_quiz_children($quiz_id);

        global $wpdb;
        $quiz_table = $wpdb->prefix . self::TABLE_QUIZZES;
        $wpdb->delete($quiz_table, ['id' => $quiz_id], ['%d']);

        return true;
    }

    public static function delete_quiz_children(int $quiz_id): void
    {
        global $wpdb;
        $question_table = $wpdb->prefix . self::TABLE_QUESTIONS;
        $answer_table = $wpdb->prefix . self::TABLE_ANSWERS;

        $question_ids = $wpdb->get_col(
            $wpdb->prepare("SELECT id FROM {$question_table} WHERE quiz_id = %d", $quiz_id)
        );

        foreach ((array) $question_ids as $qid) {
            $qid = (int) $qid;
            if ($qid <= 0) {
                continue;
            }
            $wpdb->delete($answer_table, ['question_id' => $qid], ['%d']);
        }

        $wpdb->delete($question_table, ['quiz_id' => $quiz_id], ['%d']);
    }

    public static function delete_question(int $quiz_id, int $question_id): bool
    {
        if ($quiz_id <= 0 || $question_id <= 0) {
            return false;
        }

        global $wpdb;
        $question_table = $wpdb->prefix . self::TABLE_QUESTIONS;
        $answer_table = $wpdb->prefix . self::TABLE_ANSWERS;

        $wpdb->delete($answer_table, ['question_id' => $question_id], ['%d']);
        $wpdb->delete($question_table, ['id' => $question_id, 'quiz_id' => $quiz_id], ['%d', '%d']);

        return true;
    }

    public static function insert_questions(int $quiz_id, array $questions, int $offset = 0): array
    {
        global $wpdb;
        $question_table = $wpdb->prefix . self::TABLE_QUESTIONS;
        $answer_table = $wpdb->prefix . self::TABLE_ANSWERS;

        $results = [];
        $offset = max(0, (int) $offset);
        foreach (array_values($questions) as $index => $q) {
            $ok = $wpdb->insert(
                $question_table,
                [
                    'quiz_id' => $quiz_id,
                    'type' => 'single',
                    'prompt' => (string) ($q['prompt'] ?? ''),
                    'explanation' => null,
                    'points' => 1,
                    'sort_order' => (int) ($offset + $index),
                    'meta_json' => wp_json_encode(['title' => (string) ($q['title'] ?? '')]),
                ],
                ['%d', '%s', '%s', '%s', '%d', '%d', '%s']
            );

            if (!$ok) {
                $results[] = ['success' => false, 'question_id' => 0];
                continue;
            }

            $question_id = (int) $wpdb->insert_id;
            $a_index = 0;
            foreach ((array) ($q['answers'] ?? []) as $a) {
                $wpdb->insert(
                    $answer_table,
                    [
                        'question_id' => $question_id,
                        'answer_text' => (string) ($a['text'] ?? ''),
                        'is_correct' => !empty($a['correct']) ? 1 : 0,
                        'sort_order' => $a_index,
                        'meta_json' => null,
                    ],
                    ['%d', '%s', '%d', '%d', '%s']
                );
                $a_index++;
            }

            $results[] = ['success' => true, 'question_id' => $question_id];
        }

        return $results;
    }

    public static function get_quiz_raw(int $quiz_id): ?array
    {
        global $wpdb;
        $quiz_table = $wpdb->prefix . self::TABLE_QUIZZES;
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, title, passing_score, time_limit_seconds, settings_json 
                 FROM {$quiz_table} WHERE id = %d LIMIT 1",
                $quiz_id
            ),
            ARRAY_A
        );
    }

    public static function get_questions_raw(int $quiz_id): array
    {
        global $wpdb;
        $question_table = $wpdb->prefix . self::TABLE_QUESTIONS;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, prompt, sort_order, meta_json
                 FROM {$question_table}
                 WHERE quiz_id = %d
                 ORDER BY sort_order ASC, id ASC",
                $quiz_id
            ),
            ARRAY_A
        );
    }

    public static function get_answers_raw(int $question_id): array
    {
        global $wpdb;
        $answer_table = $wpdb->prefix . self::TABLE_ANSWERS;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT answer_text, is_correct, sort_order
                 FROM {$answer_table}
                 WHERE question_id = %d
                 ORDER BY sort_order ASC, id ASC",
                $question_id
            ),
            ARRAY_A
        );
    }

    public static function has_demo_placeholder(int $quiz_id): bool
    {
        global $wpdb;
        $question_table = $wpdb->prefix . self::TABLE_QUESTIONS;
        $answer_table = $wpdb->prefix . self::TABLE_ANSWERS;

        $q = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, prompt, meta_json
                 FROM {$question_table}
                 WHERE quiz_id = %d
                 ORDER BY sort_order ASC, id ASC
                 LIMIT 1",
                $quiz_id
            ),
            ARRAY_A
        );
        if (!is_array($q) || empty($q['id'])) {
            return false;
        }

        $prompt_plain = trim(wp_strip_all_tags((string) $q['prompt']));
        if ($prompt_plain !== '') {
            return false;
        }

        $meta = json_decode((string) $q['meta_json'], true);
        $title = strtolower(trim((string) ($meta['title'] ?? '')));
        if (!in_array($title, ['pregunta 1', 'question 1'], true)) {
            return false;
        }

        $answers = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT answer_text FROM {$answer_table} WHERE question_id = %d ORDER BY sort_order ASC LIMIT 2",
                (int) $q['id']
            ),
            ARRAY_A
        );

        if (!is_array($answers) || count($answers) < 2) {
            return false;
        }

        $a1 = strtolower(trim((string) ($answers[0]['answer_text'] ?? '')));
        $a2 = strtolower(trim((string) ($answers[1]['answer_text'] ?? '')));

        return ($a1 === 'respuesta 1' && $a2 === 'respuesta 2') || ($a1 === 'answer 1' && $a2 === 'answer 2');
    }
}
