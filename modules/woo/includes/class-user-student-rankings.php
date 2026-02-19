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
            $related = get_post_meta((int) $pid, '_related_course', true);
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

        $course_quizzes = []; // course_id => [first, final]
        $quiz_ids = [];

        foreach ($owned_courses as $cid) {
            $cid = (int) $cid;
            if ($cid <= 0) {
                continue;
            }
            $first = (int) get_post_meta($cid, '_first_quiz_id', true);
            $final = (int) get_post_meta($cid, '_final_quiz_id', true);
            if ($first > 0 || $final > 0) {
                if ($first <= 0) {
                    $first = $final;
                }
                if ($final <= 0) {
                    $final = $first;
                }

                $course_quizzes[$cid] = [(int) $first, (int) $final];
                if ($first > 0) {
                    $quiz_ids[$first] = true;
                }
                if ($final > 0) {
                    $quiz_ids[$final] = true;
                }
            }
        }

        if (empty($course_quizzes) || empty($quiz_ids)) {
            return [];
        }

        global $wpdb;
        $ua = $wpdb->prefix . 'learndash_user_activity';
        $uam = $wpdb->prefix . 'learndash_user_activity_meta';

        $course_placeholders = implode(',', array_fill(0, count($course_quizzes), '%d'));
        $quiz_list = array_map('intval', array_keys($quiz_ids));
        $quiz_placeholders = implode(',', array_fill(0, count($quiz_list), '%d'));

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ua.user_id, ua.course_id, ua.post_id AS quiz_id, ua.activity_completed,
                        CAST(uam.activity_meta_value AS DECIMAL(10,2)) AS pct
                 FROM {$ua} ua
                 INNER JOIN {$uam} uam
                   ON uam.activity_id = ua.activity_id
                  AND uam.activity_meta_key = 'percentage'
                 WHERE ua.activity_type = 'quiz'
                   AND ua.activity_status = 1
                   AND ua.course_id IN ({$course_placeholders})
                   AND ua.post_id IN ({$quiz_placeholders})
                 ORDER BY ua.user_id ASC, ua.course_id ASC, ua.post_id ASC, ua.activity_completed ASC",
                array_merge(array_keys($course_quizzes), $quiz_list)
            ),
            ARRAY_A
        );

        $grouped = []; // "user:course:quiz" => [pct, pct, ...] in chronological order
        foreach ((array) $rows as $r) {
            $uid = (int) ($r['user_id'] ?? 0);
            $cid = (int) ($r['course_id'] ?? 0);
            $qid = (int) ($r['quiz_id'] ?? 0);
            if ($uid <= 0 || $cid <= 0 || $qid <= 0) {
                continue;
            }
            if (!isset($course_quizzes[$cid])) {
                continue;
            }
            $pct = isset($r['pct']) ? (float) $r['pct'] : null;
            if ($pct === null) {
                continue;
            }

            $key = $uid . ':' . $cid . ':' . $qid;
            if (!isset($grouped[$key])) {
                $grouped[$key] = [];
            }
            $grouped[$key][] = $pct;
        }

        $out = [];
        foreach ($course_quizzes as $cid => $pair) {
            $cid = (int) $cid;
            $first_id = (int) ($pair[0] ?? 0);
            $final_id = (int) ($pair[1] ?? 0);
            if ($cid <= 0 || $first_id <= 0 || $final_id <= 0) {
                continue;
            }

            // Iterate over users by scanning grouped keys for this course.
            // grouped keys are "user:course:quiz" — build a set of users that have either quiz.
            $users = [];
            foreach ($grouped as $gk => $_p) {
                // Fast parse: user:course:quiz
                $parts = explode(':', $gk);
                if (count($parts) !== 3) {
                    continue;
                }
                if ((int) $parts[1] !== $cid) {
                    continue;
                }
                $qid = (int) $parts[2];
                if ($qid !== $first_id && $qid !== $final_id) {
                    continue;
                }
                $users[(int) $parts[0]] = true;
            }

            foreach (array_keys($users) as $uid) {
                $uid = (int) $uid;
                if ($uid <= 0) {
                    continue;
                }

                $first_key = $uid . ':' . $cid . ':' . $first_id;
                $final_key = $uid . ':' . $cid . ':' . $final_id;

                $first_pcts = $grouped[$first_key] ?? [];
                $final_pcts = $grouped[$final_key] ?? [];

                $inc = null;
                if ($first_id === $final_id) {
                    if (!is_array($first_pcts) || count($first_pcts) < 2) {
                        continue;
                    }
                    $first = (float) $first_pcts[0];
                    $last = (float) $first_pcts[count($first_pcts) - 1];
                    $inc = $last - $first;
                } else {
                    if (!is_array($first_pcts) || empty($first_pcts) || !is_array($final_pcts) || empty($final_pcts)) {
                        continue;
                    }
                    $first = (float) $first_pcts[0];
                    $last = (float) $final_pcts[count($final_pcts) - 1];
                    $inc = $last - $first;
                }

                if ($inc === null || $inc <= 0) {
                    continue;
                }

                $user = self::user_payload($uid);
                $out[] = [
                    'user_id' => $uid,
                    'name' => (string) ($user['name'] ?? ''),
                    'avatar' => (string) ($user['avatar'] ?? ''),
                    'course_id' => $cid,
                    'course' => (string) get_the_title($cid),
                    'increase' => (float) $inc,
                ];
            }
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
        $ua = $wpdb->prefix . 'learndash_user_activity';

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ua.user_id, ua.course_id,
                        (GREATEST(0, ua.activity_completed - ua.activity_started) / 86400) AS days
                 FROM {$ua} ua
                 WHERE ua.activity_type = 'course'
                   AND ua.activity_status = 1
                   AND ua.activity_started IS NOT NULL
                   AND ua.activity_completed IS NOT NULL
                   AND ua.course_id IN ({$placeholders})
                 ORDER BY days {$dir}, ua.activity_completed {$dir}
                 LIMIT 10",
                $owned_courses
            ),
            ARRAY_A
        );

        $out = [];
        foreach ((array) $rows as $r) {
            $uid = (int) ($r['user_id'] ?? 0);
            $cid = (int) ($r['course_id'] ?? 0);
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
