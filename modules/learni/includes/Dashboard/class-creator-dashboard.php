<?php
/**
 * Handles the User Course Creator Dashboard.
 * 
 * Refactored to use Traits for modularity.
 * Now ~300 lines after extracting Assets.
 */

if (!defined('ABSPATH'))
    exit;

// Load Assets Trait
require_once __DIR__ . '/traits/trait-dashboard-assets.php';

class PL_CC_Creator_Dashboard
{
    use PL_CC_Dashboard_Assets_Trait;

    const REWRITE_TAG = 'pcg_creator_user';
    const SECTION_VAR = 'cc_section';

    public function __construct()
    {
        add_action('init', [$this, 'add_rewrite_rules']);
        add_filter('query_vars', [$this, 'add_query_vars']);
        add_filter('template_include', [$this, 'load_dashboard_template']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_escrito_frontend_assets'], 999);
        add_action('admin_post_pl_cc_save_membership_tier', [$this, 'handle_save_membership_tier']);

        // Shortcode as fallback or alternative
        add_shortcode('pcg_course_creator_dashboard', [$this, 'render_dashboard_shortcode']);

        // Wrap the frontend post content to guarantee 1:1 matching with Editor
        add_filter('the_content', [$this, 'wrap_escrito_content']);

        add_filter('body_class', [$this, 'add_dashboard_body_classes']);
    }

    /**
     * Save the creator's monthly paid membership tier amount from Center-2 > Perfil.
     */
    public function handle_save_membership_tier(): void
    {
        if (!is_user_logged_in()) {
            wp_safe_redirect(wp_login_url());
            exit;
        }

        check_admin_referer('pl_cc_membership_tier', 'pl_cc_membership_tier_nonce');

        $creator_user_id = (int) get_current_user_id();
        $user_slug = sanitize_key((string) ($_POST['user_slug'] ?? ''));

        $user = $user_slug !== '' ? get_user_by('slug', $user_slug) : null;
        if (!$user || (int) $user->ID !== $creator_user_id) {
            wp_die(__('No autorizado.', 'politeia-learning'), 403);
        }

        $amount_raw = (string) ($_POST['monthly_amount'] ?? '');
        $amount_minor = absint(preg_replace('/[^0-9]/', '', $amount_raw));
        if ($amount_minor <= 0) {
            $this->redirect_membership_settings(__('Ingresa un monto válido.', 'politeia-learning'));
        }

        $error = '';

        if (class_exists('Politeia_PPS_Subscription_Engine')) {
            $res = Politeia_PPS_Subscription_Engine::upsert_creator_monthly_tier($creator_user_id, $amount_minor, 'CLP');
            if (is_wp_error($res)) {
                $error = $res->get_error_message();
            }
        } else {
            update_user_meta($creator_user_id, 'politeia_membership_monthly_amount', $amount_minor);
        }

        if ($error !== '') {
            $this->redirect_membership_settings($error);
        }

        // Save "what content is unlocked" for subscribers (MVP): profile tabs policy.
        if (class_exists('PL_Relationships')) {
            $raw_tabs = $_POST['pl_policy_subscribe_tabs'] ?? [];
            if (!is_array($raw_tabs)) {
                $raw_tabs = [];
            }
            $tabs = array_values(array_unique(array_filter(array_map('sanitize_key', $raw_tabs))));
            // Always include main tab for sanity.
            if (!in_array('main', $tabs, true)) {
                array_unshift($tabs, 'main');
            }
            update_user_meta($creator_user_id, PL_Relationships::META_POLICY_SUBSCRIBE, ['profile_tabs' => $tabs]);
        }

        $this->redirect_membership_settings('');
    }

    private function redirect_membership_settings(string $error_message): void
    {
        $ref = wp_get_referer();
        if (!is_string($ref) || $ref === '') {
            $ref = home_url('/');
        }

        $ref = remove_query_arg(['pl_membership_notice', 'pl_membership_error'], $ref);

        $args = [
            'section' => 'profile',
            'profile_tab' => 'membership',
        ];

        if ($error_message !== '') {
            $args['pl_membership_error'] = $error_message;
        } else {
            $args['pl_membership_notice'] = 'saved';
        }

        wp_safe_redirect(add_query_arg($args, $ref));
        exit;
    }

    /**
     * Add custom rewrite rules for /members/{user}/center
     */
    public function add_rewrite_rules()
    {
        // Register both slugs to avoid needing a rewrite-flush when switching templates.
        foreach (['center', 'center-2'] as $slug) {
            add_rewrite_rule(
                'members/([^/]+)/' . preg_quote($slug, '/') . '/?$',
                'index.php?' . self::REWRITE_TAG . '=$matches[1]',
                'top'
            );
        }
    }

    private function resolve_user_slug_from_request_uri(): string
    {
        $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        if ($uri === '') {
            return '';
        }

        $path = (string) wp_parse_url($uri, PHP_URL_PATH);
        if ($path === '') {
            return '';
        }

        if (preg_match('#^/members/([^/]+)/(center|center-2)/?$#', $path, $m)) {
            return sanitize_key(rawurldecode((string) ($m[1] ?? '')));
        }

        return '';
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
        if (empty($user_slug)) {
            // Fallback when rewrites haven't been flushed yet.
            $user_slug = $this->resolve_user_slug_from_request_uri();
            if ($user_slug !== '') {
                set_query_var(self::REWRITE_TAG, $user_slug);
            }
        }

        if (!empty($user_slug)) {
            $user = get_user_by('slug', $user_slug);

            if ($user) {
                $current_user_id = get_current_user_id();

                if ($current_user_id === $user->ID || current_user_can('manage_options')) {
                    $op_template = get_option('pcg_operation_template', '/center');
                    $template_name = ($op_template === '/center-2') ? 'main-dashboard-2.php' : 'main-dashboard.php';
                    
                    $custom_template = PL_CC_PATH . 'templates/dashboard/' . $template_name;
                    if (file_exists($custom_template)) {
                        return $custom_template;
                    }
                }

                // Not authorized to view another user's Center dashboard: send to public profile instead of a 404.
                $profile_url = home_url('/profile/' . rawurlencode((string) $user->user_nicename) . '/');
                wp_safe_redirect($profile_url);
                exit;
            }
            return $template;
        }
        return $template;
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

        $op_template = get_option('pcg_operation_template', '/center');
        $template_name = ($op_template === '/center-2') ? 'main-dashboard-2.php' : 'main-dashboard.php';

        include PL_CC_PATH . 'templates/dashboard/' . $template_name;
    }
}
