<?php
/**
 * Main Admin Dashboard and Menu for Politeia Learning.
 */

if (!defined('ABSPATH'))
    exit;

class PL_Core_Admin
{

    public function __construct()
    {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    /**
     * Register main Politeia Learning menu.
     */
    public function register_menu()
    {
        add_menu_page(
            __('Politeia Learning', 'politeia-learning'),
            __('Politeia Learning', 'politeia-learning'),
            'manage_options',
            'politeia-learning',
            [$this, 'render_dashboard'],
            'dashicons-welcome-learn-more',
            30
        );

        add_submenu_page(
            'politeia-learning',
            __('Style Options', 'politeia-learning'),
            __('Style Options', 'politeia-learning'),
            'manage_options',
            'pcg-style-options',
            [$this, 'render_style_options']
        );
    }

    /**
     * Enqueue dashboard assets.
     */
    public function enqueue_assets($hook)
    {
        if ('toplevel_page_politeia-learning' !== $hook && 'politeia-learning_page_pcg-style-options' !== $hook) {
            return;
        }

        wp_enqueue_style('pcg-core-admin', PL_CORE_URL . 'assets/css/core-admin.css', [], '1.0.0');
    }

    /**
     * Render the dashboard page.
     */
    public function render_dashboard()
    {
        $plugins_status = $this->check_plugins_status();
        include PL_CORE_PATH . 'templates/dashboard.php';
    }

    /**
     * Render the Style Options page.
     */
    public function render_style_options()
    {
        if (isset($_POST['pcg_style_options_submitted']) && check_admin_referer('pcg_save_style_options')) {
            $creator_max_width = sanitize_text_field($_POST['pcg_creator_max_width'] ?? '1400px');
            $container_max_width = sanitize_text_field($_POST['pcg_container_max_width'] ?? '1200px');

            update_option('pcg_creator_max_width', $creator_max_width);
            update_option('pcg_container_max_width', $container_max_width);

            echo '<div class="updated"><p>' . __('Settings saved.', 'politeia-learning') . '</p></div>';
        }

        $creator_max_width = get_option('pcg_creator_max_width', '1400px');
        $container_max_width = get_option('pcg_container_max_width', '1200px');

        include PL_CORE_PATH . 'templates/style-options.php';
    }

    /**
     * Check if required plugins are active.
     */
    private function check_plugins_status()
    {
        $required_plugins = [
            'woocommerce/woocommerce.php' => [
                'name' => 'WooCommerce',
                'url' => 'https://woocommerce.com/',
            ],
            'sfwd-lms/sfwd_lms.php' => [
                'name' => 'LearnDash LMS',
                'url' => 'https://www.learndash.com/',
            ],
            'learndash-woocommerce/learndash_woocommerce.php' => [
                'name' => 'LearnDash WooCommerce Integration',
                'url' => 'https://www.learndash.com/support/docs/add-ons/woocommerce/',
            ],
        ];

        include_once(ABSPATH . 'wp-admin/includes/plugin.php');

        $status = [];
        foreach ($required_plugins as $path => $info) {
            $is_active = is_plugin_active($path);
            $status[] = [
                'name' => $info['name'],
                'path' => $path,
                'active' => $is_active,
                'url' => $info['url'],
            ];
        }

        return $status;
    }
}
