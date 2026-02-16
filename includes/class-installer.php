<?php
/**
 * Handles database schema installation and updates for Politeia Learning.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PL_Installer
{
    /**
     * Return the schema definition for all plugin tables.
     *
     * @return array<string,string>
     */
    public static function get_schema_sql(): array
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $roles_table = $wpdb->prefix . 'politeia_course_roles';

        return [
            $roles_table => sprintf(
                "CREATE TABLE %s (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    course_id BIGINT UNSIGNED NOT NULL,
                    user_id BIGINT UNSIGNED NOT NULL,
                    role_slug VARCHAR(50) NOT NULL,
                    role_description TEXT NULL,
                    profit_percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY  (id),
                    KEY course_id (course_id),
                    KEY user_id (user_id)
                ) %s;",
                $roles_table,
                $charset_collate
            ),
        ];
    }

    /**
     * Install or update the plugin schema.
     */
    public static function install(): void
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        foreach (self::get_schema_sql() as $table => $sql) {
            dbDelta($sql);
        }
    }
}
