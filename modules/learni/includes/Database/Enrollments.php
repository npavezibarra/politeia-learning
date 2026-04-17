<?php

namespace Learni\Database;

final class Enrollments
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_CANCELLED = 'cancelled';

    public const SOURCE_WOOCOMMERCE = 'woocommerce';
    public const SOURCE_DIRECT = 'direct';
    public const SOURCE_MANUAL = 'manual';

    public static function user_has_active(int $user_id, int $course_post_id): bool
    {
        if ($user_id <= 0 || $course_post_id <= 0) {
            return false;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'learni_enrollments';

        $status = (string) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT status FROM {$table} WHERE user_id = %d AND course_post_id = %d LIMIT 1",
                $user_id,
                $course_post_id
            )
        );

        return $status === self::STATUS_ACTIVE;
    }

    /**
     * @return array{id:int,status:string,source:string,payment_provider:?string,payment_reference:?string,woocommerce_order_id:?int}|null
     */
    public static function get_enrollment(int $user_id, int $course_post_id): ?array
    {
        if ($user_id <= 0 || $course_post_id <= 0) {
            return null;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'learni_enrollments';

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, status, source, payment_provider, payment_reference, woocommerce_order_id
                 FROM {$table}
                 WHERE user_id = %d AND course_post_id = %d
                 LIMIT 1",
                $user_id,
                $course_post_id
            ),
            ARRAY_A
        );

        if (!is_array($row) || empty($row['id'])) {
            return null;
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'status' => (string) ($row['status'] ?? ''),
            'source' => (string) ($row['source'] ?? ''),
            'payment_provider' => isset($row['payment_provider']) ? (string) $row['payment_provider'] : null,
            'payment_reference' => isset($row['payment_reference']) ? (string) $row['payment_reference'] : null,
            'woocommerce_order_id' => isset($row['woocommerce_order_id']) && $row['woocommerce_order_id'] !== null ? (int) $row['woocommerce_order_id'] : null,
        ];
    }

    /**
     * True when the user is the "owner" of the enrollment (purchaser/direct/manual), not a partner invite.
     */
    public static function user_is_owner(int $user_id, int $course_post_id): bool
    {
        $row = self::get_enrollment($user_id, $course_post_id);
        if (!is_array($row) || ($row['status'] ?? '') !== self::STATUS_ACTIVE) {
            return false;
        }

        $source = (string) ($row['source'] ?? '');
        $provider = (string) ($row['payment_provider'] ?? '');

        if ($source === self::SOURCE_WOOCOMMERCE || $source === self::SOURCE_DIRECT) {
            return true;
        }

        // Manual enrollments are considered owner unless they were created by a course-partner invite.
        if ($source === self::SOURCE_MANUAL && $provider !== 'partner_invite') {
            return true;
        }

        return false;
    }

    /**
     * Delete enrollment only if it was granted via course-partner invite.
     */
    public static function unenroll_if_partner_invite(int $user_id, int $course_post_id): bool
    {
        $row = self::get_enrollment($user_id, $course_post_id);
        if (!is_array($row)) {
            return false;
        }

        $source = (string) ($row['source'] ?? '');
        $provider = (string) ($row['payment_provider'] ?? '');
        if ($source === self::SOURCE_MANUAL && $provider === 'partner_invite') {
            return self::delete($user_id, $course_post_id);
        }

        return false;
    }

    public static function upsert(int $user_id, int $course_post_id, array $data): bool
    {
        if ($user_id <= 0 || $course_post_id <= 0) {
            return false;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'learni_enrollments';

        $existing_id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE user_id = %d AND course_post_id = %d LIMIT 1",
                $user_id,
                $course_post_id
            )
        );

        $defaults = [
            'status' => self::STATUS_ACTIVE,
            'source' => self::SOURCE_MANUAL,
            'woocommerce_order_id' => null,
            'payment_provider' => null,
            'payment_reference' => null,
            'payment_amount' => null,
            'payment_currency' => null,
            'started_at' => current_time('mysql'),
            'expires_at' => null,
            'created_at' => current_time('mysql'),
        ];

        $payload = array_merge($defaults, $data);

        $row = [
            'user_id' => $user_id,
            'course_post_id' => $course_post_id,
            'status' => (string) $payload['status'],
            'source' => (string) $payload['source'],
            'woocommerce_order_id' => $payload['woocommerce_order_id'] ? (int) $payload['woocommerce_order_id'] : null,
            'payment_provider' => $payload['payment_provider'] ? sanitize_key((string) $payload['payment_provider']) : null,
            'payment_reference' => $payload['payment_reference'] ? sanitize_text_field((string) $payload['payment_reference']) : null,
            'payment_amount' => $payload['payment_amount'] === null || $payload['payment_amount'] === '' ? null : (string) $payload['payment_amount'],
            'payment_currency' => $payload['payment_currency'] ? strtoupper(sanitize_text_field((string) $payload['payment_currency'])) : null,
            'started_at' => $payload['started_at'],
            'expires_at' => $payload['expires_at'],
            'created_at' => $payload['created_at'],
        ];

        if ($existing_id > 0) {
            $update = $row;
            unset($update['user_id'], $update['course_post_id'], $update['created_at']);
            $ok = $wpdb->update($table, $update, ['id' => $existing_id]);
            return $ok !== false;
        }

        $ok = $wpdb->insert($table, $row);
        return (bool) $ok;
    }

    /**
     * @return array<int, array{id:int, courseId:int, title:string, status:string, startedAt:string}>
     */
    public static function get_for_user(int $user_id): array
    {
        if ($user_id <= 0) {
            return [];
        }

        global $wpdb;
        $table = $wpdb->prefix . 'learni_enrollments';

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT e.id, e.course_post_id as courseId, p.post_title as title, e.status, e.started_at as startedAt
                 FROM {$table} e
                 INNER JOIN {$wpdb->posts} p ON e.course_post_id = p.ID
                 WHERE e.user_id = %d
                 ORDER BY e.created_at DESC",
                $user_id
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    public static function delete(int $user_id, int $course_post_id): bool
    {
        if ($user_id <= 0 || $course_post_id <= 0) {
            return false;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'learni_enrollments';

        $ok = $wpdb->delete($table, ['user_id' => $user_id, 'course_post_id' => $course_post_id]);
        return $ok !== false;
    }
}
