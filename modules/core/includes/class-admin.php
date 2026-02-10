<?php
/**
 * Main Admin Dashboard and Menu for Politeia Learning.
 */

if (!defined('ABSPATH'))
    exit;

class PCG_Core_Admin
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
            __('Politeia Learning', 'politeia-course-group'),
            __('Politeia Learning', 'politeia-course-group'),
            'manage_options',
            'politeia-learning',
            [$this, 'render_dashboard'],
            'dashicons-welcome-learn-more',
            30
        );
    }

    /**
     * Enqueue dashboard assets.
     */
    public function enqueue_assets($hook)
    {
        if ('toplevel_page_politeia-learning' !== $hook) {
            return;
        }

        wp_enqueue_style('pcg-core-admin', PCG_CORE_URL . 'assets/css/core-admin.css', [], '1.0.0');
    }

    /**
     * Render the dashboard page.
     */
    public function render_dashboard()
    {
        $plugins_status = $this->check_plugins_status();
        include PCG_CORE_PATH . 'templates/dashboard.php';
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
            'learndash-woocommerce/learndash-woocommerce.php' => [
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
