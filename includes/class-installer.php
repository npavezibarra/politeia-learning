<?php
/**
 * Handles database schema installation and updates for Politeia Learning.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PL_Installer
{
    private const SNAPSHOTS_TABLE = 'politeia_inclusion_snapshots';
    private const APPROVALS_TABLE = 'politeia_inclusion_approvals';

    /**
     * Attempt to migrate legacy schema changes that dbDelta won't handle (like column renames).
     */
    private static function migrate_roles_table(): void
    {
        global $wpdb;
        if (!$wpdb) {
            return;
        }

        $table = $wpdb->prefix . 'politeia_course_roles';

        // Table existence check.
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($exists !== $table) {
            return;
        }

        $has_course_id = (bool) $wpdb->get_var("SHOW COLUMNS FROM {$table} LIKE 'course_id'");
        $has_object_id = (bool) $wpdb->get_var("SHOW COLUMNS FROM {$table} LIKE 'object_id'");
        $has_object_type = (bool) $wpdb->get_var("SHOW COLUMNS FROM {$table} LIKE 'object_type'");

        // Rename course_id -> object_id (clean schema).
        if ($has_course_id && !$has_object_id) {
            $wpdb->query("ALTER TABLE {$table} CHANGE course_id object_id BIGINT UNSIGNED NOT NULL");
        }

        // Add object_type if missing.
        if (!$has_object_type) {
            // Default existing rows to 'course'.
            $wpdb->query("ALTER TABLE {$table} ADD object_type VARCHAR(20) NOT NULL DEFAULT 'course' AFTER object_id");
            $wpdb->query($wpdb->prepare("UPDATE {$table} SET object_type = %s WHERE object_type = '' OR object_type IS NULL", 'course'));
        }

        // Ensure indexes exist (dbDelta should handle these, but we also guard here).
        $has_course_key = (bool) $wpdb->get_var("SHOW INDEX FROM {$table} WHERE Key_name = 'course_id'");
        if ($has_course_key) {
            $wpdb->query("ALTER TABLE {$table} DROP INDEX course_id");
        }

        $has_object_key = (bool) $wpdb->get_var("SHOW INDEX FROM {$table} WHERE Key_name = 'object_id'");
        if (!$has_object_key) {
            $wpdb->query("ALTER TABLE {$table} ADD KEY object_id (object_id)");
        }

        $has_object_type_id_key = (bool) $wpdb->get_var("SHOW INDEX FROM {$table} WHERE Key_name = 'object_type_id'");
        if (!$has_object_type_id_key) {
            $wpdb->query("ALTER TABLE {$table} ADD KEY object_type_id (object_type, object_id)");
        }
    }

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
        $snapshots_table = $wpdb->prefix . self::SNAPSHOTS_TABLE;
        $approvals_table = $wpdb->prefix . self::APPROVALS_TABLE;

        return [
            $roles_table => sprintf(
                "CREATE TABLE %s (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    object_id BIGINT UNSIGNED NOT NULL,
                    object_type VARCHAR(20) NOT NULL DEFAULT 'course',
                    user_id BIGINT UNSIGNED NOT NULL,
                    role_slug VARCHAR(50) NOT NULL,
                    role_description TEXT NULL,
                    profit_percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY  (id),
                    KEY object_id (object_id),
                    KEY object_type_id (object_type, object_id),
                    KEY user_id (user_id)
                ) %s;",
                $roles_table,
                $charset_collate
            ),
            $snapshots_table => sprintf(
                "CREATE TABLE %s (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    container_type VARCHAR(20) NOT NULL,
                    container_id BIGINT UNSIGNED NOT NULL,
                    status VARCHAR(20) NOT NULL DEFAULT 'draft',
                    created_by BIGINT UNSIGNED NOT NULL,
                    snapshot_hash CHAR(64) NOT NULL,
                    payload LONGTEXT NOT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY  (id),
                    KEY container_lookup (container_type, container_id),
                    KEY status_lookup (status),
                    KEY created_by (created_by)
                ) %s;",
                $snapshots_table,
                $charset_collate
            ),
            $approvals_table => sprintf(
                "CREATE TABLE %s (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    snapshot_id BIGINT UNSIGNED NOT NULL,
                    approver_user_id BIGINT UNSIGNED NOT NULL,
                    status VARCHAR(20) NOT NULL DEFAULT 'pending',
                    role_slug VARCHAR(100) NOT NULL,
                    role_description TEXT NULL,
                    profit_percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00,
                    decision_note TEXT NULL,
                    decided_at DATETIME NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY  (id),
                    UNIQUE KEY snapshot_approver (snapshot_id, approver_user_id),
                    KEY approver_status (approver_user_id, status),
                    KEY snapshot_status (snapshot_id, status)
                ) %s;",
                $approvals_table,
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

        // Handle legacy migrations before dbDelta.
        self::migrate_roles_table();

        foreach (self::get_schema_sql() as $table => $sql) {
            dbDelta($sql);
        }
    }
}
