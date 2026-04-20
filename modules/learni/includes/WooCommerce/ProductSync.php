<?php

namespace Learni\WooCommerce;

use Learni\PostTypes\Course;

final class ProductSync
{
    /**
     * Ensure a WooCommerce product exists and is linked to this Learni course.
     *
     * @return array{product_id:int, edit_url:string}
     */
    public static function ensure_for_course(int $course_id): array
    {
        if (!class_exists('WooCommerce')) {
            return ['product_id' => 0, 'edit_url' => ''];
        }

        $course = get_post($course_id);
        if (!($course instanceof \WP_Post) || $course->post_type !== Course::POST_TYPE) {
            return ['product_id' => 0, 'edit_url' => ''];
        }

        $product_id = (int) get_post_meta($course_id, Course::META_WC_PRODUCT_ID, true);
        if ($product_id <= 0) {
            $product_id = (int) self::find_product_for_course($course_id);
        }

        if ($product_id <= 0) {
            $product_id = (int) wp_insert_post(
                [
                    'post_type' => 'product',
                    'post_status' => 'publish',
                    'post_title' => (string) get_the_title($course),
                ],
                true
            );

            if (is_wp_error($product_id) || $product_id <= 0) {
                return ['product_id' => 0, 'edit_url' => ''];
            }
        }

        $price = (float) get_post_meta($course_id, Course::META_PRICE, true);

        // Link product -> course (Learni) and maintain a compatibility link for existing Politeia modules.
        update_post_meta($product_id, Integration::PRODUCT_META_COURSE_ID, $course_id);
        update_post_meta($product_id, '_learni_related_course', [$course_id]);

        // Also store product id on the course for reuse.
        update_post_meta($course_id, Course::META_WC_PRODUCT_ID, $product_id);
        update_post_meta($course_id, '_pcg_woo_product_id', $product_id);

        update_post_meta($product_id, '_virtual', 'yes');
        update_post_meta($product_id, '_sold_individually', 'yes');
        update_post_meta($product_id, '_regular_price', (string) $price);
        update_post_meta($product_id, '_price', (string) $price);

        // Mirror the course thumbnail to the product if present.
        $thumb_id = get_post_thumbnail_id($course_id);
        if ($thumb_id) {
            update_post_meta($product_id, '_thumbnail_id', (int) $thumb_id);
        }

        $edit_url = '';
        if (function_exists('get_edit_post_link')) {
            $link = get_edit_post_link($product_id, 'raw');
            if (is_string($link)) {
                $edit_url = $link;
            }
        }

        return ['product_id' => $product_id, 'edit_url' => $edit_url];
    }

    private static function find_product_for_course(int $course_id): int
    {
        $products = get_posts(
            [
                'post_type' => 'product',
                'post_status' => ['publish', 'draft', 'pending', 'private'],
                'numberposts' => 1,
                'fields' => 'ids',
                'meta_key' => Integration::PRODUCT_META_COURSE_ID,
                'meta_value' => $course_id,
                'suppress_filters' => false,
            ]
        );

        return !empty($products) ? (int) $products[0] : 0;
    }
}

