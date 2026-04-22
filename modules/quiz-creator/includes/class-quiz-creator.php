<?php
/**
 * Quiz Creator Class (Learni-backed)
 *
 * Stores quizzes/questions/answers in Learni tables:
 * - {$wpdb->prefix}learni_quizzes
 * - {$wpdb->prefix}learni_quiz_questions
 * - {$wpdb->prefix}learni_quiz_answers
 */

if (!defined('ABSPATH')) {
    exit;
}

class PQC_Quiz_Creator
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
            "SELECT q.id, q.title, COUNT(qq.id) AS question_count
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
                // Low-confidence stub: keep it available, but let any richer match win.
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
        if (!$wpdb) {
            return false;
        }

        $table = $wpdb->prefix . self::TABLE_QUIZZES;
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE id = %d LIMIT 1",
                $quiz_id
            )
        ) > 0;
    }

    /**
     * @param array{title:string,settings:array,questions:array} $quiz_data
     * @return array|WP_Error
     */
    public static function create_quiz(array $quiz_data)
    {
        global $wpdb;
        if (!$wpdb) {
            return new WP_Error('db_missing', __('Database unavailable.', 'politeia-quiz-creator'));
        }

        $title = sanitize_text_field((string) ($quiz_data['title'] ?? ''));
        $settings = isset($quiz_data['settings']) && is_array($quiz_data['settings']) ? $quiz_data['settings'] : [];
        $course_id = (int) ($settings['course_id'] ?? 0);
        if ($title === '' || $course_id <= 0) {
            return new WP_Error('invalid_data', __('Quiz title and course are required.', 'politeia-quiz-creator'));
        }

        if (get_post_type($course_id) !== 'learni_course') {
            return new WP_Error('invalid_course', __('Invalid course.', 'politeia-quiz-creator'));
        }

        $questions = isset($quiz_data['questions']) && is_array($quiz_data['questions']) ? $quiz_data['questions'] : [];
        $normalized = self::normalize_questions_payload($questions);
        if (is_wp_error($normalized)) {
            return $normalized;
        }

        $quizzes_table = $wpdb->prefix . self::TABLE_QUIZZES;

        // One evaluation quiz per course (overwrite the best matching existing one).
        $existing_id = self::get_quiz_id_by_course($course_id);

        $question_order = isset($settings['questionOrder']) ? sanitize_text_field((string) $settings['questionOrder']) : '';
        if ($question_order !== 'random' && $question_order !== 'in_order') {
            $question_order = !empty($settings['random_questions']) ? 'random' : 'in_order';
        }

        $settings_json = wp_json_encode(
            [
                'questionOrder' => $question_order,
                'random_questions' => $question_order === 'random' ? 1 : 0,
                // Answers are always shuffled (not configurable).
                'shuffleAnswers' => true,
                'random_answers' => 1,
                'run_once' => (int) ($settings['run_once'] ?? 0),
                'force_solve' => (int) ($settings['force_solve'] ?? 0),
                'show_points' => (int) ($settings['show_points'] ?? 0),
            ]
        );

        $passing = (int) ($settings['passing_percentage'] ?? 80);
        $time_limit = (int) ($settings['time_limit'] ?? 0);

        if ($existing_id > 0) {
            $wpdb->update(
                $quizzes_table,
                [
                    'title' => $title,
                    'passing_score' => $passing,
                    'time_limit_seconds' => $time_limit,
                    'settings_json' => $settings_json,
                ],
                ['id' => $existing_id],
                ['%s', '%d', '%d', '%s'],
                ['%d']
            );
            self::delete_quiz_children($existing_id);
            $quiz_id = $existing_id;
        } else {
            // Omit lesson_post_id so it remains NULL for course-level quizzes.
            $ok = $wpdb->insert(
                $quizzes_table,
                [
                    'course_post_id' => $course_id,
                    'title' => $title,
                    'passing_score' => $passing,
                    'time_limit_seconds' => $time_limit,
                    'settings_json' => $settings_json,
                    'created_at' => current_time('mysql'),
                ],
                ['%d', '%s', '%d', '%d', '%s', '%s']
            );
            if (!$ok) {
                return new WP_Error('db_insert_failed', __('Failed to create quiz.', 'politeia-quiz-creator'));
            }
            $quiz_id = (int) $wpdb->insert_id;
        }

        $insert_results = self::insert_questions($quiz_id, $normalized);

        return [
            'quiz_post_id' => $quiz_id,
            'quiz_url' => '',
            'edit_url' => '',
            'questions' => $insert_results,
        ];
    }

    /**
     * Used by the editor template.
     *
     * @return array{id:int,questions:array}|null
     */
    public static function get_quiz_data(int $quiz_id): ?array
    {
        if ($quiz_id <= 0) {
            return null;
        }

        global $wpdb;
        $quiz_table = $wpdb->prefix . self::TABLE_QUIZZES;
        $question_table = $wpdb->prefix . self::TABLE_QUESTIONS;
        $answer_table = $wpdb->prefix . self::TABLE_ANSWERS;

        $quiz = $wpdb->get_row(
            $wpdb->prepare("SELECT id, title, passing_score, time_limit_seconds, settings_json FROM {$quiz_table} WHERE id = %d LIMIT 1", $quiz_id),
            ARRAY_A
        );
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
                // Compatibility keys used by Learni
                if (isset($decoded['shuffleAnswers']) && !isset($decoded['random_answers'])) {
                    $settings['random_answers'] = !empty($decoded['shuffleAnswers']) ? 1 : 0;
                }
                if (isset($decoded['questionOrder']) && (string) $decoded['questionOrder'] === 'random' && !isset($decoded['random_questions'])) {
                    $settings['random_questions'] = 1;
                }
            }
        }
        // Answers are always shuffled (not configurable).
        $settings['random_answers'] = 1;

        $questions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, prompt, sort_order, meta_json
                 FROM {$question_table}
                 WHERE quiz_id = %d
                 ORDER BY sort_order ASC, id ASC",
                $quiz_id
            ),
            ARRAY_A
        );

        $out_questions = [];
        foreach ((array) $questions as $q) {
            $qid = (int) ($q['id'] ?? 0);
            if ($qid <= 0) {
                continue;
            }

            $meta = [];
            if (!empty($q['meta_json'])) {
                $decoded = json_decode((string) $q['meta_json'], true);
                if (is_array($decoded)) {
                    $meta = $decoded;
                }
            }

            $answers = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT answer_text, is_correct, sort_order
                     FROM {$answer_table}
                     WHERE question_id = %d
                     ORDER BY sort_order ASC, id ASC",
                    $qid
                ),
                ARRAY_A
            );

            $out_answers = [];
            foreach ((array) $answers as $a) {
                $text = trim((string) ($a['answer_text'] ?? ''));
                if ($text === '') {
                    continue;
                }
                $out_answers[] = [
                    'text' => $text,
                    'correct' => !empty($a['is_correct']),
                    'points' => 0,
                ];
            }

            $title = (string) ($meta['title'] ?? '');
            if ($title === '') {
                $plain = trim(wp_strip_all_tags((string) ($q['prompt'] ?? '')));
                if ($plain !== '') {
                    $plain = trim((string) preg_replace('/\s+/', ' ', $plain));
                    $title = strlen($plain) > 64 ? (substr($plain, 0, 64) . '…') : $plain;
                }
            }

            $out_questions[] = [
                'id' => $qid,
                'pro_id' => 0,
                'title' => $title,
                'question_text' => (string) ($q['prompt'] ?? ''),
                'answers' => $out_answers,
            ];
        }

        return [
            'id' => (int) ($quiz['id'] ?? 0),
            'title' => (string) ($quiz['title'] ?? ''),
            'passing_score' => (int) ($quiz['passing_score'] ?? 0),
            'time_limit_seconds' => (int) ($quiz['time_limit_seconds'] ?? 0),
            'settings' => $settings,
            'questions' => $out_questions,
        ];
    }

    /**
     * Persist editor changes.
     *
     * @param array{quiz_id:int,questions:array} $payload
     */
    public static function save_quiz_changes(array $payload): bool
    {
        global $wpdb;
        if (!$wpdb) {
            return false;
        }

        $quiz_id = (int) ($payload['quiz_id'] ?? 0);
        if ($quiz_id <= 0) {
            return false;
        }

        $question_table = $wpdb->prefix . self::TABLE_QUESTIONS;
        $answer_table = $wpdb->prefix . self::TABLE_ANSWERS;
        $quiz_table = $wpdb->prefix . self::TABLE_QUIZZES;

        $questions = isset($payload['questions']) && is_array($payload['questions']) ? $payload['questions'] : [];

        // Update sort order based on current UI order.
        foreach (array_values($questions) as $index => $q) {
            $qid = (int) ($q['id'] ?? 0);
            if ($qid <= 0) {
                continue;
            }

            $title = sanitize_text_field((string) ($q['title'] ?? ''));
            $prompt = wp_kses_post((string) ($q['question_text'] ?? ''));

            $wpdb->update(
                $question_table,
                [
                    'prompt' => $prompt,
                    'sort_order' => (int) $index,
                    'meta_json' => wp_json_encode(['title' => $title]),
                ],
                ['id' => $qid, 'quiz_id' => $quiz_id],
                ['%s', '%d', '%s'],
                ['%d', '%d']
            );

            // Replace answers wholesale (simple + robust for now).
            $wpdb->delete($answer_table, ['question_id' => $qid], ['%d']);
            $answers = isset($q['answers']) && is_array($q['answers']) ? $q['answers'] : [];
            foreach (array_values($answers) as $a_index => $a) {
                $text = sanitize_text_field((string) ($a['text'] ?? ''));
                if ($text === '') {
                    continue;
                }
                $wpdb->insert(
                    $answer_table,
                    [
                        'question_id' => $qid,
                        'answer_text' => $text,
                        'is_correct' => !empty($a['correct']) ? 1 : 0,
                        'sort_order' => (int) $a_index,
                        'meta_json' => null,
                    ],
                    ['%d', '%s', '%d', '%d', '%s']
                );
            }
        }

        // Update quiz title if provided.
        if (!empty($payload['title'])) {
            $wpdb->update(
                $quiz_table,
                ['title' => sanitize_text_field((string) $payload['title'])],
                ['id' => $quiz_id],
                ['%s'],
                ['%d']
            );
        }

        return true;
    }

    /**
     * Replace current quiz questions with the provided array (JSON-decoded).
     *
     * @param int $quiz_id
     * @param array $questions
     * @return array{total_questions:int,questions_inserted:int}|WP_Error
     */
    public static function replace_quiz_questions(int $quiz_id, array $questions)
    {
        global $wpdb;
        if (!$wpdb) {
            return new WP_Error('db_missing', __('Database unavailable.', 'politeia-quiz-creator'));
        }

        if ($quiz_id <= 0) {
            return new WP_Error('invalid_quiz', __('Invalid quiz.', 'politeia-quiz-creator'));
        }

        $quiz_table = $wpdb->prefix . self::TABLE_QUIZZES;
        $exists = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$quiz_table} WHERE id = %d LIMIT 1", $quiz_id));
        if ($exists <= 0) {
            return new WP_Error('quiz_not_found', __('Quiz not found.', 'politeia-quiz-creator'));
        }

        $normalized = self::normalize_questions_payload($questions);
        if (is_wp_error($normalized)) {
            return $normalized;
        }

        self::delete_quiz_children($quiz_id);
        $insert_results = self::insert_questions($quiz_id, $normalized);

        $inserted = 0;
        foreach ($insert_results as $row) {
            if (!empty($row['success'])) {
                $inserted++;
            }
        }

        return [
            'total_questions' => count($normalized),
            'questions_inserted' => $inserted,
        ];
    }

    /**
     * Append new questions to the end of an existing quiz.
     *
     * @param int $quiz_id
     * @param array $questions
     * @return array{total_questions:int,questions_inserted:int}|WP_Error
     */
    public static function append_quiz_questions(int $quiz_id, array $questions)
    {
        global $wpdb;
        if (!$wpdb) {
            return new WP_Error('db_missing', __('Database unavailable.', 'politeia-quiz-creator'));
        }

        if ($quiz_id <= 0) {
            return new WP_Error('invalid_quiz', __('Invalid quiz.', 'politeia-quiz-creator'));
        }

        $quiz_table = $wpdb->prefix . self::TABLE_QUIZZES;
        $exists = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$quiz_table} WHERE id = %d LIMIT 1", $quiz_id));
        if ($exists <= 0) {
            return new WP_Error('quiz_not_found', __('Quiz not found.', 'politeia-quiz-creator'));
        }

        $normalized = self::normalize_questions_payload($questions);
        if (is_wp_error($normalized)) {
            return $normalized;
        }

        $question_table = $wpdb->prefix . self::TABLE_QUESTIONS;
        $offset = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$question_table} WHERE quiz_id = %d", $quiz_id));
        $replaced_placeholder = false;

        // If the quiz only has the initial demo placeholder question, treat "append" as "replace"
        // so users don't get stuck seeing the demo as Question 1.
        if ($offset === 1 && self::quiz_has_demo_placeholder_question($quiz_id)) {
            self::delete_quiz_children($quiz_id);
            $offset = 0;
            $replaced_placeholder = true;
        }

        $insert_results = self::insert_questions($quiz_id, $normalized, $offset);

        $inserted = 0;
        foreach ($insert_results as $row) {
            if (!empty($row['success'])) {
                $inserted++;
            }
        }

        return [
            'total_questions' => $offset + count($normalized),
            'questions_inserted' => $inserted,
            'go_to_index' => $replaced_placeholder ? 0 : $offset,
            'replaced_placeholder' => $replaced_placeholder ? 1 : 0,
        ];
    }

    /**
     * Update quiz settings_json (subset + shuffle).
     *
     * @param int $quiz_id
     * @param array $settings
     * @return array<string,mixed>|WP_Error
     */
    public static function update_quiz_settings(int $quiz_id, array $settings)
    {
        global $wpdb;
        if (!$wpdb) {
            return new WP_Error('db_missing', __('Database unavailable.', 'politeia-quiz-creator'));
        }

        if ($quiz_id <= 0) {
            return new WP_Error('invalid_quiz', __('Invalid quiz.', 'politeia-quiz-creator'));
        }

        $quiz_table = $wpdb->prefix . self::TABLE_QUIZZES;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT id, settings_json FROM {$quiz_table} WHERE id = %d LIMIT 1", $quiz_id),
            ARRAY_A
        );
        if (!is_array($row) || empty($row['id'])) {
            return new WP_Error('quiz_not_found', __('Quiz not found.', 'politeia-quiz-creator'));
        }

        $existing = [];
        if (!empty($row['settings_json'])) {
            $decoded = json_decode((string) $row['settings_json'], true);
            if (is_array($decoded)) {
                $existing = $decoded;
            }
        }

        $allowed = [
            'questions_per_attempt' => 0,
            'questions_subset_random' => 0,
            'questionOrder' => 'in_order',
        ];

        $next = $existing;
        foreach ($allowed as $k => $default) {
            if (array_key_exists($k, $settings)) {
                $v = $settings[$k];
                if ($k === 'questions_per_attempt') {
                    $v = (int) $v;
                    if ($v < 0) {
                        $v = 0;
                    }
                } elseif ($k === 'questionOrder') {
                    $v = sanitize_text_field((string) $v);
                    if ($v !== 'random' && $v !== 'in_order') {
                        $v = 'in_order';
                    }
                } else {
                    $v = !empty($v) ? 1 : 0;
                }
                $next[$k] = $v;
            }
        }

        // Compatibility keys consumed by learner runtime.
        $next['shuffleAnswers'] = true;
        $next['random_answers'] = 1;
        $next['random_questions'] = (isset($next['questionOrder']) && (string) $next['questionOrder'] === 'random') ? 1 : 0;

        $ok = $wpdb->update(
            $quiz_table,
            ['settings_json' => wp_json_encode($next)],
            ['id' => $quiz_id],
            ['%s'],
            ['%d']
        );
        if ($ok === false) {
            return new WP_Error('settings_update_failed', __('Failed to save settings.', 'politeia-quiz-creator'));
        }

        // Return a normalized subset for the editor.
        return [
            'random_questions' => !empty($next['random_questions']) ? 1 : 0,
            'random_answers' => 1,
            'run_once' => !empty($next['run_once']) ? 1 : 0,
            'force_solve' => !empty($next['force_solve']) ? 1 : 0,
            'show_points' => !empty($next['show_points']) ? 1 : 0,
            'questions_per_attempt' => (int) ($next['questions_per_attempt'] ?? 0),
            'questions_subset_random' => !empty($next['questions_subset_random']) ? 1 : 0,
            'questionOrder' => isset($next['questionOrder']) ? (string) $next['questionOrder'] : 'in_order',
        ];
    }

    /**
     * Append a default question at the end of an existing quiz.
     *
     * @return array|WP_Error
     */
    public static function insert_default_question($quiz_id, $insert_after_index = -1, $opts = [])
    {
        global $wpdb;

        $quiz_id = (int) $quiz_id;
        if ($quiz_id <= 0) {
            return new WP_Error('invalid_quiz', __('Invalid quiz.', 'politeia-quiz-creator'));
        }

        $answers_per_question = (int) ($opts['answers_per_question'] ?? 4);
        if ($answers_per_question < 2) {
            $answers_per_question = 2;
        }
        if ($answers_per_question > 8) {
            $answers_per_question = 8;
        }

        $question_table = $wpdb->prefix . self::TABLE_QUESTIONS;
        $answer_table = $wpdb->prefix . self::TABLE_ANSWERS;

        $count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$question_table} WHERE quiz_id = %d", $quiz_id));
        $insert_after_index = (int) $insert_after_index;
        $insert_pos = $insert_after_index + 1;
        if ($insert_pos < 0) {
            $insert_pos = 0;
        }
        if ($insert_pos > $count) {
            $insert_pos = $count;
        }

        // Shift existing sort orders to make room.
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$question_table} SET sort_order = sort_order + 1 WHERE quiz_id = %d AND sort_order >= %d",
                $quiz_id,
                $insert_pos
            )
        );

        $ok = $wpdb->insert(
            $question_table,
            [
                'quiz_id' => $quiz_id,
                'type' => 'single',
                'prompt' => '',
                'explanation' => null,
                'points' => 1,
                'sort_order' => $insert_pos,
                'meta_json' => wp_json_encode(['title' => sprintf('Pregunta %d', $insert_pos + 1)]),
            ],
            ['%d', '%s', '%s', '%s', '%d', '%d', '%s']
        );
        if (!$ok) {
            return new WP_Error('db_insert_failed', __('Failed to add question.', 'politeia-quiz-creator'));
        }

        $question_id = (int) $wpdb->insert_id;

        for ($i = 0; $i < $answers_per_question; $i++) {
            $letter = chr(ord('A') + $i);
            $wpdb->insert(
                $answer_table,
                [
                    'question_id' => $question_id,
                    'answer_text' => sprintf('Opción %s', $letter),
                    'is_correct' => $i === 0 ? 1 : 0,
                    'sort_order' => $i,
                    'meta_json' => null,
                ],
                ['%d', '%s', '%d', '%d', '%s']
            );
        }

        return [
            'question_post_id' => $question_id,
            'question_pro_id' => 0,
            'total_questions' => $count + 1,
        ];
    }

    public static function delete_question(int $quiz_id, int $question_id)
    {
        global $wpdb;
        if (!$wpdb) {
            return new WP_Error('db_missing', __('Database unavailable.', 'politeia-quiz-creator'));
        }

        $quiz_id = (int) $quiz_id;
        $question_id = (int) $question_id;
        if ($quiz_id <= 0 || $question_id <= 0) {
            return new WP_Error('invalid_data', __('Invalid request.', 'politeia-quiz-creator'));
        }

        $question_table = $wpdb->prefix . self::TABLE_QUESTIONS;
        $answer_table = $wpdb->prefix . self::TABLE_ANSWERS;

        $wpdb->delete($answer_table, ['question_id' => $question_id], ['%d']);
        $wpdb->delete($question_table, ['id' => $question_id, 'quiz_id' => $quiz_id], ['%d', '%d']);

        return true;
    }

    public static function delete_quiz(int $quiz_id): bool
    {
        global $wpdb;
        if (!$wpdb) {
            return false;
        }

        $quiz_id = (int) $quiz_id;
        if ($quiz_id <= 0) {
            return false;
        }

        self::delete_quiz_children($quiz_id);

        $quiz_table = $wpdb->prefix . self::TABLE_QUIZZES;
        $wpdb->delete($quiz_table, ['id' => $quiz_id], ['%d']);

        return true;
    }

    private static function delete_quiz_children(int $quiz_id): void
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

    /**
     * Normalize the payload produced by the file parser/manual UI.
     *
     * @param array $questions
     * @return array|WP_Error
     */
    private static function normalize_questions_payload(array $questions)
    {
        $out = [];
        $i = 0;

        $derive_title_from_prompt = function (string $prompt, int $index): string {
            $plain = trim(wp_strip_all_tags($prompt));
            if ($plain === '') {
                return sanitize_text_field('Pregunta ' . ($index + 1));
            }
            $max = 64;
            $t = substr($plain, 0, $max);
            $t = preg_replace('/\s+/', ' ', (string) $t);
            $t = trim((string) $t);
            if ($t === '') {
                return sanitize_text_field('Pregunta ' . ($index + 1));
            }
            if (strlen($plain) > $max) {
                $t .= '…';
            }
            return sanitize_text_field($t);
        };

        foreach ($questions as $q) {
            if (!is_array($q)) {
                continue;
            }

            $question_text = (string) ($q['question_text'] ?? ($q['text'] ?? ''));
            $question_text = wp_kses_post($question_text);
            if (trim(wp_strip_all_tags($question_text)) === '') {
                $question_text = '';
            }

            // Title is optional; we derive it from the question text for internal use.
            $title = '';
            if (isset($q['title'])) {
                $title = sanitize_text_field((string) $q['title']);
            }
            if ($title === '') {
                $title = $derive_title_from_prompt($question_text, $i);
            }

            $answers = isset($q['answers']) && is_array($q['answers']) ? $q['answers'] : [];
            $norm_answers = [];
            $has_correct = false;
            foreach ($answers as $a) {
                if (!is_array($a)) {
                    continue;
                }
                $text = sanitize_text_field((string) ($a['text'] ?? ''));
                if ($text === '') {
                    continue;
                }
                $correct = !empty($a['correct']);
                if ($correct) {
                    $has_correct = true;
                }
                $norm_answers[] = ['text' => $text, 'correct' => $correct];
            }

            if (count($norm_answers) < 2) {
                return new WP_Error('invalid_answers', __('Each question must have at least 2 answers.', 'politeia-quiz-creator'));
            }
            if (!$has_correct) {
                // Default first as correct.
                $norm_answers[0]['correct'] = true;
            }

            $out[] = [
                'title' => $title,
                'prompt' => $question_text,
                'answers' => $norm_answers,
            ];
            $i++;
        }

        if (empty($out)) {
            return new WP_Error('empty_quiz', __('No questions provided.', 'politeia-quiz-creator'));
        }

        return $out;
    }

    /**
     * @param int $quiz_id
     * @param array<int,array{title:string,prompt:string,answers:array<int,array{text:string,correct:bool}>}> $questions
     * @return array<int,array{success:bool,question_id:int}>
     */
    private static function insert_questions(int $quiz_id, array $questions, int $offset = 0): array
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
                    'prompt' => (string) $q['prompt'],
                    'explanation' => null,
                    'points' => 1,
                    'sort_order' => (int) ($offset + $index),
                    'meta_json' => wp_json_encode(['title' => (string) $q['title']]),
                ],
                ['%d', '%s', '%s', '%s', '%d', '%d', '%s']
            );

            if (!$ok) {
                $results[] = ['success' => false, 'question_id' => 0];
                continue;
            }

            $question_id = (int) $wpdb->insert_id;
            $a_index = 0;
            foreach ((array) $q['answers'] as $a) {
                $wpdb->insert(
                    $answer_table,
                    [
                        'question_id' => $question_id,
                        'answer_text' => (string) $a['text'],
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

    private static function quiz_has_demo_placeholder_question(int $quiz_id): bool
    {
        global $wpdb;
        if (!$wpdb || $quiz_id <= 0) {
            return false;
        }

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

        $prompt = (string) ($q['prompt'] ?? '');
        $prompt_plain = trim(wp_strip_all_tags($prompt));
        if ($prompt_plain !== '') {
            return false;
        }

        $meta = [];
        if (!empty($q['meta_json'])) {
            $decoded = json_decode((string) $q['meta_json'], true);
            if (is_array($decoded)) {
                $meta = $decoded;
            }
        }
        $title = strtolower(trim((string) ($meta['title'] ?? '')));
        $title_ok = in_array($title, ['pregunta 1', 'question 1'], true);
        if (!$title_ok) {
            return false;
        }

        $answers = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT answer_text
                 FROM {$answer_table}
                 WHERE question_id = %d
                 ORDER BY sort_order ASC, id ASC
                 LIMIT 2",
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
