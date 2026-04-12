<?php
/**
 * Module: Member Profile
 * Description: Custom profile template for Politeia members.
 */

if (!defined('ABSPATH'))
    exit;

define('PL_MEMBER_PROFILE_PATH', plugin_dir_path(__FILE__));
define('PL_MEMBER_PROFILE_URL', plugin_dir_url(__FILE__));

/**
 * Autoload classes for this module
 */
spl_autoload_register(function ($class) {
    if (strpos($class, 'PL_Member_Profile_') === 0) {
        $file = PL_MEMBER_PROFILE_PATH . 'includes/class-' . strtolower(str_replace(['PL_Member_Profile_', '_'], ['', '-'], $class)) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

/**
 * Initialize Module
 */
add_action('init', function () {
    if (class_exists('PL_Member_Profile_Public_Route')) {
        new PL_Member_Profile_Public_Route();
    }
    if (class_exists('PL_Member_Profile_Template')) {
        new PL_Member_Profile_Template();
    }
    if (class_exists('PL_Member_Profile_Portfolio_Manager')) {
        PL_Member_Profile_Portfolio_Manager::get_instance();
    }
}, 0);
