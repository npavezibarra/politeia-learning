<?php
/**
 * Module: Blog Post
 * Description: Custom blog post template for Politeia.
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PL_BP_PATH', plugin_dir_path(__FILE__));
define('PL_BP_URL', plugin_dir_url(__FILE__));

spl_autoload_register(function ($class) {
    if (strpos($class, 'PL_BP_') !== 0) {
        return;
    }

    $file = PL_BP_PATH . 'includes/class-' . strtolower(str_replace(['PL_BP_', '_'], ['', '-'], $class)) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

add_action('plugins_loaded', function () {
    if (class_exists('PL_BP_Blog_Post_Template')) {
        new PL_BP_Blog_Post_Template();
    }
}, 20);

