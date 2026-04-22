<?php
/**
 * Trait for Escritos (Posts) management in Course Creator.
 */

if (!defined('ABSPATH'))
    exit;

trait PL_CC_Escritos_Trait
{
    /**
     * Handles saving an "Escrito" (post).
     */
    public function handle_save_escrito()
    {
        check_ajax_referer('pcg_creator_nonce', 'nonce');
        $data = $_POST['escrito_data'] ?? [];
        if (empty($data)) wp_send_json_error(['message' => 'No se han recibido datos.']);

        $post_id = intval($data['id'] ?? 0);
        $status = sanitize_text_field($data['status'] ?? 'publish');
        if (!in_array($status, ['draft', 'publish'])) $status = 'publish';

        $post_data = [
            'post_title' => sanitize_text_field($data['title']),
            'post_content' => wp_kses_post($data['content']),
            'post_excerpt' => wp_kses_post($data['excerpt'] ?? ''),
            'post_status' => $status,
            'post_type' => 'post',
            'post_author' => get_current_user_id()
        ];

        if ($post_id > 0) {
            $post_data['ID'] = $post_id;
            $post_id = wp_update_post($post_data);
        } else {
            $post_id = wp_insert_post($post_data);
        }

        if (is_wp_error($post_id)) wp_send_json_error(['message' => 'Error al guardar: ' . $post_id->get_error_message()]);

        $thumbnail_id = intval($data['thumbnail_id'] ?? 0);
        if ($thumbnail_id > 0) set_post_thumbnail($post_id, $thumbnail_id);
        else delete_post_thumbnail($post_id);

        wp_send_json_success(['escrito_id' => $post_id, 'permalink' => get_permalink($post_id), 'message' => 'Escrito publicado exitosamente.']);
    }

    public function handle_get_my_escritos()
    {
        check_ajax_referer('pcg_creator_nonce', 'nonce');
        $posts = get_posts([
            'post_type' => 'post', 'post_status' => ['publish', 'draft'],
            'author' => get_current_user_id(), 'posts_per_page' => -1,
            'orderby' => 'date', 'order' => 'DESC'
        ]);
        $data = [];
        foreach ($posts as $post) {
            $data[] = [
                'id' => $post->ID, 'title' => $post->post_title,
                'thumbnail_url' => get_the_post_thumbnail_url($post->ID, 'large') ?: '',
                'date' => get_the_date('', $post->ID), 'status' => $post->post_status,
                'permalink' => get_permalink($post->ID)
            ];
        }
        wp_send_json_success($data);
    }

    public function handle_get_escrito_for_edit()
    {
        check_ajax_referer('pcg_creator_nonce', 'nonce');
        $post_id = intval($_POST['escrito_id'] ?? 0);
        $post = get_post($post_id);
        if (!$post || $post->post_author != get_current_user_id()) wp_send_json_error(['message' => 'Escrito no encontrado.']);

        wp_send_json_success([
            'id' => $post->ID, 'title' => $post->post_title, 'content' => $post->post_content,
            'excerpt' => $post->post_excerpt, 'status' => $post->post_status,
            'thumbnail_id' => get_post_thumbnail_id($post->ID),
            'thumbnail_url' => get_the_post_thumbnail_url($post->ID, 'full'),
            'permalink' => get_permalink($post->ID)
        ]);
    }

    public function handle_delete_escrito()
    {
        check_ajax_referer('pcg_creator_nonce', 'nonce');
        $post_id = intval($_POST['escrito_id'] ?? 0);
        $post = get_post($post_id);
        if (!$post || $post->post_author != get_current_user_id()) wp_send_json_error(['message' => 'No autorizado.']);

        wp_trash_post($post_id);
        wp_send_json_success();
    }
}
