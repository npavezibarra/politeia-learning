<?php
/**
 * AJAX Handler Class
 * Handles file upload and quiz creation via AJAX
 */

if (!defined('ABSPATH')) {
    exit;
}

class PQC_Ajax_Handler
{

    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('wp_ajax_pqc_upload_quiz', [$this, 'handle_upload']);
        add_action('wp_ajax_pqc_download_sample', [$this, 'handle_download_sample']);
        add_action('wp_ajax_pqc_save_quiz_changes', [$this, 'handle_save_changes']);
        add_action('wp_ajax_pqc_get_quiz_module', [$this, 'handle_get_quiz_module']);
        add_action('wp_ajax_pqc_delete_quiz', [$this, 'handle_delete_quiz']);
        add_action('wp_ajax_pqc_get_quiz_editor', [$this, 'handle_get_quiz_editor']);
        add_action('wp_ajax_pqc_add_question', [$this, 'handle_add_question']);
        add_action('wp_ajax_pqc_delete_question', [$this, 'handle_delete_question']);
        add_action('wp_ajax_pqc_import_questions_json', [$this, 'handle_import_questions_json']);
        add_action('wp_ajax_pqc_save_quiz_settings', [$this, 'handle_save_quiz_settings']);
    }

    /**
     * Handle quiz save changes
     */
    public function handle_save_changes()
    {
        // Verify nonce
        if (!check_ajax_referer('pqc_upload_nonce', 'nonce', false)) {
            wp_send_json_error(__('Security check failed.', 'politeia-quiz-creator'));
        }

        $quiz_data = isset($_POST['quiz_data']) ? json_decode(stripslashes($_POST['quiz_data']), true) : [];

        if (empty($quiz_data) || empty($quiz_data['quiz_id'])) {
            wp_send_json_error(__('Invalid quiz data.', 'politeia-quiz-creator'));
        }

        $quiz_id = intval($quiz_data['quiz_id']);

        // Check permissions
        if (!function_exists('pqc_can_access_quiz_creator') || !pqc_can_access_quiz_creator(0, $quiz_id)) {
            wp_send_json_error(__('You do not have permission to edit quizzes.', 'politeia-quiz-creator'));
        }

        $ok = PQC_Quiz_Creator::save_quiz_changes($quiz_data);
        if (!$ok) {
            wp_send_json_error(__('Failed to save quiz changes.', 'politeia-quiz-creator'));
        }

        wp_send_json_success([
            'message' => __('Quiz changes saved successfully!', 'politeia-quiz-creator')
        ]);
    }

    /**
     * Handle quiz file upload
     */
    public function handle_upload()
    {
        // Verify nonce
        if (!check_ajax_referer('pqc_upload_nonce', 'nonce', false)) {
            wp_send_json_error([
                'message' => __('Security check failed.', 'politeia-quiz-creator')
            ]);
        }

        // Get quiz settings from form
        $quiz_settings = isset($_POST['quiz_settings']) ? json_decode(stripslashes($_POST['quiz_settings']), true) : [];

        if (empty($quiz_settings) || empty($quiz_settings['title'])) {
            wp_send_json_error([
                'message' => __('Quiz settings are required.', 'politeia-quiz-creator')
            ]);
        }

        // Check if file OR text was provided
        $file = !empty($_FILES['quiz_file']) ? $_FILES['quiz_file'] : null;
        $json_text = isset($_POST['quiz_json_text']) ? stripslashes($_POST['quiz_json_text']) : '';

        if (!$file && empty($json_text)) {
            wp_send_json_error([
                'message' => __('No questions data provided (file or text).', 'politeia-quiz-creator')
            ]);
        }

        $parsed_questions = [];

        if ($file) {
            // Check for upload errors
            if ($file['error'] !== UPLOAD_ERR_OK) {
                wp_send_json_error(['message' => __('File upload error.', 'politeia-quiz-creator')]);
            }

            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $parsed_questions = PQC_File_Parser::parse_file($file['tmp_name'], $file_ext);
        } else {
            // Parse from raw JSON text
            $parsed_questions = json_decode($json_text, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                wp_send_json_error(['message' => __('Invalid JSON format in pasted text.', 'politeia-quiz-creator')]);
            }
        }

        if (is_wp_error($parsed_questions)) {
            wp_send_json_error([
                'message' => $parsed_questions->get_error_message(),
                'code' => $parsed_questions->get_error_code()
            ]);
        }

        $course_id = intval($_POST['course_id'] ?? 0);

        // Question order: default to respecting the provided order.
        $question_order = 'in_order';
        if (isset($quiz_settings['question_order'])) {
            $v = sanitize_text_field((string) $quiz_settings['question_order']);
            if ($v === 'random' || $v === 'in_order') {
                $question_order = $v;
            }
        } elseif (array_key_exists('respect_question_order', $quiz_settings)) {
            $question_order = !empty($quiz_settings['respect_question_order']) ? 'in_order' : 'random';
        } elseif (array_key_exists('random_questions', $quiz_settings)) {
            $question_order = !empty($quiz_settings['random_questions']) ? 'random' : 'in_order';
        }

        // Check permissions (allow course authors without broad WP caps)
        if (!function_exists('pqc_can_access_quiz_creator') || !pqc_can_access_quiz_creator($course_id, 0)) {
            wp_send_json_error([
                'message' => __('You do not have permission to create quizzes.', 'politeia-quiz-creator')
            ]);
        }

        // Merge settings from form with questions from file
        $quiz_data = [
            'title' => sanitize_text_field($quiz_settings['title']),
            'settings' => [
                'time_limit' => intval($quiz_settings['time_limit'] ?? 0),
                'passing_percentage' => intval($quiz_settings['passing_percentage'] ?? 80),
                'questionOrder' => $question_order,
                'random_questions' => $question_order === 'random' ? 1 : 0,
                // Answers are always shuffled (not configurable).
                'random_answers' => 1,
                'run_once' => intval($quiz_settings['run_once'] ?? 0),
                'force_solve' => intval($quiz_settings['force_solve'] ?? 0),
                'show_points' => intval($quiz_settings['show_points'] ?? 0),
                'course_id' => $course_id,
            ],
            'questions' => $parsed_questions
        ];

        error_log('PQC: Creating quiz - ' . $quiz_data['title']);

        // Create the quiz
        $result = PQC_Quiz_Creator::create_quiz($quiz_data);

        if (is_wp_error($result)) {
            error_log('PQC: Quiz creation error - ' . $result->get_error_message());
            wp_send_json_error([
                'message' => $result->get_error_message(),
                'code' => $result->get_error_code()
            ]);
        }

        error_log('PQC: Quiz created successfully - ID: ' . $result['quiz_post_id']);

        // Success!
        wp_send_json_success([
            'message' => sprintf(
                __('Quiz "%s" created successfully with %d questions!', 'politeia-quiz-creator'),
                $quiz_data['title'],
                count($quiz_data['questions'])
            ),
            'quiz_id' => $result['quiz_post_id'],
            'quiz_url' => $result['quiz_url'],
            'edit_url' => $result['edit_url'],
            'questions_created' => count(array_filter($result['questions'], function ($q) {
                return $q['success'];
            })),
            'total_questions' => count($result['questions'])
        ]);
    }

    /**
     * Handle sample file download
     */
    public function handle_download_sample()
    {
        // Verify nonce
        if (!check_ajax_referer('pqc_upload_nonce', 'nonce', false)) {
            wp_die(__('Security check failed.', 'politeia-quiz-creator'));
        }

        $format = isset($_GET['format']) ? sanitize_text_field($_GET['format']) : 'json';
        $allowed_formats = ['json', 'csv', 'xml', 'txt'];

        if (!in_array($format, $allowed_formats)) {
            wp_die(__('Invalid format.', 'politeia-quiz-creator'));
        }

        $sample_data = PQC_File_Parser::get_sample_data($format);

        // Set headers for download
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="quiz-questions-sample.' . $format . '"');
        header('Content-Length: ' . strlen($sample_data));

        echo $sample_data;
        exit;
    }

    /**
     * Get quiz module HTML via AJAX
     */
    public function handle_get_quiz_module()
    {
        $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;

        if (!$course_id) {
            wp_send_json_error(__('Course ID is required.', 'politeia-quiz-creator'));
        }

        $html = do_shortcode('[politeia_quiz_creator course_id="' . $course_id . '"]');

        wp_send_json_success(['html' => $html]);
    }

    /**
     * Get quiz editor HTML via AJAX (by quiz_id)
     */
    public function handle_get_quiz_editor()
    {
        $quiz_id = isset($_POST['quiz_id']) ? intval($_POST['quiz_id']) : 0;

        if (!$quiz_id) {
            wp_send_json_error(__('Quiz ID is required.', 'politeia-quiz-creator'));
        }

        // Check permissions
        if (!function_exists('pqc_can_access_quiz_creator') || !pqc_can_access_quiz_creator(0, $quiz_id)) {
            wp_send_json_error(__('You do not have permission to access this quiz.', 'politeia-quiz-creator'));
        }

        $html = do_shortcode('[politeia_quiz_creator quiz_id="' . $quiz_id . '"]');
        wp_send_json_success(['html' => $html]);
    }

    /**
     * Add a new question at the end of the quiz
     */
    public function handle_add_question()
    {
        // Verify nonce
        if (!check_ajax_referer('pqc_upload_nonce', 'nonce', false)) {
            wp_send_json_error(__('Security check failed.', 'politeia-quiz-creator'));
        }

        $quiz_id = isset($_POST['quiz_id']) ? intval($_POST['quiz_id']) : 0;
        if (!$quiz_id) {
            wp_send_json_error(__('Quiz ID is required.', 'politeia-quiz-creator'));
        }

        // Check permissions
        if (!function_exists('pqc_can_access_quiz_creator') || !pqc_can_access_quiz_creator(0, $quiz_id)) {
            wp_send_json_error(__('You do not have permission to edit quizzes.', 'politeia-quiz-creator'));
        }

        $answers_per_question = isset($_POST['answers_per_question']) ? intval($_POST['answers_per_question']) : 4;
        if ($answers_per_question < 2) {
            $answers_per_question = 2;
        }
        if ($answers_per_question > 8) {
            $answers_per_question = 8;
        }

        $insert_after = isset($_POST['insert_after']) ? intval($_POST['insert_after']) : -1;

        $result = PQC_Quiz_Creator::insert_default_question($quiz_id, $insert_after, [
            'answers_per_question' => $answers_per_question,
        ]);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success([
            'message' => __('Question added.', 'politeia-quiz-creator'),
            'question_post_id' => $result['question_post_id'],
            'question_pro_id' => $result['question_pro_id'],
            'total_questions' => $result['total_questions'],
        ]);
    }

    /**
     * Delete quiz via AJAX
     */
    public function handle_delete_quiz()
    {
        // Verify nonce
        if (!check_ajax_referer('pqc_upload_nonce', 'nonce', false)) {
            wp_send_json_error(__('Security check failed.', 'politeia-quiz-creator'));
        }

        $quiz_id = isset($_POST['quiz_id']) ? intval($_POST['quiz_id']) : 0;

        if (!$quiz_id) {
            wp_send_json_error(__('Quiz ID is required.', 'politeia-quiz-creator'));
        }

        // Check permissions
        if (!function_exists('pqc_can_access_quiz_creator') || !pqc_can_access_quiz_creator(0, $quiz_id)) {
            wp_send_json_error(__('You do not have permission to delete quizzes.', 'politeia-quiz-creator'));
        }

        $success = PQC_Quiz_Creator::delete_quiz($quiz_id);

        if ($success) {
            wp_send_json_success(['message' => __('Quiz deleted successfully.', 'politeia-quiz-creator')]);
        } else {
            wp_send_json_error(__('Failed to delete quiz.', 'politeia-quiz-creator'));
        }
    }

    /**
     * Import questions JSON into an existing quiz (replaces current questions).
     */
    public function handle_import_questions_json()
    {
        error_log('PQC: import_questions_json called');
        // Verify nonce
        if (!check_ajax_referer('pqc_upload_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'politeia-quiz-creator')]);
        }

        $quiz_id = isset($_POST['quiz_id']) ? intval($_POST['quiz_id']) : 0;
        if (!$quiz_id) {
            error_log('PQC: import_questions_json missing quiz_id');
            wp_send_json_error(['message' => __('Quiz ID is required.', 'politeia-quiz-creator')]);
        }

        // Check permissions
        if (!function_exists('pqc_can_access_quiz_creator') || !pqc_can_access_quiz_creator(0, $quiz_id)) {
            error_log('PQC: import_questions_json permission denied for quiz_id=' . $quiz_id);
            wp_send_json_error(['message' => __('You do not have permission to edit quizzes.', 'politeia-quiz-creator')]);
        }

        $json_text = isset($_POST['quiz_json_text']) ? stripslashes((string) $_POST['quiz_json_text']) : '';
        $json_text = trim($json_text);
        if ($json_text === '') {
            error_log('PQC: import_questions_json empty json');
            wp_send_json_error(['message' => __('No JSON provided.', 'politeia-quiz-creator')]);
        }

        $parsed = json_decode($json_text, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($parsed)) {
            error_log('PQC: import_questions_json invalid json, error=' . json_last_error_msg());
            wp_send_json_error(['message' => __('Invalid JSON format.', 'politeia-quiz-creator')]);
        }

        $mode = isset($_POST['mode']) ? sanitize_text_field((string) $_POST['mode']) : 'append';
        if ($mode === 'replace') {
            $result = PQC_Quiz_Creator::replace_quiz_questions($quiz_id, $parsed);
        } else {
            $result = PQC_Quiz_Creator::append_quiz_questions($quiz_id, $parsed);
        }
        if (is_wp_error($result)) {
            error_log('PQC: import_questions_json failed: ' . $result->get_error_code() . ' ' . $result->get_error_message());
            wp_send_json_error(['message' => $result->get_error_message(), 'code' => $result->get_error_code()]);
        }

        wp_send_json_success([
            'message' => __('Questions imported.', 'politeia-quiz-creator'),
            'total_questions' => (int) ($result['total_questions'] ?? 0),
            'questions_inserted' => (int) ($result['questions_inserted'] ?? 0),
            'go_to_index' => (int) ($result['go_to_index'] ?? 0),
            'replaced_placeholder' => (int) ($result['replaced_placeholder'] ?? 0),
        ]);
    }

    public function handle_save_quiz_settings()
    {
        if (!check_ajax_referer('pqc_upload_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'politeia-quiz-creator')]);
        }

        $quiz_id = isset($_POST['quiz_id']) ? intval($_POST['quiz_id']) : 0;
        if (!$quiz_id) {
            wp_send_json_error(['message' => __('Quiz ID is required.', 'politeia-quiz-creator')]);
        }

        if (!function_exists('pqc_can_access_quiz_creator') || !pqc_can_access_quiz_creator(0, $quiz_id)) {
            wp_send_json_error(['message' => __('You do not have permission to edit quizzes.', 'politeia-quiz-creator')]);
        }

        $raw = isset($_POST['settings']) ? stripslashes((string) $_POST['settings']) : '';
        $raw = trim($raw);
        $settings = $raw !== '' ? json_decode($raw, true) : null;
        if (!is_array($settings)) {
            wp_send_json_error(['message' => __('Invalid settings payload.', 'politeia-quiz-creator')]);
        }

        $result = PQC_Quiz_Creator::update_quiz_settings($quiz_id, $settings);
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message(), 'code' => $result->get_error_code()]);
        }

        wp_send_json_success([
            'message' => __('Settings saved.', 'politeia-quiz-creator'),
            'settings' => $result,
        ]);
    }

    /**
     * Delete a single question via AJAX
     */
    public function handle_delete_question()
    {
        // Verify nonce
        if (!check_ajax_referer('pqc_upload_nonce', 'nonce', false)) {
            wp_send_json_error(__('Security check failed.', 'politeia-quiz-creator'));
        }

        $quiz_id = isset($_POST['quiz_id']) ? intval($_POST['quiz_id']) : 0;
        $question_id = isset($_POST['question_id']) ? intval($_POST['question_id']) : 0;

        if (!$quiz_id || !$question_id) {
            wp_send_json_error(__('Quiz ID and Question ID are required.', 'politeia-quiz-creator'));
        }

        // Check permissions
        if (!function_exists('pqc_can_access_quiz_creator') || !pqc_can_access_quiz_creator(0, $quiz_id)) {
            wp_send_json_error(__('You do not have permission to edit quizzes.', 'politeia-quiz-creator'));
        }

        $result = PQC_Quiz_Creator::delete_question($quiz_id, $question_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(['message' => __('Question deleted successfully.', 'politeia-quiz-creator')]);
    }
}
