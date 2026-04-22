<?php

namespace Learni\QuizEditor;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles the [politeia_quiz_creator] shortcode within Learni.
 */
final class Shortcode
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
        add_shortcode('politeia_quiz_creator', [$this, 'render']);
        add_shortcode('politeia_quiz_editor', [$this, 'render']);
    }

    public function render($atts)
    {
        $atts = shortcode_atts([
            'course_id' => 0,
            'quiz_id' => 0,
        ], $atts);

        $course_id = (int) $atts['course_id'];
        if (!$course_id && isset($_GET['course_id'])) $course_id = (int) $_GET['course_id'];

        $quiz_id = (int) $atts['quiz_id'];
        if (!$quiz_id && isset($_GET['edit_quiz'])) $quiz_id = (int) $_GET['edit_quiz'];

        if (!Permissions::can_access($course_id, $quiz_id)) {
            return '<p>' . __('No tienes permiso para acceder al editor de exámenes.', 'politeia-learning') . '</p>';
        }

        if ($course_id && !$quiz_id) {
            $quiz_id = QuizRepository::get_quiz_id_by_course($course_id);
        }

        $this->enqueue_assets();

        ob_start();
        
        // If no quiz exists for course, create stub
        if ($course_id && !$quiz_id) {
            $course_title = get_the_title($course_id);
            $result = QuizEditor::create_quiz([
                'title' => $course_title ?: __('Evaluación', 'politeia-learning'),
                'settings' => [
                    'course_id' => $course_id,
                    'time_limit' => 0,
                    'passing_percentage' => 80,
                    'questionOrder' => 'in_order',
                    'run_once' => 0,
                    'force_solve' => 1,
                    'show_points' => 0,
                ],
                'questions' => [
                    [
                        'title' => 'Pregunta 1',
                        'question_text' => '',
                        'answers' => [
                            ['text' => 'Respuesta 1', 'correct' => true],
                            ['text' => 'Respuesta 2', 'correct' => false],
                        ],
                    ],
                ],
            ]);

            if (!is_wp_error($result)) {
                $quiz_id = (int) ($result['quiz_post_id'] ?? 0);
            }
        }

        if ($quiz_id && QuizRepository::quiz_exists($quiz_id)) {
            $template = PL_LEARNI_PATH . 'templates/quiz-creator/quiz-editor.php';
            if (file_exists($template)) {
                include $template;
            }
        } else {
            $template = PL_LEARNI_PATH . 'templates/quiz-creator/upload-form.php';
            if (file_exists($template)) {
                include $template;
            }
        }

        return ob_get_clean();
    }

    public function enqueue_assets()
    {
        wp_enqueue_style('pqc-poppins', 'https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap', [], null);
        
        // Use PL_LEARNI_URL for assets
        wp_enqueue_style('pqc-styles', PL_LEARNI_URL . 'assets/quiz-creator/css/quiz-creator.css', ['pqc-poppins'], '1.0.0');
        
        if (defined('PL_LEARNI_URL')) {
            wp_enqueue_style('pl-learni-material-symbols', 'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&icon_names=auto_stories&display=swap', [], null);
            wp_enqueue_style('pl-learni-learner', PL_LEARNI_URL . 'assets/learner.css', ['pl-learni-material-symbols'], '1.0.0');
        }

        wp_enqueue_script('pqc-scripts', PL_LEARNI_URL . 'assets/quiz-creator/js/quiz-creator.js', ['jquery'], '1.0.0', true);

        wp_localize_script('pqc-scripts', 'pqcData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pqc_upload_nonce'),
            'strings' => [
                'uploading' => __('Subiendo y procesando...', 'politeia-learning'),
                'success' => __('¡Examen creado correctamente!', 'politeia-learning'),
                'error' => __('Error al crear el examen.', 'politeia-learning'),
                'invalidFile' => __('Archivo inválido.', 'politeia-learning'),
            ]
        ]);
    }
}
