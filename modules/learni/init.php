<?php
/**
 * Module: Learni (internal)
 * Description: Internal LMS engine used by Politeia Learning (courses, lessons, specializations, programs, enrollments, progress, quizzes).
 *
 * NOTE: This module intentionally does NOT register Learni's full frontend routing layer yet.
 * Center (/center-2) is migrated first; public learner UX will be migrated incrementally.
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PL_LEARNI_PATH', plugin_dir_path(__FILE__));
define('PL_LEARNI_URL', plugin_dir_url(__FILE__));

// Compatibility constants for migrated Course Creator logic.
define('PL_CC_PATH', PL_LEARNI_PATH);
define('PL_CC_URL', PL_LEARNI_URL);

// Learni constants expected by the ported classes.
if (!defined('LEARNI_VERSION')) {
    define('LEARNI_VERSION', '0.1.15');
}
if (!defined('LEARNI_DB_VERSION')) {
    define('LEARNI_DB_VERSION', 6);
}
if (!defined('LEARNI_PLUGIN_FILE')) {
    define('LEARNI_PLUGIN_FILE', __FILE__);
}
if (!defined('LEARNI_PLUGIN_DIR')) {
    define('LEARNI_PLUGIN_DIR', PL_LEARNI_PATH);
}

require_once PL_LEARNI_PATH . 'includes/Database/Installer.php';
require_once PL_LEARNI_PATH . 'includes/Database/Enrollments.php';
require_once PL_LEARNI_PATH . 'includes/Database/Progress.php';
require_once PL_LEARNI_PATH . 'includes/Courses/Outline.php';
require_once PL_LEARNI_PATH . 'includes/Access/Access.php';
require_once PL_LEARNI_PATH . 'includes/PostTypes/Course.php';
require_once PL_LEARNI_PATH . 'includes/PostTypes/Lesson.php';
require_once PL_LEARNI_PATH . 'includes/PostTypes/Specialization.php';
require_once PL_LEARNI_PATH . 'includes/PostTypes/Program.php';
require_once PL_LEARNI_PATH . 'includes/Frontend/Templates.php';
require_once PL_LEARNI_PATH . 'includes/Frontend/Certificates.php';
require_once PL_LEARNI_PATH . 'includes/Frontend/Actions.php';
require_once PL_LEARNI_PATH . 'includes/Frontend/Assessment.php';
require_once PL_LEARNI_PATH . 'includes/Frontend/ViewCourse.php';
require_once PL_LEARNI_PATH . 'includes/Frontend/ViewLesson.php';
require_once PL_LEARNI_PATH . 'includes/Frontend/CrossEvalPopup.php';
require_once PL_LEARNI_PATH . 'includes/Certificates/CertificateCode.php';
require_once PL_LEARNI_PATH . 'includes/Rest/Routes.php';
require_once PL_LEARNI_PATH . 'includes/WooCommerce/Integration.php';
require_once PL_LEARNI_PATH . 'includes/WooCommerce/ProductSync.php';
require_once PL_LEARNI_PATH . 'includes/Admin/UserProfile.php';

final class PL_Learni_Module
{
    /**
     * Ensure schema + caps exist at activation time.
     */
    public static function activate(): void
    {
        \Learni\Database\Installer::activate();
        self::register_post_types();
    }

    public static function init(): void
    {
        add_action('plugins_loaded', [__CLASS__, 'maybe_upgrade'], 5);
        add_action('init', [__CLASS__, 'register_post_types'], 0);
        add_action('init', [__CLASS__, 'register_frontend_templates'], 1);
        add_action('rest_api_init', ['\\Learni\\Rest\\Routes', 'register'], 5);
        add_action('plugins_loaded', [__CLASS__, 'maybe_init_woocommerce'], 20);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_cross_eval_popup'], 25);

        // Dashboard (ex-Course Creator) initialization.
        add_action('init', [__CLASS__, 'init_dashboard'], 0);

        if (is_admin()) {
            \Learni\Admin\UserProfile::init();
        }

        // Legacy PL_CC autoloader for migrated classes.
        spl_autoload_register([__CLASS__, 'autoload_dashboard_classes']);
    }

    /**
     * Legacy autoloader for PL_CC classes migrated to Dashboard subfolder.
     */
    public static function autoload_dashboard_classes(string $class): void
    {
        if (strpos($class, 'PL_CC_') === 0) {
            $file = PL_LEARNI_PATH . 'includes/Dashboard/class-' . strtolower(str_replace(['PL_CC_', '_'], ['', '-'], $class)) . '.php';
            if (file_exists($file)) {
                require_once $file;
            }
        }
    }

    /**
     * Initialize Dashboard components.
     */
    public static function init_dashboard(): void
    {
        if (class_exists('PL_CC_Creator_Dashboard')) {
            new PL_CC_Creator_Dashboard();
        }
        if (class_exists('PL_CC_Course_Save_Handler')) {
            new PL_CC_Course_Save_Handler();
        }
        if (class_exists('PL_CC_Inclusion_Approvals')) {
            PL_CC_Inclusion_Approvals::init();
        }
    }

    public static function maybe_upgrade(): void
    {
        \Learni\Database\Installer::maybe_upgrade();
    }

    public static function register_post_types(): void
    {
        \Learni\PostTypes\Course::register();
        \Learni\PostTypes\Lesson::register();
        \Learni\PostTypes\Specialization::register();
        \Learni\PostTypes\Program::register();
    }

    public static function register_frontend_templates(): void
    {
        if (class_exists('PL_Learni_Frontend_Templates')) {
            PL_Learni_Frontend_Templates::init();
        }
    }

    public static function enqueue_cross_eval_popup(): void
    {
        if (class_exists('PL_Learni_Cross_Eval_Popup')) {
            PL_Learni_Cross_Eval_Popup::init();
        }
    }

    public static function maybe_init_woocommerce(): void
    {
        \Learni\WooCommerce\Integration::maybe_init();
    }
}

PL_Learni_Module::init();
