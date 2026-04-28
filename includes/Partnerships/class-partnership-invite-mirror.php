<?php
/**
 * Best-effort mirror of invite lifecycle into `wp_politeia_user_object_partnerships`.
 *
 * Scope (Phase 2):
 * - Reading Planner invites (object_type=reading_plan) mirror as `status=pending|accepted|declined|expired|revoked`.
 * - Does NOT change the source of truth yet (legacy invite tables remain authoritative).
 */

if (!defined('ABSPATH')) {
    exit;
}

class PL_Partnership_Invite_Mirror
{
    private const TABLE_SLUG = 'politeia_user_object_partnerships';

    /**
     * Mirror a pending invite to the unified partnerships table (best-effort).
     *
     * Multi-invite safe: only revokes/replaces pending rows for the same (object_type, object_id, invitee_email, role).
     */
    public static function mirror_pending_invite(string $object_type, int $object_id, string $email, string $role, string $token_hash, string $expires_at, int $owner_user_id): void
    {
        global $wpdb;
        if (!$wpdb) {
            return;
        }

        $object_type = sanitize_key($object_type);
        $object_id = (int) $object_id;
        $email = sanitize_email($email);
        $role = sanitize_key($role ?: 'observer');
        $token_hash = trim($token_hash);
        $expires_at = trim($expires_at);
        $owner_user_id = (int) $owner_user_id;

        if ($object_type === '' || $object_id <= 0 || !$email || !is_email($email) || $role === '' || $token_hash === '' || $expires_at === '') {
            return;
        }

        $table = $wpdb->prefix . self::TABLE_SLUG;
        $table_exists = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table);
        if (!$table_exists) {
            return;
        }

        $required_cols = [
            'object_type',
            'object_id',
            'owner_user_id',
            'partner_user_id',
            'invitee_email',
            'role',
            'status',
            'invitation_token_hash',
            'invited_at',
            'expires_at',
            'accepted_at',
            'declined_at',
            'revoked_at',
            'created_at',
            'updated_at',
        ];
        foreach ($required_cols as $col) {
            if (!PL_Partnership_Utils::invites_table_column_exists($table, $col)) {
                return;
            }
        }

        $now = current_time('mysql');

        // Revoke pending invite for this same object/email/role only.
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table}
                 SET status = %s, revoked_at = %s, updated_at = %s
                 WHERE object_type = %s
                   AND object_id = %d
                   AND invitee_email = %s
                   AND role = %s
                   AND status = %s",
                'revoked',
                $now,
                $now,
                $object_type,
                $object_id,
                $email,
                $role,
                'pending'
            )
        );

        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id
                 FROM {$table}
                 WHERE object_type = %s
                   AND object_id = %d
                   AND invitee_email = %s
                   AND role = %s
                 LIMIT 1",
                $object_type,
                $object_id,
                $email,
                $role
            ),
            ARRAY_A
        );

        if (is_array($existing) && !empty($existing['id'])) {
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table}
                     SET owner_user_id = %d,
                         partner_user_id = NULL,
                         status = %s,
                         invitation_token_hash = %s,
                         invited_at = %s,
                         expires_at = %s,
                         accepted_at = NULL,
                         declined_at = NULL,
                         revoked_at = NULL,
                         updated_at = %s
                     WHERE id = %d",
                    $owner_user_id,
                    'pending',
                    $token_hash,
                    $now,
                    $expires_at,
                    $now,
                    (int) $existing['id']
                )
            );
            return;
        }

        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$table}
                    (object_type, object_id, owner_user_id, partner_user_id, invitee_email, role, status, invitation_token_hash, invited_at, expires_at, accepted_at, declined_at, revoked_at, created_at, updated_at)
                 VALUES
                    (%s, %d, %d, NULL, %s, %s, %s, %s, %s, %s, NULL, NULL, NULL, %s, %s)",
                $object_type,
                $object_id,
                $owner_user_id,
                $email,
                $role,
                'pending',
                $token_hash,
                $now,
                $expires_at,
                $now,
                $now
            )
        );
    }

    /**
     * Mirror invite status transitions by token_hash (best-effort).
     *
     * @param string $timestamp_column One of: accepted_at, declined_at, revoked_at, updated_at.
     */
    public static function mirror_invite_status(string $token_hash, string $status, string $timestamp_column): void
    {
        global $wpdb;
        if (!$wpdb) {
            return;
        }

        $token_hash = trim($token_hash);
        $status = sanitize_key($status);
        $timestamp_column = trim($timestamp_column);

        if ($token_hash === '' || $status === '' || $timestamp_column === '') {
            return;
        }

        $table = $wpdb->prefix . self::TABLE_SLUG;
        $table_exists = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table);
        if (!$table_exists) {
            return;
        }

        $required = ['invitation_token_hash', 'status', 'updated_at'];
        foreach ($required as $col) {
            if (!PL_Partnership_Utils::invites_table_column_exists($table, $col)) {
                return;
            }
        }
        if ($timestamp_column !== 'updated_at' && !PL_Partnership_Utils::invites_table_column_exists($table, $timestamp_column)) {
            return;
        }

        $now = current_time('mysql');
        $data = [
            'status' => $status,
            'updated_at' => $now,
        ];
        $format = ['%s', '%s'];
        if ($timestamp_column !== 'updated_at') {
            $data[$timestamp_column] = $now;
            $format[] = '%s';
        }

        $wpdb->update(
            $table,
            $data,
            ['invitation_token_hash' => $token_hash],
            $format,
            ['%s']
        );
    }
}

