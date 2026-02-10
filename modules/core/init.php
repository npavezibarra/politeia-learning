<?php
/**
 * Module: Core
 * Description: Core functionality and main admin dashboard for Politeia Learning.
 */

if (!defined('ABSPATH'))
    exit;

define('PCG_CORE_PATH', plugin_dir_path(__FILE__));
define('PCG_CORE_URL', plugin_dir_url(__FILE__));

/**
 * Autoload classes for this module
 */
spl_autoload_register(function ($class) {
    if (strpos($class, 'PCG_Core_') === 0) {
        $file = PCG_CORE_PATH . 'includes/class-' . strtolower(str_replace(['PCG_Core_', '_'], ['', '-'], $class)) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

/**
 * Initialize Module
 */
add_action('plugins_loaded', function () {
    if (class_exists('PCG_Core_Admin')) {
        new PCG_Core_Admin();
    }
}, 10);
