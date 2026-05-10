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

        // Unified Learning Taxonomy (Categories/Tags).
        add_submenu_page(
            'politeia-learning',
            __('Categorías', 'politeia-learning'),
            __('Categorías', 'politeia-learning'),
            'manage_options',
            'edit-tags.php?taxonomy=pl_learning_category'
        );

        add_submenu_page(
            'politeia-learning',
            __('Etiquetas', 'politeia-learning'),
            __('Etiquetas', 'politeia-learning'),
            'manage_options',
            'edit-tags.php?taxonomy=pl_learning_tag'
        );

        add_submenu_page(
            'politeia-learning',
            __('Style Options', 'politeia-learning'),
            __('Style Options', 'politeia-learning'),
            'manage_options',
            'pcg-style-options',
            [$this, 'render_style_options']
        );

        add_submenu_page(
            'politeia-learning',
            __('Profile Template', 'politeia-learning'),
            __('Profile Template', 'politeia-learning'),
            'manage_options',
            'pcg-profile-template',
            [$this, 'render_profile_template_settings']
        );

        add_submenu_page(
            'politeia-learning',
            __('Módulos', 'politeia-learning'),
            __('Módulos', 'politeia-learning'),
            'manage_options',
            'pcg-modules-options',
            [$this, 'render_modules_options']
        );

        add_submenu_page(
            'politeia-learning',
            __('UI Inventory', 'politeia-learning'),
            __('UI Inventory', 'politeia-learning'),
            'manage_options',
            'pl-ui-inventory',
            [$this, 'render_ui_inventory']
        );
    }

    /**
     * Enqueue dashboard assets.
     */
    public function enqueue_assets($hook)
    {
        $allowed = [
            'toplevel_page_politeia-learning',
            'politeia-learning_page_pcg-style-options',
            'politeia-learning_page_pl-ui-inventory',
        ];
        if (!in_array((string) $hook, $allowed, true)) {
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
     * Render the Profile Template settings page.
     */
    public function render_profile_template_settings()
    {
        if (isset($_POST['pcg_profile_template_submitted']) && check_admin_referer('pcg_save_profile_template')) {
            $profile_template = sanitize_text_field($_POST['pcg_profile_template'] ?? 'politeia-profile');
            $operation_template = sanitize_text_field($_POST['pcg_operation_template'] ?? '/center');

            $allowed_profile_templates = [
                'politeia-profile',
                'politeia-profile-fullwidth',
            ];
            if (!in_array($profile_template, $allowed_profile_templates, true)) {
                $profile_template = 'politeia-profile';
            }
            
            update_option('pcg_profile_template', $profile_template);
            update_option('pcg_operation_template', $operation_template);

            // Flush rewrite rules to ensure the new operation template slug is active
            flush_rewrite_rules(false);

            echo '<div class="updated"><p>' . __('Settings saved.', 'politeia-learning') . '</p></div>';
        }

        $current_template = (string) get_option('pcg_profile_template', 'politeia-profile');
        if ($current_template === 'default' || $current_template === '') {
            $current_template = 'politeia-profile';
        }
        $current_operation_template = get_option('pcg_operation_template', '/center');

        include PL_CORE_PATH . 'templates/profile-template-options.php';
    }

    /**
     * Render the Modules Options (Functionalities) page.
     */
    public function render_modules_options()
    {
        $modules_config = [
            'create-course' => ['label' => 'Mis Cursos'],
            'mis-escritos' => ['label' => 'Mis Escritos'],
            'especializacion' => ['label' => 'Especializaciones'],
            'create-group' => ['label' => 'Programas'],
            'sales' => ['label' => 'Ventas'],
            'students' => ['label' => 'Estudiantes']
        ];

        if (isset($_POST['pcg_modules_options_submitted']) && check_admin_referer('pcg_save_modules_options')) {
            $submitted_modules = isset($_POST['pcg_modules']) && is_array($_POST['pcg_modules']) ? $_POST['pcg_modules'] : [];

            $sanitized_modules = [];
            foreach ($modules_config as $key => $config) {
                // Determine checked state for both context
                $sanitized_modules[$key] = [
                    'users' => !empty($submitted_modules[$key]['users']),
                    'admin' => !empty($submitted_modules[$key]['admin'])
                ];
            }

            update_option('pcg_modules_visibility', $sanitized_modules);

            echo '<div class="updated"><p>' . __('Settings saved.', 'politeia-learning') . '</p></div>';
        }

        // Use default 'true' everywhere if not set
        $default_settings = [];
        foreach ($modules_config as $key => $config) {
            $default_settings[$key] = ['users' => true, 'admin' => true];
        }

        $current_settings = get_option('pcg_modules_visibility', $default_settings);

        include PL_CORE_PATH . 'templates/modules-options.php';
    }

    public function render_ui_inventory()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'politeia-learning'));
        }

        $created_id = 0;
        $error = '';

        if (isset($_POST['pl_ui_inventory_create']) && check_admin_referer(PL_Core_UI_Inventory::NONCE_ACTION_CREATE_PAGE)) {
            if (class_exists('PL_Core_UI_Inventory')) {
                $created_id = PL_Core_UI_Inventory::create_page_if_missing();
                if ($created_id <= 0) {
                    $error = __('Could not create page.', 'politeia-learning');
                }
            } else {
                $error = __('UI Inventory is unavailable.', 'politeia-learning');
            }
        }

        if (isset($_POST['pl_ui_inventory_rescan']) && check_admin_referer(PL_Core_UI_Inventory::NONCE_ACTION_RESCAN)) {
            delete_transient('pl_ui_inventory_scan_v1');
        }

        $page_id = class_exists('PL_Core_UI_Inventory') ? PL_Core_UI_Inventory::get_page_id() : 0;
        $page_link = ($page_id > 0) ? get_permalink($page_id) : '';

        include PL_CORE_PATH . 'templates/ui-inventory.php';
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
