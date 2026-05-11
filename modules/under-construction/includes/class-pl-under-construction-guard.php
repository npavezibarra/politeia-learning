<?php
/**
 * Frontend guard + role-based login restriction while enabled.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PL_Under_Construction_Guard
{
    public function __construct()
    {
        add_action('template_redirect', [$this, 'maybe_render_under_construction'], 0);
        add_filter('wp_authenticate_user', [$this, 'maybe_block_login_by_role'], 20, 2);
    }

    private function is_enabled(): bool
    {
        if (class_exists('PL_Under_Construction_Admin')) {
            return PL_Under_Construction_Admin::is_enabled();
        }

        return (bool) get_option('pl_under_construction_enabled', false);
    }

    private function is_allowed_user(): bool
    {
        if (!is_user_logged_in()) {
            return false;
        }

        $user = wp_get_current_user();
        if (!$user || empty($user->roles) || !is_array($user->roles)) {
            return false;
        }

        return in_array('administrator', $user->roles, true) || in_array('editor', $user->roles, true);
    }

    private function is_allowed_request(): bool
    {
        if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
            return true;
        }

        if (defined('REST_REQUEST') && REST_REQUEST) {
            return true;
        }

        if (function_exists('wp_is_json_request') && wp_is_json_request()) {
            return true;
        }

        $uri_path = '';
        if (isset($_SERVER['REQUEST_URI'])) {
            $uri_path = (string) wp_parse_url((string) wp_unslash($_SERVER['REQUEST_URI']), PHP_URL_PATH);
        }

        $uri_path = ltrim($uri_path, '/');
        if ($uri_path === 'wp-login.php') {
            return true;
        }

        if (strpos($uri_path, 'wp-admin') === 0) {
            return true;
        }

        return false;
    }

    public function maybe_render_under_construction(): void
    {
        if (!$this->is_enabled()) {
            return;
        }

        if ($this->is_allowed_request()) {
            return;
        }

        if ($this->is_allowed_user()) {
            return;
        }

        $requested_url = home_url('/');
        if (isset($_SERVER['REQUEST_URI'])) {
            $requested_url = home_url((string) wp_unslash($_SERVER['REQUEST_URI']));
        }

        $logo_url = $this->get_logo_url();
        $login_fallback_url = add_query_arg(
            [
                'pl_auth_view' => 'login',
                'redirect_to' => $requested_url,
            ],
            home_url('/')
        );

        status_header(200);
        nocache_headers();

        include PL_UNDER_CONSTRUCTION_PATH . 'templates/under-construction.php';
        exit;
    }

    private function get_logo_url(): string
    {
        $custom_logo_id = (int) get_theme_mod('custom_logo');
        if ($custom_logo_id > 0) {
            $logo = wp_get_attachment_image_url($custom_logo_id, 'full');
            if (is_string($logo) && $logo !== '') {
                return $logo;
            }
        }

        $site_icon = get_site_icon_url(512);
        if (is_string($site_icon) && $site_icon !== '') {
            return $site_icon;
        }

        return '';
    }

    public function maybe_block_login_by_role($user, $password)
    {
        if (!$this->is_enabled()) {
            return $user;
        }

        if (is_wp_error($user) || !($user instanceof WP_User)) {
            return $user;
        }

        $roles = is_array($user->roles ?? null) ? $user->roles : [];
        $is_allowed = in_array('administrator', $roles, true) || in_array('editor', $roles, true);
        if ($is_allowed) {
            return $user;
        }

        return new WP_Error(
            'pl_uc_role_blocked',
            __('Solo administradores o editores pueden ingresar mientras el sitio está en construcción.', 'politeia-learning')
        );
    }
}
