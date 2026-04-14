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

