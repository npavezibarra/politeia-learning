<?php
/**
 * Admin UI for Under Construction toggle.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PL_Under_Construction_Admin
{
    public const OPTION_ENABLED = 'pl_under_construction_enabled';
    private const NONCE_ACTION = 'pl_under_construction_save_settings';

    public function __construct()
    {
        add_action('admin_menu', [$this, 'register_menu'], 50);
    }

    public function register_menu(): void
    {
        add_submenu_page(
            'politeia-learning',
            __('Under Construction', 'politeia-learning'),
            __('Under Construction', 'politeia-learning'),
            'manage_options',
            'pl-under-construction',
            [$this, 'render_page']
        );
    }

    public static function is_enabled(): bool
    {
        return (bool) get_option(self::OPTION_ENABLED, false);
    }

    public function render_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'politeia-learning'));
        }

        $saved = false;
        if (isset($_POST['pl_under_construction_submitted']) && check_admin_referer(self::NONCE_ACTION)) {
            $enabled = !empty($_POST['pl_under_construction_enabled']);
            update_option(self::OPTION_ENABLED, $enabled ? 1 : 0);
            $saved = true;
        }

        $enabled = self::is_enabled();
        include PL_UNDER_CONSTRUCTION_PATH . 'templates/admin-page.php';
    }
}

