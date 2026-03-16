<?php
/**
 * Handles the profile template switching logic.
 */

if (!defined('ABSPATH'))
    exit;

class PL_Member_Profile_Template
{
    public function __construct()
    {
        // Lower priority (9) allows specialized routes (at 10+) to override this template
        add_filter('template_include', [$this, 'load_profile_template'], 9);
        add_filter('bp_template_include', [$this, 'load_profile_template'], 9);
    }

    /**
     * Override the template for member profiles if the custom template is selected.
     */
    public function load_profile_template($template)
    {
        // Use bp_is_user() to detect any member-related page
        if (function_exists('bp_is_user') && bp_is_user()) {
            
            // Specifically target the main profile view/front
            $is_profile_path = bp_is_user_profile() || bp_is_user_front() || (function_exists('bp_is_my_profile') && bp_is_my_profile());
            
            if ($is_profile_path) {
                $selected_template = get_option('pcg_profile_template', 'default');
                if ($selected_template === 'politeia-profile') {
                    $custom = PL_MEMBER_PROFILE_PATH . 'templates/politeia-profile.php';
                    if (file_exists($custom)) {
                        return $custom;
                    }
                }
            }
        }
        return $template;
    }
}
