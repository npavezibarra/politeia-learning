<?php

namespace Learni\Auth\Utilities;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Utility functions for Authentication.
 */
class AuthUtils
{
    /**
     * Builds the URL for the auth modal.
     */
    public static function build_modal_url(string $view, string $redirect_to = '', array $args = []): string
    {
        $view = self::sanitize_view($view);
        $redirect_to = self::resolve_redirect_to($redirect_to);

        $query_args = array_merge([
            'pl_auth_view' => $view,
            'redirect_to' => $redirect_to,
        ], $args);

        return add_query_arg($query_args, home_url('/'));
    }

    /**
     * Resolves and validates the redirect_to URL.
     */
    public static function resolve_redirect_to(string $redirect_to): string
    {
        $redirect_to = trim($redirect_to);
        if ($redirect_to === '') {
            $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '/';
            $redirect_to = home_url($request_uri ?: '/');
        }

        return wp_validate_redirect($redirect_to, home_url('/'));
    }

    /**
     * Sanitizes the auth view name.
     */
    public static function sanitize_view(string $view): string
    {
        $view = sanitize_key($view);
        if (!in_array($view, ['login', 'register', 'forgot'], true)) {
            return 'login';
        }

        return $view;
    }

    /**
     * Generates a unique username from an email address.
     */
    public static function generate_username_from_email(string $email): string
    {
        $base = sanitize_user(strstr($email, '@', true) ?: $email, true);
        if ($base === '') {
            $base = 'user';
        }

        $username = $base;
        $i = 1;
        while (username_exists($username)) {
            $username = $base . $i;
            $i++;
        }

        return $username;
    }
}
