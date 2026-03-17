<?php
/**
 * Handles automatic database upgrades for Politeia Learning.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PL_Upgrader
{
    const DB_VERSION_OPTION = 'politeia_learning_db_version';

    /**
     * Check if a database upgrade is needed and run it.
     */
    public static function maybe_upgrade()
    {
        $stored_version = get_option(self::DB_VERSION_OPTION, '0.0.0');

        if (version_compare($stored_version, PL_DB_VERSION, '<')) {
            PL_Installer::install();
            update_option(self::DB_VERSION_OPTION, PL_DB_VERSION);
        }

        if (defined('PL_RUN_PARTNERSHIP_MIGRATION') && PL_RUN_PARTNERSHIP_MIGRATION) {
            self::upgrade_create_partnership_table();
            self::upgrade_migrate_course_roles();
            self::upgrade_migrate_plan_participants();
        }
    }

    private static function upgrade_create_partnership_table(): void
    {
        PL_Installer::install();
    }

    private static function upgrade_migrate_course_roles(): void
    {
        if (get_option('pl_partnership_migration_course_roles_v1')) {
            return;
        }

        global $wpdb;
        if (!$wpdb) {
            return;
        }

        $target = $wpdb->prefix . 'politeia_user_object_partnerships';
        $source = $wpdb->prefix . 'politeia_course_roles';

        $source_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $source));
        $target_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $target));
        if ($source_exists !== $source || $target_exists !== $target) {
            return;
        }

        $sql = "
            INSERT INTO {$target}
                (object_type, object_id, owner_user_id, partner_user_id, role, status, created_at, updated_at)
            SELECT
                cr.object_type,
                cr.object_id,
                owners.owner_user_id,
                cr.user_id AS partner_user_id,
                cr.role_slug AS role,
                'active' AS status,
                cr.created_at,
                cr.updated_at
            FROM {$source} cr
            LEFT JOIN (
                SELECT
                    object_type,
                    object_id,
                    MAX(CASE WHEN role_slug = 'Autor principal' THEN user_id ELSE NULL END) AS owner_user_id
                FROM {$source}
                GROUP BY object_type, object_id
            ) owners
                ON owners.object_type = cr.object_type AND owners.object_id = cr.object_id
            ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                owner_user_id = COALESCE({$target}.owner_user_id, VALUES(owner_user_id)),
                updated_at = GREATEST({$target}.updated_at, VALUES(updated_at))
        ";

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $wpdb->query($sql);
        update_option('pl_partnership_migration_course_roles_v1', 1);
    }

    private static function upgrade_migrate_plan_participants(): void
    {
        if (get_option('pl_partnership_migration_plan_participants_v1')) {
            return;
        }

        global $wpdb;
        if (!$wpdb) {
            return;
        }

        $target = $wpdb->prefix . 'politeia_user_object_partnerships';
        $participants = $wpdb->prefix . 'politeia_plan_participants';
        $plans = $wpdb->prefix . 'politeia_plans';

        $participants_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $participants));
        $plans_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $plans));
        $target_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $target));
        if ($participants_exists !== $participants || $plans_exists !== $plans || $target_exists !== $target) {
            return;
        }

        $sql = "
            INSERT INTO {$target}
                (object_type, object_id, owner_user_id, partner_user_id, role, status, notification_preferences, created_at, updated_at, revoked_at)
            SELECT
                'reading_plan' AS object_type,
                pp.plan_id AS object_id,
                p.user_id AS owner_user_id,
                pp.user_id AS partner_user_id,
                pp.role AS role,
                CASE WHEN pp.revoked_at IS NULL THEN 'active' ELSE 'revoked' END AS status,
                JSON_OBJECT('notify_on', pp.notify_on) AS notification_preferences,
                pp.added_at AS created_at,
                COALESCE(pp.revoked_at, pp.added_at) AS updated_at,
                pp.revoked_at
            FROM {$participants} pp
            INNER JOIN {$plans} p
                ON p.id = pp.plan_id
            ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                notification_preferences = COALESCE(VALUES(notification_preferences), {$target}.notification_preferences),
                revoked_at = COALESCE(VALUES(revoked_at), {$target}.revoked_at),
                updated_at = GREATEST({$target}.updated_at, VALUES(updated_at))
        ";

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $wpdb->query($sql);
        update_option('pl_partnership_migration_plan_participants_v1', 1);
    }
}
