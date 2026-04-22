<?php

namespace Learni\QuizEditor;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX Handler for Learni Quiz Editor.
 */
final class AjaxHandler
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

    public function handle_save_changes()
    {
        if (!check_ajax_referer('pqc_upload_nonce', 'nonce', false)) {
            wp_send_json_error(__('Security check failed.', 'politeia-learning'));
        }

        $quiz_data = isset($_POST['quiz_data']) ? json_decode(stripslashes($_POST['quiz_data']), true) : [];
        $quiz_id = (int) ($quiz_data['quiz_id'] ?? 0);

        if ($quiz_id <= 0) wp_send_json_error(__('Invalid quiz data.', 'politeia-learning'));

        if (!$this->can_access(0, $quiz_id)) {
            wp_send_json_error(__('No tienes permiso para editar este examen.', 'politeia-learning'));
        }

        if (QuizEditor::save_changes($quiz_data)) {
            wp_send_json_success(['message' => __('Cambios guardados correctamente.', 'politeia-learning')]);
        } else {
            wp_send_json_error(__('Error al guardar los cambios.', 'politeia-learning'));
        }
    }

    public function handle_upload()
    {
        if (!check_ajax_referer('pqc_upload_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'politeia-learning')]);
        }

        $quiz_settings = isset($_POST['quiz_settings']) ? json_decode(stripslashes($_POST['quiz_settings']), true) : [];
        $course_id = (int) ($_POST['course_id'] ?? 0);

        if (empty($quiz_settings) || empty($quiz_settings['title']) || $course_id <= 0) {
            wp_send_json_error(['message' => __('Faltan datos del examen o curso.', 'politeia-learning')]);
        }

        if (!$this->can_access($course_id)) {
            wp_send_json_error(['message' => __('No tienes permiso para crear exámenes en este curso.', 'politeia-learning')]);
        }

        $file = $_FILES['quiz_file'] ?? null;
        $json_text = isset($_POST['quiz_json_text']) ? stripslashes($_POST['quiz_json_text']) : '';

        if ($file) {
            if ($file['error'] !== UPLOAD_ERR_OK) wp_send_json_error(['message' => __('Error al subir archivo.', 'politeia-learning')]);
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $parsed = FileParser::parse($file['tmp_name'], $file_ext);
        } else {
            $parsed = json_decode($json_text, true);
            if (json_last_error() !== JSON_ERROR_NONE) wp_send_json_error(['message' => __('JSON inválido.', 'politeia-learning')]);
        }

        if (is_wp_error($parsed)) wp_send_json_error(['message' => $parsed->get_error_message()]);

        $result = QuizEditor::create_quiz([
            'title' => sanitize_text_field($quiz_settings['title']),
            'settings' => array_merge($quiz_settings, ['course_id' => $course_id]),
            'questions' => $parsed
        ]);

        if (is_wp_error($result)) wp_send_json_error(['message' => $result->get_error_message()]);

        wp_send_json_success([
            'message' => __('Examen creado correctamente.', 'politeia-learning'),
            'quiz_id' => $result['quiz_post_id'],
        ]);
    }

    public function handle_download_sample()
    {
        if (!check_ajax_referer('pqc_upload_nonce', 'nonce', false)) wp_die('Security check failed.');

        $format = sanitize_text_field($_GET['format'] ?? 'json');
        $sample = FileParser::get_sample_data($format);

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="quiz-sample.' . $format . '"');
        echo $sample;
        exit;
    }

    public function handle_get_quiz_module()
    {
        $course_id = (int) ($_POST['course_id'] ?? 0);
        if (!$course_id) wp_send_json_error('ID de curso requerido');
        wp_send_json_success(['html' => do_shortcode('[politeia_quiz_creator course_id="' . $course_id . '"]')]);
    }

    public function handle_get_quiz_editor()
    {
        $quiz_id = (int) ($_POST['quiz_id'] ?? 0);
        if (!$quiz_id || !$this->can_access(0, $quiz_id)) wp_send_json_error('Acceso denegado');
        wp_send_json_success(['html' => do_shortcode('[politeia_quiz_creator quiz_id="' . $quiz_id . '"]')]);
    }

    public function handle_add_question()
    {
        if (!check_ajax_referer('pqc_upload_nonce', 'nonce', false)) wp_send_json_error('Security failed');
        $quiz_id = (int) ($_POST['quiz_id'] ?? 0);
        if (!$quiz_id || !$this->can_access(0, $quiz_id)) wp_send_json_error('Acceso denegado');

        $answers_per_question = max(2, min(8, (int) ($_POST['answers_per_question'] ?? 4)));
        $after_index = (int) ($_POST['insert_after'] ?? -1);

        $result = QuizEditor::insert_default_question($quiz_id, $after_index, ['answers_per_question' => $answers_per_question]);
        if (is_wp_error($result)) wp_send_json_error($result->get_error_message());

        wp_send_json_success([
            'message' => 'Pregunta agregada',
            'question_id' => $result['question_id'],
            'total_questions' => $result['total_questions']
        ]);
    }

    public function handle_delete_quiz()
    {
        if (!check_ajax_referer('pqc_upload_nonce', 'nonce', false)) wp_send_json_error('Security failed');
        $quiz_id = (int) ($_POST['quiz_id'] ?? 0);
        if (!$quiz_id || !$this->can_access(0, $quiz_id)) wp_send_json_error('Acceso denegado');

        if (QuizRepository::delete_quiz($quiz_id)) {
            wp_send_json_success(['message' => 'Examen eliminado']);
        } else {
            wp_send_json_error('Error al eliminar');
        }
    }

    public function handle_import_questions_json()
    {
        if (!check_ajax_referer('pqc_upload_nonce', 'nonce', false)) wp_send_json_error('Security failed');
        $quiz_id = (int) ($_POST['quiz_id'] ?? 0);
        if (!$quiz_id || !$this->can_access(0, $quiz_id)) wp_send_json_error('Acceso denegado');

        $json = stripslashes((string) ($_POST['quiz_json_text'] ?? ''));
        $parsed = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) wp_send_json_error('JSON inválido');

        $mode = $_POST['mode'] ?? 'append';
        $result = ($mode === 'replace') ? QuizEditor::replace_questions($quiz_id, $parsed) : QuizEditor::append_questions($quiz_id, $parsed);

        if (is_wp_error($result)) wp_send_json_error($result->get_error_message());
        wp_send_json_success($result);
    }

    public function handle_save_quiz_settings()
    {
        if (!check_ajax_referer('pqc_upload_nonce', 'nonce', false)) wp_send_json_error('Security failed');
        $quiz_id = (int) ($_POST['quiz_id'] ?? 0);
        if (!$quiz_id || !$this->can_access(0, $quiz_id)) wp_send_json_error('Acceso denegado');

        $settings = json_decode(stripslashes((string) ($_POST['settings'] ?? '')), true);
        if (!is_array($settings)) wp_send_json_error('Datos inválidos');

        $result = QuizEditor::update_settings($quiz_id, $settings);
        if (is_wp_error($result)) wp_send_json_error($result->get_error_message());

        wp_send_json_success([
            'message' => 'Ajustes guardados',
            'settings' => $result
        ]);
    }

    public function handle_delete_question()
    {
        if (!check_ajax_referer('pqc_upload_nonce', 'nonce', false)) wp_send_json_error('Security failed');
        $quiz_id = (int) ($_POST['quiz_id'] ?? 0);
        $question_id = (int) ($_POST['question_id'] ?? 0);
        if (!$quiz_id || !$question_id || !$this->can_access(0, $quiz_id)) wp_send_json_error('Acceso denegado');

        if (QuizRepository::delete_question($quiz_id, $question_id)) {
            wp_send_json_success(['message' => 'Pregunta eliminada']);
        } else {
            wp_send_json_error('Error al eliminar');
        }
    }

    private function can_access(int $course_id = 0, int $quiz_id = 0): bool
    {
        return Permissions::can_access($course_id, $quiz_id);
    }
}
