<?php

if (!defined('ABSPATH')) {
    exit;
}

class PL_Smartphone_Menu
{
    public static function init(): void
    {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets'], 30);
        add_action('wp_body_open', [__CLASS__, 'render_header'], 5);
    }

    private static function is_center_dashboard_request(): bool
    {
        // Center pages set this query var via rewrite: /members/{user}/center(-2)/
        $slug = (string) get_query_var('pcg_creator_user');
        return $slug !== '';
    }

    public static function enqueue_assets(): void
    {
        if (!self::is_center_dashboard_request()) {
            return;
        }

        $css_path = PL_SMARTPHONE_PATH . 'menu/assets/css/header.css';
        $ver = file_exists($css_path) ? (string) filemtime($css_path) : '1';
        wp_enqueue_style('pl-smartphone-header', PL_SMARTPHONE_URL . 'menu/assets/css/header.css', [], $ver);

        $js_path = PL_SMARTPHONE_PATH . 'menu/assets/js/menu.js';
        $js_ver = file_exists($js_path) ? (string) filemtime($js_path) : '1';
        wp_enqueue_script('pl-smartphone-menu', PL_SMARTPHONE_URL . 'menu/assets/js/menu.js', [], $js_ver, true);
    }

    public static function render_header(): void
    {
        if (!self::is_center_dashboard_request()) {
            return;
        }

        $template = PL_SMARTPHONE_PATH . 'menu/header.php';
        if (!file_exists($template)) {
            return;
        }

        include $template;
    }
}
