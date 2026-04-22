<?php
/**
 * Module: Smartphone
 * Description: Smartphone-only UX components for Politeia (<= 599px).
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PL_SMARTPHONE_PATH', plugin_dir_path(__FILE__));
define('PL_SMARTPHONE_URL', plugin_dir_url(__FILE__));

spl_autoload_register(function ($class) {
    if (strpos($class, 'PL_Smartphone_') !== 0) {
        return;
    }

    $file = PL_SMARTPHONE_PATH . 'includes/class-' . strtolower(str_replace('_', '-', $class)) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

if (did_action('plugins_loaded')) {
    if (class_exists('PL_Smartphone_Menu')) {
        PL_Smartphone_Menu::init();
    }
} else {
    add_action('plugins_loaded', function () {
        if (class_exists('PL_Smartphone_Menu')) {
            PL_Smartphone_Menu::init();
        }
    }, 20);
}

