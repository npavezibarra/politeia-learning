<?php
/**
 * Module bootstrap: Bookshelf (Bookshelf port)
 *
 * Phase 10 goal:
 * - Run Bookshelf functionality as a module within `politeia-learning`.
 * - Keep `politeia-bookshelf` installed as a shim, but avoid redundant/conflicting code.
 *
 * Enablement:
 * - Enabled by default when the module is present.
 * - To disable explicitly, set:
 *   define('PL_BOOKSHELF_MODULE_ENABLED', false);
 */

if (!defined('ABSPATH')) {
    exit;
}

// Default: enabled. Allow explicit disable via constant.
if (defined('PL_BOOKSHELF_MODULE_ENABLED') && !PL_BOOKSHELF_MODULE_ENABLED) {
    return;
}

// If Bookshelf plugin (or another copy) already loaded these modules, avoid double boot.
if (
    defined('POLITEIA_READING_PATH')
    || defined('POLITEIA_CHATGPT_DIR')
    || class_exists('\\Politeia\\Reading\\Init')
    || class_exists('\\Politeia\\ChatGPT\\Init')
    || class_exists('\\Politeia\\UserBaseline\\Init')
) {
    return;
}

// Bookshelf template helpers (My Books pages). These defer to Politeia Learning helpers when available.
require_once __DIR__ . '/includes/template-helpers.php';

// Shared settings helpers used by Reading + ChatGPT (Google Books API key, etc).
require_once __DIR__ . '/admin/google-books-settings.php';

// Bookshelf admin menu + settings screens.
require_once __DIR__ . '/bookshelf-admin.php';

// Core modules.
require_once __DIR__ . '/modules/user-baseline/init.php';
require_once __DIR__ . '/modules/user-baseline/user-baseline.php';
if (class_exists('\\Politeia\\UserBaseline\\Init') && method_exists('\\Politeia\\UserBaseline\\Init', 'register')) {
    \Politeia\UserBaseline\Init::register();
}

require_once __DIR__ . '/modules/reading/Init.php';
if (class_exists('\\Politeia\\Reading\\Init') && method_exists('\\Politeia\\Reading\\Init', 'register')) {
    \Politeia\Reading\Init::register();
}

require_once __DIR__ . '/modules/chatgpt/init.php';
if (class_exists('\\Politeia\\ChatGPT\\Init') && method_exists('\\Politeia\\ChatGPT\\Init', 'register')) {
    \Politeia\ChatGPT\Init::register();
}
