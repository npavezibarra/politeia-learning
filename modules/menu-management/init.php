<?php
/**
 * Module: Menu Management
 * Description: Adds dynamic menu items that WordPress menus can't generate (e.g., user-specific links).
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PL_MM_PATH', plugin_dir_path(__FILE__));
define('PL_MM_URL', plugin_dir_url(__FILE__));

spl_autoload_register(function ($class) {
    if (strpos($class, 'PL_MM_') !== 0) {
        return;
    }

    $file = PL_MM_PATH . 'includes/class-' . strtolower(str_replace(['PL_MM_', '_'], ['', '-'], $class)) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

add_action('plugins_loaded', function () {
    if (class_exists('PL_MM_Menu_Manager')) {
        new PL_MM_Menu_Manager();
    }
}, 20);

