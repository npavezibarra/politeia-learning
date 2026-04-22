<?php

namespace Learni\Navigation;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles rendering for Mobile/Smartphone navigation elements.
 */
class MobileRenderer
{
    /**
     * Renders the smartphone-specific header if applicable.
     */
    public static function render_header(): void
    {
        if (is_admin()) {
            return;
        }

        $is_center = self::is_center_request();
        $breadcrumb = $is_center ? NavEngine::get_breadcrumb() : [];
        $bc_action = $breadcrumb['action'] ?? '';
        $bc_parent = $breadcrumb['parent'] ?? '';
        $bc_sub    = $breadcrumb['sub'] ?? '';
        $section_label = $bc_parent;
        
        $logo_html = function_exists('get_custom_logo') ? (string) get_custom_logo() : '';
        $home_url = home_url('/');

        ob_start();
        $template = PL_NAV_PATH . 'templates/navigation/mobile-header.php';
        if (file_exists($template)) {
            include $template;
        }
        echo ob_get_clean();
    }

    /**
     * Checks if we are in the Center/Dashboard area where the smartphone menu is used.
     */
    public static function is_center_request(): bool
    {
        // Center pages set this query var via rewrite: /members/{user}/center/
        return (string) get_query_var('pcg_creator_user') !== '';
    }
}
