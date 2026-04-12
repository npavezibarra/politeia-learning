<?php
/**
 * Handles the profile template switching logic.
 */

if (!defined('ABSPATH'))
    exit;

class PL_Member_Profile_Template
{
    private const TEMPLATE_DEFAULT = 'politeia-profile';
    private const TEMPLATE_FULLWIDTH = 'politeia-profile-fullwidth';

    public function __construct()
    {
        // Lower priority (9) allows specialized routes (at 10+) to override this template.
        // IMPORTANT: Pure WordPress only (no BuddyBoss/BuddyPress dependencies).
        add_filter('template_include', [$this, 'load_profile_template'], 9);
    }

    /**
     * Override the template for member profiles if the custom template is selected.
     */
    public function load_profile_template($template)
    {
        $username = (string) get_query_var('pl_profile_username', '');
        if ($username === '') {
            return $template;
        }

        $selected_template = (string) get_option('pcg_profile_template', self::TEMPLATE_DEFAULT);
        // Back-compat: previous UI had a "default" option; keep route working.
        if ($selected_template === 'default' || $selected_template === '') {
            $selected_template = self::TEMPLATE_DEFAULT;
        }

        $templates = [
            self::TEMPLATE_DEFAULT => PL_MEMBER_PROFILE_PATH . 'templates/politeia-profile.php',
            self::TEMPLATE_FULLWIDTH => PL_MEMBER_PROFILE_PATH . 'templates/politeia-profile-fullwidth.php',
        ];

        $custom = $templates[$selected_template] ?? $templates[self::TEMPLATE_DEFAULT];
        if (!file_exists($custom)) {
            // Always fail back to default template if the selected file is missing.
            $custom = $templates[self::TEMPLATE_DEFAULT];
            if (!file_exists($custom)) {
                return $template;
            }
        }

        // Resolve user for the template (pure WP).
        $decoded = trim(rawurldecode($username));
        if ($decoded === '') {
            return $template;
        }

        $user = get_user_by('slug', $decoded);
        if (!$user) {
            $user = get_user_by('login', $decoded);
        }
        if (!$user) {
            // Keep default 404 behavior when user doesn't exist.
            return $template;
        }

        set_query_var('pl_profile_user_id', (int) $user->ID);

        // Prevent WordPress from sending a 404 for our custom rewrite route.
        global $wp_query;
        if ($wp_query instanceof WP_Query) {
            $wp_query->is_404 = false;
            $wp_query->set('error', '');
        }
        status_header(200);

        return $custom;
    }
}
