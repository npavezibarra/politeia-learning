<?php

if (!defined('ABSPATH')) {
    exit;
}

class PL_MM_Menu_Manager
{
    /**
     * Theme locations where the dynamic item should be added.
     *
     * @var string[]
     */
    private $theme_locations = [
        'header-menu',
        'mobile-menu-logged-in',
    ];

    public function __construct()
    {
        add_filter('wp_nav_menu_items', [$this, 'add_center_item'], 20, 2);
    }

    public function add_center_item(string $items, $args): string
    {
        if (!is_user_logged_in()) {
            return $items;
        }

        $theme_location = isset($args->theme_location) ? (string) $args->theme_location : '';
        if ($theme_location === '' || !in_array($theme_location, $this->theme_locations, true)) {
            return $items;
        }

        // Prevent duplicates.
        if (strpos($items, 'pl-center-menu-item') !== false) {
            return $items;
        }

        $current_user = wp_get_current_user();
        $username = isset($current_user->user_login) ? (string) $current_user->user_login : '';
        if ($username === '') {
            return $items;
        }

        $center_url = home_url(sprintf('/members/%s/center', rawurlencode($username)));
        $label = __('Center', 'politeia-learning');

        $li = sprintf(
            '<li class="menu-item menu-item-type-custom menu-item-object-custom pl-center-menu-item"><a href="%s">%s</a></li>',
            esc_url($center_url),
            esc_html($label)
        );

        return $items . $li;
    }
}

