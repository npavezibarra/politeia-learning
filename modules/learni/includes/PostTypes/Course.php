<?php

namespace Learni\PostTypes;

final class Course
{
    public const POST_TYPE = 'learni_course';
    public const META_PRICE = 'learni_price';
    public const META_LINEAR_ORDER = 'learni_linear_order';
    public const META_PAYMENT_MODE = 'learni_payment_mode'; // woocommerce|direct
    public const META_WC_PRODUCT_ID = 'learni_wc_product_id';

    // Politeia-specific meta carried over from the Center editor.
    public const META_COVER_PHOTO_ID = 'pl_cover_photo_id';
    public const META_CERTIFICATE_ATTACHMENT_ID = 'learni_certificate_attachment_id';
    public const META_CERTIFICATE_TITLE = 'pl_certificate_title';
    public const META_CERTIFICATE_CONGRATS = 'pl_certificate_congrats';
    public const META_CERTIFICATE_CLAIM_FIRST = 'pl_certificate_claim_first';
    public const META_CERTIFICATE_CLAIM_FINAL = 'pl_certificate_claim_final';
    public const META_CERTIFICATE_CLAIM_VARIATION = 'pl_certificate_claim_variation';
    public const META_CERTIFICATE_LOGO_ATTACHMENT_ID = 'pl_certificate_logo_attachment_id';
    public const META_CERTIFICATE_LOGO_ALIGN = 'pl_certificate_logo_align';
    public const META_CERTIFICATE_SIGNATURE_ATTACHMENT_ID = 'pl_certificate_signature_attachment_id';
    public const META_CERTIFICATE_SIGNATURE_LABEL = 'pl_certificate_signature_label';

    private static bool $did_register_meta = false;
    private static bool $did_register_hooks = false;

    public static function register(): void
    {
        if (!post_type_exists(self::POST_TYPE)) {
            register_post_type(
                self::POST_TYPE,
                [
                    'labels' => [
                        'name' => __('Courses', 'politeia-learning'),
                        'singular_name' => __('Course', 'politeia-learning'),
                    ],
                    'public' => true,
                    'show_in_rest' => true,
                    'has_archive' => true,
                    'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'revisions'],
                    // Preserve common Politeia archive slug expectations.
                    'rewrite' => ['slug' => 'courses', 'with_front' => false],
                ]
            );
        }

        if (!self::$did_register_meta) {
            self::register_meta();
            self::$did_register_meta = true;
        }

        if (!self::$did_register_hooks) {
            add_action('save_post_' . self::POST_TYPE, [__CLASS__, 'maybe_sync_woocommerce_product'], 20, 3);
            self::$did_register_hooks = true;
        }
    }

    private static function register_meta(): void
    {
        register_post_meta(
            self::POST_TYPE,
            self::META_PRICE,
            [
                'type' => 'number',
                'single' => true,
                'show_in_rest' => true,
                'default' => 0,
                'sanitize_callback' => static function ($value) {
                    return is_numeric($value) ? (float) $value : 0;
                },
                'auth_callback' => static function ($allowed, $meta_key, $post_id) {
                    return current_user_can('edit_post', (int) $post_id);
                },
            ]
        );

        register_post_meta(
            self::POST_TYPE,
            self::META_LINEAR_ORDER,
            [
                'type' => 'boolean',
                'single' => true,
                'show_in_rest' => true,
                'default' => true,
                'sanitize_callback' => static function ($value) {
                    return (bool) $value;
                },
                'auth_callback' => static function ($allowed, $meta_key, $post_id) {
                    return current_user_can('edit_post', (int) $post_id);
                },
            ]
        );

        register_post_meta(
            self::POST_TYPE,
            self::META_PAYMENT_MODE,
            [
                'type' => 'string',
                'single' => true,
                'show_in_rest' => true,
                'default' => 'woocommerce',
                'sanitize_callback' => static function ($value) {
                    $value = is_string($value) ? sanitize_key($value) : '';
                    return in_array($value, ['woocommerce', 'direct'], true) ? $value : 'woocommerce';
                },
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

        register_post_meta(
            self::POST_TYPE,
            self::META_COVER_PHOTO_ID,
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

        register_post_meta(
            self::POST_TYPE,
            self::META_CERTIFICATE_ATTACHMENT_ID,
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

        register_post_meta(
            self::POST_TYPE,
            self::META_CERTIFICATE_TITLE,
            [
                'type' => 'string',
                'single' => true,
                'show_in_rest' => true,
                'default' => '',
                'sanitize_callback' => static function ($value) {
                    return is_string($value) ? sanitize_text_field($value) : '';
                },
                'auth_callback' => static function ($allowed, $meta_key, $post_id) {
                    return current_user_can('edit_post', (int) $post_id);
                },
            ]
        );

        register_post_meta(
            self::POST_TYPE,
            self::META_CERTIFICATE_CONGRATS,
            [
                'type' => 'string',
                'single' => true,
                'show_in_rest' => true,
                'default' => '',
                'sanitize_callback' => static function ($value) {
                    return is_string($value) ? wp_kses_post($value) : '';
                },
                'auth_callback' => static function ($allowed, $meta_key, $post_id) {
                    return current_user_can('edit_post', (int) $post_id);
                },
            ]
        );

        register_post_meta(
            self::POST_TYPE,
            self::META_CERTIFICATE_CLAIM_FIRST,
            [
                'type' => 'boolean',
                'single' => true,
                'show_in_rest' => true,
                'default' => false,
                'sanitize_callback' => static function ($value) {
                    return (bool) $value;
                },
                'auth_callback' => static function ($allowed, $meta_key, $post_id) {
                    return current_user_can('edit_post', (int) $post_id);
                },
            ]
        );

        register_post_meta(
            self::POST_TYPE,
            self::META_CERTIFICATE_CLAIM_FINAL,
            [
                'type' => 'boolean',
                'single' => true,
                'show_in_rest' => true,
                'default' => false,
                'sanitize_callback' => static function ($value) {
                    return (bool) $value;
                },
                'auth_callback' => static function ($allowed, $meta_key, $post_id) {
                    return current_user_can('edit_post', (int) $post_id);
                },
            ]
        );

        register_post_meta(
            self::POST_TYPE,
            self::META_CERTIFICATE_CLAIM_VARIATION,
            [
                'type' => 'boolean',
                'single' => true,
                'show_in_rest' => true,
                'default' => false,
                'sanitize_callback' => static function ($value) {
                    return (bool) $value;
                },
                'auth_callback' => static function ($allowed, $meta_key, $post_id) {
                    return current_user_can('edit_post', (int) $post_id);
                },
            ]
        );

        register_post_meta(
            self::POST_TYPE,
            self::META_CERTIFICATE_LOGO_ATTACHMENT_ID,
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

        register_post_meta(
            self::POST_TYPE,
            self::META_CERTIFICATE_LOGO_ALIGN,
            [
                'type' => 'string',
                'single' => true,
                'show_in_rest' => true,
                'default' => 'left',
                'sanitize_callback' => static function ($value) {
                    $value = is_string($value) ? sanitize_key($value) : '';
                    return in_array($value, ['left', 'center', 'right'], true) ? $value : 'left';
                },
                'auth_callback' => static function ($allowed, $meta_key, $post_id) {
                    return current_user_can('edit_post', (int) $post_id);
                },
            ]
        );

        register_post_meta(
            self::POST_TYPE,
            self::META_CERTIFICATE_SIGNATURE_ATTACHMENT_ID,
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

        register_post_meta(
            self::POST_TYPE,
            self::META_CERTIFICATE_SIGNATURE_LABEL,
            [
                'type' => 'string',
                'single' => true,
                'show_in_rest' => true,
                'default' => '',
                'sanitize_callback' => static function ($value) {
                    return is_string($value) ? sanitize_text_field($value) : '';
                },
                'auth_callback' => static function ($allowed, $meta_key, $post_id) {
                    return current_user_can('edit_post', (int) $post_id);
                },
            ]
        );
    }

    /**
     * @param int      $post_id
     * @param \WP_Post $post
     * @param bool     $update
     */
    public static function maybe_sync_woocommerce_product(int $post_id, \WP_Post $post, bool $update): void
    {
        if ($post_id <= 0) {
            return;
        }

        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        if (!class_exists('WooCommerce')) {
            return;
        }

        // Only sync when course is published.
        if ($post->post_status !== 'publish') {
            return;
        }

        // Sync price if it exists.
        $price = (float) get_post_meta($post_id, self::META_PRICE, true);
        if ($price <= 0) {
            return;
        }

        $mode = (string) get_post_meta($post_id, self::META_PAYMENT_MODE, true);
        $mode = $mode !== '' ? $mode : 'woocommerce';
        if ($mode !== 'woocommerce') {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (class_exists('\\Learni\\WooCommerce\\ProductSync')) {
            \Learni\WooCommerce\ProductSync::ensure_for_course($post_id);
        }
    }
}
