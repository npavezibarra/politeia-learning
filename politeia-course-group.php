<?php
/**
 * Plugin Name: Politeia Course Group
 * Description: Custom functionalities for Politeia website related to courses, grouping, selling, and creating courses.
 * Author: Nico / Politeia
 * Version: 1.1.0
 * Text Domain: politeia-course-group
 * Codex Enabled: true
 */

if (!defined('ABSPATH'))
    exit;

// Core Constants
define('PCG_PATH', plugin_dir_path(__FILE__));
define('PCG_URL', plugin_dir_url(__FILE__));

/**
 * Load Global Dependencies
 */
// Composer Autoloader
if (file_exists(PCG_PATH . 'vendor/autoload.php')) {
    require_once PCG_PATH . 'vendor/autoload.php';
}

// Codex Init
if (file_exists(PCG_PATH . 'codex/init.php')) {
    require_once PCG_PATH . 'codex/init.php';
}

/**
 * Module Loader Class
 * Manages the different standalone modules of the plugin.
 */
class PCG_Module_Loader
{
    /**
     * List of available modules and their status.
     * In the future, this could be managed via an admin UI or settings.
     */
    private static $modules = [
        'course-programs' => true,
        'course-integration' => true,
    ];

    /**
     * Initialize active modules.
     */
    public static function init()
    {
        foreach (self::$modules as $module_slug => $enabled) {
            if ($enabled) {
                $init_file = PCG_PATH . 'modules/' . $module_slug . '/init.php';
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
PCG_Module_Loader::init();
