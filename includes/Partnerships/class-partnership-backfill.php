<?php
/**
 * Backfill helpers to populate unified partnerships from legacy tables.
 *
 * Phase goal:
 * - Make `wp_politeia_user_object_partnerships` sufficiently complete so we can
 *   enable `PL_READING_PLANNER_PARTNERSHIPS_AUTHORITATIVE` without breaking access.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PL_Partnership_Backfill
{
    /**
     * Backfill reading_plan observers from `wp_politeia_plan_participants` into unified partnerships.
     *
     * @return array<string,int> Stats.
     */
    public static function backfill_reading_plan_observers(int $batch_size = 500, int $max_batches = 0, bool $dry_run = false): array
    {
        global $wpdb;

        $stats = [
            'batches' => 0,
            'rows' => 0,
            'activated' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        if (!$wpdb) {
            $stats['errors']++;
            return $stats;
        }

        if (!class_exists('PL_Partnerships_Repository') || !method_exists('PL_Partnerships_Repository', 'add_partner')) {
            $stats['errors']++;
            return $stats;
        }

        $batch_size = max(1, min(2000, (int) $batch_size));
        $max_batches = max(0, (int) $max_batches);

        $participants_table = $wpdb->prefix . 'politeia_plan_participants';
        $plans_table = $wpdb->prefix . 'politeia_plans';

        $participants_exists = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $participants_table)) === $participants_table);
        $plans_exists = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $plans_table)) === $plans_table);
        if (!$participants_exists || !$plans_exists) {
            $stats['errors']++;
            return $stats;
        }

        $has_revoked_at = class_exists('PL_Partnership_Utils')
            && method_exists('PL_Partnership_Utils', 'invites_table_column_exists')
            && PL_Partnership_Utils::invites_table_column_exists($participants_table, 'revoked_at');

        $revoked_clause = $has_revoked_at ? 'AND pp.revoked_at IS NULL' : '';

        $offset = 0;
        $batch = 0;
        while (true) {
            if ($max_batches > 0 && $batch >= $max_batches) {
                break;
            }

            $sql = $wpdb->prepare(
                "SELECT
                    pp.plan_id AS plan_id,
                    pp.user_id AS user_id,
                    p.user_id AS owner_user_id
                 FROM {$participants_table} pp
                 INNER JOIN {$plans_table} p
                    ON p.id = pp.plan_id
                 WHERE pp.role = %s
                   {$revoked_clause}
                 ORDER BY pp.plan_id ASC, pp.user_id ASC
                 LIMIT %d OFFSET %d",
                'observer',
                $batch_size,
                $offset
            );

            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $rows = $wpdb->get_results($sql, ARRAY_A);
            if (!is_array($rows) || empty($rows)) {
                break;
            }

            $stats['batches']++;

            foreach ($rows as $row) {
                $stats['rows']++;
                $plan_id = (int) ($row['plan_id'] ?? 0);
                $user_id = (int) ($row['user_id'] ?? 0);
                $owner_user_id = (int) ($row['owner_user_id'] ?? 0);

                if ($plan_id <= 0 || $user_id <= 0) {
                    $stats['errors']++;
                    continue;
                }

                if ($dry_run) {
                    $stats['skipped']++;
                    continue;
                }

                try {
                    $ok = PL_Partnerships_Repository::add_partner('reading_plan', $plan_id, $user_id, 'observer', $owner_user_id);
                    if ($ok) {
                        $stats['activated']++;
                    } else {
                        $stats['skipped']++;
                    }
                } catch (\Throwable $e) {
                    $stats['errors']++;
                }
            }

            $offset += $batch_size;
            $batch++;
        }

        return $stats;
    }

    /**
     * Backfill pending reading_plan invites from legacy invites into unified partnerships.
     *
     * This helps migration when older pending invites predate the mirror pipeline.
     *
     * @return array<string,int> Stats.
     */
    public static function backfill_reading_plan_pending_invites(int $batch_size = 500, int $max_batches = 0, bool $dry_run = false): array
    {
        global $wpdb;

        $stats = [
            'batches' => 0,
            'rows' => 0,
            'upserted' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        if (!$wpdb) {
            $stats['errors']++;
            return $stats;
        }

        $batch_size = max(1, min(2000, (int) $batch_size));
        $max_batches = max(0, (int) $max_batches);

        $legacy_invites_table = $wpdb->prefix . 'politeia_plan_participant_invites';
        $partnerships_table = $wpdb->prefix . 'politeia_user_object_partnerships';

        $legacy_exists = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $legacy_invites_table)) === $legacy_invites_table);
        $partnerships_exists = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $partnerships_table)) === $partnerships_table);
        if (!$legacy_exists || !$partnerships_exists) {
            $stats['errors']++;
            return $stats;
        }

        // Ensure the unified table has invite columns.
        $required_cols = ['invitee_email', 'invitation_token_hash', 'invited_at', 'expires_at', 'accepted_at', 'declined_at', 'revoked_at', 'status'];
        foreach ($required_cols as $col) {
            if (!class_exists('PL_Partnership_Utils') || !PL_Partnership_Utils::invites_table_column_exists($partnerships_table, $col)) {
                $stats['errors']++;
                return $stats;
            }
        }

        $has_object_type = class_exists('PL_Partnership_Utils') && PL_Partnership_Utils::invites_table_column_exists($legacy_invites_table, 'object_type');
        $has_object_id = class_exists('PL_Partnership_Utils') && PL_Partnership_Utils::invites_table_column_exists($legacy_invites_table, 'object_id');

        $offset = 0;
        $batch = 0;
        while (true) {
            if ($max_batches > 0 && $batch >= $max_batches) {
                break;
            }

            // Build correctly with dynamic prepares (avoid juggling placeholders).
            if ($has_object_type) {
                $sql = $wpdb->prepare(
                    "SELECT id, plan_id" . ($has_object_id ? ", object_id" : "") . ", inviter_user_id, invitee_email, role, status, token_hash, expires_at, created_at
                     FROM {$legacy_invites_table}
                     WHERE object_type = %s AND status = %s
                     ORDER BY id ASC
                     LIMIT %d OFFSET %d",
                    'reading_plan',
                    'pending',
                    $batch_size,
                    $offset
                );
            } else {
                $sql = $wpdb->prepare(
                    "SELECT id, plan_id, inviter_user_id, invitee_email, role, status, token_hash, expires_at, created_at
                     FROM {$legacy_invites_table}
                     WHERE status = %s
                     ORDER BY id ASC
                     LIMIT %d OFFSET %d",
                    'pending',
                    $batch_size,
                    $offset
                );
            }

            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $rows = $wpdb->get_results($sql, ARRAY_A);
            if (!is_array($rows) || empty($rows)) {
                break;
            }

            $stats['batches']++;

            foreach ($rows as $row) {
                $stats['rows']++;

                $plan_id = (int) ($row['object_id'] ?? $row['plan_id'] ?? 0);
                $inviter_user_id = (int) ($row['inviter_user_id'] ?? 0);
                $email = sanitize_email((string) ($row['invitee_email'] ?? ''));
                $role = sanitize_key((string) ($row['role'] ?? 'observer')) ?: 'observer';
                $token_hash = (string) ($row['token_hash'] ?? '');
                $expires_at = (string) ($row['expires_at'] ?? '');
                $created_at = (string) ($row['created_at'] ?? '');

                if ($plan_id <= 0 || $inviter_user_id <= 0 || !$email || !is_email($email) || $token_hash === '' || $expires_at === '') {
                    $stats['errors']++;
                    continue;
                }

                if ($dry_run) {
                    $stats['skipped']++;
                    continue;
                }

                // Insert/upsert via SQL to preserve invited_at/created_at.
                $now = $created_at !== '' ? $created_at : current_time('mysql');
                $insert_sql = $wpdb->prepare(
                    "INSERT INTO {$partnerships_table}
                        (object_type, object_id, owner_user_id, partner_user_id, invitee_email, role, status, invitation_token_hash, invited_at, expires_at, accepted_at, declined_at, revoked_at, created_at, updated_at)
                     VALUES
                        (%s, %d, %d, NULL, %s, %s, %s, %s, %s, %s, NULL, NULL, NULL, %s, %s)
                     ON DUPLICATE KEY UPDATE
                        owner_user_id = VALUES(owner_user_id),
                        invitee_email = VALUES(invitee_email),
                        role = VALUES(role),
                        status = VALUES(status),
                        invitation_token_hash = VALUES(invitation_token_hash),
                        invited_at = VALUES(invited_at),
                        expires_at = VALUES(expires_at),
                        accepted_at = NULL,
                        declined_at = NULL,
                        revoked_at = NULL,
                        updated_at = VALUES(updated_at)",
                    'reading_plan',
                    $plan_id,
                    $inviter_user_id,
                    $email,
                    $role,
                    'pending',
                    $token_hash,
                    $now,
                    $expires_at,
                    $now,
                    $now
                );

                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $result = $wpdb->query($insert_sql);
                if ($result === false) {
                    $stats['errors']++;
                } else {
                    $stats['upserted']++;
                }
            }

            $offset += $batch_size;
            $batch++;
        }

        return $stats;
    }
}
