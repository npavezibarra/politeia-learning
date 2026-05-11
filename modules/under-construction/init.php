<?php
/**
 * Module: Under Construction
 * Description: Toggle a site-wide under construction mode with an allowlist for admin/editor.
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PL_UNDER_CONSTRUCTION_PATH', plugin_dir_path(__FILE__));
define('PL_UNDER_CONSTRUCTION_URL', plugin_dir_url(__FILE__));

spl_autoload_register(function ($class) {
    if (strpos($class, 'PL_Under_Construction_') !== 0) {
        return;
    }

    $file = PL_UNDER_CONSTRUCTION_PATH . 'includes/class-' . strtolower(str_replace('_', '-', $class)) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

add_action('plugins_loaded', function () {
    if (class_exists('PL_Under_Construction_Admin')) {
        new PL_Under_Construction_Admin();
    }

    if (class_exists('PL_Under_Construction_Guard')) {
        new PL_Under_Construction_Guard();
    }
}, 10);

