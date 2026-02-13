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

        // Check permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('You do not have permission to edit quizzes.', 'politeia-quiz-creator'));
        }

        $quiz_data = isset($_POST['quiz_data']) ? json_decode(stripslashes($_POST['quiz_data']), true) : [];

        if (empty($quiz_data) || empty($quiz_data['quiz_id'])) {
            wp_send_json_error(__('Invalid quiz data.', 'politeia-quiz-creator'));
        }

        $quiz_id = intval($quiz_data['quiz_id']);

        // Update Quiz Title
        if (!empty($quiz_data['title'])) {
            wp_update_post([
                'ID' => $quiz_id,
                'post_title' => sanitize_text_field($quiz_data['title'])
            ]);
        }

        global $wpdb;

        // Update Questions
        if (!empty($quiz_data['questions']) && is_array($quiz_data['questions'])) {
            // Load LD Classes if needed
            if (!class_exists('WpProQuiz_Model_AnswerTypes') && defined('WPPROQUIZ_PATH')) {
                require_once WPPROQUIZ_PATH . '/lib/model/WpProQuiz_Model_AnswerTypes.php';
            }

            foreach ($quiz_data['questions'] as $q_data) {
                $q_post_id = intval($q_data['id']);
                $q_pro_id = intval($q_data['pro_id']);

                // Update Question Post Title
                wp_update_post([
                    'ID' => $q_post_id,
                    'post_title' => sanitize_text_field($q_data['title'])
                ]);

                // Prepare Answer Objects for LD
                $answer_objects = [];
                if (class_exists('WpProQuiz_Model_AnswerTypes')) {
                    foreach ($q_data['answers'] as $a_data) {
                        $ans_obj = new WpProQuiz_Model_AnswerTypes();
                        $ans_obj->setAnswer($a_data['text']);
                        $ans_obj->setCorrect($a_data['correct'] ? 1 : 0);
                        $ans_obj->setPoints(intval($a_data['points']));
                        $ans_obj->setHtml(true);
                        $answer_objects[] = $ans_obj;
                    }

                    // Update ProQuiz Table
                    $wpdb->update(
                        "{$wpdb->prefix}learndash_pro_quiz_question",
                        [
                            'title' => sanitize_text_field($q_data['title']),
                            'question' => wp_kses_post($q_data['question_text']),
                            'answer_data' => serialize($answer_objects)
                        ],
                        ['id' => $q_pro_id],
                        ['%s', '%s', '%s'],
                        ['%d']
                    );
                }
            }
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

        // Check permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error([
                'message' => __('You do not have permission to create quizzes.', 'politeia-quiz-creator')
            ]);
        }

        // Get quiz settings from form
        $quiz_settings = isset($_POST['quiz_settings']) ? json_decode(stripslashes($_POST['quiz_settings']), true) : [];

        if (empty($quiz_settings) || empty($quiz_settings['title'])) {
            wp_send_json_error([
                'message' => __('Quiz settings are required.', 'politeia-quiz-creator')
            ]);
        }

        // Check if file was uploaded
        if (empty($_FILES['quiz_file'])) {
            wp_send_json_error([
                'message' => __('No file uploaded.', 'politeia-quiz-creator')
            ]);
        }

        $file = $_FILES['quiz_file'];

        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error([
                'message' => __('File upload error.', 'politeia-quiz-creator')
            ]);
        }

        // Get file extension
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['json', 'csv', 'xml', 'txt'];

        if (!in_array($file_ext, $allowed_extensions)) {
            wp_send_json_error([
                'message' => __('Invalid file type. Allowed: JSON, CSV, XML, TXT', 'politeia-quiz-creator')
            ]);
        }

        // Parse the file (questions only)
        $log_file = WP_CONTENT_DIR . '/pqc-debug.log';
        file_put_contents($log_file, date('Y-m-d H:i:s') . " - Starting file parse\n", FILE_APPEND);

        error_log('PQC: Starting file parse - ' . $file['name']);
        $parsed_questions = PQC_File_Parser::parse_file($file['tmp_name'], $file_ext);

        if (is_wp_error($parsed_questions)) {
            file_put_contents($log_file, date('Y-m-d H:i:s') . " - Parse error: " . $parsed_questions->get_error_message() . "\n", FILE_APPEND);
            error_log('PQC: File parse error - ' . $parsed_questions->get_error_message());
            wp_send_json_error([
                'message' => $parsed_questions->get_error_message(),
                'code' => $parsed_questions->get_error_code()
            ]);
        }

        $question_count = count($parsed_questions);
        file_put_contents($log_file, date('Y-m-d H:i:s') . " - Parsed {$question_count} questions\n", FILE_APPEND);
        error_log('PQC: Parsed ' . $question_count . ' questions');

        // Merge settings from form with questions from file
        $quiz_data = [
            'title' => sanitize_text_field($quiz_settings['title']),
            'settings' => [
                'time_limit' => intval($quiz_settings['time_limit'] ?? 0),
                'passing_percentage' => intval($quiz_settings['passing_percentage'] ?? 80),
                'random_questions' => intval($quiz_settings['random_questions'] ?? 0),
                'random_answers' => intval($quiz_settings['random_answers'] ?? 0),
                'run_once' => intval($quiz_settings['run_once'] ?? 0),
                'force_solve' => intval($quiz_settings['force_solve'] ?? 0),
                'show_points' => intval($quiz_settings['show_points'] ?? 0),
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
}
