<?php
/**
 * Module: Login Register
 * Description: Front-end login, registration, and email confirmation flow for Politeia Learning.
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PL_AUTH_PATH', plugin_dir_path(__FILE__));
define('PL_AUTH_URL', plugin_dir_url(__FILE__));

spl_autoload_register(function ($class) {
    if (strpos($class, 'PL_Auth_') !== 0) {
        return;
    }

    $file = PL_AUTH_PATH . 'includes/class-' . strtolower(str_replace('_', '-', $class)) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

if (did_action('plugins_loaded')) {
    if (class_exists('PL_Auth_Login_Register')) {
        PL_Auth_Login_Register::init();
    }
} else {
    add_action('plugins_loaded', function () {
        if (class_exists('PL_Auth_Login_Register')) {
            PL_Auth_Login_Register::init();
        }
    }, 20);
}
