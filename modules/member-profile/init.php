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

/**
 * Assets for the public profile route.
 *
 * IMPORTANT: load assets via wp_enqueue_* so they are printed in wp_head() and
 * not injected late inside templates (which can break font/icon loading).
 */
add_action('wp_enqueue_scripts', function (): void {
    $username = (string) get_query_var(PL_Member_Profile_Public_Route::USERNAME_VAR, '');
    if ($username === '') {
        return;
    }

    // Google Material Symbols (icons). Load the full set to avoid "missing icon" fallbacks.
    wp_enqueue_style(
        'politeia-material-symbols-outlined',
        'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=block',
        [],
        null
    );
}, 5);
