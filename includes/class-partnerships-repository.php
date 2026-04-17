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

    private static function maybe_unenroll_course_partner(int $partner_user_id, int $course_id): void
    {
        if ($partner_user_id <= 0 || $course_id <= 0) {
            return;
        }

        if (!class_exists('\\Learni\\Database\\Enrollments') || !method_exists('\\Learni\\Database\\Enrollments', 'unenroll_if_partner_invite')) {
            return;
        }

        try {
            \Learni\Database\Enrollments::unenroll_if_partner_invite($partner_user_id, $course_id);
        } catch (\Throwable $e) {
            // ignore
        }
    }

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
     * Fetch active partnerships for an object filtered by role.
     *
     * @param string $object_type
     * @param int    $object_id
     * @param string $role
     * @return array<int,array<string,mixed>>
     */
    public static function get_object_partners_by_role($object_type, $object_id, $role): array
    {
        global $wpdb;
        if (!$wpdb) {
            return [];
        }

        $object_type = is_string($object_type) ? trim($object_type) : '';
        $role = is_string($role) ? trim($role) : '';
        $object_id = (int) $object_id;

        if ($object_type === '' || $role === '' || $object_id <= 0) {
            return [];
        }

        $table = $wpdb->prefix . self::TABLE_SLUG;

        $sql = $wpdb->prepare(
            "SELECT *
            FROM {$table}
            WHERE object_type = %s
              AND object_id = %d
              AND role = %s
              AND status = %s",
            $object_type,
            $object_id,
            $role,
            'active'
        );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results($sql, ARRAY_A);
        return is_array($rows) ? $rows : [];
    }

    public static function get_single_partner($object_type, $object_id, $role = '')
    {
        $role = is_string($role) ? trim($role) : '';

        if ($role !== '' && method_exists(__CLASS__, 'get_object_partners_by_role')) {
            $partners = self::get_object_partners_by_role($object_type, $object_id, $role);
        } else {
            $partners = self::get_object_partners($object_type, $object_id);
        }
        return !empty($partners) ? $partners[0] : null;
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
     * @param int    $owner_user_id Owner user ID (used for auditing/permissions).
     */
    public static function add_partner($object_type, $object_id, $user_id, $role = 'observer', $owner_user_id = 0): bool
    {
        global $wpdb;
        if (!$wpdb) {
            return false;
        }

        $object_type = is_string($object_type) ? trim($object_type) : '';
        $role = is_string($role) ? trim($role) : '';
        $object_id = (int) $object_id;
        $user_id = (int) $user_id;
        $owner_user_id = (int) $owner_user_id;

        if ($object_type === '' || $role === '' || $object_id <= 0 || $user_id <= 0) {
            return false;
        }

        $table = $wpdb->prefix . self::TABLE_SLUG;

        if ($object_type === 'course' && $role === 'partner') {
            $active_partner_ids = [];
            $sql = $wpdb->prepare(
                "SELECT partner_user_id
                 FROM {$table}
                 WHERE object_type = %s
                   AND object_id = %d
                   AND role = %s
                   AND status = %s
                   AND partner_user_id IS NOT NULL",
                $object_type,
                $object_id,
                $role,
                'active'
            );
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $cols = $wpdb->get_col($sql);
            foreach ((array) $cols as $pid) {
                $pid = (int) $pid;
                if ($pid > 0) {
                    $active_partner_ids[] = $pid;
                }
            }

            // Course "partner" is single-slot: revoke prior partner rows only.
            $revoked = $wpdb->update(
                $table,
                [
                    'status' => 'revoked',
                    'revoked_at' => current_time('mysql'),
                ],
                [
                    'object_type' => $object_type,
                    'object_id' => $object_id,
                    'role' => $role,
                    'status' => 'active',
                ],
                [
                    '%s',
                    '%s',
                ],
                [
                    '%s',
                    '%d',
                    '%s',
                    '%s',
                ]
            );

            // If we're replacing partners, the prior partner should lose access (unenroll) unless they own the course.
            if ($revoked !== false) {
                foreach ($active_partner_ids as $pid) {
                    if ($pid === $user_id) {
                        continue;
                    }
                    self::maybe_unenroll_course_partner($pid, $object_id);
                }
            }
        }

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
            $update = [
                'status' => 'active',
                'revoked_at' => null,
                'updated_at' => $updated_at,
            ];
            $update_format = [
                '%s',
                '%s',
                '%s',
            ];
            if ($owner_user_id > 0) {
                $update['owner_user_id'] = $owner_user_id;
                $update_format[] = '%d';
            }

            $updated = $wpdb->update(
                $table,
                $update,
                [
                    'id' => (int) $existing['id'],
                ],
                $update_format,
                [
                    '%d',
                ]
            );

            return $updated !== false;
        }

        $now = current_time('mysql');
        $data = [
            'object_type' => $object_type,
            'object_id' => $object_id,
            'partner_user_id' => $user_id,
            'role' => $role,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $formats = [
            '%s',
            '%d',
            '%d',
            '%s',
            '%s',
            '%s',
            '%s',
        ];
        if ($owner_user_id > 0) {
            $data['owner_user_id'] = $owner_user_id;
            $formats[] = '%d';
        }

        $inserted = $wpdb->insert($table, $data, $formats);

        if ($inserted === 1) {
            return true;
        }

        return false;
    }

    /**
     * Revoke partner relationships for an object.
     *
     * When $partner_user_id is 0, revokes all active rows for the role/object.
     */
    public static function revoke_partner($object_type, $object_id, $partner_user_id = 0, $role = 'partner'): bool
    {
        global $wpdb;
        if (!$wpdb) {
            return false;
        }

        $object_type = is_string($object_type) ? trim($object_type) : '';
        $role = is_string($role) ? trim($role) : '';
        $object_id = (int) $object_id;
        $partner_user_id = (int) $partner_user_id;

        if ($object_type === '' || $role === '' || $object_id <= 0) {
            return false;
        }

        $table = $wpdb->prefix . self::TABLE_SLUG;
        $now = current_time('mysql');

        $active_partner_ids = [];
        if ($object_type === 'course' && $role === 'partner') {
            $sql = $wpdb->prepare(
                "SELECT partner_user_id
                 FROM {$table}
                 WHERE object_type = %s
                   AND object_id = %d
                   AND role = %s
                   AND status = %s",
                $object_type,
                $object_id,
                $role,
                'active'
            );
            if ($partner_user_id > 0) {
                $sql = $wpdb->prepare(
                    "SELECT partner_user_id
                     FROM {$table}
                     WHERE object_type = %s
                       AND object_id = %d
                       AND role = %s
                       AND status = %s
                       AND partner_user_id = %d",
                    $object_type,
                    $object_id,
                    $role,
                    'active',
                    $partner_user_id
                );
            }
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $cols = $wpdb->get_col($sql);
            foreach ((array) $cols as $pid) {
                $pid = (int) $pid;
                if ($pid > 0) {
                    $active_partner_ids[] = $pid;
                }
            }
        }

        $where = [
            'object_type' => $object_type,
            'object_id' => $object_id,
            'role' => $role,
            'status' => 'active',
        ];
        $where_format = ['%s', '%d', '%s', '%s'];

        if ($partner_user_id > 0) {
            $where['partner_user_id'] = $partner_user_id;
            $where_format[] = '%d';
        }

        $result = $wpdb->update(
            $table,
            [
                'status' => 'revoked',
                'revoked_at' => $now,
                'updated_at' => $now,
            ],
            $where,
            ['%s', '%s', '%s'],
            $where_format
        );

        if ($result !== false && $result > 0) {
            foreach ($active_partner_ids as $pid) {
                self::maybe_unenroll_course_partner($pid, $object_id);
            }
        }

        return $result !== false;
    }
}
