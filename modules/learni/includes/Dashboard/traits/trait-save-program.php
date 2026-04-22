<?php
/**
 * Trait for Program management in Course Creator.
 */

if (!defined('ABSPATH'))
    exit;

trait PL_CC_Program_Trait
{
    public function handle_get_my_programas()
    {
        check_ajax_referer('pcg_creator_nonce', 'nonce');
        $user_id = get_current_user_id();
        global $wpdb;

        $pending_program_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT s.container_id FROM {$wpdb->prefix}" . PL_CC_Inclusion_Approvals::APPROVALS_TABLE . " a
             INNER JOIN {$wpdb->prefix}" . PL_CC_Inclusion_Approvals::SNAPSHOTS_TABLE . " s ON s.id = a.snapshot_id
             WHERE a.approver_user_id = %d AND a.status = %s AND s.status = %s AND s.container_type = %s",
            $user_id, 'pending', PL_CC_Inclusion_Approvals::STATUS_PENDING, 'program'
        ));

        $participant_program_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT object_id FROM {$wpdb->prefix}politeia_course_roles WHERE object_type = %s AND user_id = %d",
            'program', $user_id
        ));

        $own_program_ids = get_posts(['post_type' => 'learni_program', 'post_status' => ['publish', 'draft'], 'author' => $user_id, 'posts_per_page' => -1, 'fields' => 'ids']);

        $program_ids = array_values(array_unique(array_filter(array_map('absint', array_merge((array) $own_program_ids, (array) $pending_program_ids, (array) $participant_program_ids)))));
        if (empty($program_ids)) wp_send_json_success([]);

        $programs = [];
        foreach ($program_ids as $pid) {
            $p = get_post($pid);
            if ($p && $p->post_type === 'learni_program' && in_array($p->post_status, ['publish', 'draft'])) $programs[] = $p;
        }
        usort($programs, static function ($a, $b) { return strcmp((string) $b->post_date, (string) $a->post_date); });

        $data = [];
        foreach ($programs as $post) {
            $group_ids = get_post_meta($post->ID, 'learni_specializations');
            $pending_snapshot_id = PL_CC_Inclusion_Approvals::get_pending_snapshot_id($post->ID);
            $pending_snapshot_status = $pending_snapshot_id ? (string) $wpdb->get_var($wpdb->prepare("SELECT status FROM {$wpdb->prefix}" . PL_CC_Inclusion_Approvals::SNAPSHOTS_TABLE . " WHERE id = %d", $pending_snapshot_id)) : '';

            $data[] = [
                'id' => $post->ID, 'title' => $post->post_title, 'thumbnail_url' => get_the_post_thumbnail_url($post->ID, 'medium') ?: '',
                'group_count' => count((array)$group_ids), 'price' => get_post_meta($post->ID, 'learni_price', true), 'permalink' => get_permalink($post->ID),
                'can_delete' => ((int)$post->post_author === $user_id) || current_user_can('manage_options'),
                'can_edit' => ((int)$post->post_author === $user_id) || current_user_can('manage_options'),
                'post_status' => $post->post_status, 'pending_snapshot_status' => $pending_snapshot_status,
                'is_pending_approval' => ($pending_snapshot_status === PL_CC_Inclusion_Approvals::STATUS_PENDING) || in_array((int)$post->ID, (array)$pending_program_ids, true),
            ];
        }
        wp_send_json_success($data);
    }

    public function handle_save_programa()
    {
        check_ajax_referer('pcg_creator_nonce', 'nonce');
        $data = $_POST['programa_data'] ?? [];
        $programa_id = absint($data['id'] ?? 0);
        $title = sanitize_text_field($data['title'] ?? '');
        if ($title === '') wp_send_json_error(['message' => __('Ingresa un nombre.', 'politeia-learning')]);

        $post_data = ['post_title' => $title, 'post_content' => wp_kses_post($data['description'] ?? ''), 'post_status' => 'draft', 'post_type' => 'learni_program', 'post_author' => get_current_user_id()];
        if ($programa_id > 0) {
            if (!$this->user_can_manage_programa($programa_id, get_current_user_id())) wp_send_json_error(['message' => __('No autorizado.', 'politeia-learning')], 403);
            $post_data['ID'] = $programa_id; unset($post_data['post_author']);
            $programa_id = wp_update_post($post_data);
        } else {
            $programa_id = wp_insert_post($post_data);
        }

        $numeric_price = (int) preg_replace('/[^0-9]/', '', $data['price'] ?? '0');
        $price_type = $numeric_price > 0 ? 'closed' : 'open';
        update_post_meta($programa_id, 'learni_price', $numeric_price);
        update_post_meta($programa_id, '_pcg_program_access_mode', $price_type);

        $group_ids = array_values(array_unique(array_filter(array_map('absint', (array)($data['group_ids'] ?? [])))));
        delete_post_meta($programa_id, 'learni_specializations');
        foreach ($group_ids as $gid) add_post_meta($programa_id, 'learni_specializations', $gid);

        $this->assign_learning_terms((int)$programa_id, $data['category_ids'] ?? [], $data['tag_ids'] ?? []);

        $participants = [];
        $creator_id = get_current_user_id();
        foreach ((array)($data['teachers'] ?? []) as $t) {
            $participants[] = ['user_id' => absint($t['user_id']), 'role_slug' => sanitize_text_field($t['role_slug']), 'role_description' => wp_kses_post($t['role_description']), 'profit_percentage' => floatval($t['profit_percentage'])];
        }
        if (empty($participants)) $participants[] = ['user_id' => $creator_id, 'role_slug' => __('Autor principal', 'politeia-learning'), 'role_description' => '', 'profit_percentage' => 100];

        $payload = ['included' => array_map(function($gid) { return ['type' => 'group', 'id' => (int)$gid]; }, $group_ids), 'participants' => $participants, 'data' => ['title' => $title, 'description' => $post_data['post_content'], 'price_type' => $price_type, 'price' => $numeric_price, 'group_ids' => $group_ids]];
        $snapshot = PL_CC_Inclusion_Approvals::create_snapshot('program', $programa_id, $creator_id, $payload);
        
        $product_url = ($price_type === 'closed') ? $this->sync_program_to_woo_product($programa_id, $data, $price_type, $group_ids, $snapshot['status'] === PL_CC_Inclusion_Approvals::STATUS_APPROVED ? 'publish' : 'draft') : '';
        update_post_meta($programa_id, '_pcg_program_custom_button_url', ($snapshot['status'] === PL_CC_Inclusion_Approvals::STATUS_APPROVED) ? $product_url : '');

        if ($snapshot['status'] === PL_CC_Inclusion_Approvals::STATUS_APPROVED) $this->apply_inclusion_snapshot('program', $programa_id, (int)$snapshot['snapshot_id']);

        wp_send_json_success(['programa_id' => $programa_id, 'permalink' => get_permalink($programa_id), 'product_url' => $product_url, 'snapshot_status' => $snapshot['status']]);
    }

    public function handle_get_programa_for_edit()
    {
        check_ajax_referer('pcg_creator_nonce', 'nonce');
        $programa_id = absint($_POST['programa_id'] ?? 0);
        if (!$this->user_can_manage_programa($programa_id, get_current_user_id())) wp_send_json_error(['message' => __('No autorizado.', 'politeia-learning')], 403);

        $post = get_post($programa_id);
        $group_ids = array_values(array_unique(array_filter(array_map('absint', (array)get_post_meta($programa_id, 'learni_specializations')))));
        
        $teachers_data = [];
        $roles = $this->get_course_roles_for_object('program', (int)$programa_id);
        foreach ($roles as $role) {
            $user = get_userdata($role->user_id);
            if ($user) $teachers_data[] = ['id' => $user->ID, 'name' => $user->display_name . ' (' . $user->user_email . ')', 'avatar' => get_avatar_url($user->ID), 'role_slug' => $role->role_slug, 'profit_percentage' => $role->profit_percentage, 'role_description' => $role->role_description];
        }

        $meta_terms = $this->get_learning_terms_for_object((int)$programa_id);
        wp_send_json_success(['id' => $post->ID, 'title' => $post->post_title, 'description' => $post->post_content, 'price' => get_post_meta($programa_id, 'learni_price', true), 'group_ids' => $group_ids, 'category_ids' => $meta_terms['category_ids'], 'tag_ids' => $meta_terms['tag_ids'], 'tags' => $meta_terms['tags'], 'teachers' => $teachers_data]);
    }

    public function handle_delete_programa()
    {
        check_ajax_referer('pcg_creator_nonce', 'nonce');
        $programa_id = absint($_POST['programa_id'] ?? 0);
        if (!current_user_can('manage_options') && (int)get_post_field('post_author', $programa_id) !== get_current_user_id()) wp_send_json_error(['message' => __('No autorizado.', 'politeia-learning')], 403);

        $product_id = (int)get_post_meta($programa_id, '_pcg_woo_product_id', true);
        if ($product_id) wp_delete_post($product_id, true);

        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'politeia_course_roles', ['object_type' => 'program', 'object_id' => $programa_id], ['%s', '%d']);
        wp_delete_post($programa_id, true);
        wp_send_json_success(['message' => __('Programa eliminado.', 'politeia-learning')]);
    }
}
