<?php
/**
 * Bookshelf admin menu (ported from politeia-bookshelf).
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/admin/functionalities-settings.php';

/**
 * Register Politeia Bookshelf admin menu.
 */
if (!function_exists('politeia_bookshelf_register_menu')) {
function politeia_bookshelf_register_menu(): void
{
    add_menu_page(
        __('Politeia Bookshelf', 'politeia-bookshelf'),
        __('Politeia Bookshelf', 'politeia-bookshelf'),
        'manage_options',
        'politeia-bookshelf',
        'politeia_bookshelf_render_admin_page',
        'dashicons-book-alt',
        6
    );

    add_submenu_page(
        'politeia-bookshelf',
        __('Overview', 'politeia-bookshelf'),
        __('Overview', 'politeia-bookshelf'),
        'manage_options',
        'politeia-bookshelf',
        'politeia_bookshelf_render_admin_page'
    );

    add_submenu_page(
        'politeia-bookshelf',
        __('Google Books API', 'politeia-bookshelf'),
        __('Google Books API', 'politeia-bookshelf'),
        'manage_options',
        'politeia-bookshelf-google-books',
        'politeia_bookshelf_render_admin_page'
    );

    add_submenu_page(
        'politeia-bookshelf',
        __('Functionalities', 'politeia-bookshelf'),
        __('Functionalities', 'politeia-bookshelf'),
        'manage_options',
        'politeia-bookshelf-functionalities',
        'politeia_bookshelf_render_functionalities_page'
    );
}
add_action('admin_menu', 'politeia_bookshelf_register_menu');
}
