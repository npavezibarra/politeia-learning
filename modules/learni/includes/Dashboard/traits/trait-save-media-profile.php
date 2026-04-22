<?php
/**
 * Trait for Media and Profile management in Course Creator.
 */

if (!defined('ABSPATH'))
    exit;

trait PL_CC_Media_Profile_Trait
{
    /**
     * Handles uploading and saving a cropped image from the cropper.
     */
    public function handle_upload_cropped_image()
    {
        check_ajax_referer('pcg_creator_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('No autorizado.', 'politeia-learning')]);
        }

        $image_data = $_POST['image_data'] ?? '';
        $type = $_POST['type'] ?? 'thumbnail'; // thumbnail or cover

        if (empty($image_data)) {
            wp_send_json_error(['message' => 'No image data received.']);
        }

        $ext = '.png';
        if (preg_match('/^data:image\/(\w+);base64,/', $image_data, $typeMatch)) {
            $ext = $typeMatch[1] === 'jpeg' ? '.jpg' : '.' . $typeMatch[1];
            $image_data = substr($image_data, strpos($image_data, ',') + 1);
        }

        $decoded_image = base64_decode($image_data);

        if (!$decoded_image) {
            wp_send_json_error(['message' => 'Invalid image data.']);
        }

        $upload_dir = wp_upload_dir();
        $user = wp_get_current_user();
        $username = sanitize_title($user->user_login);
        $entity_id = intval($_POST['entity_id'] ?? 0);

        $filename = "{$username}-{$entity_id}-" . time() . '-' . sanitize_title($type) . $ext;
        $file_path = $upload_dir['path'] . '/' . $filename;

        // Save to file
        if (false === file_put_contents($file_path, $decoded_image)) {
            wp_send_json_error(['message' => 'No se pudo guardar el archivo en el servidor.']);
        }

        $filetype = wp_check_filetype($filename, null);
        $attachment = [
            'post_mime_type' => $filetype['type'],
            'post_title' => sanitize_file_name($filename),
            'post_content' => '',
            'post_status' => 'inherit',
            'post_author' => get_current_user_id()
        ];

        $attach_id = wp_insert_attachment($attachment, $file_path);

        if (is_wp_error($attach_id)) {
            wp_send_json_error(['message' => 'Error al crear el attachment: ' . $attach_id->get_error_message()]);
        }

        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
        wp_update_attachment_metadata($attach_id, $attach_data);

        wp_send_json_success([
            'id' => $attach_id,
            'url' => wp_get_attachment_url($attach_id)
        ]);
    }

    /**
     * Handles saving the user profile avatar.
     */
    public function handle_save_profile_avatar()
    {
        check_ajax_referer('pcg_creator_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('No autorizado.', 'politeia-learning')]);
        }

        $image_data = isset($_POST['image_data']) ? (string) wp_unslash($_POST['image_data']) : '';
        if (empty($image_data)) {
            wp_send_json_error(['message' => 'No image data received.']);
        }

        // Decode base64
        if (preg_match('/^data:image\/(\w+);base64,/', $image_data, $typeMatch)) {
            $image_data = substr($image_data, strpos($image_data, ',') + 1);
        }
        $decoded_image = base64_decode($image_data);

        if (!$decoded_image) {
            wp_send_json_error(['message' => 'Invalid image data.']);
        }

        $user_id = get_current_user_id();
        $upload_dir = wp_upload_dir();
        if (!empty($upload_dir['error'])) {
            wp_send_json_error(['message' => $upload_dir['error']]);
        }

        $subdir = trailingslashit($upload_dir['basedir']) . 'politeia/profile-avatars/' . $user_id;
        if (!wp_mkdir_p($subdir)) {
            wp_send_json_error(['message' => 'Could not create avatar directory.']);
        }

        $extension = 'png';
        if (preg_match('/^data:image\/(\w+);base64,/', $image_data, $type_match)) {
            $extension = strtolower((string) $type_match[1]);
            if ($extension === 'jpeg') {
                $extension = 'jpg';
            }
            if (!in_array($extension, ['jpg', 'png', 'gif', 'webp'], true)) {
                $extension = 'png';
            }
        }

        $filename = sprintf('avatar-%d-%s.%s', $user_id, wp_generate_password(12, false, false), $extension);
        $file_path = trailingslashit($subdir) . $filename;

        if (file_put_contents($file_path, $decoded_image) === false) {
            wp_send_json_error(['message' => 'Could not save avatar file.']);
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $filetype = wp_check_filetype($file_path, null);
        $attachment_id = wp_insert_attachment(
            [
                'post_mime_type' => $filetype['type'] ?: 'image/png',
                'post_title' => sprintf('Profile avatar % d', $user_id),
                'post_content' => '',
                'post_status' => 'inherit',
                'post_author' => $user_id,
            ],
            $file_path
        );

        if (is_wp_error($attachment_id) || !$attachment_id) {
            wp_send_json_error(['message' => 'Could not create avatar attachment.']);
        }

        $metadata = wp_generate_attachment_metadata((int) $attachment_id, $file_path);
        if (!is_wp_error($metadata) && !empty($metadata)) {
            wp_update_attachment_metadata((int) $attachment_id, $metadata);
        }

        $previous_attachment_id = absint(get_user_meta($user_id, '_pl_profile_avatar_attachment_id', true));
        if ($previous_attachment_id > 0 && $previous_attachment_id !== (int) $attachment_id) {
            wp_delete_attachment($previous_attachment_id, true);
        }

        update_user_meta($user_id, '_pl_profile_avatar_attachment_id', (int) $attachment_id);
        update_user_meta($user_id, '_pl_profile_avatar_url', wp_get_attachment_url((int) $attachment_id));

        wp_send_json_success([
            'url' => wp_get_attachment_image_url((int) $attachment_id, 'full') ?: wp_get_attachment_url((int) $attachment_id),
            'message' => __('Foto de perfil actualizada exitosamente.', 'politeia-learning')
        ]);
    }
}
