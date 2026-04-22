<?php

namespace Learni\Auth;

use Learni\Auth\Utilities\AuthUtils;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Custom Reset Password page handling.
 * 
 * Route: /restablecer-contrasena/
 */
final class PasswordPage
{
    private const QUERY_VAR = 'pl_auth_reset_password';
    private const SLUG = 'restablecer-contrasena';
    private const FLUSH_OPTION = 'pl_auth_reset_password_rewrite_v2';

    public static function init(): void
    {
        add_action('init', [__CLASS__, 'add_rewrite_rules']);
        add_filter('query_vars', [__CLASS__, 'add_query_vars']);
        add_filter('template_include', [__CLASS__, 'template_include'], 20);
        add_action('admin_init', [__CLASS__, 'maybe_flush_rewrites']);
    }

    public static function add_rewrite_rules(): void
    {
        add_rewrite_rule(
            '^' . self::SLUG . '/?$',
            'index.php?' . self::QUERY_VAR . '=1',
            'top'
        );
    }

    public static function add_query_vars(array $vars): array
    {
        $vars[] = self::QUERY_VAR;
        return $vars;
    }

    public static function template_include(string $template): string
    {
        if (!get_query_var(self::QUERY_VAR)) {
            return $template;
        }

        global $wp_query;
        if ($wp_query instanceof \WP_Query) {
            $wp_query->is_404 = false;
            $wp_query->is_page = true;
        }

        status_header(200);
        nocache_headers();

        $file = PL_AUTH_PATH . 'templates/auth/reset-password-page.php';
        return file_exists($file) ? $file : $template;
    }

    public static function maybe_flush_rewrites(): void
    {
        if (get_option(self::FLUSH_OPTION)) {
            return;
        }

        flush_rewrite_rules(false);
        update_option(self::FLUSH_OPTION, 1, true);
    }
}
