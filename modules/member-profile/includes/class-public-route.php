<?php
/**
 * Public profile route for pure WordPress (no BuddyBoss/BuddyPress).
 *
 * URL:
 *   /profile/{username}
 *
 * Query vars:
 *   - pl_profile_username
 *   - pl_profile_user_id (set later by the template loader)
 */

if (!defined('ABSPATH')) {
    exit;
}

class PL_Member_Profile_Public_Route
{
    public const USERNAME_VAR = 'pl_profile_username';
    public const USER_ID_VAR = 'pl_profile_user_id';

    public function __construct()
    {
        add_action('init', [$this, 'add_rewrite_rules']);
        add_filter('query_vars', [$this, 'add_query_vars']);
    }

    public function add_rewrite_rules(): void
    {
        add_rewrite_rule(
            '^profile/([^/]+)/?$',
            'index.php?' . self::USERNAME_VAR . '=$matches[1]',
            'top'
        );
    }

    /**
     * @param array<int, string> $vars
     * @return array<int, string>
     */
    public function add_query_vars(array $vars): array
    {
        $vars[] = self::USERNAME_VAR;
        $vars[] = self::USER_ID_VAR;
        return $vars;
    }

    /**
     * Used from activation hooks to ensure rewrite rules are present before flushing.
     */
    public static function register_rewrites_for_flush(): void
    {
        add_rewrite_rule(
            '^profile/([^/]+)/?$',
            'index.php?' . self::USERNAME_VAR . '=$matches[1]',
            'top'
        );
    }
}
