<?php
/**
 * Manager class for partnerships core logic.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PL_Partnership_Manager
{
    public const COURSE_POST_TYPE = 'sfwd-courses';
    public const LEARNI_COURSE_POST_TYPE = 'learni_course';
    public const COURSE_TEACHERS_META_KEY = '_pcg_course_teachers';
    public const PARTNERSHIPS_TABLE_SLUG = 'politeia_user_object_partnerships';
    public const LEGACY_INVITES_TABLE_SLUG = 'politeia_plan_participant_invites';

    /**
     * Create an invite for a course.
     */
    public static function create_invite(string $object_type, int $object_id, string $email, string $role = 'observer'): string
    {
        global $wpdb;
        if (!$wpdb) {
            return '';
        }

        $object_type = sanitize_key($object_type);
        $object_id = (int) $object_id;
        $email = sanitize_email($email);
        $role = sanitize_key($role ?: 'observer');

        if ($object_type === '' || $object_id <= 0 || !$email || !is_email($email)) {
            return '';
        }

        $partnerships_table = $wpdb->prefix . self::PARTNERSHIPS_TABLE_SLUG;
        $partnerships_exists = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $partnerships_table)) === $partnerships_table);

        $has_invite_cols = $partnerships_exists
            && PL_Partnership_Utils::invites_table_column_exists($partnerships_table, 'invitation_token_hash')
            && PL_Partnership_Utils::invites_table_column_exists($partnerships_table, 'invitee_email')
            && PL_Partnership_Utils::invites_table_column_exists($partnerships_table, 'invited_at')
            && PL_Partnership_Utils::invites_table_column_exists($partnerships_table, 'expires_at')
            && PL_Partnership_Utils::invites_table_column_exists($partnerships_table, 'accepted_at')
            && PL_Partnership_Utils::invites_table_column_exists($partnerships_table, 'revoked_at');

        if ($has_invite_cols) {
            $token = bin2hex(random_bytes(32));
            $token_hash = hash('sha256', $token);
            $now = current_time('mysql');
            $expires_at = gmdate('Y-m-d H:i:s', current_time('timestamp', true) + (7 * DAY_IN_SECONDS));

            // Single-slot: revoke existing pending invites for this object+role.
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$partnerships_table}
                     SET status = %s, revoked_at = %s, updated_at = %s
                     WHERE object_type = %s
                       AND object_id = %d
                       AND role = %s
                       AND status = %s",
                    'revoked',
                    $now,
                    $now,
                    $object_type,
                    $object_id,
                    $role,
                    'pending'
                )
            );

            // Upsert by (object_type, object_id, invitee_email, role) due to unique key.
            $existing = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id
                     FROM {$partnerships_table}
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
                $updated = $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$partnerships_table}
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
                        (int) get_current_user_id(),
                        'pending',
                        $token_hash,
                        $now,
                        $expires_at,
                        $now,
                        (int) $existing['id']
                    )
                );

                return $updated !== false ? $token : '';
            }

            $inserted = $wpdb->query(
                $wpdb->prepare(
                    "INSERT INTO {$partnerships_table}
                        (object_type, object_id, owner_user_id, partner_user_id, invitee_email, role, status, invitation_token_hash, invited_at, expires_at, created_at, updated_at)
                     VALUES
                        (%s, %d, %d, NULL, %s, %s, %s, %s, %s, %s, %s, %s)",
                    $object_type,
                    $object_id,
                    (int) get_current_user_id(),
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

            if ($inserted) {
                PL_Partnership_Utils::debug_log('invite_created', [
                    'source' => 'partnerships',
                    'object_type' => $object_type,
                    'object_id' => $object_id,
                    'role' => $role,
                    'email' => $email,
                    'expires_at' => $expires_at,
                ]);
                return $token;
            }

            return '';
        }

        return self::create_invite_legacy($object_type, $object_id, $email, $role);
    }

    /**
     * Create a legacy invite.
     */
    public static function create_invite_legacy(string $object_type, int $object_id, string $email, string $role): string
    {
        global $wpdb;
        if (!$wpdb) {
            return '';
        }

        $table = $wpdb->prefix . self::LEGACY_INVITES_TABLE_SLUG;
        $table_exists = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table);
        if (!$table_exists) {
            if (class_exists('\\Politeia\\ReadingPlanner\\Installer')) {
                try {
                    \Politeia\ReadingPlanner\Installer::install();
                } catch (\Throwable $e) {
                    // ignore
                }
            }

            $table_exists = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table);
            if (!$table_exists) {
                return '';
            }
        }

        $invitee_user = get_user_by('email', $email);
        $invitee_user_id = $invitee_user instanceof WP_User ? (int) $invitee_user->ID : 0;
        $has_invitee_user_id = PL_Partnership_Utils::invites_table_column_exists($table, 'invitee_user_id');
        $has_object_type = PL_Partnership_Utils::invites_table_column_exists($table, 'object_type');
        $has_object_id = PL_Partnership_Utils::invites_table_column_exists($table, 'object_id');
        if (!$has_object_type || !$has_object_id) {
            return '';
        }

        $token = bin2hex(random_bytes(32));
        $token_hash = hash('sha256', $token);
        $now = current_time('mysql');
        $expires_at = gmdate('Y-m-d H:i:s', current_time('timestamp', true) + (7 * DAY_IN_SECONDS));

        $has_revoked_at = self::invites_table_column_exists($table, 'revoked_at');
        if ($has_revoked_at) {
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table}
                     SET status = %s, revoked_at = %s, updated_at = %s
                     WHERE object_type = %s
                       AND object_id = %d
                       AND role = %s
                       AND status = %s",
                    'revoked',
                    $now,
                    $now,
                    $object_type,
                    $object_id,
                    $role,
                    'pending'
                )
            );
        } else {
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table}
                     SET status = %s, updated_at = %s
                     WHERE object_type = %s
                       AND object_id = %d
                       AND role = %s
                       AND status = %s",
                    'revoked',
                    $now,
                    $object_type,
                    $object_id,
                    $role,
                    'pending'
                )
            );
        }

        $inserted = $wpdb->insert(
            $table,
            [
                'plan_id' => $object_id,
                'object_type' => $object_type,
                'object_id' => $object_id,
                'inviter_user_id' => (int) get_current_user_id(),
                'invitee_email' => $email,
                'invitee_user_id' => ($has_invitee_user_id && $invitee_user_id > 0) ? $invitee_user_id : null,
                'role' => $role,
                'status' => 'pending',
                'token_hash' => $token_hash,
                'expires_at' => $expires_at,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                '%d', '%s', '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s',
            ]
        );

        if (false !== $inserted) {
            PL_Partnership_Utils::debug_log('invite_created', [
                'source' => 'legacy',
                'object_type' => $object_type,
                'object_id' => $object_id,
                'role' => $role,
                'email' => $email,
                'expires_at' => $expires_at,
            ]);
        }

        return (false !== $inserted) ? $token : '';
    }

    /**
     * Accept an invite for a user.
     */
    public static function accept_invite_for_user(string $raw_token, int $user_id): bool
    {
        PL_Partnership_Utils::debug_log('invite_accept_attempt', [
            'token' => $raw_token,
            'user_id' => $user_id,
        ]);

        $invite = self::get_pending_invite_by_raw_token($raw_token);
        if (!$invite) {
            PL_Partnership_Utils::debug_log('invite_accept_failed', [
                'reason' => 'invite_not_found_or_not_pending',
                'token' => $raw_token,
                'user_id' => $user_id,
            ]);
            return false;
        }

        $now_ts = current_time('timestamp', true);
        $expires_ts = strtotime((string) ($invite['expires_at'] ?? '') . ' UTC');
        if ($expires_ts && $expires_ts < $now_ts) {
            self::mark_invite_expired($invite);
            PL_Partnership_Utils::debug_log('invite_accept_failed', [
                'reason' => 'expired',
                'source' => (string) ($invite['_pl_source'] ?? ''),
                'invite_id' => (int) ($invite['id'] ?? 0),
                'token' => $raw_token,
                'user_id' => $user_id,
            ]);
            return false;
        }

        $user = get_userdata($user_id);
        if (!($user instanceof WP_User)) {
            return false;
        }

        $current_email = PL_Partnership_Utils::normalize_email((string) ($user->user_email ?? ''));
        $invite_email = PL_Partnership_Utils::normalize_email((string) ($invite['invitee_email'] ?? ''));
        if ('' === $current_email || '' === $invite_email || $current_email !== $invite_email) {
            PL_Partnership_Utils::debug_log('invite_accept_failed', [
                'reason' => 'email_mismatch',
                'source' => (string) ($invite['_pl_source'] ?? ''),
                'invite_id' => (int) ($invite['id'] ?? 0),
                'user_id' => $user_id,
            ]);
            return false;
        }

        $object_type = (string) ($invite['object_type'] ?? 'reading_plan');
        $object_id = (int) ($invite['object_id'] ?? 0);
        $role = sanitize_key((string) ($invite['role'] ?? 'observer'));

        if ($object_id <= 0) {
            return false;
        }

        if (!class_exists('PL_Partnerships_Repository') || !method_exists('PL_Partnerships_Repository', 'add_partner')) {
            return false;
        }

        $already_partner = false;
        if (method_exists('PL_Partnerships_Repository', 'get_object_partners_by_role')) {
            $rows = PL_Partnerships_Repository::get_object_partners_by_role($object_type, $object_id, $role);
            if (is_array($rows) && !empty($rows)) {
                $first = $rows[0] ?? null;
                if (is_array($first) && (int) ($first['partner_user_id'] ?? 0) === $user_id) {
                    $already_partner = true;
                }
            }
        }

        if (!$already_partner) {
            $owner_user_id = (int) ($invite['owner_user_id'] ?? 0);
            $ok = PL_Partnerships_Repository::add_partner($object_type, $object_id, $user_id, $role, $owner_user_id);
            if (!$ok) {
                return false;
            }
        }

        if ($object_type === 'course' && $role === 'partner') {
            self::ensure_course_enrollment_for_partner($user_id, $object_id);
        }

        $now = current_time('mysql');
        global $wpdb;
        if (!$wpdb) {
            return false;
        }

        $invite_id = (int) ($invite['id'] ?? 0);
        if ($invite_id <= 0) {
            return false;
        }

        $source = (string) ($invite['_pl_source'] ?? 'legacy');
        if ($source === 'partnerships') {
            $table = $wpdb->prefix . self::PARTNERSHIPS_TABLE_SLUG;
            $updated = $wpdb->update(
                $table,
                [
                    'status' => 'accepted',
                    'accepted_at' => $now,
                    'updated_at' => $now,
                ],
                ['id' => $invite_id],
                ['%s', '%s', '%s'],
                ['%d']
            );

            if ($updated !== false) {
                PL_Partnership_Utils::debug_log('invite_accepted', [
                    'source' => 'partnerships',
                    'invite_id' => $invite_id,
                    'object_type' => $object_type,
                    'object_id' => $object_id,
                    'role' => $role,
                    'user_id' => $user_id,
                ]);
            }
            return $updated !== false;
        }

        $table = $wpdb->prefix . self::LEGACY_INVITES_TABLE_SLUG;
        $updated = $wpdb->update(
            $table,
            [
                'status' => 'accepted',
                'accepted_at' => $now,
                'invitee_user_id' => $user_id,
                'updated_at' => $now,
            ],
            ['id' => $invite_id],
            ['%s', '%s', '%d', '%s'],
            ['%d']
        );

        if ($updated !== false) {
            PL_Partnership_Utils::debug_log('invite_accepted', [
                'source' => 'legacy',
                'invite_id' => $invite_id,
                'object_type' => $object_type,
                'object_id' => $object_id,
                'role' => $role,
                'user_id' => $user_id,
            ]);
        }
        return $updated !== false;
    }

    /**
     * Respond to a Reading Planner (reading_plan) invite for a user.
     *
     * Phase 2 goal:
     * - Single pipeline for accept/decline that updates BOTH:
     *   - unified table `wp_politeia_user_object_partnerships` (when present)
     *   - legacy table `wp_politeia_plan_participant_invites`
     * - Best-effort keeps `wp_politeia_plan_participants` in sync on accept.
     *
     * @return array<string,mixed> Result payload.
     */
    public static function respond_to_reading_plan_invite_for_user(string $raw_token, int $user_id, string $action = 'accept'): array
    {
        $raw_token = strtolower(trim($raw_token));
        $action = sanitize_key($action);
        if (!in_array($action, ['accept', 'decline'], true)) {
            $action = 'accept';
        }

        if (!preg_match('/^[a-f0-9]{64}$/', $raw_token) || $user_id <= 0) {
            return ['ok' => false, 'error' => 'invalid_token'];
        }

        $user = get_userdata($user_id);
        if (!($user instanceof \WP_User)) {
            return ['ok' => false, 'error' => 'invalid_user'];
        }

        $token_hash = hash('sha256', $raw_token);

        global $wpdb;
        if (!$wpdb) {
            return ['ok' => false, 'error' => 'db_unavailable'];
        }

        $legacy_table = $wpdb->prefix . self::LEGACY_INVITES_TABLE_SLUG;
        $legacy_exists = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $legacy_table)) === $legacy_table);
        if (!$legacy_exists) {
            return ['ok' => false, 'error' => 'legacy_invites_missing'];
        }

        $partnerships_table = $wpdb->prefix . self::PARTNERSHIPS_TABLE_SLUG;
        $partnerships_exists = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $partnerships_table)) === $partnerships_table)
            && PL_Partnership_Utils::invites_table_column_exists($partnerships_table, 'invitation_token_hash');

        $legacy_invite = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT *
                 FROM {$legacy_table}
                 WHERE token_hash = %s
                 LIMIT 1",
                $token_hash
            ),
            ARRAY_A
        );

        $partnerships_invite = null;
        if ($partnerships_exists) {
            $partnerships_invite = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT *
                     FROM {$partnerships_table}
                     WHERE invitation_token_hash = %s
                     LIMIT 1",
                    $token_hash
                ),
                ARRAY_A
            );
        }

        if (!is_array($legacy_invite) && !is_array($partnerships_invite)) {
            return ['ok' => false, 'error' => 'invite_not_found'];
        }

        // Determine invite email (legacy preferred).
        $invite_email = '';
        if (is_array($legacy_invite) && isset($legacy_invite['invitee_email'])) {
            $invite_email = (string) $legacy_invite['invitee_email'];
        } elseif (is_array($partnerships_invite) && isset($partnerships_invite['invitee_email'])) {
            $invite_email = (string) $partnerships_invite['invitee_email'];
        }

        $current_email = PL_Partnership_Utils::normalize_email((string) ($user->user_email ?? ''));
        $invite_email_norm = PL_Partnership_Utils::normalize_email($invite_email);
        if ($current_email === '' || $invite_email_norm === '' || $current_email !== $invite_email_norm) {
            return ['ok' => false, 'error' => 'email_mismatch'];
        }

        // Ensure it's a reading_plan invite.
        $object_type = '';
        $plan_id = 0;
        $role = 'observer';
        $notify_on = 'none';
        $inviter_user_id = 0;

        if (is_array($legacy_invite)) {
            $object_type = (string) ($legacy_invite['object_type'] ?? 'reading_plan');
            $plan_id = (int) ($legacy_invite['object_id'] ?? ($legacy_invite['plan_id'] ?? 0));
            $role = sanitize_key((string) ($legacy_invite['role'] ?? 'observer')) ?: 'observer';
            $notify_on = sanitize_key((string) ($legacy_invite['notify_on'] ?? 'none')) ?: 'none';
            $inviter_user_id = (int) ($legacy_invite['inviter_user_id'] ?? 0);
        } elseif (is_array($partnerships_invite)) {
            $object_type = (string) ($partnerships_invite['object_type'] ?? 'reading_plan');
            $plan_id = (int) ($partnerships_invite['object_id'] ?? 0);
            $role = sanitize_key((string) ($partnerships_invite['role'] ?? 'observer')) ?: 'observer';
            $inviter_user_id = (int) ($partnerships_invite['owner_user_id'] ?? 0);
        }

        if ($object_type !== 'reading_plan' || $plan_id <= 0) {
            return ['ok' => false, 'error' => 'invalid_invite_object'];
        }

        $legacy_pending = is_array($legacy_invite) && (($legacy_invite['status'] ?? '') === 'pending');
        $partnerships_pending = is_array($partnerships_invite) && (($partnerships_invite['status'] ?? '') === 'pending');
        if (!$legacy_pending && !$partnerships_pending) {
            return [
                'ok' => false,
                'error' => 'invite_not_pending',
                'legacy_status' => is_array($legacy_invite) ? (string) ($legacy_invite['status'] ?? '') : '',
                'partnerships_status' => is_array($partnerships_invite) ? (string) ($partnerships_invite['status'] ?? '') : '',
            ];
        }

        // Expiry check (prefer legacy, fallback partnerships).
        $expires_at = '';
        if (is_array($legacy_invite) && isset($legacy_invite['expires_at'])) {
            $expires_at = (string) $legacy_invite['expires_at'];
        } elseif (is_array($partnerships_invite) && isset($partnerships_invite['expires_at'])) {
            $expires_at = (string) $partnerships_invite['expires_at'];
        }

        $now_ts = current_time('timestamp', true);
        $expires_ts = $expires_at !== '' ? strtotime($expires_at . ' UTC') : 0;
        if ($expires_ts && $expires_ts < $now_ts) {
            $now = current_time('mysql');

            if ($legacy_pending) {
                $wpdb->update(
                    $legacy_table,
                    ['status' => 'expired', 'updated_at' => $now],
                    ['id' => (int) ($legacy_invite['id'] ?? 0)],
                    ['%s', '%s'],
                    ['%d']
                );
            }
            if ($partnerships_pending) {
                $wpdb->update(
                    $partnerships_table,
                    ['status' => 'expired', 'updated_at' => $now],
                    ['id' => (int) ($partnerships_invite['id'] ?? 0)],
                    ['%s', '%s'],
                    ['%d']
                );
            }

            return ['ok' => false, 'error' => 'invite_expired'];
        }

        $now = current_time('mysql');

        if ($action === 'decline') {
            if ($legacy_pending) {
                $wpdb->update(
                    $legacy_table,
                    [
                        'status' => 'declined',
                        'declined_at' => $now,
                        'invitee_user_id' => $user_id,
                        'updated_at' => $now,
                    ],
                    ['id' => (int) ($legacy_invite['id'] ?? 0)],
                    ['%s', '%s', '%d', '%s'],
                    ['%d']
                );
            }
            if ($partnerships_pending) {
                $wpdb->update(
                    $partnerships_table,
                    [
                        'status' => 'declined',
                        'declined_at' => $now,
                        'updated_at' => $now,
                    ],
                    ['id' => (int) ($partnerships_invite['id'] ?? 0)],
                    ['%s', '%s', '%s'],
                    ['%d']
                );
            }

            return ['ok' => true, 'action' => 'decline', 'plan_id' => $plan_id];
        }

        // Accept: ensure unified partnership relationship.
        if (class_exists('PL_Partnerships_Repository') && method_exists('PL_Partnerships_Repository', 'add_partner')) {
            $wpdb_owner_user_id = 0;
            if (is_array($partnerships_invite)) {
                $wpdb_owner_user_id = (int) ($partnerships_invite['owner_user_id'] ?? 0);
            } elseif (is_array($legacy_invite)) {
                $wpdb_owner_user_id = (int) ($legacy_invite['inviter_user_id'] ?? 0);
            }
            try {
                PL_Partnerships_Repository::add_partner('reading_plan', $plan_id, $user_id, $role, $wpdb_owner_user_id);
            } catch (\Throwable $e) {
                // ignore
            }
        }

        // Accept: keep legacy plan participants table in sync.
        self::upsert_reading_plan_participant($plan_id, $user_id, $role, $notify_on, $inviter_user_id);

        if ($legacy_pending) {
            $wpdb->update(
                $legacy_table,
                [
                    'status' => 'accepted',
                    'accepted_at' => $now,
                    'invitee_user_id' => $user_id,
                    'updated_at' => $now,
                ],
                ['id' => (int) ($legacy_invite['id'] ?? 0)],
                ['%s', '%s', '%d', '%s'],
                ['%d']
            );
        }
        if ($partnerships_pending) {
            $wpdb->update(
                $partnerships_table,
                [
                    'status' => 'accepted',
                    'accepted_at' => $now,
                    'updated_at' => $now,
                ],
                ['id' => (int) ($partnerships_invite['id'] ?? 0)],
                ['%s', '%s', '%s'],
                ['%d']
            );
        }

        return ['ok' => true, 'action' => 'accept', 'plan_id' => $plan_id];
    }

    /**
     * Create a Reading Planner invite (object_type=reading_plan).
     *
     * Phase 2 goal:
     * - One pipeline to create invites that writes to the legacy invites table,
     *   and mirrors to the unified partnerships table (best-effort).
     *
     * @return array<string,mixed> Result payload.
     */
    public static function create_reading_plan_invite_for_user(int $plan_id, int $owner_user_id, string $invitee_email, string $role = 'observer', string $notify_on = 'none'): array
    {
        $plan_id = (int) $plan_id;
        $owner_user_id = (int) $owner_user_id;
        $invitee_email = sanitize_email($invitee_email);
        $role = sanitize_key($role ?: 'observer');
        $notify_on = sanitize_key($notify_on ?: 'none');

        if ($plan_id <= 0 || $owner_user_id <= 0 || !$invitee_email || !is_email($invitee_email)) {
            return ['ok' => false, 'error' => 'invalid_input'];
        }

        if ($role !== 'observer') {
            return ['ok' => false, 'error' => 'invalid_role'];
        }

        $allowed_notify = ['none', 'failures_only', 'milestones', 'daily_summary', 'weekly_summary'];
        if (!in_array($notify_on, $allowed_notify, true)) {
            $notify_on = 'none';
        }

        global $wpdb;
        if (!$wpdb) {
            return ['ok' => false, 'error' => 'db_unavailable'];
        }

        // Ensure required legacy tables exist (Installer is safe best-effort).
        $invites_table = $wpdb->prefix . self::LEGACY_INVITES_TABLE_SLUG;
        $participants_table = $wpdb->prefix . 'politeia_plan_participants';
        $invites_exists = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $invites_table)) === $invites_table);
        $participants_exists = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $participants_table)) === $participants_table);

        if (!$invites_exists || !$participants_exists) {
            if (class_exists('\\Politeia\\ReadingPlanner\\Installer')) {
                try {
                    \Politeia\ReadingPlanner\Installer::install();
                } catch (\Throwable $e) {
                    // ignore
                }
            }
            $invites_exists = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $invites_table)) === $invites_table);
            $participants_exists = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $participants_table)) === $participants_table);
        }

        if (!$invites_exists || !$participants_exists) {
            return ['ok' => false, 'error' => 'missing_invite_tables'];
        }

        // Plan ownership check.
        $plans_table = $wpdb->prefix . 'politeia_plans';
        $plan = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, user_id, name FROM {$plans_table} WHERE id = %d LIMIT 1",
                $plan_id
            ),
            ARRAY_A
        );
        if (!$plan) {
            return ['ok' => false, 'error' => 'plan_not_found'];
        }
        if ((int) ($plan['user_id'] ?? 0) !== $owner_user_id) {
            return ['ok' => false, 'error' => 'forbidden'];
        }

        // Cannot invite yourself.
        $owner = get_userdata($owner_user_id);
        $owner_email = $owner ? PL_Partnership_Utils::normalize_email((string) $owner->user_email) : '';
        $normalized_invitee_email = PL_Partnership_Utils::normalize_email($invitee_email);
        if ($owner_email && $normalized_invitee_email === $owner_email) {
            return ['ok' => false, 'error' => 'cannot_invite_owner'];
        }

        // Prevent inviting an already-active participant when we can resolve user_id.
        $invitee_user = get_user_by('email', $invitee_email);
        $invitee_user_id = $invitee_user ? (int) $invitee_user->ID : 0;
        if ($invitee_user_id > 0) {
            $already = false;

            if (class_exists('PL_Partnerships_Repository') && method_exists('PL_Partnerships_Repository', 'get_object_partners')) {
                try {
                    $rows = PL_Partnerships_Repository::get_object_partners('reading_plan', $plan_id);
                    foreach ((array) $rows as $row) {
                        if (is_array($row) && (int) ($row['partner_user_id'] ?? 0) === $invitee_user_id && ($row['status'] ?? '') === 'active') {
                            $already = true;
                            break;
                        }
                    }
                } catch (\Throwable $e) {
                    // ignore
                }
            }

            $authoritative_partnerships = defined('PL_READING_PLANNER_PARTNERSHIPS_AUTHORITATIVE') && PL_READING_PLANNER_PARTNERSHIPS_AUTHORITATIVE;

            if (!$already && !$authoritative_partnerships) {
                $participants_has_revoked_at = PL_Partnership_Utils::invites_table_column_exists($participants_table, 'revoked_at');
                $active_clause = $participants_has_revoked_at ? 'AND revoked_at IS NULL' : '';
                $already = (bool) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT 1 FROM {$participants_table} WHERE plan_id = %d AND user_id = %d AND role = %s {$active_clause} LIMIT 1",
                        $plan_id,
                        $invitee_user_id,
                        $role
                    )
                );
            }

            if ($already) {
                return ['ok' => false, 'error' => 'already_participant'];
            }
        }

        $now = current_time('mysql');
        $expires_at = gmdate('Y-m-d H:i:s', current_time('timestamp', true) + (7 * DAY_IN_SECONDS));

        // Revoke any existing pending legacy invites for this exact object/email/role.
        $invites_has_object_type = PL_Partnership_Utils::invites_table_column_exists($invites_table, 'object_type');
        $invites_has_object_id = PL_Partnership_Utils::invites_table_column_exists($invites_table, 'object_id');
        $invites_has_revoked_at = PL_Partnership_Utils::invites_table_column_exists($invites_table, 'revoked_at');

        $revoked_fields = $invites_has_revoked_at
            ? "status = %s, revoked_at = %s, updated_at = %s"
            : "status = %s, updated_at = %s";

        if ($invites_has_object_type && $invites_has_object_id) {
            if ($invites_has_revoked_at) {
                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$invites_table}
                         SET {$revoked_fields}
                         WHERE object_type = %s
                           AND object_id = %d
                           AND invitee_email = %s
                           AND role = %s
                           AND status = %s",
                        'revoked',
                        $now,
                        $now,
                        'reading_plan',
                        $plan_id,
                        $invitee_email,
                        $role,
                        'pending'
                    )
                );
            } else {
                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$invites_table}
                         SET {$revoked_fields}
                         WHERE object_type = %s
                           AND object_id = %d
                           AND invitee_email = %s
                           AND role = %s
                           AND status = %s",
                        'revoked',
                        $now,
                        'reading_plan',
                        $plan_id,
                        $invitee_email,
                        $role,
                        'pending'
                    )
                );
            }
        } else {
            // Backwards compat: use plan_id column only.
            if ($invites_has_revoked_at) {
                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$invites_table}
                         SET {$revoked_fields}
                         WHERE plan_id = %d
                           AND invitee_email = %s
                           AND role = %s
                           AND status = %s",
                        'revoked',
                        $now,
                        $now,
                        $plan_id,
                        $invitee_email,
                        $role,
                        'pending'
                    )
                );
            } else {
                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$invites_table}
                         SET {$revoked_fields}
                         WHERE plan_id = %d
                           AND invitee_email = %s
                           AND role = %s
                           AND status = %s",
                        'revoked',
                        $now,
                        $plan_id,
                        $invitee_email,
                        $role,
                        'pending'
                    )
                );
            }
        }

        // Create new legacy invite.
        $token = bin2hex(random_bytes(32));
        $token_hash = hash('sha256', $token);

        $has_invitee_user_id = PL_Partnership_Utils::invites_table_column_exists($invites_table, 'invitee_user_id');
        $has_notify_on = PL_Partnership_Utils::invites_table_column_exists($invites_table, 'notify_on');

        $insert = [
            'plan_id' => $plan_id,
            'inviter_user_id' => $owner_user_id,
            'invitee_email' => $invitee_email,
            'invitee_user_id' => ($has_invitee_user_id && $invitee_user_id > 0) ? $invitee_user_id : null,
            'role' => $role,
            'status' => 'pending',
            'token_hash' => $token_hash,
            'expires_at' => $expires_at,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $format = ['%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s'];

        if ($invites_has_object_type) {
            $insert['object_type'] = 'reading_plan';
            $format[] = '%s';
        }
        if ($invites_has_object_id) {
            $insert['object_id'] = $plan_id;
            $format[] = '%d';
        }
        if ($has_notify_on) {
            $insert['notify_on'] = $notify_on;
            $format[] = '%s';
        }

        $inserted = $wpdb->insert($invites_table, $insert, $format);
        if (false === $inserted) {
            return ['ok' => false, 'error' => 'invite_insert_failed', 'db_error' => (string) $wpdb->last_error];
        }

        $invite_id = (int) $wpdb->insert_id;

        // Mirror to unified table (best-effort).
        if (class_exists('PL_Partnership_Invite_Mirror')) {
            PL_Partnership_Invite_Mirror::mirror_pending_invite('reading_plan', $plan_id, $invitee_email, $role, $token_hash, $expires_at, $owner_user_id);
        }

        PL_Partnership_Utils::debug_log('invite_created', [
            'source' => 'reading_plan',
            'object_type' => 'reading_plan',
            'object_id' => $plan_id,
            'role' => $role,
            'email' => $invitee_email,
            'expires_at' => $expires_at,
        ]);

        return [
            'ok' => true,
            'invite_id' => $invite_id,
            'token' => $token,
            'expires_at' => $expires_at,
            'is_registered_user' => ($invitee_user_id > 0),
        ];
    }

    private static function upsert_reading_plan_participant(int $plan_id, int $participant_user_id, string $role, string $notify_on, int $added_by_user_id): void
    {
        global $wpdb;
        if (!$wpdb || $plan_id <= 0 || $participant_user_id <= 0) {
            return;
        }

        $participants_table = $wpdb->prefix . 'politeia_plan_participants';
        $participants_exists = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $participants_table)) === $participants_table);
        if (!$participants_exists) {
            if (class_exists('\\Politeia\\ReadingPlanner\\Installer')) {
                try {
                    \Politeia\ReadingPlanner\Installer::install();
                } catch (\Throwable $e) {
                    // ignore
                }
            }
        }

        $participants_exists = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $participants_table)) === $participants_table);
        if (!$participants_exists) {
            return;
        }

        $role = sanitize_key($role ?: 'observer');
        $notify_on = sanitize_key($notify_on ?: 'none');

        $has_added_by = PL_Partnership_Utils::invites_table_column_exists($participants_table, 'added_by_user_id');
        $has_added_at = PL_Partnership_Utils::invites_table_column_exists($participants_table, 'added_at');
        $has_revoked_at = PL_Partnership_Utils::invites_table_column_exists($participants_table, 'revoked_at');

        $columns = ['plan_id', 'user_id', 'role', 'notify_on'];
        $placeholders = ['%d', '%d', '%s', '%s'];
        $params = [$plan_id, $participant_user_id, $role, $notify_on];

        if ($has_added_by) {
            $columns[] = 'added_by_user_id';
            $placeholders[] = '%d';
            $params[] = (int) $added_by_user_id;
        }
        if ($has_added_at) {
            $columns[] = 'added_at';
            $placeholders[] = '%s';
            $params[] = current_time('mysql');
        }

        $updates = [
            'role = VALUES(role)',
            'notify_on = VALUES(notify_on)',
        ];
        if ($has_added_by) {
            $updates[] = 'added_by_user_id = VALUES(added_by_user_id)';
        }
        if ($has_added_at) {
            $updates[] = 'added_at = VALUES(added_at)';
        }
        if ($has_revoked_at) {
            $updates[] = 'revoked_at = NULL';
        }

        $sql = "INSERT INTO {$participants_table} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ") ON DUPLICATE KEY UPDATE " . implode(', ', $updates);
        $wpdb->query($wpdb->prepare($sql, $params));
    }

    /**
     * Accept a course partner invite by ID.
     */
    public static function accept_course_partner_invite_by_id(string $source, int $invite_id, int $user_id): bool
    {
        global $wpdb;
        if (!$wpdb || $invite_id <= 0 || $user_id <= 0) {
            return false;
        }

        $user = get_userdata($user_id);
        if (!($user instanceof \WP_User)) {
            return false;
        }
        $current_email = PL_Partnership_Utils::normalize_email((string) ($user->user_email ?? ''));
        if ($current_email === '') {
            return false;
        }

        $now_ts = current_time('timestamp', true);
        $now = current_time('mysql');

        if ($source === 'legacy') {
            $table = $wpdb->prefix . self::LEGACY_INVITES_TABLE_SLUG;
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id, object_type, object_id, role, status, invitee_email, expires_at
                     FROM {$table}
                     WHERE id = %d
                     LIMIT 1",
                    $invite_id
                ),
                ARRAY_A
            );
            if (!is_array($row)) {
                return false;
            }

            if (sanitize_key((string) ($row['object_type'] ?? '')) !== 'course') {
                return false;
            }
            if (sanitize_key((string) ($row['role'] ?? '')) !== 'partner') {
                return false;
            }
            if (sanitize_key((string) ($row['status'] ?? '')) !== 'pending') {
                return false;
            }
            $invite_email = PL_Partnership_Utils::normalize_email((string) ($row['invitee_email'] ?? ''));
            if ($invite_email === '' || $invite_email !== $current_email) {
                return false;
            }

            $expires_ts = strtotime((string) ($row['expires_at'] ?? '') . ' UTC');
            if ($expires_ts && $expires_ts < $now_ts) {
                return false;
            }

            $object_id = (int) ($row['object_id'] ?? 0);
            if ($object_id <= 0) {
                return false;
            }
            if (!class_exists('PL_Partnerships_Repository') || !method_exists('PL_Partnerships_Repository', 'add_partner')) {
                return false;
            }
            if (!PL_Partnerships_Repository::add_partner('course', $object_id, $user_id, 'partner', 0)) {
                return false;
            }

            self::ensure_course_enrollment_for_partner($user_id, $object_id);

            $updated = $wpdb->update(
                $table,
                [
                    'status' => 'accepted',
                    'accepted_at' => $now,
                    'invitee_user_id' => $user_id,
                    'updated_at' => $now,
                ],
                ['id' => $invite_id],
                ['%s', '%s', '%d', '%s'],
                ['%d']
            );

            return $updated !== false;
        }

        $table = $wpdb->prefix . self::PARTNERSHIPS_TABLE_SLUG;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, object_type, object_id, role, status, invitee_email, expires_at, owner_user_id
                 FROM {$table}
                 WHERE id = %d
                 LIMIT 1",
                $invite_id
            ),
            ARRAY_A
        );
        if (!is_array($row)) {
            return false;
        }

        if (sanitize_key((string) ($row['object_type'] ?? '')) !== 'course') {
            return false;
        }
        if (sanitize_key((string) ($row['role'] ?? '')) !== 'partner') {
            return false;
        }
        if (sanitize_key((string) ($row['status'] ?? '')) !== 'pending') {
            return false;
        }
        $invite_email = PL_Partnership_Utils::normalize_email((string) ($row['invitee_email'] ?? ''));
        if ($invite_email === '' || $invite_email !== $current_email) {
            return false;
        }

        $expires_ts = strtotime((string) ($row['expires_at'] ?? '') . ' UTC');
        if ($expires_ts && $expires_ts < $now_ts) {
            return false;
        }

        $object_id = (int) ($row['object_id'] ?? 0);
        if ($object_id <= 0) {
            return false;
        }
        if (!class_exists('PL_Partnerships_Repository') || !method_exists('PL_Partnerships_Repository', 'add_partner')) {
            return false;
        }
        $owner_user_id = (int) ($row['owner_user_id'] ?? 0);
        if (!PL_Partnerships_Repository::add_partner('course', $object_id, $user_id, 'partner', $owner_user_id)) {
            return false;
        }

        self::ensure_course_enrollment_for_partner($user_id, $object_id);

        $updated = $wpdb->update(
            $table,
            [
                'status' => 'accepted',
                'accepted_at' => $now,
                'updated_at' => $now,
            ],
            ['id' => $invite_id],
            ['%s', '%s', '%s'],
            ['%d']
        );

        return $updated !== false;
    }

    /**
     * Decline a course partner invite by ID.
     */
    public static function decline_course_partner_invite_by_id(string $source, int $invite_id, int $user_id): bool
    {
        global $wpdb;
        if (!$wpdb || $invite_id <= 0 || $user_id <= 0) {
            return false;
        }

        $user = get_userdata($user_id);
        if (!($user instanceof \WP_User)) {
            return false;
        }
        $current_email = PL_Partnership_Utils::normalize_email((string) ($user->user_email ?? ''));
        if ($current_email === '') {
            return false;
        }

        $now = current_time('mysql');
        $table = $wpdb->prefix . (($source === 'legacy') ? self::LEGACY_INVITES_TABLE_SLUG : self::PARTNERSHIPS_TABLE_SLUG);

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, object_type, object_id, role, status, invitee_email
                 FROM {$table}
                 WHERE id = %d
                 LIMIT 1",
                $invite_id
            ),
            ARRAY_A
        );
        if (!is_array($row)) {
            return false;
        }

        if (sanitize_key((string) ($row['object_type'] ?? '')) !== 'course') {
            return false;
        }
        if (sanitize_key((string) ($row['role'] ?? '')) !== 'partner') {
            return false;
        }
        if (sanitize_key((string) ($row['status'] ?? '')) !== 'pending') {
            return false;
        }
        $invite_email = PL_Partnership_Utils::normalize_email((string) ($row['invitee_email'] ?? ''));
        if ($invite_email === '' || $invite_email !== $current_email) {
            return false;
        }

        $data = [
            'status' => 'declined',
            'updated_at' => $now,
        ];
        $formats = ['%s', '%s'];

        if ($source === 'legacy') {
            if (PL_Partnership_Utils::invites_table_column_exists($table, 'declined_at')) {
                $data['declined_at'] = $now;
                $formats[] = '%s';
            }
            $updated = $wpdb->update(
                $table,
                $data,
                ['id' => $invite_id],
                $formats,
                ['%d']
            );
            return $updated !== false;
        }

        $data['declined_at'] = $now;
        $formats[] = '%s';
        $updated = $wpdb->update(
            $table,
            $data,
            ['id' => $invite_id],
            $formats,
            ['%d']
        );
        return $updated !== false;
    }

    /**
     * Mark an invite as expired.
     */
    public static function mark_invite_expired(array $invite): void
    {
        global $wpdb;
        if (!$wpdb) {
            return;
        }

        $invite_id = (int) ($invite['id'] ?? 0);
        if ($invite_id <= 0) {
            return;
        }

        $source = (string) ($invite['_pl_source'] ?? 'legacy');
        $now = current_time('mysql');

        if ($source === 'partnerships') {
            $table = $wpdb->prefix . self::PARTNERSHIPS_TABLE_SLUG;
            $wpdb->update(
                $table,
                [
                    'status' => 'expired',
                    'updated_at' => $now,
                ],
                ['id' => $invite_id],
                ['%s', '%s'],
                ['%d']
            );
            return;
        }

        $table = $wpdb->prefix . self::LEGACY_INVITES_TABLE_SLUG;
        $wpdb->update(
            $table,
            [
                'status' => 'expired',
                'updated_at' => $now,
            ],
            ['id' => $invite_id],
            ['%s', '%s'],
            ['%d']
        );
    }

    /**
     * Get pending invite by raw token.
     */
    public static function get_pending_invite_by_raw_token(string $raw_token): ?array
    {
        $raw_token = strtolower(trim($raw_token));
        if (!preg_match('/^[a-f0-9]{64}$/', $raw_token)) {
            return null;
        }

        $token_hash = hash('sha256', $raw_token);

        global $wpdb;
        if (!$wpdb) {
            return null;
        }

        $partnerships_table = $wpdb->prefix . self::PARTNERSHIPS_TABLE_SLUG;
        $partnerships_exists = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $partnerships_table)) === $partnerships_table);

        if ($partnerships_exists && PL_Partnership_Utils::invites_table_column_exists($partnerships_table, 'invitation_token_hash')) {
            $invite = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT *
                     FROM {$partnerships_table}
                     WHERE invitation_token_hash = %s
                       AND status = %s
                     LIMIT 1",
                    $token_hash,
                    'pending'
                ),
                ARRAY_A
            );

            if (is_array($invite)) {
                $invite['_pl_source'] = 'partnerships';
                return $invite;
            }
        }

        $invites_table = $wpdb->prefix . self::LEGACY_INVITES_TABLE_SLUG;
        $invite = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT *
                 FROM {$invites_table}
                 WHERE token_hash = %s
                   AND status = %s
                 LIMIT 1",
                $token_hash,
                'pending'
            ),
            ARRAY_A
        );

        if (is_array($invite)) {
            $invite['_pl_source'] = 'legacy';
            return $invite;
        }

        return null;
    }

    /**
     * Get any invite by raw token.
     */
    public static function get_invite_by_raw_token(string $raw_token): ?array
    {
        $raw_token = strtolower(trim($raw_token));
        if (!preg_match('/^[a-f0-9]{64}$/', $raw_token)) {
            return null;
        }

        $token_hash = hash('sha256', $raw_token);

        global $wpdb;
        if (!$wpdb) {
            return null;
        }

        $partnerships_table = $wpdb->prefix . self::PARTNERSHIPS_TABLE_SLUG;
        $partnerships_exists = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $partnerships_table)) === $partnerships_table);

        if ($partnerships_exists && PL_Partnership_Utils::invites_table_column_exists($partnerships_table, 'invitation_token_hash')) {
            $invite = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT *
                     FROM {$partnerships_table}
                     WHERE invitation_token_hash = %s
                     LIMIT 1",
                    $token_hash
                ),
                ARRAY_A
            );

            if (is_array($invite)) {
                $invite['_pl_source'] = 'partnerships';
                return $invite;
            }
        }

        $invites_table = $wpdb->prefix . self::LEGACY_INVITES_TABLE_SLUG;
        $invite = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT *
                 FROM {$invites_table}
                 WHERE token_hash = %s
                 LIMIT 1",
                $token_hash
            ),
            ARRAY_A
        );

        if (is_array($invite)) {
            $invite['_pl_source'] = 'legacy';
            return $invite;
        }

        return null;
    }

    /**
     * Check if user can manage a course.
     */
    public static function user_can_manage_course(int $user_id, int $course_id): bool
    {
        if ($user_id <= 0 || $course_id <= 0) {
            return false;
        }

        if (current_user_can('manage_options')) {
            return true;
        }

        $post = get_post($course_id);
        $post_type = $post ? (string) ($post->post_type ?? '') : '';
        if (!$post || !in_array($post_type, [self::COURSE_POST_TYPE, self::LEARNI_COURSE_POST_TYPE], true)) {
            return false;
        }

        if ($post_type === self::LEARNI_COURSE_POST_TYPE && class_exists('PL_Partnerships_Repository') && method_exists('PL_Partnerships_Repository', 'get_object_partners_by_role')) {
            try {
                $rows = PL_Partnerships_Repository::get_object_partners_by_role('course', $course_id, 'partner');
                foreach ((array) $rows as $row) {
                    if (is_array($row) && (int) ($row['partner_user_id'] ?? 0) === $user_id && ($row['status'] ?? '') === 'active') {
                        return false;
                    }
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        $author_id = (int) ($post->post_author ?? 0);
        if ($author_id === $user_id) {
            return true;
        }

        $teacher_ids = get_post_meta($course_id, self::COURSE_TEACHERS_META_KEY, false);
        $teacher_ids = array_map('absint', (array) $teacher_ids);
        if (in_array($user_id, $teacher_ids, true)) {
            return true;
        }

        if ($post_type === self::LEARNI_COURSE_POST_TYPE && class_exists('\\Learni\\Database\\Enrollments') && method_exists('\\Learni\\Database\\Enrollments', 'user_is_owner')) {
            try {
                if ((bool) \Learni\Database\Enrollments::user_is_owner($user_id, $course_id)) {
                    return true;
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        if ($post_type === self::COURSE_POST_TYPE && function_exists('sfwd_lms_has_access') && (bool) sfwd_lms_has_access($course_id, $user_id)) {
            return true;
        }

        return false;
    }

    /**
     * Ensure course enrollment for partner.
     */
    public static function ensure_course_enrollment_for_partner(int $user_id, int $course_id): void
    {
        if ($user_id <= 0 || $course_id <= 0) {
            return;
        }

        if (!class_exists('\\Learni\\Database\\Enrollments')) {
            return;
        }

        if (method_exists('\\Learni\\Database\\Enrollments', 'get_enrollment')) {
            $row = \Learni\Database\Enrollments::get_enrollment($user_id, $course_id);
            if (is_array($row) && (($row['status'] ?? '') === \Learni\Database\Enrollments::STATUS_ACTIVE)) {
                $source = (string) ($row['source'] ?? '');
                $provider = (string) ($row['payment_provider'] ?? '');
                $ref = (string) ($row['payment_reference'] ?? '');

                if ($source === \Learni\Database\Enrollments::SOURCE_MANUAL && $ref === 'course_partner' && $provider !== 'partner_invite') {
                    \Learni\Database\Enrollments::upsert($user_id, $course_id, [
                        'status' => \Learni\Database\Enrollments::STATUS_ACTIVE,
                        'source' => \Learni\Database\Enrollments::SOURCE_MANUAL,
                        'payment_provider' => 'partner_invite',
                        'payment_reference' => 'course_partner',
                    ]);
                }
                return;
            }
        }

        \Learni\Database\Enrollments::upsert($user_id, $course_id, [
            'status' => \Learni\Database\Enrollments::STATUS_ACTIVE,
            'source' => \Learni\Database\Enrollments::SOURCE_MANUAL,
            'payment_provider' => 'partner_invite',
            'payment_reference' => 'course_partner',
        ]);
    }
}
