<?php
/**
 * Shortcode Class
 * Handles the [politeia_quiz_creator] shortcode
 */

if (!defined('ABSPATH')) {
    exit;
}

class PQC_Shortcode
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
        add_shortcode('politeia_quiz_creator', [$this, 'render_shortcode']);
        // Alias for convenience
        add_shortcode('politeia_quiz_editor', [$this, 'render_shortcode']);
    }

    /**
     * Render the shortcode
     */
    public function render_shortcode($atts)
    {
        $atts = shortcode_atts([
            'course_id' => 0,
            'quiz_id' => 0,
            'title' => __('Quiz Creator', 'politeia-quiz-creator'),
        ], $atts);

        $course_id = intval($atts['course_id']);
        if (!$course_id && isset($_GET['course_id'])) {
            $course_id = intval($_GET['course_id']);
        }

        $quiz_id = intval($atts['quiz_id']);
        if (!$quiz_id && isset($_GET['edit_quiz'])) {
            $quiz_id = intval($_GET['edit_quiz']);
        }

        // Check permissions (allow course authors without broad WP caps)
        if (!function_exists('pqc_can_access_quiz_creator') || !pqc_can_access_quiz_creator($course_id, $quiz_id)) {
            return '<p>' . __('You do not have permission to access the Quiz Creator.', 'politeia-quiz-creator') . '</p>';
        }

        $this->enqueue_shortcode_assets();

        // If course_id is provided but no quiz_id, try to find linked quiz
        if ($course_id && !$quiz_id) {
            $quiz_id = PQC_Quiz_Creator::get_quiz_id_by_course($course_id);
        }

        $course_title = '';
        if ($course_id) {
            $course_title = get_the_title($course_id);
        }

        ob_start();
        $create_error = '';

        // If there is no quiz yet for this course, create a stub quiz so the "create" screen
        // uses the exact same editor UI as the edit screen.
        if ($course_id && !$quiz_id && class_exists('PQC_Quiz_Creator')) {
            $default_quiz_title = !empty($course_title) ? $course_title : '';

            $stub = [
                'title' => $default_quiz_title !== '' ? $default_quiz_title : __('Evaluation', 'politeia-quiz-creator'),
                'settings' => [
                    'course_id' => $course_id,
                    'time_limit' => 0,
                    'passing_percentage' => 80,
                    'questionOrder' => 'in_order',
                    'random_questions' => 0,
                    'random_answers' => 1,
                    'run_once' => 0,
                    'force_solve' => 1,
                    'show_points' => 0,
                ],
                'questions' => [
                    [
                        'title' => __('Pregunta 1', 'politeia-quiz-creator'),
                        'question_text' => '',
                        'answers' => [
                            ['text' => __('Respuesta 1', 'politeia-quiz-creator'), 'correct' => true],
                            ['text' => __('Respuesta 2', 'politeia-quiz-creator'), 'correct' => false],
                        ],
                    ],
                ],
            ];

            $result = PQC_Quiz_Creator::create_quiz($stub);
            if (is_wp_error($result)) {
                $create_error = $result->get_error_message();
            } else {
                $quiz_id = (int) ($result['quiz_post_id'] ?? 0);
            }
        }

        if ($create_error !== '') {
            echo '<div class="pqc-container"><p class="pqc-error-msg">' . esc_html($create_error) . '</p></div>';
        } elseif ($quiz_id && class_exists('PQC_Quiz_Creator') && PQC_Quiz_Creator::quiz_exists((int) $quiz_id)) {
            include PQC_PLUGIN_DIR . 'templates/quiz-editor.php';
        } else {
            // Fallback: if stub creation failed or no course_id was provided, use the wizard.
            $default_quiz_title = !empty($course_title) ? $course_title : '';
            include PQC_PLUGIN_DIR . 'templates/upload-form.php';
        }
        return ob_get_clean();
    }

    /**
     * Enqueue assets for shortcode
     */
    private function enqueue_shortcode_assets()
    {
        // Fonts
        wp_enqueue_style(
            'pqc-poppins',
            'https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap',
            [],
            null
        );

        // CSS
        wp_enqueue_style(
            'pqc-styles',
            PQC_PLUGIN_URL . 'assets/css/quiz-creator.css',
            ['pqc-poppins'],
            PQC_VERSION
        );

        // Reuse Learni quiz modal CSS for the Preview overlay in the editor.
        if (defined('PL_LEARNI_URL')) {
            wp_enqueue_style(
                'pl-learni-material-symbols',
                'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&icon_names=auto_stories&display=swap',
                [],
                null
            );
            wp_enqueue_style(
                'pl-learni-learner',
                PL_LEARNI_URL . 'assets/learner.css',
                ['pl-learni-material-symbols'],
                defined('LEARNI_VERSION') ? (string) LEARNI_VERSION : '0.0.0'
            );
        }

        // JS
        wp_enqueue_script(
            'pqc-scripts',
            PQC_PLUGIN_URL . 'assets/js/quiz-creator.js',
            ['jquery'],
            PQC_VERSION,
            true
        );

        // Localize script
        wp_localize_script('pqc-scripts', 'pqcData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pqc_upload_nonce'),
            'strings' => [
                'uploading' => __('Uploading and processing...', 'politeia-quiz-creator'),
                'success' => __('Quiz created successfully!', 'politeia-quiz-creator'),
                'error' => __('Error creating quiz. Please check the file format.', 'politeia-quiz-creator'),
                'invalidFile' => __('Invalid file type. Please upload JSON, CSV, XML, or TXT file.', 'politeia-quiz-creator'),
            ]
        ]);
    }
}
