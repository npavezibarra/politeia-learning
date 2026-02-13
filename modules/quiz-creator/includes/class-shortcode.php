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
        error_log('PQC: Shortcode render_shortcode() called');

        // Check permissions
        if (!current_user_can('edit_posts')) {
            return '<p>' . __('You do not have permission to access the Quiz Creator.', 'politeia-quiz-creator') . '</p>';
        }

        $this->enqueue_shortcode_assets();

        $quiz_id = isset($_GET['edit_quiz']) ? intval($_GET['edit_quiz']) : 0;

        ob_start();
        if ($quiz_id && get_post_type($quiz_id) === 'sfwd-quiz') {
            include PQC_PLUGIN_DIR . 'templates/quiz-editor.php';
        } else {
            include PQC_PLUGIN_DIR . 'templates/upload-form.php';
        }
        return ob_get_clean();
    }

    /**
     * Enqueue assets for shortcode
     */
    private function enqueue_shortcode_assets()
    {
        // CSS
        wp_enqueue_style(
            'pqc-styles',
            PQC_PLUGIN_URL . 'assets/css/quiz-creator.css',
            [],
            PQC_VERSION
        );

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
