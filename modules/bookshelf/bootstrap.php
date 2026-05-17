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

// If another copy already loaded these modules, avoid double boot.
//
// IMPORTANT:
// Some environments define legacy constants (e.g. POLITEIA_READING_PATH) even when the
// module classes are not actually loaded. We only guard on class existence to avoid
// disabling the module accidentally.
if (
    class_exists('\\Politeia\\Reading\\Init')
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

// Safe require helper (prevents production fatals when some submodules are not deployed).
$pl_bookshelf_require_if_exists = static function (string $path, string $label): bool {
    if (file_exists($path)) {
        require_once $path;
        return true;
    }
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[Bookshelf] Missing module file: ' . $label . ' (' . $path . ')');
    }
    return false;
};

// Core modules.
if (
    $pl_bookshelf_require_if_exists(__DIR__ . '/modules/user-baseline/init.php', 'user-baseline/init.php')
    && $pl_bookshelf_require_if_exists(__DIR__ . '/modules/user-baseline/user-baseline.php', 'user-baseline/user-baseline.php')
    && class_exists('\\Politeia\\UserBaseline\\Init')
    && method_exists('\\Politeia\\UserBaseline\\Init', 'register')
) {
    \Politeia\UserBaseline\Init::register();
}

if (
    $pl_bookshelf_require_if_exists(__DIR__ . '/modules/reading/Init.php', 'reading/Init.php')
    && class_exists('\\Politeia\\Reading\\Init')
    && method_exists('\\Politeia\\Reading\\Init', 'register')
) {
    \Politeia\Reading\Init::register();
}

// ChatGPT module is optional in production (may be deployed later).
if (
    $pl_bookshelf_require_if_exists(__DIR__ . '/modules/chatgpt/init.php', 'chatgpt/init.php')
    && class_exists('\\Politeia\\ChatGPT\\Init')
    && method_exists('\\Politeia\\ChatGPT\\Init', 'register')
) {
    \Politeia\ChatGPT\Init::register();
}
