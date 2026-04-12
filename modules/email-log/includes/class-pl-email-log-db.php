<?php
/**
 * Database operations for Email Log.
 */

if (!defined('ABSPATH')) {
    exit;
}

final class PL_Email_Log_DB
{
    private static $instance = null;
    private $table_name;
    private $legacy_table_name;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'politeia_email_logs';
        $this->legacy_table_name = $wpdb->prefix . 'pl_email_logs';

        $this->maybe_migrate_legacy_table();
    }

    private function maybe_migrate_legacy_table(): void
    {
        global $wpdb;
        if (!$wpdb) {
            return;
        }

        $old = (string) $this->legacy_table_name;
        $new = (string) $this->table_name;

        if ($old === '' || $new === '' || $old === $new) {
            return;
        }

        $old_exists = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $old)) === $old);
        $new_exists = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $new)) === $new);

        if ($old_exists && !$new_exists) {
            $old_escaped = str_replace('`', '``', $old);
            $new_escaped = str_replace('`', '``', $new);
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $wpdb->query("RENAME TABLE `{$old_escaped}` TO `{$new_escaped}`");
        }
    }

    public function table_exists(): bool
    {
        global $wpdb;
        if (!$wpdb) {
            return false;
        }

        return ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $this->table_name)) === $this->table_name);
    }

    /**
     * Create the table if it doesn't exist.
     */
    public function create_table()
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $this->table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            recipient text NOT NULL,
            subject text NOT NULL,
            content longtext NOT NULL,
            headers text DEFAULT '',
            email_type varchar(50) DEFAULT 'general' NOT NULL,
            template varchar(100) DEFAULT '' NOT NULL,
            file_path varchar(255) DEFAULT '' NOT NULL,
            sent_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY sent_at (sent_at),
            KEY email_type (email_type)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Insert a new log entry.
     *
     * @param array<string,mixed> $data
     */
    public function insert_log($data)
    {
        global $wpdb;
        return $wpdb->insert($this->table_name, $data);
    }

    /**
     * Get logs with pagination and filters.
     */
    public function get_logs($limit = 20, $offset = 0, $search = '')
    {
        global $wpdb;

        $query = "SELECT * FROM $this->table_name";
        $where = [];
        $params = [];

        if (!empty($search)) {
            $where[] = "(recipient LIKE %s OR subject LIKE %s OR content LIKE %s)";
            $params[] = '%' . $wpdb->esc_like($search) . '%';
            $params[] = '%' . $wpdb->esc_like($search) . '%';
            $params[] = '%' . $wpdb->esc_like($search) . '%';
        }

        if (!empty($where)) {
            $query .= " WHERE " . implode(" AND ", $where);
        }

        $query .= " ORDER BY sent_at DESC LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;

        return $wpdb->get_results($wpdb->prepare($query, $params));
    }

    /**
     * Get total count of logs.
     */
    public function get_total_count($search = '')
    {
        global $wpdb;
        $query = "SELECT COUNT(*) FROM $this->table_name";

        if (!empty($search)) {
            $query .= $wpdb->prepare(
                " WHERE (recipient LIKE %s OR subject LIKE %s OR content LIKE %s)",
                '%' . $wpdb->esc_like($search) . '%',
                '%' . $wpdb->esc_like($search) . '%',
                '%' . $wpdb->esc_like($search) . '%'
            );
        }

        return (int) $wpdb->get_var($query);
    }

    /**
     * Get a single log by ID.
     */
    public function get_log($id)
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $this->table_name WHERE id = %d", $id));
    }

    /**
     * Delete old logs (Cleanup).
     */
    public function delete_old_logs($days = 30)
    {
        global $wpdb;
        return $wpdb->query($wpdb->prepare(
            "DELETE FROM $this->table_name WHERE sent_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
    }
}
