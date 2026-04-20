<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX endpoint for the creator "ESTUDIANTES" rankings.
 *
 * Rankings are computed for the currently logged-in creator and their owned courses.
 */
class PL_Woo_User_Student_Rankings
{
    const AJAX_ACTION = 'pl_get_user_student_rankings';
    const NONCE_ACTION = 'pl_user_student_rankings';

    public static function init(): void
    {
        add_action('wp_ajax_' . self::AJAX_ACTION, [__CLASS__, 'handle']);
        add_action('wp_ajax_nopriv_' . self::AJAX_ACTION, [__CLASS__, 'handle_nopriv']);
    }

    public static function handle_nopriv(): void
    {
        wp_send_json_error(['message' => 'unauthorized'], 401);
    }

    private static function paid_statuses(): array
    {
        if (function_exists('wc_get_is_paid_statuses')) {
            $raw = (array) wc_get_is_paid_statuses();
            $norm = [];
            foreach ($raw as $s) {
                $s = (string) $s;
                $s = preg_replace('/^wc-/', '', $s);
                if ($s !== '') {
                    $norm[] = $s;
                }
            }
            $norm = array_values(array_unique($norm));
            if (!empty($norm)) {
                return $norm;
            }
        }

        return ['processing', 'completed'];
    }

    private static function owned_course_ids(int $owner_user_id): array
    {
        if (!function_exists('get_posts')) {
            return [];
        }

        $product_ids = get_posts([
            'post_type' => 'product',
            'post_status' => ['publish', 'private', 'draft'],
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => 'product_owner',
                    'value' => $owner_user_id,
                    'compare' => '=',
                ],
            ],
            'tax_query' => [
                [
                    'taxonomy' => 'product_cat',
                    'field' => 'slug',
                    'terms' => ['cursos'],
                ],
            ],
        ]);

        $course_ids = [];
        foreach ((array) $product_ids as $pid) {
            $related = get_post_meta((int) $pid, '_learni_related_course', true);
            if (empty($related)) {
                $related = get_post_meta((int) $pid, '_related_course', true);
            }
            if (is_array($related)) {
                foreach ($related as $cid) {
                    $cid = (int) $cid;
                    if ($cid > 0) {
                        $course_ids[$cid] = true;
                    }
                }
            } else {
                $cid = (int) $related;
                if ($cid > 0) {
                    $course_ids[$cid] = true;
                }
            }
        }

        return array_map('intval', array_keys($course_ids));
    }

    private static function course_quiz_ids(array $course_ids): array
    {
        $out = [];
        foreach ($course_ids as $cid) {
            $cid = (int) $cid;
            if ($cid <= 0) {
                continue;
            }
            $qid = (int) get_post_meta($cid, '_first_quiz_id', true);
            if ($qid <= 0) {
                $qid = (int) get_post_meta($cid, '_final_quiz_id', true);
            }
            if ($qid > 0) {
                $out[$qid] = true;
            }
        }
        return array_map('intval', array_keys($out));
    }

    private static function bucket_for_product(int $product_id): ?string
    {
        $terms = get_the_terms($product_id, 'product_cat');
        if (!is_array($terms)) {
            return null;
        }

        foreach ($terms as $t) {
            $slug = sanitize_title($t->name);
            if ($slug === 'cursos') {
                return 'courses';
            }
        }

        return null;
    }

    private static function user_payload(int $user_id): array
    {
        $u = get_user_by('id', $user_id);

        $first = $u ? (string) get_user_meta($user_id, 'first_name', true) : '';
        $last = $u ? (string) get_user_meta($user_id, 'last_name', true) : '';
        $full_name = trim(trim($first) . ' ' . trim($last));

        if ($full_name === '' && $u && !empty($u->display_name)) {
            $full_name = (string) $u->display_name;
        }
        if ($full_name === '' && $u && !empty($u->user_login)) {
            $full_name = (string) $u->user_login;
        }
        if ($full_name === '') {
            $full_name = (string) $user_id;
        }

        $avatar = function_exists('get_avatar_url')
            ? (string) get_avatar_url($user_id, ['size' => 64])
            : '';

        return [
            'name' => $full_name,
            'avatar' => $avatar,
        ];
    }

    private static function ranking_most_purchased_courses(int $owner_user_id): array
    {
        if (!class_exists('WooCommerce') || !function_exists('wc_get_orders')) {
            return [];
        }

        $order_ids = wc_get_orders([
            'limit' => -1,
            'return' => 'ids',
            'type' => 'shop_order',
            'status' => self::paid_statuses(),
        ]);

        $counts = []; // user_id => qty

        foreach ((array) $order_ids as $oid) {
            $order = wc_get_order($oid);
            if (!$order) {
                continue;
            }

            $customer_id = method_exists($order, 'get_customer_id') ? (int) $order->get_customer_id() : 0;
            if ($customer_id <= 0) {
                continue;
            }

            foreach ($order->get_items('line_item') as $item) {
                if (!is_a($item, 'WC_Order_Item_Product')) {
                    continue;
                }

                $product_id = (int) $item->get_product_id();
                $parent_id = (int) wp_get_post_parent_id($product_id);
                $base_product_id = $parent_id > 0 ? $parent_id : $product_id;

                $owner_id = (int) get_post_meta($base_product_id, 'product_owner', true);
                if ($owner_id !== (int) $owner_user_id) {
                    continue;
                }

                if (self::bucket_for_product($base_product_id) !== 'courses') {
                    continue;
                }

                $quantity = (int) $item->get_quantity();
                if ($quantity <= 0) {
                    continue;
                }

                if (!isset($counts[$customer_id])) {
                    $counts[$customer_id] = 0;
                }
                $counts[$customer_id] += $quantity;
            }
        }

        arsort($counts);

        $out = [];
        foreach ($counts as $uid => $qty) {
            $uid = (int) $uid;
            if ($uid <= 0) {
                continue;
            }
            $user = self::user_payload($uid);
            $out[] = [
                'user_id' => $uid,
                'name' => (string) ($user['name'] ?? ''),
                'avatar' => (string) ($user['avatar'] ?? ''),
                'courses' => (int) $qty,
            ];
            if (count($out) >= 10) {
                break;
            }
        }

        return $out;
    }

    private static function ranking_quiz_improvement(array $owned_courses): array
    {
        if (empty($owned_courses)) {
            return [];
        }

        $quiz_ids = self::course_quiz_ids($owned_courses);
        if (empty($quiz_ids)) {
            return [];
        }

        global $wpdb;
        $table_attempts = $wpdb->prefix . 'learni_quiz_attempts';
        $quiz_placeholders = implode(',', array_fill(0, count($quiz_ids), '%d'));

        $attempts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT quiz_id, user_id, score, submitted_at
                 FROM {$table_attempts}
                 WHERE quiz_id IN ({$quiz_placeholders})
                   AND status = 'submitted'
                 ORDER BY user_id ASC, quiz_id ASC, submitted_at ASC",
                $quiz_ids
            ),
            ARRAY_A
        );

        $grouped = [];
        foreach ((array) $attempts as $a) {
            $key = $a['user_id'] . ':' . $a['quiz_id'];
            $grouped[$key][] = (float) $a['score'];
        }

        $out = [];
        foreach ($grouped as $key => $scores) {
            if (count($scores) < 2) {
                continue;
            }
            [$uid, $qid] = explode(':', $key);
            $uid = (int) $uid;
            $qid = (int) $qid;

            $first = $scores[0];
            $last = $scores[count($scores) - 1];
            $inc = $last - $first;

            if ($inc <= 0) {
                continue;
            }

            // Find which course this quiz belongs to (for display).
            $cid = 0;
            foreach ($owned_courses as $owned_cid) {
                if ((int) get_post_meta($owned_cid, '_first_quiz_id', true) === $qid || (int) get_post_meta($owned_cid, '_final_quiz_id', true) === $qid) {
                    $cid = $owned_cid;
                    break;
                }
            }

            $user = self::user_payload($uid);
            $out[] = [
                'user_id' => $uid,
                'name' => (string) ($user['name'] ?? ''),
                'avatar' => (string) ($user['avatar'] ?? ''),
                'course_id' => $cid,
                'course' => $cid > 0 ? (string) get_the_title($cid) : '',
                'increase' => (float) $inc,
            ];
        }

        usort($out, function ($a, $b) {
            return ($b['increase'] <=> $a['increase']);
        });

        return array_slice($out, 0, 10);
    }

    private static function ranking_course_completion_days(array $owned_courses, string $direction): array
    {
        if (empty($owned_courses)) {
            return [];
        }

        $dir = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';
        $placeholders = implode(',', array_fill(0, count($owned_courses), '%d'));

        global $wpdb;
        $table_progress = $wpdb->prefix . 'learni_progress';
        $table_enrollments = $wpdb->prefix . 'learni_enrollments';

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT e.user_id, e.course_post_id,
                        (TIMESTAMPDIFF(SECOND, e.started_at, MAX(p.completed_at)) / 86400) AS days
                 FROM {$table_enrollments} e
                 INNER JOIN {$table_progress} p ON e.user_id = p.user_id AND e.course_post_id = p.course_post_id
                 WHERE e.course_post_id IN ({$placeholders})
                   AND p.status = 'complete'
                 GROUP BY e.user_id, e.course_post_id
                 ORDER BY days {$dir}
                 LIMIT 10",
                $owned_courses
            ),
            ARRAY_A
        );

        $out = [];
        foreach ((array) $rows as $r) {
            $uid = (int) ($r['user_id'] ?? 0);
            $cid = (int) ($r['course_post_id'] ?? 0);
            $days = isset($r['days']) ? (float) $r['days'] : null;
            if ($uid <= 0 || $cid <= 0 || $days === null) {
                continue;
            }
            $user = self::user_payload($uid);
            $out[] = [
                'user_id' => $uid,
                'name' => (string) ($user['name'] ?? ''),
                'avatar' => (string) ($user['avatar'] ?? ''),
                'course_id' => $cid,
                'course' => (string) get_the_title($cid),
                'days' => max(0.0, $days),
            ];
        }

        return $out;
    }

    public static function handle(): void
    {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'unauthorized'], 401);
        }

        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        $owner_user_id = get_current_user_id();
        $owned_courses = self::owned_course_ids((int) $owner_user_id);

        wp_send_json_success([
            'purchases' => self::ranking_most_purchased_courses((int) $owner_user_id),
            'quiz_improvement' => self::ranking_quiz_improvement($owned_courses),
            'fastest_completion' => self::ranking_course_completion_days($owned_courses, 'asc'),
            'slowest_completion' => self::ranking_course_completion_days($owned_courses, 'desc'),
        ]);
    }
}
