<?php
/**
 * Module: Quiz Creator
 * Description: Create LearnDash quizzes by uploading structured files (JSON, CSV, XML, TXT).
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define module constants
define('PCG_QC_VERSION', '1.0.0');
define('PCG_QC_PATH', plugin_dir_path(__FILE__));
define('PCG_QC_URL', plugin_dir_url(__FILE__));

// Map old constants to new ones to maintain compatibility in included files
if (!defined('PQC_VERSION'))
    define('PQC_VERSION', PCG_QC_VERSION);
if (!defined('PQC_PLUGIN_DIR'))
    define('PQC_PLUGIN_DIR', PCG_QC_PATH);
if (!defined('PQC_PLUGIN_URL'))
    define('PQC_PLUGIN_URL', PCG_QC_URL);

/**
 * Quiz Creator Module Class
 */
class PCG_QC_Module
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
        $this->load_dependencies();
        $this->init_hooks();
    }

    private function load_dependencies()
    {
        require_once PCG_QC_PATH . 'includes/class-quiz-creator.php';
        require_once PCG_QC_PATH . 'includes/class-file-parser.php';
        require_once PCG_QC_PATH . 'includes/class-shortcode.php';
        require_once PCG_QC_PATH . 'includes/class-ajax-handler.php';
    }

    private function init_hooks()
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('init', [$this, 'init_components']);
    }

    public function init_components()
    {
        if (!defined('LEARNDASH_VERSION')) {
            return;
        }

        // Initialize components
        PQC_Shortcode::get_instance();
        PQC_Ajax_Handler::get_instance();
    }

    public function enqueue_assets()
    {
        // CSS
        wp_enqueue_style(
            'pqc-styles',
            PCG_QC_URL . 'assets/css/quiz-creator.css',
            [],
            PCG_QC_VERSION
        );

        // JS
        wp_enqueue_script(
            'pqc-scripts',
            PCG_QC_URL . 'assets/js/quiz-creator.js',
            ['jquery'],
            PCG_QC_VERSION,
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

// Initialize the module
PCG_QC_Module::get_instance();
