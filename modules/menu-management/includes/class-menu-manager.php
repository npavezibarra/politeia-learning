<?php

if (!defined('ABSPATH')) {
    exit;
}

class PL_MM_Menu_Manager
{
    /**
     * Theme locations where the plugin should manage the main navigation.
     *
     * @var string[]
     */
    private $preferred_theme_locations = [
        // BuddyBoss (legacy).
        'header-menu',
        'header-menu-logout',
        'mobile-menu-logged-in',
        'mobile-menu-logged-out',

        // Common WordPress themes.
        'primary',
        'primary-menu',
        'menu-1',
        'top',
        'main',
        'header',
        'header-menu',
        'main-menu',
    ];

    public function __construct()
    {
        // Ensure we can still render our menu even if no menu is assigned to the location.
        add_filter('wp_nav_menu_args', [$this, 'filter_wp_nav_menu_args'], 5, 1);

        // Hard override: short-circuit wp_nav_menu() output for managed locations.
        add_filter('pre_wp_nav_menu', [$this, 'pre_wp_nav_menu'], 10000, 2);

        // Gutenberg Navigation block support (Site Editor). Only overrides when the block has our marker class.
        add_filter('render_block_core_navigation', [$this, 'filter_navigation_block'], 10000, 2);
        // Fallback for environments where the block-specific filter is not fired as expected.
        add_filter('render_block', [$this, 'filter_render_block'], 10000, 2);
        // Extra compatibility: some setups still use the unsanitized filter name.
        add_filter('render_block_core/navigation', [$this, 'filter_navigation_block'], 10000, 2);

        // Replace the menu items with plugin-managed items for the selected locations.
        // Use a very late priority to override any theme/plugin that appends default items.
        add_filter('wp_nav_menu_items', [$this, 'filter_menu_items'], 10000, 2);

        // Keep the site header visible while scrolling.
        add_action('wp_enqueue_scripts', [$this, 'enqueue_fixed_header_styles'], 20);
    }

    public function enqueue_fixed_header_styles(): void
    {
        if (is_admin()) {
            return;
        }

        // Use a "dummy" style handle so we can safely attach inline CSS.
        wp_register_style('pl-mm-fixed-header', false, [], '1.0.0');
        wp_enqueue_style('pl-mm-fixed-header');

        // Prefer position: sticky (does not break layout like fixed would).
        $css = ''
            . 'header.wp-block-template-part,'
            . '#masthead,'
            . '#header{'
            . 'position:sticky;'
            . 'top:0;'
            . 'z-index:9999;'
            . 'background:inherit;'
            . 'border-bottom:1px solid #dcdcdc;'
            . '}'
            . 'body.admin-bar header.wp-block-template-part,'
            . 'body.admin-bar #masthead,'
            . 'body.admin-bar #header{'
            . 'top:var(--wp-admin--admin-bar--height,32px);'
            . '}'
            . '.pl-managed-menu .wp-block-navigation-item__label,'
            . '.pl-managed-menu .wp-block-pages-list__item__link,'
            . 'header.wp-block-template-part .wp-block-navigation-item__label{'
            . 'text-transform:uppercase;'
            . 'font-size:12px;'
            . 'font-weight:600;'
            . 'letter-spacing:1px;'
            . '}'
            . '.pl-user-menu-link,'
            . '.pl-user-menu-link.wp-block-navigation-item__content,'
            . '.pl-user-menu-link.wp-block-pages-list__item__link{'
            . 'display:inline-flex;'
            . 'align-items:center;'
            . 'gap:8px;'
            . 'font-family:Poppins,sans-serif;'
            . 'text-transform:none;'
            . 'letter-spacing:0;'
            . '}'
            . '.pl-user-menu__label{'
            . 'font-family:Poppins,sans-serif;'
            . 'text-transform:none;'
            . 'font-size:13px;'
            . 'letter-spacing:0;'
            . '}'
            . '.pl-user-menu__caret{'
            . 'width:12px;'
            . 'height:12px;'
            . 'display:block;'
            . 'flex:0 0 auto;'
            . 'color:currentColor;'
            . '}'
            . '.pl-user-menu__avatar{'
            . 'width:40px;'
            . 'height:40px;'
            . 'border-radius:999px;'
            . 'object-fit:cover;'
            . 'flex:0 0 auto;'
            . '}'
            . '.pl-user-menu-item{'
            . 'margin-left:8px;'
            . 'position:relative;'
            . '}'
            . '.pl-auth-menu-item{'
            . 'margin-left:12px;'
            . '}'
            . '.pl-auth-menu-link{'
            . 'display:inline-flex;'
            . 'align-items:center;'
            . 'justify-content:center;'
            . 'min-height:0;'
            . 'padding:7px 20px;'
            . 'border-radius:6px;'
            . 'background:#000;'
            . 'color:#fff !important;'
            . 'font-family:Poppins,sans-serif;'
            . 'font-size:12px;'
            . 'font-weight:500 !important;'
            . 'letter-spacing:2px;'
            . 'text-transform:uppercase;'
            . 'text-decoration:none !important;'
            . 'border:0;'
            . 'appearance:none;'
            . 'cursor:pointer;'
            . '}'
            . '.pl-auth-menu-link:hover,'
            . '.pl-auth-menu-link:focus{'
            . 'background:#1a1a1a;'
            . 'color:#fff !important;'
            . '}'
            . '.pl-user-menu__toggle{'
            . 'display:inline-flex;'
            . 'align-items:center;'
            . 'gap:8px;'
            . '}'
            . 'ul.pl-user-menu__dropdown{'
            . 'display:none;'
            . 'position:absolute;'
            . 'top:calc(100% + 10px);'
            . 'right:0;'
            . 'min-width:220px;'
            . 'padding:10px;'
            . 'margin:0;'
            . 'list-style:none;'
            . 'background:#fff;'
            . 'border:1px solid #e5e5e5;'
            . 'border-radius:12px;'
            . 'box-shadow:0 18px 40px rgba(0,0,0,.12);'
            . 'z-index:10000;'
            . '}'
            . '.pl-user-menu-item.is-open .pl-user-menu__dropdown{'
            . 'display:block;'
            . '}'
            . '.pl-user-menu__toggle{'
            . 'background:transparent;'
            . 'border:0;'
            . 'padding:0;'
            . 'cursor:pointer;'
            . 'font:inherit;'
            . 'text-align:left;'
            . '}'
            . '.pl-user-menu__toggle:focus-visible{'
            . 'outline:2px solid currentColor;'
            . 'outline-offset:4px;'
            . '}'
            . '.pl-user-menu__dropdown-item a{'
            . 'display:flex;'
            . 'align-items:center;'
            . 'padding:10px 12px;'
            . 'border-radius:8px;'
            . 'font-size:13px;'
            . 'font-weight:500;'
            . 'text-transform:none;'
            . 'letter-spacing:0;'
            . 'color:#111;'
            . 'text-decoration:none;'
            . '}'
            . '.pl-user-menu__dropdown-item a:hover,'
            . '.pl-user-menu__dropdown-item a:focus{'
            . 'background:#f3f3f3;'
            . '}'
            . '.pl-user-menu__toggle-caret{'
            . 'width:12px;'
            . 'height:12px;'
            . 'display:block;'
            . '}';

        wp_add_inline_style('pl-mm-fixed-header', $css);

        wp_register_script('pl-mm-user-dropdown', false, [], '1.0.0', true);
        wp_enqueue_script('pl-mm-user-dropdown');
        wp_add_inline_script(
            'pl-mm-user-dropdown',
            '(function(){'
            . 'var selector = ".pl-user-menu-item";'
            . 'function closeAll(except){'
            . 'document.querySelectorAll(selector + ".is-open").forEach(function(item){'
            . 'if (except && item === except) { return; }'
            . 'item.classList.remove("is-open");'
            . 'var toggle = item.querySelector(".pl-user-menu__toggle");'
            . 'if (toggle) { toggle.setAttribute("aria-expanded","false"); }'
            . '});'
            . '}'
            . 'document.addEventListener("click", function(event){'
            . 'var item = event.target.closest(selector);'
            . 'var toggle = event.target.closest(".pl-user-menu__toggle");'
            . 'if (toggle && item) {'
            . 'event.preventDefault();'
            . 'var isOpen = item.classList.contains("is-open");'
            . 'closeAll(item);'
            . 'if (!isOpen) {'
            . 'item.classList.add("is-open");'
            . 'toggle.setAttribute("aria-expanded","true");'
            . '}'
            . 'return;'
            . '}'
            . 'if (!event.target.closest(".pl-user-menu__dropdown")) {'
            . 'closeAll();'
            . '}'
            . '});'
            . 'document.addEventListener("keydown", function(event){'
            . 'if (event.key === "Escape") { closeAll(); }'
            . '});'
            . '})();'
        );
    }

    /**
     * Replace items inside a Navigation block with plugin-managed items.
     *
     * This mirrors the Red Cultural pattern: the CTA is injected into the actual
     * block navigation markup instead of relying on a separate header fragment.
     *
     * @param string $block_content
     * @param array $block
     */
    public function filter_navigation_block(string $block_content, array $block): string
    {
        if (is_admin()) {
            return $block_content;
        }

        if (!isset($block['blockName']) || (string) $block['blockName'] !== 'core/navigation') {
            return $block_content;
        }

        // The theme footer uses Navigation blocks with overlayMenu="never"; keep the CTA out of footer links.
        if (strpos($block_content, '"overlayMenu":"never"') !== false) {
            return $block_content;
        }

        if (strpos($block_content, 'pl-auth-menu-link') !== false || strpos($block_content, 'pl-user-menu-link') !== false) {
            return $block_content;
        }

        $items = $this->build_navigation_block_items_html((object) ['theme_location' => 'pl-managed-menu']);
        if ($items === '') {
            return $block_content;
        }

        // Navigation block can render different list containers depending on its inner blocks:
        // - Default: ul.wp-block-navigation__container
        // - "Page List" inner block: ul.wp-block-page-list / ul.wp-block-pages-list
        // Replace ALL matching containers so desktop + responsive overlays stay consistent.
        $pattern = '~(<ul[^>]*class="[^"]*(?:wp-block-navigation__container|wp-block-page-list|wp-block-pages-list|wp-block-pages-list__list)[^"]*"[^>]*>)(.*?)(</ul>)~s';
        $replacement = '$1' . $items . '$3';
        $updated = preg_replace($pattern, $replacement, $block_content);

        return is_string($updated) && $updated !== '' ? $updated : $block_content;
    }

    /**
     * Generic render_block fallback for Navigation blocks.
     *
     * @param string $block_content
     * @param array $block
     */
    public function filter_render_block(string $block_content, array $block): string
    {
        if (!isset($block['blockName']) || (string) $block['blockName'] !== 'core/navigation') {
            return $block_content;
        }

        return $this->filter_navigation_block($block_content, $block);
    }

    /**
     * Short-circuit wp_nav_menu() output for managed locations.
     *
     * @param string|null $nav_menu
     * @param mixed $args
     * @return string|null
     */
    public function pre_wp_nav_menu($nav_menu, $args)
    {
        $theme_location = '';
        if (is_array($args) && isset($args['theme_location'])) {
            $theme_location = (string) $args['theme_location'];
        } elseif (is_object($args) && isset($args->theme_location)) {
            $theme_location = (string) $args->theme_location;
        }

        if ($theme_location === '' || !$this->should_manage_theme_location($theme_location)) {
            return $nav_menu;
        }

        return $this->build_menu_html($this->normalize_menu_args($args));
    }

    /**
     * Force a safe fallback callback for managed locations.
     */
    public function filter_wp_nav_menu_args(array $args): array
    {
        $theme_location = isset($args['theme_location']) ? (string) $args['theme_location'] : '';
        if ($theme_location === '' || !$this->should_manage_theme_location($theme_location)) {
            return $args;
        }

        // If a theme forgot to assign a menu, we still want consistent navigation.
        $args['fallback_cb'] = [$this, 'fallback_cb'];

        return $args;
    }

    /**
     * Fallback used when there is no menu assigned to the managed theme location.
     * Expected to echo markup (WordPress convention for fallback callbacks).
     */
    public function fallback_cb(array $args): void
    {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $this->build_menu_html($this->normalize_menu_args($args));
    }

    /**
     * Build the full menu HTML output.
     */
    private function build_menu_html(array $args): string
    {
        $items = $this->build_items_html((object) $args);

        $items_wrap = isset($args['items_wrap']) && is_string($args['items_wrap'])
            ? $args['items_wrap']
            : '<ul id="%1$s" class="%2$s">%3$s</ul>';

        $menu_id = isset($args['menu_id']) ? (string) $args['menu_id'] : '';
        $menu_class = isset($args['menu_class']) ? (string) $args['menu_class'] : 'menu';

        // WordPress core does not escape the items HTML; we generate it with proper escaping.
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        return sprintf($items_wrap, esc_attr($menu_id), esc_attr($menu_class), $items);
    }

    /**
     * Normalize wp_nav_menu arguments to an array.
     *
     * WordPress passes $args as an array to some filters and as an object to others.
     */
    private function normalize_menu_args($args): array
    {
        if (is_array($args)) {
            return $args;
        }

        if (is_object($args)) {
            return get_object_vars($args);
        }

        return [];
    }

    public function filter_menu_items(string $items, $args): string
    {
        $theme_location = isset($args->theme_location) ? (string) $args->theme_location : '';
        if ($theme_location === '' || !$this->should_manage_theme_location($theme_location)) {
            return $items;
        }

        // Replace any default/theme-provided items with our plugin-managed menu.
        return $this->build_items_html($args);
    }

    /**
     * Decide if a theme_location should be managed by the plugin.
     */
    private function should_manage_theme_location(string $theme_location): bool
    {
        $registered = get_registered_nav_menus();
        if (!is_array($registered) || $registered === []) {
            return false;
        }

        $locations = $this->get_managed_theme_locations($registered);
        return in_array($theme_location, $locations, true);
    }

    /**
     * @param array<string, string> $registered_nav_menus
     * @return string[]
     */
    private function get_managed_theme_locations(array $registered_nav_menus): array
    {
        $locations = array_values(array_intersect(array_keys($registered_nav_menus), $this->preferred_theme_locations));

        // If we couldn't match any common location and the theme has only one, manage that one.
        if ($locations === [] && count($registered_nav_menus) === 1) {
            $locations = [array_key_first($registered_nav_menus)];
        }

        /**
         * Allow overriding which theme locations are managed by the plugin.
         *
         * @param string[] $locations
         * @param array<string, string> $registered_nav_menus
         */
        $locations = apply_filters('pl_mm_managed_theme_locations', $locations, $registered_nav_menus);

        return array_values(array_filter(array_unique(array_map('strval', (array) $locations))));
    }

    /**
     * Build the <li> list for the managed menu.
     */
    private function build_items_html($args): string
    {
        $menu_items = $this->get_menu_items($args);
        if ($menu_items === []) {
            return '';
        }

        $current_path = $this->get_current_request_path();
        $html = '';

        foreach ($menu_items as $item) {
            $label = isset($item['label']) ? (string) $item['label'] : '';
            $url = isset($item['url']) ? (string) $item['url'] : '';
            if ($label === '' || $url === '') {
                continue;
            }

            if (isset($item['type']) && (string) $item['type'] === 'user') {
                $html .= $this->render_user_menu_item_html($item, false);
                continue;
            }
            if (isset($item['type']) && (string) $item['type'] === 'auth') {
                $html .= $this->render_auth_menu_item_html($item, false);
                continue;
            }

            $classes = isset($item['classes']) && is_array($item['classes']) ? $item['classes'] : [];
            $item_path = (string) wp_parse_url($url, PHP_URL_PATH);
            if ($item_path !== '' && $current_path !== '' && $this->paths_match($current_path, $item_path)) {
                $classes[] = 'current-menu-item';
            }

            $class_attr = $classes !== [] ? ' class="' . esc_attr(implode(' ', array_map('sanitize_html_class', $classes))) . '"' : '';

            $html .= sprintf(
                '<li%s><a class="pl-menu-link" href="%s">%s</a></li>',
                $class_attr,
                esc_url($url),
                esc_html($label)
            );
        }

        return $html;
    }

    /**
     * Build <li> items compatible with the core Navigation block markup.
     */
    private function build_navigation_block_items_html($args): string
    {
        $menu_items = $this->get_menu_items($args);
        if ($menu_items === []) {
            return '';
        }

        $current_path = $this->get_current_request_path();
        $html = '';

        foreach ($menu_items as $item) {
            $label = isset($item['label']) ? (string) $item['label'] : '';
            $url = isset($item['url']) ? (string) $item['url'] : '';
            if ($label === '' || $url === '') {
                continue;
            }

            if (isset($item['type']) && (string) $item['type'] === 'user') {
                $html .= $this->render_user_menu_item_html($item, true);
                continue;
            }
            if (isset($item['type']) && (string) $item['type'] === 'auth') {
                $html .= $this->render_auth_menu_item_html($item, true);
                continue;
            }

            // Include both Navigation and Page List classes to work with either markup/CSS.
            $classes = ['wp-block-navigation-item', 'wp-block-navigation-link', 'wp-block-pages-list__item', 'menu-item'];
            $item_path = (string) wp_parse_url($url, PHP_URL_PATH);
            if ($item_path !== '' && $current_path !== '' && $this->paths_match($current_path, $item_path)) {
                $classes[] = 'current-menu-item';
            }

            $html .= sprintf(
                '<li class="%s"><a class="wp-block-navigation-item__content wp-block-pages-list__item__link" href="%s"><span class="wp-block-navigation-item__label">%s</span></a></li>',
                esc_attr(implode(' ', array_map('sanitize_html_class', $classes))),
                esc_url($url),
                esc_html($label)
            );
        }

        return $html;
    }

    /**
     * Return an array of menu items the plugin will render.
     *
     * Each item supports:
     * - label (string)
     * - url (string)
     * - requires_login (bool, optional)
     * - classes (string[], optional)
     *
     * @return array<int, array<string, mixed>>
     */
    private function get_menu_items($args): array
    {
        $items = [];

        $items[] = [
            'label' => 'Cursos',
            'url' => (get_post_type_archive_link('sfwd-courses') ?: home_url('/courses/')),
            'classes' => ['menu-item', 'menu-item-type-custom', 'menu-item-object-custom', 'pl-menu-item-courses'],
        ];

        $items[] = [
            'label' => __('My Books', 'politeia-learning'),
            'url' => home_url('/my-books/'),
            'classes' => ['menu-item', 'menu-item-type-custom', 'menu-item-object-custom', 'pl-menu-item-my-books'],
        ];

        if (is_user_logged_in()) {
            $center_url = $this->get_center_url_for_current_user();
            if ($center_url !== '') {
                $items[] = [
                    'label' => __('Center', 'politeia-learning'),
                    'url' => $center_url,
                    'classes' => ['menu-item', 'menu-item-type-custom', 'menu-item-object-custom', 'pl-center-menu-item'],
                ];
            }

            $items[] = [
                'type' => 'user',
                'label' => $this->get_current_user_first_name(),
                'url' => $center_url !== '' ? $center_url : home_url('/'),
                'classes' => ['menu-item', 'menu-item-type-custom', 'menu-item-object-custom', 'pl-user-menu-item'],
            ];
        } else {
            $items[] = [
                'type' => 'auth',
                'label' => __('INGRESAR', 'politeia-learning'),
                'url' => '#',
                'classes' => ['menu-item', 'menu-item-type-custom', 'menu-item-object-custom', 'pl-auth-menu-item'],
            ];
        }

        /**
         * Allow other modules/plugins to add/remove items.
         *
         * @param array<int, array<string, mixed>> $items
         * @param object $args
         */
        $items = apply_filters('pl_mm_menu_items', $items, $args);

        // Enforce requires_login where used.
        $items = array_values(array_filter((array) $items, function ($item) {
            if (!is_array($item)) {
                return false;
            }
            if (!isset($item['requires_login'])) {
                return true;
            }
            return (bool) $item['requires_login'] ? is_user_logged_in() : true;
        }));

        return $items;
    }

    private function get_center_url_for_current_user(): string
    {
        $current_user = wp_get_current_user();
        $username = isset($current_user->user_login) ? (string) $current_user->user_login : '';
        if ($username === '') {
            return '';
        }

        $op_template = get_option('pcg_operation_template', '/center');
        $slug = ltrim((string) $op_template, '/');
        if ($slug === '') {
            $slug = 'center';
        }

        return home_url(sprintf('/members/%s/%s', rawurlencode($username), rawurlencode($slug)));
    }

    private function get_current_user_first_name(): string
    {
        $current_user = wp_get_current_user();
        if (!$current_user || 0 === (int) $current_user->ID) {
            return '';
        }

        $first_name = trim((string) get_user_meta((int) $current_user->ID, 'first_name', true));
        if ($first_name !== '') {
            return $first_name;
        }

        $display_name = trim((string) $current_user->display_name);
        return $display_name !== '' ? $display_name : __('Account', 'politeia-learning');
    }

    private function get_my_reading_stats_url_for_current_user(): string
    {
        $current_user = wp_get_current_user();
        $username = isset($current_user->user_login) ? (string) $current_user->user_login : '';
        if ($username === '') {
            return '';
        }

        return home_url(sprintf('/members/%s/my-reading-stats', rawurlencode($username)));
    }

    private function get_my_profile_url_for_current_user(): string
    {
        $current_user = wp_get_current_user();
        if (!$current_user || 0 === (int) $current_user->ID) {
            return '';
        }

        $slug = isset($current_user->user_nicename) ? (string) $current_user->user_nicename : '';
        if ($slug === '') {
            $slug = isset($current_user->user_login) ? (string) $current_user->user_login : '';
        }
        if ($slug === '') {
            return '';
        }

        return home_url(sprintf('/profile/%s', rawurlencode($slug)));
    }

    private function get_my_plans_url_for_current_user(): string
    {
        $current_user = wp_get_current_user();
        $username = isset($current_user->user_login) ? (string) $current_user->user_login : '';
        if ($username === '') {
            return '';
        }

        return home_url(sprintf('/members/%s/my-plans-ver-2', rawurlencode($username)));
    }

    /**
     * Render the logged-in user menu item with avatar + dropdown icon.
     *
     * @param array<string, mixed> $item
     * @param bool $navigation_block
     */
    private function render_user_menu_item_html(array $item, bool $navigation_block): string
    {
        $label = isset($item['label']) ? (string) $item['label'] : '';
        if ($label === '') {
            return '';
        }

        $current_user = wp_get_current_user();
        $avatar_url = ($current_user && (int) $current_user->ID > 0)
            ? get_avatar_url((int) $current_user->ID, ['size' => 64])
            : '';

        $classes = isset($item['classes']) && is_array($item['classes']) ? $item['classes'] : [];
        if ($navigation_block) {
            $classes = array_merge(
                ['wp-block-navigation-item', 'wp-block-navigation-link', 'wp-block-pages-list__item', 'menu-item'],
                $classes
            );
        }

        $class_attr = $classes !== [] ? ' class="' . esc_attr(implode(' ', array_map('sanitize_html_class', $classes))) . '"' : '';
        $avatar_html = $avatar_url !== ''
            ? sprintf(
                '<img class="pl-user-menu__avatar" src="%s" alt="" aria-hidden="true" />',
                esc_url($avatar_url)
            )
            : '';

        $caret_svg = '<svg class="pl-user-menu__toggle-caret" viewBox="0 0 20 20" aria-hidden="true" focusable="false"><path fill="currentColor" d="M5.5 7.5 10 12l4.5-4.5 1.1 1.1L10 14.2 4.4 8.6z"/></svg>';
        $dropdown_items = $this->get_user_dropdown_items();
        $dropdown_html = $this->render_user_dropdown_html($dropdown_items);

        $toggle_markup = sprintf(
            '<button type="button" class="%s" aria-haspopup="true" aria-expanded="false">%s<span class="pl-user-menu__label">%s</span>%s</button>',
            $navigation_block
                ? 'wp-block-navigation-item__content wp-block-pages-list__item__link pl-user-menu-link pl-user-menu__toggle'
                : 'pl-menu-link pl-user-menu-link pl-user-menu__toggle',
            $avatar_html,
            esc_html($label),
            $caret_svg
        );

        return sprintf(
            '<li%s>%s%s</li>',
            $class_attr,
            $toggle_markup,
            $dropdown_html
        );
    }

    /**
     * Render the logged-out auth button.
     *
     * @param array<string, mixed> $item
     * @param bool $navigation_block
     */
    private function render_auth_menu_item_html(array $item, bool $navigation_block): string
    {
        $label = isset($item['label']) ? (string) $item['label'] : '';
        $url = isset($item['url']) ? (string) $item['url'] : '';
        if ($label === '' || $url === '') {
            return '';
        }

        $classes = isset($item['classes']) && is_array($item['classes']) ? $item['classes'] : [];
        if ($navigation_block) {
            $classes = array_merge(
                ['wp-block-navigation-item', 'wp-block-navigation-link', 'wp-block-pages-list__item', 'menu-item'],
                $classes
            );
        }

        $class_attr = $classes !== [] ? ' class="' . esc_attr(implode(' ', array_map('sanitize_html_class', $classes))) . '"' : '';
        $link_class = $navigation_block
            ? 'wp-block-navigation-item__content wp-block-pages-list__item__link pl-menu-link pl-auth-menu-link'
            : 'pl-menu-link pl-auth-menu-link';
        $inline_style = 'display:inline-flex;align-items:center;justify-content:center;min-height:0;padding:10px 20px;border:0;border-radius:6px;background:#000;color:#fff !important;font-family:Poppins,sans-serif !important;font-size:12px;font-weight:500 !important;letter-spacing:2px;line-height:1;text-transform:uppercase;text-decoration:none !important;appearance:none;cursor:pointer;opacity:1 !important;visibility:visible !important;';
        $onclick = "if (window.PLAuthOpenModal) { window.PLAuthOpenModal('login'); return false; }";

        return sprintf(
            '<li%s><button type="button" class="%s" style="%s" onclick="%s" data-pl-auth-open="1" data-rcp-auth-open="1" data-pl-auth-view="login">%s</button></li>',
            $class_attr,
            esc_attr($link_class),
            esc_attr($inline_style),
            esc_attr($onclick),
            esc_html($label)
        );
    }

    /**
     * @return array<int, array{label:string,url:string,classes?:string[]}>
     */
    private function get_user_dropdown_items(): array
    {
        $items = [];

        $profile_url = $this->get_my_profile_url_for_current_user();
        if ($profile_url !== '') {
            $profile_label = __('Perfil', 'politeia-learning');
            if ($profile_label === '') {
                $profile_label = 'Perfil';
            }
            $items[] = [
                'label' => $profile_label,
                'url' => $profile_url,
                'classes' => ['pl-user-menu__dropdown-item', 'pl-user-menu__dropdown-item--profile'],
            ];
        }

        $stats_url = $this->get_my_reading_stats_url_for_current_user();
        if ($stats_url !== '') {
            $items[] = [
                'label' => __('My Reading Stats', 'politeia-learning'),
                'url' => $stats_url,
                'classes' => ['pl-user-menu__dropdown-item', 'pl-user-menu__dropdown-item--stats'],
            ];
        }

        $plans_url = $this->get_my_plans_url_for_current_user();
        if ($plans_url !== '') {
            $items[] = [
                'label' => __('My Plans', 'politeia-learning'),
                'url' => $plans_url,
                'classes' => ['pl-user-menu__dropdown-item', 'pl-user-menu__dropdown-item--plans'],
            ];
        }

        $items[] = [
            'label' => __('Cerrar Sesión', 'politeia-learning'),
            'url' => wp_logout_url(home_url('/')),
            'classes' => ['pl-user-menu__dropdown-item', 'pl-user-menu__dropdown-item--logout'],
        ];

        return apply_filters('pl_mm_user_dropdown_items', $items);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function render_user_dropdown_html(array $items): string
    {
        if ($items === []) {
            return '';
        }

        $html = '<ul class="pl-user-menu__dropdown">';
        foreach ($items as $item) {
            $label = isset($item['label']) ? (string) $item['label'] : '';
            $url = isset($item['url']) ? (string) $item['url'] : '';
            if ($label === '' || $url === '') {
                continue;
            }

            $classes = isset($item['classes']) && is_array($item['classes']) ? $item['classes'] : [];
            $class_attr = $classes !== [] ? ' class="' . esc_attr(implode(' ', array_map('sanitize_html_class', $classes))) . '"' : ' class="pl-user-menu__dropdown-item"';

            $html .= sprintf(
                '<li%s><a href="%s">%s</a></li>',
                $class_attr,
                esc_url($url),
                esc_html($label)
            );
        }
        $html .= '</ul>';

        return $html;
    }

    private function get_current_request_path(): string
    {
        $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        if ($uri === '') {
            return '';
        }

        $path = (string) wp_parse_url($uri, PHP_URL_PATH);
        return $path !== '' ? trailingslashit($path) : '';
    }

    private function paths_match(string $current_path, string $item_path): bool
    {
        $current = trailingslashit($current_path);
        $item = trailingslashit($item_path);

        if ($current === $item) {
            return true;
        }

        // For profile pages like /members/{user}/{section}/, allow prefix matches.
        if (strpos($current, $item) === 0) {
            return true;
        }

        return false;
    }
}
