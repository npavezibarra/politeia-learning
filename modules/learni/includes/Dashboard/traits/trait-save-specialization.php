<?php
/**
 * Trait for Specialization management in Course Creator.
 */

if (!defined('ABSPATH'))
    exit;

trait PL_CC_Specialization_Trait
{
    public function handle_get_my_specializations()
    {
        check_ajax_referer('pcg_creator_nonce', 'nonce');
        $user_id = get_current_user_id();
        global $wpdb;

        $pending_group_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT s.container_id FROM {$wpdb->prefix}" . PL_CC_Inclusion_Approvals::APPROVALS_TABLE . " a
             INNER JOIN {$wpdb->prefix}" . PL_CC_Inclusion_Approvals::SNAPSHOTS_TABLE . " s ON s.id = a.snapshot_id
             WHERE a.approver_user_id = %d AND a.status = %s AND s.status = %s AND s.container_type = %s",
            $user_id, 'pending', PL_CC_Inclusion_Approvals::STATUS_PENDING, 'group'
        ));

        $participant_group_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT object_id FROM {$wpdb->prefix}politeia_course_roles WHERE object_type = %s AND user_id = %d",
            'group', $user_id
        ));

        $author_group_ids = get_posts(['post_type' => 'learni_special', 'post_status' => ['publish', 'draft'], 'author' => $user_id, 'posts_per_page' => -1, 'fields' => 'ids']);

        $group_ids = array_values(array_unique(array_filter(array_map('absint', array_merge((array) $author_group_ids, (array) $pending_group_ids, (array) $participant_group_ids)))));
        if (empty($group_ids)) wp_send_json_success([]);

        $groups = [];
        foreach ($group_ids as $gid) {
            $p = get_post($gid);
            if ($p && $p->post_type === 'learni_special' && in_array($p->post_status, ['publish', 'draft'])) $groups[] = $p;
        }
        usort($groups, static function ($a, $b) { return strcmp((string) $b->post_date, (string) $a->post_date); });

        $data = [];
        foreach ($groups as $group) {
            $course_ids = get_post_meta($group->ID, 'learni_courses');
            $course_titles = [];
            if (is_array($course_ids)) {
                foreach ($course_ids as $cid) { $t = get_the_title($cid); if ($t) $course_titles[] = $t; }
            }
            $pending_snapshot_id = PL_CC_Inclusion_Approvals::get_pending_snapshot_id($group->ID);
            $pending_snapshot_status = $pending_snapshot_id ? (string) $wpdb->get_var($wpdb->prepare("SELECT status FROM {$wpdb->prefix}" . PL_CC_Inclusion_Approvals::SNAPSHOTS_TABLE . " WHERE id = %d", $pending_snapshot_id)) : '';

            $data[] = [
                'id' => $group->ID, 'title' => $group->post_title, 'thumbnail_url' => get_the_post_thumbnail_url($group->ID, 'medium') ?: '',
                'course_count' => count((array)$course_ids), 'permalink' => get_permalink($group->ID),
                'can_delete' => current_user_can('manage_options') || ((int)$group->post_author === $user_id),
                'can_edit' => $this->user_can_manage_group($group->ID, $user_id), 'course_titles' => $course_titles,
                'post_status' => $group->post_status, 'pending_snapshot_status' => $pending_snapshot_status,
                'is_pending_approval' => ($pending_snapshot_status === PL_CC_Inclusion_Approvals::STATUS_PENDING) || in_array((int)$group->ID, (array)$pending_group_ids, true),
            ];
        }
        wp_send_json_success($data);
    }

    public function handle_get_published_specializations()
    {
        check_ajax_referer('pcg_creator_nonce', 'nonce');
        $groups = get_posts(['post_type' => 'learni_special', 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'date', 'order' => 'DESC']);
        $data = [];
        foreach ($groups as $group) {
            $data[] = [
                'id' => $group->ID, 'title' => $group->post_title, 'author_id' => $group->post_author,
                'author_name' => get_the_author_meta('display_name', $group->post_author),
                'author_avatar' => get_avatar_url($group->post_author, ['size' => 64]),
                'author_email' => get_the_author_meta('user_email', $group->post_author),
            ];
        }
        wp_send_json_success($data);
    }

    public function handle_save_specialization()
    {
        check_ajax_referer('pcg_creator_nonce', 'nonce');
        $data = $_POST['group_data'] ?? [];
        $group_id = absint($data['id'] ?? 0);
        $title = sanitize_text_field($data['title'] ?? '');
        if ($title === '') wp_send_json_error(['message' => __('Ingresa un nombre.', 'politeia-learning')]);

        $post_data = ['post_title' => $title, 'post_content' => wp_kses_post($data['description'] ?? ''), 'post_status' => 'draft', 'post_type' => 'learni_special', 'post_author' => get_current_user_id()];
        if ($group_id > 0) {
            if (!$this->user_can_manage_group($group_id, get_current_user_id())) wp_send_json_error(['message' => __('No autorizado.', 'politeia-learning')], 403);
            $post_data['ID'] = $group_id; unset($post_data['post_author']);
            $group_id = wp_update_post($post_data);
        } else {
            $group_id = wp_insert_post($post_data);
        }

        $numeric_price = (int) preg_replace('/[^0-9]/', '', $data['price'] ?? '0');
        $price_type = $numeric_price > 0 ? 'closed' : 'free';
        update_post_meta($group_id, 'learni_price_type', $price_type);
        update_post_meta($group_id, 'learni_price', $numeric_price);

        $course_ids = array_values(array_unique(array_filter(array_map('absint', (array)($data['course_ids'] ?? [])))));
        delete_post_meta($group_id, 'learni_courses');
        foreach ($course_ids as $cid) add_post_meta($group_id, 'learni_courses', $cid);

        $this->assign_learning_terms((int)$group_id, $data['category_ids'] ?? [], $data['tag_ids'] ?? []);

        // Snapshot and participants logic
        $participants = [];
        $creator_id = get_current_user_id();
        foreach ((array)($data['teachers'] ?? []) as $t) {
            $participants[] = ['user_id' => absint($t['user_id']), 'role_slug' => sanitize_text_field($t['role_slug']), 'role_description' => wp_kses_post($t['role_description']), 'profit_percentage' => floatval($t['profit_percentage'])];
        }
        if (empty($participants)) $participants[] = ['user_id' => $creator_id, 'role_slug' => __('Autor principal', 'politeia-learning'), 'role_description' => '', 'profit_percentage' => 100];

        $payload = ['included' => array_map(function($cid) { return ['type' => 'course', 'id' => (int)$cid]; }, $course_ids), 'participants' => $participants, 'data' => ['title' => $title, 'description' => $post_data['post_content'], 'price_type' => $price_type, 'price' => $numeric_price, 'course_ids' => $course_ids]];
        $snapshot = PL_CC_Inclusion_Approvals::create_snapshot('group', $group_id, $creator_id, $payload);
        
        $product_url = ($price_type === 'closed') ? $this->sync_group_to_woo_product($group_id, $data, $price_type, $snapshot['status'] === PL_CC_Inclusion_Approvals::STATUS_APPROVED ? 'publish' : 'draft') : '';
        update_post_meta($group_id, 'learni_custom_button_url', ($snapshot['status'] === PL_CC_Inclusion_Approvals::STATUS_APPROVED) ? $product_url : '');

        if ($snapshot['status'] === PL_CC_Inclusion_Approvals::STATUS_APPROVED) $this->apply_inclusion_snapshot('group', $group_id, (int)$snapshot['snapshot_id']);

        wp_send_json_success(['group_id' => $group_id, 'permalink' => get_permalink($group_id), 'product_url' => $product_url, 'snapshot_status' => $snapshot['status']]);
    }

    public function handle_get_specialization_for_edit()
    {
        check_ajax_referer('pcg_creator_nonce', 'nonce');
        $group_id = absint($_POST['group_id'] ?? 0);
        if (!$this->user_can_manage_group($group_id, get_current_user_id())) wp_send_json_error(['message' => __('No autorizado.', 'politeia-learning')], 403);

        $post = get_post($group_id);
        $course_ids = array_values(array_unique(array_filter(array_map('absint', (array)get_post_meta($group_id, 'learni_courses')))));
        
        $teachers_data = [];
        $roles = $this->get_course_roles_for_object('group', (int)$group_id);
        foreach ($roles as $role) {
            $user = get_userdata($role->user_id);
            if ($user) $teachers_data[] = ['id' => $user->ID, 'name' => $user->display_name . ' (' . $user->user_email . ')', 'avatar' => get_avatar_url($user->ID), 'role_slug' => $role->role_slug, 'profit_percentage' => $role->profit_percentage, 'role_description' => $role->role_description];
        }

        $meta_terms = $this->get_learning_terms_for_object((int)$group_id);
        wp_send_json_success(['id' => $post->ID, 'title' => $post->post_title, 'description' => $post->post_content, 'price' => get_post_meta($group_id, 'learni_price', true), 'course_ids' => $course_ids, 'category_ids' => $meta_terms['category_ids'], 'tag_ids' => $meta_terms['tag_ids'], 'tags' => $meta_terms['tags'], 'teachers' => $teachers_data]);
    }

    public function handle_delete_specialization()
    {
        check_ajax_referer('pcg_creator_nonce', 'nonce');
        $group_id = absint($_POST['group_id'] ?? 0);
        if (!current_user_can('manage_options') && (int)get_post_field('post_author', $group_id) !== get_current_user_id()) wp_send_json_error(['message' => __('No autorizado.', 'politeia-learning')], 403);

        $product_id = (int)get_post_meta($group_id, '_pcg_woo_product_id', true);
        if ($product_id) wp_delete_post($product_id, true);

        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'politeia_course_roles', ['object_type' => 'group', 'object_id' => $group_id], ['%s', '%d']);
        wp_delete_post($group_id, true);
        wp_send_json_success(['message' => __('Especialización eliminada.', 'politeia-learning')]);
    }

    public function handle_inclusion_snapshot_approved(string $container_type, int $container_id, int $snapshot_id): void
    {
        if (in_array($container_type, ['group', 'program'], true) && $container_id > 0 && $snapshot_id > 0) {
            $this->apply_inclusion_snapshot($container_type, $container_id, $snapshot_id);
        }
    }

    private function apply_inclusion_snapshot(string $container_type, int $container_id, int $snapshot_id): void
    {
        $payload = PL_CC_Inclusion_Approvals::get_snapshot_payload($snapshot_id);
        if (!$payload) return;

        $data = $payload['data'] ?? [];
        global $wpdb;
        $roles_table = $wpdb->prefix . 'politeia_course_roles';

        wp_update_post(['ID' => $container_id, 'post_status' => 'publish']);
        
        $price_type = sanitize_text_field($data['price_type'] ?? 'free');
        $price = (int)($data['price'] ?? 0);

        if ($container_type === 'group') {
            update_post_meta($container_id, 'learni_price_type', $price_type);
            update_post_meta($container_id, 'learni_price', $price);
            delete_post_meta($container_id, 'learni_courses');
            foreach ((array)($data['course_ids'] ?? []) as $cid) add_post_meta($container_id, 'learni_courses', $cid);
        } else {
            update_post_meta($container_id, 'learni_price', $price);
            delete_post_meta($container_id, 'learni_specializations');
            foreach ((array)($data['group_ids'] ?? []) as $gid) add_post_meta($container_id, 'learni_specializations', $gid);
        }

        $wpdb->delete($roles_table, ['object_type' => $container_type, 'object_id' => $container_id], ['%s', '%d']);
        foreach ((array)($payload['participants'] ?? []) as $p) {
            $wpdb->insert($roles_table, ['object_type' => $container_type, 'object_id' => $container_id, 'user_id' => absint($p['user_id']), 'role_slug' => sanitize_text_field($p['role_slug']), 'profit_percentage' => floatval($p['profit_percentage']), 'role_description' => wp_kses_post($p['role_description'])]);
            $this->maybe_dual_write_partnership($container_type, $container_id, absint($p['user_id']), sanitize_text_field($p['role_slug']));
        }
    }
}
