<?php
/**
 * Plugin Name: Politeia Learning
 * Description: Custom functionalities for Politeia website related to courses, grouping, selling, and creating courses.
 * Author: Nico / Politeia
 * Version: 1.2.0
 * Text Domain: politeia-learning
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
