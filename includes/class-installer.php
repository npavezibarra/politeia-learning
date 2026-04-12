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
    private const USER_PROFILE_META_TABLE = 'politeia_user_profile_meta';
    private const PORTFOLIO_SETTINGS_TABLE = 'politeia_portfolio_settings';
    private const PARTNERSHIPS_TABLE = 'politeia_user_object_partnerships';
    private const RELATIONSHIPS_TABLE = 'politeia_user_relationships';
    private const EMAIL_LOGS_TABLE = 'politeia_email_logs';
    private static bool $has_run = false;

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
     * Migrate legacy email log table name: wp_pl_email_logs -> wp_politeia_email_logs.
     */
    private static function migrate_email_logs_table(): void
    {
        global $wpdb;
        if (!$wpdb) {
            return;
        }

        $old = $wpdb->prefix . 'pl_email_logs';
        $new = $wpdb->prefix . self::EMAIL_LOGS_TABLE;

        $old_exists = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $old)) === $old);
        $new_exists = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $new)) === $new);

        if ($old_exists && !$new_exists) {
            $old_escaped = str_replace('`', '``', $old);
            $new_escaped = str_replace('`', '``', $new);
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $wpdb->query("RENAME TABLE `{$old_escaped}` TO `{$new_escaped}`");
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
        $user_profile_meta_table = $wpdb->prefix . self::USER_PROFILE_META_TABLE;
        $portfolio_settings_table = $wpdb->prefix . self::PORTFOLIO_SETTINGS_TABLE;
        $partnerships_table = $wpdb->prefix . self::PARTNERSHIPS_TABLE;
        $relationships_table = $wpdb->prefix . self::RELATIONSHIPS_TABLE;
        $email_logs_table = $wpdb->prefix . self::EMAIL_LOGS_TABLE;

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
            $user_profile_meta_table => sprintf(
                "CREATE TABLE %s (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    user_id BIGINT UNSIGNED NOT NULL,
                    context VARCHAR(64) NOT NULL DEFAULT 'default',
                    meta_key VARCHAR(191) NOT NULL,
                    value_type VARCHAR(16) NOT NULL DEFAULT 'string',
                    value_string LONGTEXT NULL,
                    value_json LONGTEXT NULL,
                    value_int BIGINT NULL,
                    value_float DECIMAL(20,6) NULL,
                    value_bool TINYINT(1) NULL,
                    value_date DATE NULL,
                    value_datetime DATETIME NULL,
                    value_hash CHAR(64) NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY  (id),
                    UNIQUE KEY user_ctx_key (user_id, context, meta_key),
                    KEY user_ctx (user_id, context),
                    KEY ctx_key_user (context, meta_key, user_id),
                    KEY ctx_key_hash (context, meta_key, value_hash),
                    KEY updated_at (updated_at)
                ) %s;",
                $user_profile_meta_table,
                $charset_collate
            ),
            $portfolio_settings_table => sprintf(
                "CREATE TABLE %s (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    user_id BIGINT UNSIGNED NOT NULL,
                    section_id VARCHAR(50) NOT NULL,
                    is_private TINYINT(1) NOT NULL DEFAULT 0,
                    visibility_mode VARCHAR(20) NOT NULL DEFAULT 'all',
                    selected_ids LONGTEXT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY  (id),
                    UNIQUE KEY user_section (user_id, section_id)
                ) %s;",
                $portfolio_settings_table,
                $charset_collate
            ),
            $partnerships_table => sprintf(
                "CREATE TABLE %s (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    object_type VARCHAR(32) NOT NULL,
                    object_id BIGINT UNSIGNED NOT NULL,
                    owner_user_id BIGINT UNSIGNED NULL,
                    partner_user_id BIGINT UNSIGNED NULL,
                    invitee_email VARCHAR(191) NULL,
                    role VARCHAR(50) NOT NULL DEFAULT 'observer',
                    status VARCHAR(20) NOT NULL DEFAULT 'active',
                    invitation_token_hash CHAR(64) NULL,
                    invited_at DATETIME NULL,
                    expires_at DATETIME NULL,
                    accepted_at DATETIME NULL,
                    declined_at DATETIME NULL,
                    revoked_at DATETIME NULL,
                    notification_preferences LONGTEXT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY  (id),
                    UNIQUE KEY uq_invitation_token_hash (invitation_token_hash),
                    UNIQUE KEY uq_object_partner_role (object_type, object_id, partner_user_id, role),
                    UNIQUE KEY uq_object_invitee_role (object_type, object_id, invitee_email, role),
                    KEY idx_object (object_type, object_id),
                    KEY idx_owner_user (owner_user_id),
                    KEY idx_partner_user (partner_user_id),
                    KEY idx_invitee_email (invitee_email),
                    KEY idx_status (status),
                    KEY idx_object_status (object_type, object_id, status)
                ) %s;",
                $partnerships_table,
                $charset_collate
            ),
            $relationships_table => sprintf(
                "CREATE TABLE %s (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    from_user_id BIGINT UNSIGNED NOT NULL,
                    to_user_id BIGINT UNSIGNED NOT NULL,
                    rel_type VARCHAR(20) NOT NULL,
                    status VARCHAR(20) NOT NULL DEFAULT 'pending',
                    expires_at DATETIME NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY  (id),
                    UNIQUE KEY uq_from_to_type (from_user_id, to_user_id, rel_type),
                    KEY idx_to_status_type (to_user_id, status, rel_type),
                    KEY idx_from_type_status (from_user_id, rel_type, status),
                    KEY idx_expires_at (expires_at)
                ) %s;",
                $relationships_table,
                $charset_collate
            ),
            $email_logs_table => sprintf(
                "CREATE TABLE %s (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    recipient LONGTEXT NOT NULL,
                    subject LONGTEXT NOT NULL,
                    content LONGTEXT NOT NULL,
                    headers LONGTEXT NULL,
                    email_type VARCHAR(50) NOT NULL DEFAULT 'general',
                    template VARCHAR(100) NOT NULL DEFAULT '',
                    file_path VARCHAR(255) NOT NULL DEFAULT '',
                    sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY  (id),
                    KEY sent_at (sent_at),
                    KEY email_type (email_type)
                ) %s;",
                $email_logs_table,
                $charset_collate
            ),
        ];
    }

    /**
     * Install or update the plugin schema.
     */
    public static function install(): void
    {
        if (self::$has_run) {
            return;
        }

        self::$has_run = true;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Step 4: Run legacy migrations only once.
        if (!get_option('pl_roles_table_migrated')) {
            self::migrate_roles_table();
            update_option('pl_roles_table_migrated', 1);
        }

        if (!get_option('politeia_email_logs_table_migrated')) {
            self::migrate_email_logs_table();
            update_option('politeia_email_logs_table_migrated', 1);
        }

        $full_sql = implode("\n", self::get_schema_sql());
        dbDelta($full_sql);
    }
}
