<?php
/**
 * Module: Course Integration
 * Description: Integrates WooCommerce, LearnDash, and Politeia custom features.
 */

if (!defined('ABSPATH'))
    exit;

// Define module constants
define('PL_CI_PATH', plugin_dir_path(__FILE__));
define('PL_CI_URL', plugin_dir_url(__FILE__));

/**
 * Autoload classes for this module
 */
spl_autoload_register(function ($class) {
    if (strpos($class, 'PL_CI_') === 0) {
        $file = PL_CI_PATH . 'includes/class-' . strtolower(str_replace(['PL_CI_', '_'], ['', '-'], $class)) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

/**
 * Initialize Module
 */
add_action('plugins_loaded', function () {
    // Multi-author support for LearnDash Courses
    if (class_exists('PL_CI_Course_Authors')) {
        new PL_CI_Course_Authors();
    }
}, 30);
