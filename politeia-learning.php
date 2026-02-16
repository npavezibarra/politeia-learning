<?php
/**
 * Plugin Name: Politeia Learning
 * Description: Custom functionalities for Politeia website related to courses, grouping, selling, and creating courses.
 * Author: Nico / Politeia
 * Version: 1.2.0
 * Text Domain: politeia-learning
 * Domain Path: /languages
 * Codex Enabled: true
 */

if (!defined('ABSPATH'))
    exit;

// Core Constants
define('PL_PATH', plugin_dir_path(__FILE__));
define('PL_URL', plugin_dir_url(__FILE__));
define('PL_DB_VERSION', '1.1.0');

// Load Global Includes
require_once PL_PATH . 'includes/class-installer.php';
require_once PL_PATH . 'includes/class-upgrader.php';

// Automatic Database Upgrades
add_action('plugins_loaded', ['PL_Upgrader', 'maybe_upgrade']);

// Load translations. WordPress will prefer WP_LANG_DIR/plugins first.
add_action('plugins_loaded', function () {
    load_plugin_textdomain('politeia-learning', false, dirname(plugin_basename(__FILE__)) . '/languages');
}, 5);

/**
 * Prefer "FirstName LastName" on LearnDash course pages when templates ask for display_name.
 * Keeps global display_name unchanged, only affects rendering.
 */
function pl_get_user_full_name_or_display_name(int $user_id, string $fallback = ''): string
{
    $user = get_userdata($user_id);
    if (!$user) {
        return $fallback;
    }

    $full_name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
    if ($full_name !== '') {
        return $full_name;
    }

    return $user->display_name ?: $fallback;
}

add_filter('get_the_author_display_name', function ($display_name, $user_id) {
    if (!is_singular('sfwd-courses')) {
        return $display_name;
    }

    return pl_get_user_full_name_or_display_name((int) $user_id, (string) $display_name);
}, 10, 2);

// BuddyBoss/BuddyPress display name (if used by the theme on course pages).
add_filter('bp_core_get_user_displayname', function ($display_name, $user_id) {
    if (!is_singular('sfwd-courses')) {
        return $display_name;
    }

    return pl_get_user_full_name_or_display_name((int) $user_id, (string) $display_name);
}, 10, 2);

/**
 * Load Global Dependencies
 */
// Composer Autoloader
if (file_exists(PL_PATH . 'vendor/autoload.php')) {
    require_once PL_PATH . 'vendor/autoload.php';
}

// Codex Init
if (file_exists(PL_PATH . 'codex/init.php')) {
    require_once PL_PATH . 'codex/init.php';
}

/**
 * Module Loader Class
 * Manages the different standalone modules of the plugin.
 */
class PL_Module_Loader
{
    /**
     * List of available modules and their status.
     * In the future, this could be managed via an admin UI or settings.
     */
    private static $modules = [
        'core' => true,
        'course-programs' => true,
        'course-integration' => true,
        'course-creator' => true,
        'quiz-creator' => true,
        'quiz-control' => true,
        'woo' => true,
    ];

    /**
     * Initialize active modules.
     */
    public static function init()
    {
        foreach (self::$modules as $module_slug => $enabled) {
            if ($enabled) {
                $init_file = PL_PATH . 'modules/' . $module_slug . '/init.php';
                if (file_exists($init_file)) {
                    require_once $init_file;
                }
            }
        }
    }

    /**
     * Check if a module is enabled.
     */
    public static function is_module_enabled($module_slug)
    {
        return isset(self::$modules[$module_slug]) && self::$modules[$module_slug];
    }
}

// Start the modules
PL_Module_Loader::init();
