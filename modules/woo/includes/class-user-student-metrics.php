<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX endpoint for the creator "ESTUDIANTES" dashboard.
 *
 * Counts purchases (line item quantities) for products whose meta `product_owner`
 * equals the current user. Buckets by product category names:
 * - Cursos
 * - Libros
 *
 * Each purchase is treated as 1 "new student" for the purchase day.
 */
class PL_Woo_User_Student_Metrics
{
    const AJAX_ACTION = 'pl_get_user_student_metrics';
    const NONCE_ACTION = 'pl_user_student_metrics';

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

    public static function init(): void
    {
        add_action('wp_ajax_' . self::AJAX_ACTION, [__CLASS__, 'handle']);
        add_action('wp_ajax_nopriv_' . self::AJAX_ACTION, [__CLASS__, 'handle_nopriv']);
    }

    public static function handle_nopriv(): void
    {
        wp_send_json_error(['message' => 'unauthorized'], 401);
    }

    private static function parse_range(array $req): array
    {
        $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
        $now = new DateTimeImmutable('now', $tz);

        $timeframe = isset($req['timeframe']) ? sanitize_text_field((string) $req['timeframe']) : 'month';
        $timeframe = in_array($timeframe, ['day', 'week', 'month', 'year', 'custom'], true) ? $timeframe : 'month';

        $start = $now->setTime(0, 0, 0);
        $end = $now->setTime(23, 59, 59);

        if ($timeframe === 'day') {
            // Today.
        } elseif ($timeframe === 'week') {
            $start = $start->sub(new DateInterval('P6D'));
        } elseif ($timeframe === 'month') {
            $start = $start->sub(new DateInterval('P29D'));
        } elseif ($timeframe === 'year') {
            $start = $start->sub(new DateInterval('P1Y'));
        } else { // custom
            $s = isset($req['start_date']) ? sanitize_text_field((string) $req['start_date']) : '';
            $e = isset($req['end_date']) ? sanitize_text_field((string) $req['end_date']) : '';

            $ds = DateTimeImmutable::createFromFormat('Y-m-d', $s, $tz);
            $de = DateTimeImmutable::createFromFormat('Y-m-d', $e, $tz);
            if ($ds instanceof DateTimeImmutable && $de instanceof DateTimeImmutable) {
                $start = $ds->setTime(0, 0, 0);
                $end = $de->setTime(23, 59, 59);
            }
        }

        if ($end < $start) {
            $tmp = $start;
            $start = $end;
            $end = $tmp;
        }

        return [$timeframe, $start, $end, $tz];
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
            if ($slug === 'libros') {
                return 'books';
            }
        }

        return null;
    }

    public static function handle(): void
    {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'unauthorized'], 401);
        }

        if (!class_exists('WooCommerce') || !function_exists('wc_get_orders')) {
            wp_send_json_error(['message' => 'woocommerce_missing'], 400);
        }

        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        $user_id = get_current_user_id();
        [$timeframe, $start, $end, $tz] = self::parse_range($_REQUEST);

        $order_ids = wc_get_orders([
            'limit' => -1,
            'return' => 'ids',
            'type' => 'shop_order',
            'status' => self::paid_statuses(),
            'date_query' => [
                [
                    'after' => $start->format('Y-m-d H:i:s'),
                    'before' => $end->format('Y-m-d H:i:s'),
                    'inclusive' => true,
                ],
            ],
        ]);

        $totals = [
            'total' => 0,
            'courses' => 0,
            'books' => 0,
        ];

        $series = []; // date => buckets
        $unique_students = []; // user_id => true

        foreach ($order_ids as $oid) {
            $order = wc_get_order($oid);
            if (!$order) {
                continue;
            }

            $created = $order->get_date_created();
            if (!$created) {
                continue;
            }

            $created_dt = (clone $created);
            $created_dt->setTimezone($tz);
            $day_key = $created_dt->format('Y-m-d');

            $customer_id = method_exists($order, 'get_customer_id') ? (int) $order->get_customer_id() : 0;

            foreach ($order->get_items('line_item') as $item) {
                if (!is_a($item, 'WC_Order_Item_Product')) {
                    continue;
                }

                $product_id = (int) $item->get_product_id();
                $parent_id = (int) wp_get_post_parent_id($product_id);
                $base_product_id = $parent_id > 0 ? $parent_id : $product_id;

                $owner_id = (int) get_post_meta($base_product_id, 'product_owner', true);
                if ($owner_id !== (int) $user_id) {
                    continue;
                }

                $bucket = self::bucket_for_product($base_product_id);
                if (!$bucket) {
                    continue;
                }

                $quantity = (int) $item->get_quantity();
                if ($quantity <= 0) {
                    continue;
                }

                if ($customer_id > 0) {
                    $unique_students[$customer_id] = true;
                }

                $totals[$bucket] += $quantity;
                $totals['total'] += $quantity;

                if (!isset($series[$day_key])) {
                    $series[$day_key] = ['courses' => 0, 'books' => 0];
                }
                $series[$day_key][$bucket] += $quantity;
            }
        }

        // Completion metrics (LearnDash).
        $avg_completion_days = 0.0;
        $completed_courses = 0;
        $assessment_delta_pct = 0.0;

        $owned_courses = self::owned_course_ids((int) $user_id);
        if (!empty($owned_courses)) {
            global $wpdb;
            $ua = $wpdb->prefix . 'learndash_user_activity';
            $uam = $wpdb->prefix . 'learndash_user_activity_meta';

            $placeholders = implode(',', array_fill(0, count($owned_courses), '%d'));
            $start_ts = (int) $start->getTimestamp();
            $end_ts = (int) $end->getTimestamp();

            $completed_courses = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*)
                     FROM {$ua}
                     WHERE activity_type = 'course'
                       AND activity_status = 1
                       AND activity_started IS NOT NULL
                       AND activity_completed IS NOT NULL
                       AND activity_completed BETWEEN %d AND %d
                       AND course_id IN ({$placeholders})",
                    array_merge([$start_ts, $end_ts], $owned_courses)
                )
            );

            $avg_raw = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT AVG(GREATEST(0, activity_completed - activity_started) / 86400)
                     FROM {$ua}
                     WHERE activity_type = 'course'
                       AND activity_status = 1
                       AND activity_started IS NOT NULL
                       AND activity_completed IS NOT NULL
                       AND activity_completed BETWEEN %d AND %d
                       AND course_id IN ({$placeholders})",
                    array_merge([$start_ts, $end_ts], $owned_courses)
                )
            );

            if ($avg_raw !== null) {
                $avg_completion_days = max(0.0, (float) $avg_raw);
            }

            // Assessment delta (first vs final attempt on the linked quiz).
            $quiz_ids = self::course_quiz_ids($owned_courses);
            if (!empty($quiz_ids)) {
                $quiz_placeholders = implode(',', array_fill(0, count($quiz_ids), '%d'));

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
                           AND ua.activity_completed BETWEEN %d AND %d
                           AND ua.course_id IN ({$placeholders})
                           AND ua.post_id IN ({$quiz_placeholders})
                         ORDER BY ua.user_id ASC, ua.course_id ASC, ua.post_id ASC, ua.activity_completed ASC",
                        array_merge([$start_ts, $end_ts], $owned_courses, $quiz_ids)
                    ),
                    ARRAY_A
                );

                $grouped = [];
                foreach ((array) $rows as $r) {
                    $uid = (int) ($r['user_id'] ?? 0);
                    $cid = (int) ($r['course_id'] ?? 0);
                    $qid = (int) ($r['quiz_id'] ?? 0);
                    if ($uid <= 0 || $cid <= 0 || $qid <= 0) {
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

                $sum = 0.0;
                $n = 0;
                foreach ($grouped as $pcts) {
                    if (!is_array($pcts) || count($pcts) < 2) {
                        continue;
                    }
                    $first = (float) $pcts[0];
                    $last = (float) $pcts[count($pcts) - 1];
                    $sum += ($last - $first);
                    $n++;
                }
                if ($n > 0) {
                    $assessment_delta_pct = $sum / $n;
                }
            }
        }

        $unique_students_count = count($unique_students);
        $avg_courses_completed_per_student = $unique_students_count > 0 ? ($completed_courses / $unique_students_count) : 0.0;

        $totals['avg_course_completion_days'] = $avg_completion_days;
        $totals['avg_courses_completed_per_student'] = $avg_courses_completed_per_student;
        $totals['assessment_delta_pct'] = $assessment_delta_pct;

        $filled = [];
        $cursor = $start;
        while ($cursor <= $end) {
            $k = $cursor->format('Y-m-d');
            $filled[] = [
                'date' => $k,
                'courses' => (int) ($series[$k]['courses'] ?? 0),
                'books' => (int) ($series[$k]['books'] ?? 0),
            ];
            $cursor = $cursor->add(new DateInterval('P1D'));
        }

        $locale = str_replace('_', '-', (string) get_locale());

        wp_send_json_success([
            'timeframe' => $timeframe,
            'range' => [
                'start' => $start->format('Y-m-d'),
                'end' => $end->format('Y-m-d'),
            ],
            'locale' => $locale,
            'totals' => $totals,
            'series' => $filled,
        ]);
    }
}
