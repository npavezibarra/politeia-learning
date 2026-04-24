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

            return $inserted ? $token : '';
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

        return (false !== $inserted) ? $token : '';
    }

    /**
     * Accept an invite for a user.
     */
    public static function accept_invite_for_user(string $raw_token, int $user_id): bool
    {
        $invite = self::get_pending_invite_by_raw_token($raw_token);
        if (!$invite) {
            return false;
        }

        $now_ts = current_time('timestamp', true);
        $expires_ts = strtotime((string) ($invite['expires_at'] ?? '') . ' UTC');
        if ($expires_ts && $expires_ts < $now_ts) {
            self::mark_invite_expired($invite);
            return false;
        }

        $user = get_userdata($user_id);
        if (!($user instanceof WP_User)) {
            return false;
        }

        $current_email = PL_Partnership_Utils::normalize_email((string) ($user->user_email ?? ''));
        $invite_email = PL_Partnership_Utils::normalize_email((string) ($invite['invitee_email'] ?? ''));
        if ('' === $current_email || '' === $invite_email || $current_email !== $invite_email) {
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

        return $updated !== false;
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
