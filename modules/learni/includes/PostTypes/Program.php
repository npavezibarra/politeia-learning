<?php

namespace Learni\PostTypes;

final class Program
{
    public const POST_TYPE = 'learni_program';
    public const META_SPECIALIZATIONS = 'learni_specializations';
    public const META_PRICE = 'learni_price';
    public const META_WC_PRODUCT_ID = 'learni_wc_product_id';

    private static bool $did_register_meta = false;

    public static function register(): void
    {
        if (!post_type_exists(self::POST_TYPE)) {
            register_post_type(
                self::POST_TYPE,
                [
                    'labels' => [
                        'name' => __('Programs', 'politeia-learning'),
                        'singular_name' => __('Program', 'politeia-learning'),
                    ],
                    'public' => true,
                    'show_in_rest' => true,
                    'has_archive' => false,
                    'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'revisions'],
                    'rewrite' => ['slug' => 'programs', 'with_front' => false],
                    'menu_icon' => 'dashicons-groups',
                ]
            );
        }

        if (!self::$did_register_meta) {
            self::register_meta();
            self::$did_register_meta = true;
        }
    }

    private static function register_meta(): void
    {
        register_post_meta(
            self::POST_TYPE,
            self::META_SPECIALIZATIONS,
            [
                'type' => 'integer',
                'single' => false, // Multiple specializations per program
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
