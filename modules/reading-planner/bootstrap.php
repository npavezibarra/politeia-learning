<?php
/**
 * Module bootstrap: Reading Planner (Bookshelf port)
 *
 * Phase 1 goal:
 * - Mirror `politeia-bookshelf/modules/reading-planner` inside `politeia-learning`.
 * - Avoid double-registration while `politeia-bookshelf` is still active.
 *
 * Enable with:
 * - define('PL_READING_PLANNER_MODULE_ENABLED', true);
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('PL_READING_PLANNER_MODULE_ENABLED') || !PL_READING_PLANNER_MODULE_ENABLED) {
    return;
}

// If Bookshelf (or another copy) already loaded Reading Planner, don't register twice.
if (defined('POLITEIA_READING_PLAN_PATH') || class_exists('\\Politeia\\ReadingPlanner\\Rest')) {
    return;
}

require_once __DIR__ . '/bookshelf-init.php';

if (class_exists('\\Politeia\\ReadingPlanner\\Init') && method_exists('\\Politeia\\ReadingPlanner\\Init', 'register')) {
    \Politeia\ReadingPlanner\Init::register();
}
