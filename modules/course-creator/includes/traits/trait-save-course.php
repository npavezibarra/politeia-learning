<?php
/**
 * Trait for Course management in Course Creator.
 */

if (!defined('ABSPATH'))
    exit;

trait PL_CC_Course_Trait
{
    public function handle_save_course()
    {
        check_ajax_referer('pcg_creator_nonce', 'nonce');

        $data = $_POST['course_data'] ?? [];
        if (empty($data)) {
            wp_send_json_error(['message' => 'No se han recibido datos del curso.']);
        }

        $course_id = intval($data['id'] ?? 0);
        $status = sanitize_text_field((string) ($data['status'] ?? 'publish'));
        if (!in_array($status, ['draft', 'publish'], true)) {
            $status = 'publish';
        }
        $title = sanitize_text_field($data['title']);
        $description = wp_kses_post($data['description']);
        $excerpt = wp_kses_post($data['excerpt'] ?? '');
        $price = sanitize_text_field($data['price']);
        $thumbnail_id = intval($data['thumbnail_id'] ?? 0);
        $progression = sanitize_text_field($data['progression'] ?? '');
        $certificate_attachment_id = intval($data['certificate_attachment_id'] ?? 0);
        $certificate_title = sanitize_text_field((string) ($data['certificate_title'] ?? ''));
        $certificate_congrats = wp_kses_post((string) ($data['certificate_congrats'] ?? ''));
        $certificate_logo_attachment_id = intval($data['certificate_logo_attachment_id'] ?? 0);
        $certificate_signature_attachment_id = intval($data['certificate_signature_attachment_id'] ?? 0);
        $certificate_signature_label = sanitize_text_field((string) ($data['certificate_signature_label'] ?? ''));
        $content_list = $data['content'] ?? [];
        $teacher_ids = $data['teachers'] ?? [];
        $category_ids = $data['category_ids'] ?? [];
        $tag_ids = $data['tag_ids'] ?? [];

        // 1. Create or Update Course (Learni internal CPT)
        $post_data = [
            'post_title' => $title,
            'post_content' => $description,
            'post_excerpt' => $excerpt,
            'post_status' => $status,
            'post_type' => 'learni_course',
            'post_author' => get_current_user_id()
        ];

        if ($course_id > 0) {
            $existing = get_post($course_id);
            if (!$existing || $existing->post_type !== 'learni_course' || (int) $existing->post_author !== (int) get_current_user_id()) {
                wp_send_json_error(['message' => __('No autorizado.', 'politeia-learning')], 403);
            }
            $post_data['ID'] = $course_id;
            $course_id = wp_update_post($post_data);
        } else {
            $course_id = wp_insert_post($post_data);
        }

        if (is_wp_error($course_id)) {
            wp_send_json_error(['message' => 'Error al guardar el curso: ' . $course_id->get_error_message()]);
        }

        // 2. Set Featured Image
        if ($thumbnail_id > 0) {
            set_post_thumbnail($course_id, $thumbnail_id);
        } else {
            delete_post_thumbnail($course_id);
        }

        // 2b. Set Cover Photo (Politeia meta on Learni courses)
        $cover_photo_id = intval($data['cover_photo_id'] ?? 0);
        $cover_meta_key = class_exists('\\Learni\\PostTypes\\Course') ? \Learni\PostTypes\Course::META_COVER_PHOTO_ID : 'pl_cover_photo_id';
        if ($cover_photo_id > 0) {
            update_post_meta($course_id, $cover_meta_key, $cover_photo_id);
        } else {
            delete_post_meta($course_id, $cover_meta_key);
        }

        // 2d. Certificate template (attachment id).
        $cert_meta_key = class_exists('\\Learni\\PostTypes\\Course') ? \Learni\PostTypes\Course::META_CERTIFICATE_ATTACHMENT_ID : 'learni_certificate_attachment_id';
        if ($certificate_attachment_id > 0) {
            update_post_meta($course_id, $cert_meta_key, $certificate_attachment_id);
        } else {
            delete_post_meta($course_id, $cert_meta_key);
        }

        // 2e. Certificate configuration.
        $meta_keys = class_exists('\\Learni\\PostTypes\\Course')
            ? [
                'title' => \Learni\PostTypes\Course::META_CERTIFICATE_TITLE,
                'congrats' => \Learni\PostTypes\Course::META_CERTIFICATE_CONGRATS,
                'claim_first' => \Learni\PostTypes\Course::META_CERTIFICATE_CLAIM_FIRST,
                'claim_final' => \Learni\PostTypes\Course::META_CERTIFICATE_CLAIM_FINAL,
                'claim_variation' => \Learni\PostTypes\Course::META_CERTIFICATE_CLAIM_VARIATION,
                'logo_id' => \Learni\PostTypes\Course::META_CERTIFICATE_LOGO_ATTACHMENT_ID,
                'logo_align' => \Learni\PostTypes\Course::META_CERTIFICATE_LOGO_ALIGN,
                'signature_id' => \Learni\PostTypes\Course::META_CERTIFICATE_SIGNATURE_ATTACHMENT_ID,
                'signature_label' => \Learni\PostTypes\Course::META_CERTIFICATE_SIGNATURE_LABEL,
            ]
            : [
                'title' => 'pl_certificate_title',
                'congrats' => 'pl_certificate_congrats',
                'claim_first' => 'pl_certificate_claim_first',
                'claim_final' => 'pl_certificate_claim_final',
                'claim_variation' => 'pl_certificate_claim_variation',
                'logo_id' => 'pl_certificate_logo_attachment_id',
                'logo_align' => 'pl_certificate_logo_align',
                'signature_id' => 'pl_certificate_signature_attachment_id',
                'signature_label' => 'pl_certificate_signature_label',
            ];

        update_post_meta($course_id, $meta_keys['title'], sanitize_text_field($certificate_title));
        update_post_meta($course_id, $meta_keys['congrats'], wp_kses_post($certificate_congrats));
        update_post_meta($course_id, $meta_keys['claim_first'], 1);
        update_post_meta($course_id, $meta_keys['claim_final'], 1);
        update_post_meta($course_id, $meta_keys['claim_variation'], 1);

        if ($certificate_logo_attachment_id > 0) {
            update_post_meta($course_id, $meta_keys['logo_id'], $certificate_logo_attachment_id);
        } else {
            delete_post_meta($course_id, $meta_keys['logo_id']);
        }
        update_post_meta($course_id, $meta_keys['logo_align'], 'left');

        if ($certificate_signature_attachment_id > 0) {
            update_post_meta($course_id, $meta_keys['signature_id'], $certificate_signature_attachment_id);
        } else {
            delete_post_meta($course_id, $meta_keys['signature_id']);
        }
        update_post_meta($course_id, $meta_keys['signature_label'], sanitize_text_field($certificate_signature_label));

        // 2c. Set Additional Teachers (Legacy Meta + New Table)
        global $wpdb;
        $roles_table = $wpdb->prefix . 'politeia_course_roles';
        $wpdb->delete($roles_table, ['object_type' => 'course', 'object_id' => $course_id], ['%s', '%d']);

        if (!empty($teacher_ids) && is_array($teacher_ids)) {
            foreach ($teacher_ids as $teacher) {
                $teacher_user_id = intval($teacher['user_id'] ?? 0);
                $teacher_role_slug = sanitize_text_field((string) ($teacher['role_slug'] ?? ''));
                $wpdb->insert($roles_table, [
                    'object_type' => 'course',
                    'object_id' => $course_id,
                    'user_id' => $teacher_user_id,
                    'role_slug' => $teacher_role_slug,
                    'profit_percentage' => floatval($teacher['profit_percentage']),
                    'role_description' => wp_kses_post($teacher['role_description']),
                ], ['%s', '%d', '%d', '%s', '%f', '%s']);

                $this->maybe_dual_write_partnership('course', (int) $course_id, (int) $teacher_user_id, (string) $teacher_role_slug);
            }
        }

        // 3. Save Learni course settings
        $numeric_price = intval(preg_replace('/[^0-9]/', '', (string) $price));
        $price_type = $numeric_price > 0 ? 'closed' : 'free';
        update_post_meta($course_id, 'learni_price', (float) $numeric_price);

        $linear = ($progression === 'on') ? false : true;
        update_post_meta($course_id, 'learni_linear_order', $linear ? 1 : 0);

        // 3. Handle Lessons and Sections
        $this->process_course_content($course_id, $content_list);

        // 4. Sync with WooCommerce
        $product_url = $this->sync_course_to_woo_product($course_id, $data, $price_type, $status);

        // 5. Save learning categories/tags.
        $this->assign_learning_terms((int) $course_id, $category_ids, $tag_ids);

        wp_send_json_success([
            'course_id' => $course_id,
            'product_url' => $product_url,
            'permalink' => get_permalink($course_id),
            'status' => get_post_status($course_id),
            'message' => __('Curso guardado exitosamente.', 'politeia-learning')
        ]);
    }

    private function process_course_content($course_id, $content_list)
    {
        $post_type = $course_id > 0 ? (string) get_post_type($course_id) : '';
        if ($post_type === 'learni_course') {
            $this->process_learni_course_content((int) $course_id, (array) $content_list);
        }
    }

    private function process_learni_course_content(int $course_id, array $content_list): void
    {
        if ($course_id <= 0) return;

        global $wpdb;
        $items_table = $wpdb->prefix . 'learni_course_items';
        $existing_lesson_ids = $wpdb->get_col($wpdb->prepare("SELECT item_ref_id FROM {$items_table} WHERE course_post_id = %d AND item_type = %s", $course_id, 'lesson'));
        
        foreach (array_values(array_unique(array_map('absint', (array) $existing_lesson_ids))) as $lesson_id) {
            if ($lesson_id > 0 && get_post_type($lesson_id) === 'learni_lesson') {
                wp_delete_post($lesson_id, true);
            }
        }
        $wpdb->delete($items_table, ['course_post_id' => $course_id], ['%d']);

        $sort = 0;
        foreach ($content_list as $item) {
            $type = (string) ($item['type'] ?? '');
            $title = sanitize_text_field((string) ($item['title'] ?? ''));
            if ($type === '' || $title === '') { $sort++; continue; }

            if ($type === 'section') {
                $wpdb->insert($items_table, [
                    'course_post_id' => $course_id,
                    'item_type' => 'header',
                    'item_ref_id' => 0,
                    'label' => $title,
                    'sort_order' => $sort,
                    'is_preview' => 0,
                    'created_at' => current_time('mysql'),
                ], ['%d', '%s', '%d', '%s', '%d', '%d', '%s']);
                $sort++; continue;
            }

            if ($type !== 'lesson') { $sort++; continue; }

            $video_url = esc_url_raw((string) ($item['video_url'] ?? ''));
            $available_date = sanitize_text_field((string) ($item['available_date'] ?? ''));
            $escrito_id = absint($item['escrito_id'] ?? 0);

            $lesson_id = wp_insert_post([
                'post_title' => $title,
                'post_status' => 'publish',
                'post_type' => 'learni_lesson',
                'post_author' => get_current_user_id(),
                'menu_order' => $sort,
            ]);

            if (is_wp_error($lesson_id) || (int) $lesson_id <= 0) { $sort++; continue; }

            update_post_meta((int) $lesson_id, 'learni_video_url', $video_url);
            update_post_meta((int) $lesson_id, 'learni_available_at', $available_date);

            if ($escrito_id > 0 && class_exists('\\Learni\\PostTypes\\Lesson')) {
                update_post_meta((int) $lesson_id, \Learni\PostTypes\Lesson::META_SOURCE_POST_ID, $escrito_id);
            }

            $wpdb->insert($items_table, [
                'course_post_id' => $course_id,
                'item_type' => 'lesson',
                'item_ref_id' => (int) $lesson_id,
                'label' => '',
                'sort_order' => $sort,
                'is_preview' => 0,
                'created_at' => current_time('mysql'),
            ], ['%d', '%s', '%d', '%s', '%d', '%d', '%s']);
            $sort++;
        }
    }

    public function handle_get_my_courses()
    {
        check_ajax_referer('pcg_creator_nonce', 'nonce');
        $courses = get_posts([
            'post_type' => 'learni_course',
            'post_status' => ['publish', 'draft'],
            'author' => get_current_user_id(),
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC'
        ]);
        $data = [];
        foreach ($courses as $post) {
            $numeric_price = (int) (float) get_post_meta($post->ID, 'learni_price', true);
            global $wpdb;
            $lesson_count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}learni_course_items WHERE course_post_id = %d AND item_type = %s", $post->ID, 'lesson'));
            $data[] = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'thumbnail_url' => get_the_post_thumbnail_url($post->ID, 'medium') ?: '',
                'price' => $numeric_price > 0 ? ('$' . number_format($numeric_price, 0, '.', ',')) : __('Gratis', 'politeia-learning'),
                'lesson_count' => $lesson_count,
                'status' => $post->post_status,
            ];
        }
        wp_send_json_success($data);
    }

    public function handle_get_published_courses()
    {
        check_ajax_referer('pcg_creator_nonce', 'nonce');
        $courses = get_posts([
            'post_type' => 'learni_course',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);
        $data = [];
        foreach ($courses as $post) {
            $data[] = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'author_id' => $post->post_author,
                'author_name' => get_the_author_meta('display_name', $post->post_author),
                'author_avatar' => get_avatar_url($post->post_author, ['size' => 64]),
                'author_email' => get_the_author_meta('user_email', $post->post_author),
            ];
        }
        wp_send_json_success($data);
    }

    public function handle_delete_course()
    {
        check_ajax_referer('pcg_creator_nonce', 'nonce');
        $course_id = intval($_POST['course_id'] ?? 0);
        if ($course_id <= 0 || get_post_type($course_id) !== 'learni_course' || get_post_field('post_author', $course_id) != get_current_user_id()) {
            wp_send_json_error(['message' => __('No autorizado.', 'politeia-learning')]);
        }
        global $wpdb;
        $items_table = $wpdb->prefix . 'learni_course_items';
        $lesson_ids = $wpdb->get_col($wpdb->prepare("SELECT item_ref_id FROM {$items_table} WHERE course_post_id = %d AND item_type = %s", $course_id, 'lesson'));
        foreach (array_values(array_unique(array_map('absint', (array) $lesson_ids))) as $lesson_id) {
            if ($lesson_id > 0) wp_delete_post($lesson_id, true);
        }
        $wpdb->delete($items_table, ['course_post_id' => $course_id], ['%d']);
        $product_id = get_post_meta($course_id, '_pcg_woo_product_id', true);
        if ($product_id) wp_delete_post($product_id, true);
        $wpdb->delete($wpdb->prefix . 'politeia_course_roles', ['object_type' => 'course', 'object_id' => $course_id], ['%s', '%d']);
        wp_delete_post($course_id, true);
        wp_send_json_success(['message' => __('Curso eliminado exitosamente.', 'politeia-learning')]);
    }

    public function handle_get_course_for_edit()
    {
        check_ajax_referer('pcg_creator_nonce', 'nonce');
        $course_id = intval($_POST['course_id'] ?? 0);
        if ($course_id <= 0 || get_post_type($course_id) !== 'learni_course' || get_post_field('post_author', $course_id) != get_current_user_id()) {
            wp_send_json_error(['message' => __('No autorizado.', 'politeia-learning')]);
        }
        $post = get_post($course_id);
        $cover_meta_key = class_exists('\\Learni\\PostTypes\\Course') ? \Learni\PostTypes\Course::META_COVER_PHOTO_ID : 'pl_cover_photo_id';
        $cert_meta_key = class_exists('\\Learni\\PostTypes\\Course') ? \Learni\PostTypes\Course::META_CERTIFICATE_ATTACHMENT_ID : 'learni_certificate_attachment_id';
        
        $certificate_attachment_id = (int) get_post_meta($course_id, $cert_meta_key, true);
        $roles = $this->get_course_roles_for_object('course', (int) $course_id);
        $teachers_data = [];
        foreach ($roles as $role) {
            $user = get_userdata($role->user_id);
            if ($user) {
                $teachers_data[] = [
                    'id' => $user->ID,
                    'name' => $user->display_name . ' (' . $user->user_email . ')',
                    'avatar' => get_avatar_url($user->ID, ['size' => 64]),
                    'role_slug' => $role->role_slug,
                    'profit_percentage' => $role->profit_percentage,
                    'role_description' => $role->role_description
                ];
            }
        }

        $content = [];
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare("SELECT item_type, item_ref_id, label FROM {$wpdb->prefix}learni_course_items WHERE course_post_id = %d ORDER BY sort_order ASC, id ASC", $course_id), ARRAY_A);
        foreach ((array) $rows as $row) {
            if ($row['item_type'] === 'header') {
                $content[] = ['type' => 'section', 'title' => (string) $row['label']];
            } elseif ($row['item_type'] === 'lesson') {
                $lid = (int) $row['item_ref_id'];
                $src_id = class_exists('\\Learni\\PostTypes\\Lesson') ? (int) get_post_meta($lid, \Learni\PostTypes\Lesson::META_SOURCE_POST_ID, true) : 0;
                $content[] = [
                    'type' => 'lesson', 'id' => $lid, 'title' => get_the_title($lid),
                    'video_url' => (string) get_post_meta($lid, 'learni_video_url', true),
                    'available_date' => (string) get_post_meta($lid, 'learni_available_at', true),
                    'escrito_id' => $src_id, 'escrito_title' => $src_id > 0 ? get_the_title($src_id) : '',
                ];
            }
        }

        $meta_terms = $this->get_learning_terms_for_object((int) $course_id);
        wp_send_json_success([
            'id' => $post->ID, 'title' => $post->post_title, 'description' => $post->post_content, 'excerpt' => $post->post_excerpt,
            'status' => $post->post_status, 'price' => (string) get_post_meta($course_id, 'learni_price', true),
            'category_ids' => $meta_terms['category_ids'], 'tag_ids' => $meta_terms['tag_ids'], 'tags' => $meta_terms['tags'],
            'thumbnail_id' => get_post_thumbnail_id($course_id), 'thumbnail_url' => get_the_post_thumbnail_url($course_id),
            'cover_photo_id' => get_post_meta($course_id, $cover_meta_key, true), 'cover_photo_url' => wp_get_attachment_url(get_post_meta($course_id, $cover_meta_key, true)),
            'certificate_attachment_id' => $certificate_attachment_id, 'certificate_url' => wp_get_attachment_url($certificate_attachment_id),
            'permalink' => get_permalink($course_id), 'progression' => get_post_meta($course_id, 'learni_linear_order', true) ? '' : 'on',
            'content' => $content, 'teachers' => $teachers_data,
        ]);
    }
}
