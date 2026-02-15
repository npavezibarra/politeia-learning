<?php
/**
 * Module: Core
 * Description: Core functionality and main admin dashboard for Politeia Learning.
 */

if (!defined('ABSPATH'))
    exit;

define('PL_CORE_PATH', plugin_dir_path(__FILE__));
define('PL_CORE_URL', plugin_dir_url(__FILE__));

/**
 * Autoload classes for this module
 */
spl_autoload_register(function ($class) {
    if (strpos($class, 'PL_Core_') === 0) {
        $file = PL_CORE_PATH . 'includes/class-' . strtolower(str_replace(['PL_Core_', '_'], ['', '-'], $class)) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

/**
 * Initialize Module
 */
add_action('plugins_loaded', function () {
    if (class_exists('PL_Core_Admin')) {
        new PL_Core_Admin();
    }
}, 10);
