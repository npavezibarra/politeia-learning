<?php
/**
 * Module: Course Creator
 * Description: Handles user-facing course and group creation, sales dashboard, and student stats.
 */

if (!defined('ABSPATH'))
    exit;

define('PCG_CC_PATH', plugin_dir_path(__FILE__));
define('PCG_CC_URL', plugin_dir_url(__FILE__));

/**
 * Autoload classes for this module
 */
spl_autoload_register(function ($class) {
    if (strpos($class, 'PCG_CC_') === 0) {
        $file = PCG_CC_PATH . 'includes/class-' . strtolower(str_replace(['PCG_CC_', '_'], ['', '-'], $class)) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

/**
 * Initialize Module
 */
add_action('plugins_loaded', function () {
    if (class_exists('PCG_CC_Creator_Dashboard')) {
        new PCG_CC_Creator_Dashboard();
    }
    if (class_exists('PCG_CC_Course_Save_Handler')) {
        new PCG_CC_Course_Save_Handler();
    }
}, 20);
