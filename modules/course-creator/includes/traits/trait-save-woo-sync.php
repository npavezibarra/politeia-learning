<?php
/**
 * Trait for WooCommerce synchronization in Course Creator.
 */

if (!defined('ABSPATH'))
    exit;

trait PL_CC_WooCommerce_Trait
{
    /**
     * Creates or updates a WooCommerce product linked to the course.
     */
    private function sync_course_to_woo_product($course_id, $data, $price_type, string $status = 'publish')
    {
        if (!class_exists('WooCommerce')) {
            return '';
        }

        $product_id = get_post_meta($course_id, '_pcg_woo_product_id', true);
        $title = sanitize_text_field($data['title']);
        $description = wp_kses_post($data['description']);
        $excerpt = wp_kses_post($data['excerpt'] ?? '');
        $price = intval(preg_replace('/[^0-9]/', '', $data['price'] ?? '0'));
        $thumbnail_id = intval($data['thumbnail_id'] ?? 0);

        // If course becomes free, we might want to unpublish or trash the product
        if ($price_type === 'free') {
            if ($product_id) {
                wp_trash_post($product_id);
                delete_post_meta($course_id, '_pcg_woo_product_id');
            }
            return '';
        }

        if (!in_array($status, ['draft', 'publish'], true)) {
            $status = 'publish';
        }

        $post_data = [
            'post_title' => $title,
            'post_content' => $description,
            'post_excerpt' => $excerpt,
            'post_status' => $status,
            'post_type' => 'product',
        ];

        if ($product_id && get_post($product_id)) {
            $post_data['ID'] = $product_id;
            wp_update_post($post_data);
        } else {
            $product_id = wp_insert_post($post_data);
            if (!is_wp_error($product_id)) {
                update_post_meta($course_id, '_pcg_woo_product_id', $product_id);
            }
        }

        if (is_wp_error($product_id)) {
            return '';
        }

        // Set Product Type to 'course'
        wp_set_object_terms($product_id, 'course', 'product_type');

        // Always ensure the "Cursos" category is set for course products created from the frontend.
        $this->ensure_required_product_category($product_id);

        // WooCommerce Meta
        update_post_meta($product_id, '_regular_price', $price);
        update_post_meta($product_id, '_price', $price);
        update_post_meta($product_id, '_thumbnail_id', $thumbnail_id);

        // Product owner: the course main author.
        $author_id = (int) get_post_field('post_author', $course_id);
        if ($author_id > 0) {
            update_post_meta($product_id, 'product_owner', $author_id);
        }

        // Link product -> course (compat for legacy Politeia modules + Learni native meta).
        update_post_meta($product_id, '_learni_related_course', [$course_id]);
        update_post_meta($product_id, '_learni_course_id', $course_id);
        update_post_meta($course_id, 'learni_wc_product_id', $product_id);

        return get_permalink($product_id);
    }

    /**
     * Creates or updates a WooCommerce product linked to a Learni Specialization.
     */
    private function sync_group_to_woo_product(int $group_id, array $data, string $price_type, string $post_status = 'publish'): string
    {
        if (!class_exists('WooCommerce')) {
            return '';
        }

        $product_id = (int) get_post_meta($group_id, '_pcg_woo_product_id', true);
        $title = sanitize_text_field($data['title'] ?? get_the_title($group_id));
        $description = wp_kses_post($data['description'] ?? '');
        $price = (int) preg_replace('/[^0-9]/', '', (string) ($data['price'] ?? '0'));
        $thumbnail_id = (int) ($data['thumbnail_id'] ?? 0);

        if ($price_type === 'free') {
            if ($product_id) {
                wp_trash_post($product_id);
                delete_post_meta($group_id, '_pcg_woo_product_id');
            }
            return '';
        }

        $post_data = [
            'post_title' => $title,
            'post_content' => $description,
            'post_excerpt' => '',
            'post_status' => $post_status,
            'post_type' => 'product',
        ];

        if ($product_id && get_post($product_id)) {
            $post_data['ID'] = $product_id;
            wp_update_post($post_data);
        } else {
            $product_id = (int) wp_insert_post($post_data);
            if ($product_id > 0 && !is_wp_error($product_id)) {
                update_post_meta($group_id, '_pcg_woo_product_id', $product_id);
            }
        }

        if (!$product_id || is_wp_error($product_id)) {
            return '';
        }

        wp_set_object_terms($product_id, 'course', 'product_type');
        $this->ensure_required_product_category($product_id);

        update_post_meta($product_id, '_regular_price', $price);
        update_post_meta($product_id, '_price', $price);
        if ($thumbnail_id > 0) {
            update_post_meta($product_id, '_thumbnail_id', $thumbnail_id);
        }

        $author_id = (int) get_post_field('post_author', $group_id);
        if ($author_id > 0) {
            update_post_meta($product_id, 'product_owner', $author_id);
        }

        // Link to Learni Specialization.
        update_post_meta($product_id, '_learni_related_specialization', $group_id);

        return get_permalink($product_id);
    }

    /**
     * Creates or updates a WooCommerce product linked to a Programa (learni_program).
     */
    private function sync_program_to_woo_product(int $programa_id, array $data, string $price_type, array $group_ids, string $post_status = 'publish'): string
    {
        if (!class_exists('WooCommerce')) {
            return '';
        }

        $product_id = (int) get_post_meta($programa_id, '_pcg_woo_product_id', true);
        $title = sanitize_text_field($data['title'] ?? get_the_title($programa_id));
        $description = wp_kses_post($data['description'] ?? '');
        $price = (int) preg_replace('/[^0-9]/', '', (string) ($data['price'] ?? '0'));
        $thumbnail_id = (int) ($data['thumbnail_id'] ?? 0);

        if ($price_type !== 'closed') {
            if ($product_id) {
                wp_trash_post($product_id);
                delete_post_meta($programa_id, '_pcg_woo_product_id');
            }
            return '';
        }

        $group_ids = array_values(array_unique(array_filter(array_map('absint', (array) $group_ids))));

        $post_data = [
            'post_title' => $title,
            'post_content' => $description,
            'post_excerpt' => '',
            'post_status' => $post_status,
            'post_type' => 'product',
        ];

        if ($product_id && get_post($product_id)) {
            $post_data['ID'] = $product_id;
            wp_update_post($post_data);
        } else {
            $product_id = (int) wp_insert_post($post_data);
            if ($product_id > 0 && !is_wp_error($product_id)) {
                update_post_meta($programa_id, '_pcg_woo_product_id', $product_id);
            }
        }

        if (!$product_id || is_wp_error($product_id)) {
            return '';
        }

        wp_set_object_terms($product_id, 'course', 'product_type');
        $this->ensure_required_product_category($product_id);

        update_post_meta($product_id, '_regular_price', $price);
        update_post_meta($product_id, '_price', $price);
        if ($thumbnail_id > 0) {
            update_post_meta($product_id, '_thumbnail_id', $thumbnail_id);
        }

        $author_id = (int) get_post_field('post_author', $programa_id);
        if ($author_id > 0) {
            update_post_meta($product_id, 'product_owner', $author_id);
        }

        // Store program linkage and sync specializations for enrollment.
        update_post_meta($product_id, '_learni_related_program', $programa_id);
        update_post_meta($product_id, '_learni_related_specializations', $group_ids);

        return get_permalink($product_id);
    }

    private function ensure_required_product_category($product_id): void
    {
        if (!taxonomy_exists('product_cat')) {
            return;
        }

        $term = get_term_by('slug', self::REQUIRED_PRODUCT_CATEGORY_SLUG, 'product_cat');
        if (!$term || is_wp_error($term)) {
            $term = get_term_by('name', self::REQUIRED_PRODUCT_CATEGORY_NAME, 'product_cat');
        }

        if (!$term || is_wp_error($term)) {
            $inserted = wp_insert_term(self::REQUIRED_PRODUCT_CATEGORY_NAME, 'product_cat', [
                'slug' => self::REQUIRED_PRODUCT_CATEGORY_SLUG,
            ]);

            if (is_wp_error($inserted)) {
                error_log('[politeia-learning] Failed to ensure required product category "Cursos": ' . $inserted->get_error_message());
                return;
            }

            $term_id = (int) ($inserted['term_id'] ?? 0);
        } else {
            $term_id = (int) $term->term_id;
        }

        if ($term_id > 0) {
            wp_set_object_terms($product_id, [$term_id], 'product_cat', true);
        }
    }
}
