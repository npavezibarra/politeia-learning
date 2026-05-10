<?php

if (!defined('ABSPATH')) {
    exit;
}

class PL_BP_Blog_Archive_Template
{
    private const QUERY_VAR = 'pl_bp_blog_archive';
    private const FLUSH_OPTION = 'pl_bp_blog_archive_flush_rewrite_v1';

    public function __construct()
    {
        add_action('init', [$this, 'register_rules']);
        add_filter('query_vars', [$this, 'register_query_vars']);
        add_filter('template_include', [$this, 'load_archive_template'], 20);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_filter('body_class', [$this, 'add_body_class']);
    }

    public function register_rules(): void
    {
        add_rewrite_rule('^blog/?$', 'index.php?' . self::QUERY_VAR . '=1', 'top');

        // Flush only once, similar to other custom routes in the codebase.
        if (!get_option(self::FLUSH_OPTION)) {
            flush_rewrite_rules(false);
            add_option(self::FLUSH_OPTION, '1', '', false);
        }
    }

    public function register_query_vars(array $vars): array
    {
        $vars[] = self::QUERY_VAR;
        return $vars;
    }

    public function load_archive_template(string $template): string
    {
        if (!get_query_var(self::QUERY_VAR) || is_admin()) {
            return $template;
        }

        $custom = PL_BP_PATH . 'templates/archive-blog.php';
        if (file_exists($custom)) {
            return $custom;
        }

        return $template;
    }

    public function enqueue_assets(): void
    {
        if (!get_query_var(self::QUERY_VAR)) {
            return;
        }

        wp_enqueue_style(
            'pl-bp-blog-archive',
            PL_BP_URL . 'assets/css/blog-archive.css',
            [],
            '1.0.0'
        );

        wp_enqueue_script(
            'pl-bp-blog-archive',
            PL_BP_URL . 'assets/js/blog-archive.js',
            [],
            '1.0.0',
            true
        );

        wp_enqueue_style(
            'pl-bp-blog-archive-fonts',
            'https://fonts.googleapis.com/css2?family=Newsreader:opsz,wght@6..72,200;6..72,300;6..72,400&family=Poppins:wght@400;600;700;800&display=swap',
            [],
            null
        );
    }

    public function add_body_class(array $classes): array
    {
        if (get_query_var(self::QUERY_VAR)) {
            $classes[] = 'pl-bp-blog-archive-body';
        }
        return $classes;
    }
}

