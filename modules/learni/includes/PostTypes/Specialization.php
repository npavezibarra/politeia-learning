<?php

namespace Learni\PostTypes;

final class Specialization
{
    /**
     * IMPORTANT: WordPress post type keys must be <= 20 chars.
     * Legacy key `learni_specialization` is 21 chars and triggers _doing_it_wrong notices.
     *
     * We migrate legacy posts to this shorter key automatically (one-time) on register.
     */
    public const POST_TYPE = 'learni_special';
    public const LEGACY_POST_TYPE = 'learni_specialization';
    public const META_COURSES = 'learni_courses';
    public const META_PRICE = 'learni_price';
    public const META_WC_PRODUCT_ID = 'learni_wc_product_id';

    private static bool $did_register_meta = false;

    public static function register(): void
    {
        self::maybe_migrate_legacy_post_type();

        if (!post_type_exists(self::POST_TYPE)) {
            register_post_type(
                self::POST_TYPE,
                [
                    'labels' => [
                        'name' => __('Specializations', 'politeia-learning'),
                        'singular_name' => __('Specialization', 'politeia-learning'),
                    ],
                    'public' => true,
                    'show_in_rest' => true,
                    'has_archive' => false,
                    'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'revisions'],
                    'rewrite' => ['slug' => 'specializations', 'with_front' => false],
                    'menu_icon' => 'dashicons-category',
                ]
            );
        }

        if (!self::$did_register_meta) {
            self::register_meta();
            self::$did_register_meta = true;
        }
    }

    private static function maybe_migrate_legacy_post_type(): void
    {
        // One-time DB migration: wp_posts.post_type from legacy key to new key.
        // This avoids registering the legacy key (which would keep triggering the warning).
        $flag = 'learni_specialization_post_type_migrated_v1';
        if (get_option($flag)) {
            return;
        }

        global $wpdb;
        if (!$wpdb) {
            return;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->posts} SET post_type = %s WHERE post_type = %s",
                self::POST_TYPE,
                self::LEGACY_POST_TYPE
            )
        );

        update_option($flag, 1, false);
    }

    private static function register_meta(): void
    {
        register_post_meta(
            self::POST_TYPE,
            self::META_COURSES,
            [
                'type' => 'integer',
                'single' => false, // Multiple courses per specialization
                'show_in_rest' => true,
                'sanitize_callback' => 'absint',
                'auth_callback' => static function ($allowed, $meta_key, $post_id) {
                    return current_user_can('edit_post', (int) $post_id);
                },
            ]
        );

        register_post_meta(
            self::POST_TYPE,
            self::META_PRICE,
            [
                'type' => 'number',
                'single' => true,
                'show_in_rest' => true,
                'default' => 0,
                'sanitize_callback' => 'floatval',
                'auth_callback' => static function ($allowed, $meta_key, $post_id) {
                    return current_user_can('edit_post', (int) $post_id);
                },
            ]
        );

        register_post_meta(
            self::POST_TYPE,
            self::META_WC_PRODUCT_ID,
            [
                'type' => 'integer',
                'single' => true,
                'show_in_rest' => true,
                'default' => 0,
                'sanitize_callback' => 'absint',
                'auth_callback' => static function ($allowed, $meta_key, $post_id) {
                    return current_user_can('edit_post', (int) $post_id);
                },
            ]
        );
    }
}
