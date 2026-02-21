<?php
/**
 * Module: Course Creator
 * Description: Handles user-facing course and group creation, sales dashboard, and student stats.
 */

if (!defined('ABSPATH'))
    exit;

define('PL_CC_PATH', plugin_dir_path(__FILE__));
define('PL_CC_URL', plugin_dir_url(__FILE__));

/**
 * Autoload classes for this module
 */
spl_autoload_register(function ($class) {
    if (strpos($class, 'PL_CC_') === 0) {
        $file = PL_CC_PATH . 'includes/class-' . strtolower(str_replace(['PL_CC_', '_'], ['', '-'], $class)) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

/**
 * Initialize Module
 */
add_action('plugins_loaded', function () {
    if (class_exists('PL_CC_Creator_Dashboard')) {
        new PL_CC_Creator_Dashboard();
    }
    if (class_exists('PL_CC_Course_Save_Handler')) {
        new PL_CC_Course_Save_Handler();
    }
    if (class_exists('PL_CC_Inclusion_Approvals')) {
        PL_CC_Inclusion_Approvals::init();
    }
}, 20);
