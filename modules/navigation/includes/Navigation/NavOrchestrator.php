<?php

namespace Learni\Navigation;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main Orchestrator for the Navigation module.
 */
class NavOrchestrator
{
    private static $instance = null;

    public static function get_instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->init_hooks();
    }

    private function init_hooks(): void
    {
        // Canonical homepage redirect: politeia.cl/ -> politeia.cl/blog/
        add_action('template_redirect', [$this, 'maybe_redirect_home_to_blog'], 0);

        // Assets with max priority
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets'], 1);
        add_action('wp_head', [$this, 'inject_emergency_styles'], 1);

        // Desktop / Classic Menu Overrides - MAX PRIORITY
        add_filter('wp_nav_menu_args', [$this, 'filter_wp_nav_menu_args'], 1, 1);
        add_filter('pre_wp_nav_menu', [$this, 'override_classic_menu'], 1, 2);
        add_filter('wp_nav_menu_items', [$this, 'override_menu_items'], 1, 2);

        // Gutenberg / Block Navigation & Footer
        add_filter('render_block_core/navigation', [GutenbergRenderer::class, 'filter_block'], 1, 2);
        add_filter('render_block_core/template-part', [$this, 'suppress_footer_block'], 1, 2);

        // Mobile / Smartphone Specific
        add_action('wp_body_open', [MobileRenderer::class, 'render_header'], 1);
    }

    /**
     * Forces the site root (/) to redirect to /blog/.
     *
     * This keeps the canonical public entrypoint stable without relying on .htaccess.
     */
    public function maybe_redirect_home_to_blog(): void
    {
        if (is_admin()) {
            return;
        }

        if (defined('WP_CLI') && WP_CLI) {
            return;
        }

        if (wp_doing_ajax() || wp_is_json_request() || (defined('REST_REQUEST') && REST_REQUEST) || (defined('DOING_CRON') && DOING_CRON)) {
            return;
        }

        $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        $path = (string) wp_parse_url($request_uri, PHP_URL_PATH);

        // Only redirect the site root.
        if ($path !== '' && $path !== '/') {
            return;
        }

        $target = home_url('/blog/');
        wp_safe_redirect($target, 301);
        exit;
    }

    /**
     * Suppresses the footer template part block on Politeia pages.
     */
    public function suppress_footer_block(string $block_content, array $block): string
    {
        if (isset($block['attrs']['slug']) && $block['attrs']['slug'] === 'footer') {
            if ($this->is_politeia_page()) {
                return '';
            }
        }
        return $block_content;
    }

    /**
     * Detects if the current page is a Politeia Learning managed page.
     */
    public function is_politeia_page(): bool
    {
        // Custom Post Types and Managed Core Types
        if (is_singular(['learni_course', 'learni_lesson', 'learni_special', 'learni_program', 'pl_member_profile', 'post', 'product'])) {
            return true;
        }
        
        // Archives
        if (is_post_type_archive(['learni_course', 'learni_special', 'learni_program', 'product'])) {
            return true;
        }

        // Member Profile custom route
        if (get_query_var('pl_profile_username')) {
            return true;
        }

        // Bookshelf / Reading Module routes
        if (get_query_var('prs_my_books_archive') || get_query_var('prs_book_slug') || get_query_var('prs_my_reading_stats') || get_query_var('prs_my_reading_stats_2')) {
            return true;
        }

        // Auth pages
        if (isset($_GET['pl_auth_action']) || is_page(['login', 'register', 'lost-password', 'mi-cuenta', 'checkout-curso'])) {
            return true;
        }

        return false;
    }

    /**
     * Inject emergency styles directly into the head to fix layout instantly.
     */
    public function inject_emergency_styles(): void
    {
        ?>
        <style id="pl-nav-emergency-css">
            /* Hide the default page menu that is breaking everything */
            .menu-primary-container > ul:not(.pl-managed-menu),
            .header-menu-container > ul:not(.pl-managed-menu),
            #header-menu:not(.pl-managed-menu),
            #primary-menu:not(.pl-managed-menu) {
                display: none !important;
            }
            
            /* Force our menu to be visible and horizontal */
            .pl-managed-menu {
                display: flex !important;
                flex-direction: row !important;
                list-style: none !important;
                gap: 20px !important;
                margin: 0 !important;
                padding: 0 !important;
                justify-content: flex-end !important;
            }
            .pl-managed-menu li { display: block !important; }
        </style>
        <?php
    }

    /**
     * Enqueue shared navigation assets.
     */
    public function enqueue_assets(): void
    {
        if (is_admin()) return;

        wp_enqueue_style(
            'pl-navigation-core',
            PL_NAV_URL . 'assets/css/navigation.css',
            [],
            filemtime(PL_NAV_PATH . 'assets/css/navigation.css')
        );

        wp_enqueue_script(
            'pl-navigation-core',
            PL_NAV_URL . 'assets/js/navigation.js',
            [],
            filemtime(PL_NAV_PATH . 'assets/js/navigation.js'),
            true
        );
    }

    /**
     * Short-circuits wp_nav_menu output for managed locations.
     */
    public function override_classic_menu($nav_menu, $args)
    {
        if ($this->should_manage_location($args)) {
            return $this->render_classic_menu($args);
        }
        return $nav_menu;
    }

    /**
     * Appends/Replaces items in existing menus.
     */
    public function override_menu_items(string $items, $args): string
    {
        if ($this->should_manage_location($args)) {
            return DesktopRenderer::build_items_html();
        }
        return $items;
    }

    private function should_manage_location($args): bool
    {
        // TOTAL TAKEOVER: Manage everything that looks like a menu to fix the broken UI instantly.
        return true;
    }

    private function render_classic_menu($args): string
    {
        $items = DesktopRenderer::build_items_html();
        
        // Use a more robust wrapper that matches standard WordPress expectations
        $menu_id = is_array($args) ? ($args['menu_id'] ?? '') : ($args->menu_id ?? '');
        $menu_class = is_array($args) ? ($args['menu_class'] ?? 'menu') : ($args->menu_class ?? 'menu');
        $items_wrap = is_array($args) ? ($args['items_wrap'] ?? '<ul id="%1$s" class="%2$s">%3$s</ul>') : ($args->items_wrap ?? '<ul id="%1$s" class="%2$s">%3$s</ul>');

        return sprintf($items_wrap, esc_attr($menu_id), esc_attr($menu_class . ' pl-managed-menu'), $items);
    }

    /**
     * Force a safe fallback callback for managed locations.
     */
    public function filter_wp_nav_menu_args(array $args): array
    {
        if ($this->should_manage_location($args)) {
            $args['fallback_cb'] = [$this, 'fallback_cb'];
        }
        return $args;
    }

    /**
     * Fallback used when there is no menu assigned.
     */
    public function fallback_cb(array $args): void
    {
        echo $this->render_classic_menu($args);
    }
}
