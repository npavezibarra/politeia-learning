<?php

namespace Learni\PostTypes;

final class Lesson
{
    public const POST_TYPE = 'learni_lesson';
    public const META_VIDEO_URL = 'learni_video_url';
    public const META_AVAILABLE_AT = 'learni_available_at'; // YYYY-MM-DD or empty

    private static bool $did_register_meta = false;

    public static function register(): void
    {
        if (!post_type_exists(self::POST_TYPE)) {
            register_post_type(
                self::POST_TYPE,
                [
                    'labels' => [
                        'name' => __('Lessons', 'politeia-learning'),
                        'singular_name' => __('Lesson', 'politeia-learning'),
                    ],
                    'public' => false,
                    // Allow viewing lessons via query var (?learni_lesson=slug) while we progressively
                    // migrate the learner UX. Keeps lessons out of archives/search.
                    'publicly_queryable' => true,
                    'exclude_from_search' => true,
                    'show_ui' => true,
                    'show_in_rest' => true,
                    'supports' => ['title', 'editor', 'thumbnail', 'revisions'],
                    'rewrite' => false,
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
            self::META_VIDEO_URL,
            [
                'type' => 'string',
                'single' => true,
                'show_in_rest' => true,
                'default' => '',
                'sanitize_callback' => 'esc_url_raw',
                'auth_callback' => static function ($allowed, $meta_key, $post_id) {
                    return current_user_can('edit_post', (int) $post_id);
                },
            ]
        );

        register_post_meta(
            self::POST_TYPE,
            self::META_AVAILABLE_AT,
            [
                'type' => 'string',
                'single' => true,
                'show_in_rest' => true,
                'default' => '',
                'sanitize_callback' => static function ($value) {
                    $value = is_string($value) ? trim($value) : '';
                    return $value;
                },
                'auth_callback' => static function ($allowed, $meta_key, $post_id) {
                    return current_user_can('edit_post', (int) $post_id);
                },
            ]
        );
    }
}
