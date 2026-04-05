<?php

if (!defined('ABSPATH')) {
    exit;
}

class PL_BP_Blog_Post_Template
{
    public function __construct()
    {
        add_filter('template_include', [$this, 'load_blog_post_template'], 20);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_filter('body_class', [$this, 'add_body_class']);
    }

    public function load_blog_post_template(string $template): string
    {
        if (!is_singular('post') || is_admin()) {
            return $template;
        }

        $custom = PL_BP_PATH . 'templates/single-post.php';
        if (file_exists($custom)) {
            return $custom;
        }

        return $template;
    }

    public function enqueue_assets(): void
    {
        if (!is_singular('post')) {
            return;
        }

        wp_enqueue_style(
            'pl-bp-blog-post',
            PL_BP_URL . 'assets/css/blog-post.css',
            [],
            '1.0.0'
        );

        wp_enqueue_style(
            'pl-bp-blog-post-fonts',
            'https://fonts.googleapis.com/css2?family=EB+Garamond:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700;900&display=swap',
            [],
            null
        );
    }

    public function add_body_class(array $classes): array
    {
        if (is_singular('post')) {
            $classes[] = 'pl-bp-blog-post-body';
        }

        return $classes;
    }
}

