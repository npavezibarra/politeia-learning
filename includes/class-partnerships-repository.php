<?php
/**
 * Data access layer for wp_politeia_user_object_partnerships.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PL_Partnerships_Repository
{
    private const TABLE_SLUG = 'politeia_user_object_partnerships';

    /**
     * Fetch active partnerships for a given object.
     *
     * @param string $object_type Supported examples: course, group, program, reading_plan.
     * @param int    $object_id   Object identifier for the given type.
     *
     * @return array<int,array<string,mixed>> Rows as associative arrays.
     */
    public static function get_object_partners($object_type, $object_id): array
    {
        global $wpdb;
        if (!$wpdb) {
            return [];
        }

        $object_type = is_string($object_type) ? trim($object_type) : '';
        $object_id = (int) $object_id;

        if ($object_type === '' || $object_id <= 0) {
            return [];
        }

        $table = $wpdb->prefix . self::TABLE_SLUG;

        $sql = $wpdb->prepare(
            "SELECT *
            FROM {$table}
            WHERE object_type = %s
              AND object_id = %d
              AND status = %s",
            $object_type,
            $object_id,
            'active'
        );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results($sql, ARRAY_A);
        return is_array($rows) ? $rows : [];
    }

    /**
     * Add (or reactivate) an active partner relationship for an object.
     *
     * Returns true when a row is inserted or re-activated; false when an active
     * relationship already exists or when inputs are invalid.
     *
     * @param string $object_type Supported examples: course, group, program, reading_plan.
     * @param int    $object_id   Object identifier for the given type.
     * @param int    $user_id     Partner user ID.
     * @param string $role        Relationship role. Default observer.
     */
    public static function add_partner($object_type, $object_id, $user_id, $role = 'observer'): bool
    {
        global $wpdb;
        if (!$wpdb) {
            return false;
        }

        $object_type = is_string($object_type) ? trim($object_type) : '';
        $role = is_string($role) ? trim($role) : '';
        $object_id = (int) $object_id;
        $user_id = (int) $user_id;

        if ($object_type === '' || $role === '' || $object_id <= 0 || $user_id <= 0) {
            return false;
        }

        $table = $wpdb->prefix . self::TABLE_SLUG;

        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, status
                FROM {$table}
                WHERE object_type = %s
                  AND object_id = %d
                  AND partner_user_id = %d
                  AND role = %s
                LIMIT 1",
                $object_type,
                $object_id,
                $user_id,
                $role
            ),
            ARRAY_A
        );

        if (is_array($existing) && !empty($existing['id'])) {
            if (($existing['status'] ?? '') === 'active') {
                return false;
            }

            $updated_at = current_time('mysql');
            $updated = $wpdb->update(
                $table,
                [
                    'status' => 'active',
                    'revoked_at' => null,
                    'updated_at' => $updated_at,
                ],
                [
                    'id' => (int) $existing['id'],
                ],
                [
                    '%s',
                    '%s',
                    '%s',
                ],
                [
                    '%d',
                ]
            );

            return $updated !== false;
        }

        $now = current_time('mysql');
        $inserted = $wpdb->insert(
            $table,
            [
                'object_type' => $object_type,
                'object_id' => $object_id,
                'partner_user_id' => $user_id,
                'role' => $role,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                '%s',
                '%d',
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
            ]
        );

        if ($inserted === 1) {
            return true;
        }

        return false;
    }
}
