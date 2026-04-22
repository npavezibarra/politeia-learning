<?php
/**
 * Trait for saving Profile data in Course Creator.
 */

if (!defined('ABSPATH'))
    exit;

trait PL_CC_Profile_Save_Trait
{
    /**
     * Handle AJAX profile save
     */
    public function handle_save_profile(): void
    {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('No autorizado.', 'politeia-learning')]);
        }

        check_ajax_referer('pcg_creator_nonce', 'nonce');

        $user_id = get_current_user_id();

        // Sanitize and save fields
        $fields = [
            'description'   => 'description', // Bio
            'job_title'     => 'job_title',
            'linkedin'      => 'linkedin',
            'instagram'     => 'instagram',
            'facebook'      => 'facebook',
            'personal_site' => 'personal_site',
            'twitter'       => 'twitter'
        ];

        foreach ($fields as $post_key => $meta_key) {
            if (isset($_POST[$post_key])) {
                $value = sanitize_text_field($_POST[$post_key]);
                update_user_meta($user_id, $meta_key, $value);
            }
        }

        // Handle names if provided
        if (isset($_POST['first_name'])) {
            wp_update_user([
                'ID'         => $user_id,
                'first_name' => sanitize_text_field($_POST['first_name'])
            ]);
        }
        if (isset($_POST['last_name'])) {
            wp_update_user([
                'ID'        => $user_id,
                'last_name' => sanitize_text_field($_POST['last_name'])
            ]);
        }

        wp_send_json_success(['message' => __('Perfil actualizado correctamente.', 'politeia-learning')]);
    }
}
