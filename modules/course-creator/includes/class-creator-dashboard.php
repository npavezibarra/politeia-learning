<?php
/**
 * Handles the User Course Creator Dashboard.
 */

if (!defined('ABSPATH'))
    exit;

class PCG_CC_Creator_Dashboard
{

    const REWRITE_TAG = 'pcg_creator_user';
    const SECTION_VAR = 'cc_section';

    public function __construct()
    {
        add_action('init', [$this, 'add_rewrite_rules']);
        add_filter('query_vars', [$this, 'add_query_vars']);
        add_filter('template_include', [$this, 'load_dashboard_template']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);

        // Shortcode as fallback or alternative
        add_shortcode('pcg_course_creator_dashboard', [$this, 'render_dashboard_shortcode']);
    }

    /**
     * Add custom rewrite rules for /members/{user}/central
     */
    public function add_rewrite_rules()
    {
        add_rewrite_rule(
            'members/([^/]+)/central/?$',
            'index.php?' . self::REWRITE_TAG . '=$matches[1]',
            'top'
        );

        // Optional: Flush rules on plugin activation would be better, but we do it manually or via a hook if needed.
    }

    /**
     * Register custom query variables
     */
    public function add_query_vars($vars)
    {
        $vars[] = self::REWRITE_TAG;
        $vars[] = self::SECTION_VAR;
        return $vars;
    }

    /**
     * Load the dashboard template if the rewrite tag is present
     */
    public function load_dashboard_template($template)
    {
        $user_slug = get_query_var(self::REWRITE_TAG);

        if (!empty($user_slug)) {
            $user = get_user_by('slug', $user_slug);

            if ($user) {
                // Check if the current user is authorized to view this dashboard
                // For now, let's allow the user to view their own dashboard
                $current_user_id = get_current_user_id();

                if ($current_user_id === $user->ID || current_user_can('manage_options')) {
                    $custom_template = PCG_CC_PATH . 'templates/main-dashboard.php';
                    if (file_exists($custom_template)) {
                        return $custom_template;
                    }
                }
            }

            // If user not found or unauthorized, we could return a 404 or redirect
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            return get_404_template();
        }

        return $template;
    }

    /**
     * Enqueue CSS and JS for the dashboard
     */
    public function enqueue_assets()
    {
        if (get_query_var(self::REWRITE_TAG)) {
            wp_enqueue_media();
            wp_enqueue_style('pcg-creator-css', PCG_CC_URL . 'assets/css/creator-dashboard.css', [], '1.0.0');

            // Inject Custom Styles from Admin Options
            $creator_max_width = get_option('pcg_creator_max_width', '1400px');
            $container_max_width = get_option('pcg_container_max_width', '1200px');

            $custom_css = "
                .pcg-creator-container { max-width: {$creator_max_width} !important; }
                .container { max-width: {$container_max_width} !important; }
                .pcg-creator-dashboard-wrapper { padding: 0px !important; }
                div#content { padding-left: 0px !important; padding-right: 0px !important; }
            ";
            wp_add_inline_style('pcg-creator-css', $custom_css);

            wp_enqueue_script('pcg-creator-js', PCG_CC_URL . 'assets/js/creator-dashboard.js', ['jquery', 'jquery-ui-sortable'], '1.0.0', true);

            wp_localize_script('pcg-creator-js', 'pcgCreatorData', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('pcg_creator_nonce'),
            ]);
        }
    }

    /**
     * Shortcode renderer (as alternative)
     */
    public function render_dashboard_shortcode($atts)
    {
        ob_start();
        $this->render_dashboard_content();
        return ob_get_clean();
    }

    /**
     * Helper to render the dashboard content
     */
    public function render_dashboard_content()
    {
        $user_slug = get_query_var(self::REWRITE_TAG);
        $user = get_user_by('slug', $user_slug);
        $section = get_query_var(self::SECTION_VAR, 'overview');

        if (!$user)
            return;

        include PCG_CC_PATH . 'templates/main-dashboard.php';
    }
}
