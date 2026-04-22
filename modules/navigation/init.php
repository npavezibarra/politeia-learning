<?php
/**
 * Module Name: Navigation
 * Description: Unified navigation system for Desktop and Smartphone.
 * Namespace: Learni\Navigation
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define module constants
if (!defined('PL_NAV_PATH')) {
    define('PL_NAV_PATH', plugin_dir_path(__FILE__));
}
if (!defined('PL_NAV_URL')) {
    define('PL_NAV_URL', plugin_dir_url(__FILE__));
}

/**
 * PSR-4 Autoloader for Learni\Navigation
 */
require_once PL_NAV_PATH . 'includes/Navigation/NavOrchestrator.php';
require_once PL_NAV_PATH . 'includes/Navigation/NavEngine.php';
require_once PL_NAV_PATH . 'includes/Navigation/DesktopRenderer.php';
require_once PL_NAV_PATH . 'includes/Navigation/GutenbergRenderer.php';
require_once PL_NAV_PATH . 'includes/Navigation/MobileRenderer.php';

// Initialize Orchestrator immediately
Learni\Navigation\NavOrchestrator::get_instance();
