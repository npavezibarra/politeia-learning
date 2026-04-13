<?php
/**
 * Module: Learni (internal)
 * Description: Internal LMS engine used by Politeia Learning (courses, lessons, enrollments, progress, quizzes).
 *
 * NOTE: This module intentionally does NOT register Learni's full frontend routing layer yet.
 * Center (/center-2) is migrated first; public learner UX will be migrated incrementally.
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PL_LEARNI_PATH', plugin_dir_path(__FILE__));
define('PL_LEARNI_URL', plugin_dir_url(__FILE__));

// Learni constants expected by the ported classes.
if (!defined('LEARNI_VERSION')) {
    define('LEARNI_VERSION', '0.1.15');
}
if (!defined('LEARNI_DB_VERSION')) {
    define('LEARNI_DB_VERSION', 5);
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
require_once PL_LEARNI_PATH . 'includes/Frontend/Templates.php';
require_once PL_LEARNI_PATH . 'includes/WooCommerce/Integration.php';
require_once PL_LEARNI_PATH . 'includes/WooCommerce/ProductSync.php';

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
        add_action('plugins_loaded', [__CLASS__, 'maybe_init_woocommerce'], 20);
    }

    public static function maybe_upgrade(): void
    {
        \Learni\Database\Installer::maybe_upgrade();
    }

    public static function register_post_types(): void
    {
        \Learni\PostTypes\Course::register();
        \Learni\PostTypes\Lesson::register();
    }

    public static function register_frontend_templates(): void
    {
        if (class_exists('PL_Learni_Frontend_Templates')) {
            PL_Learni_Frontend_Templates::init();
        }
    }

    public static function maybe_init_woocommerce(): void
    {
        \Learni\WooCommerce\Integration::maybe_init();
    }
}

PL_Learni_Module::init();
