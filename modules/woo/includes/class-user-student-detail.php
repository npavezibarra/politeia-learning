<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX endpoint for single student profile detail view.
 * Fetches courses owned by the current creator and completed/purchased by the student.
 */
class PL_Woo_User_Student_Detail
{
    const AJAX_ACTION = 'pl_get_user_student_detail';
    const NONCE_ACTION = 'pl_user_student_detail';

    public static function init(): void
    {
        add_action('wp_ajax_' . self::AJAX_ACTION, [__CLASS__, 'handle']);
    }

    public static function handle(): void
    {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'unauthorized'], 401);
        }

        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        $student_id = isset($_REQUEST['student_user_id']) ? (int) $_REQUEST['student_user_id'] : 0;
        if ($student_id <= 0) {
            wp_send_json_error(['message' => 'missing_student_id'], 400);
        }

        $creator_id = get_current_user_id();

        // 1. Get all products owned by this creator
        $owned_products = get_posts([
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => 'product_owner',
                    'value' => $creator_id,
                    'compare' => '=',
                ],
            ],
        ]);

        if (empty($owned_products)) {
            wp_send_json_success(['courses' => [], 'books' => []]);
            return;
        }

        if (!class_exists('WooCommerce')) {
            wp_send_json_error(['message' => 'woocommerce_missing'], 400);
        }

        // 2. Find which of these were purchased by the student
        $orders = wc_get_orders([
            'limit' => -1,
            'customer' => $student_id,
            'status' => ['wc-completed', 'wc-processing'],
            'type' => 'shop_order',
        ]);

        $purchased_product_ids = [];
        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                if (!is_a($item, 'WC_Order_Item_Product')) {
                    continue;
                }
                $pid = (int) $item->get_product_id();
                $parent_id = (int) wp_get_post_parent_id($pid);
                $base_product_id = $parent_id > 0 ? $parent_id : $pid;

                if (in_array($base_product_id, $owned_products, true)) {
                    $purchased_product_ids[] = $base_product_id;
                }
            }
        }
        $purchased_product_ids = array_unique($purchased_product_ids);

        $courses_data = [];
        $books_data = [];

        foreach ($purchased_product_ids as $pid) {
            $terms = get_the_terms($pid, 'product_cat');
            $type = 'other';
            if ($terms && !is_wp_error($terms)) {
                foreach ($terms as $t) {
                    $slug = sanitize_title($t->name);
                    if ($slug === 'cursos') {
                        $type = 'course';
                        break;
                    }
                    if ($slug === 'libros') {
                        $type = 'book';
                        break;
                    }
                }
            }

            if ($type === 'other') {
                continue;
            }

            // Get related Learni courses
            $related = get_post_meta($pid, '_learni_related_course', true);
            if (empty($related)) {
                $related = get_post_meta($pid, '_related_course', true);
            }
            $cids = is_array($related) ? $related : [$related];

            foreach ($cids as $cid) {
                $cid = (int) $cid;
                if ($cid <= 0) {
                    if ($type === 'book') {
                        $books_data[] = [
                            'id' => $pid,
                            'title' => get_the_title($pid),
                            'first_quiz' => '—',
                            'final_quiz' => '—',
                        ];
                    }
                    continue;
                }

                $course_post = get_post($cid);
                if (!$course_post)
                    continue;

                // Progress (only for courses)
                $progress = 0;
                if ($type === 'course' && class_exists('Learni\Database\Progress')) {
                    $p_data = \Learni\Database\Progress::course_summary($student_id, $cid);
                    $progress = isset($p_data['percent']) ? (int) $p_data['percent'] : 0;
                }

                // Initial and Final Quizzes
                $first_quiz_id = (int) get_post_meta($cid, '_first_quiz_id', true);
                $final_quiz_id = (int) get_post_meta($cid, '_final_quiz_id', true);

                $first_score = '—';
                $first_date = '';
                $first_ts = 0;
                $final_score = '—';
                $final_date = '';
                $final_ts = 0;

                if ($first_quiz_id > 0) {
                    $first_data = self::get_best_quiz_score($student_id, $cid, $first_quiz_id);
                    $first_score = $first_data['score'];
                    $first_date = $first_data['date'];
                    $first_ts = $first_data['timestamp'];
                }
                if ($final_quiz_id > 0) {
                    $final_data = self::get_best_quiz_score($student_id, $cid, $final_quiz_id);
                    $final_score = $final_data['score'];
                    $final_date = $final_data['date'];
                    $final_ts = $final_data['timestamp'];
                }

                if ($type === 'course') {
                    $days_diff = '—';
                    if ($first_ts > 0 && $final_ts > 0 && $final_ts >= $first_ts) {
                        $diff = $final_ts - $first_ts;
                        $days = floor($diff / 86400);
                        $days_diff = $days . ' ' . _n('día', 'días', $days, 'politeia-learning');
                    }

                    $courses_data[] = [
                        'id' => $cid,
                        'title' => get_the_title($cid),
                        'image' => get_the_post_thumbnail_url($cid, 'thumbnail') ?: get_the_post_thumbnail_url($pid, 'thumbnail'),
                        'progress' => $progress,
                        'first_quiz' => $first_score,
                        'first_quiz_date' => $first_date,
                        'final_quiz' => $final_score,
                        'final_quiz_date' => $final_date,
                        'days_delta' => $days_diff,
                    ];
                } else {
                    // Books
                    $reading_stats = self::get_reading_stats($student_id, get_the_title($pid));
                    $books_data[] = [
                        'id' => $pid,
                        'title' => get_the_title($pid),
                        'first_quiz' => $first_score,
                        'final_quiz' => $final_score,
                        'sessions' => $reading_stats['sessions'],
                        'pages_read' => $reading_stats['pages_read'],
                    ];
                }
            }
        }

        wp_send_json_success([
            'courses' => $courses_data,
            'books' => $books_data
        ]);
    }

    /**
     * Get reading sessions count and total pages read for a student and a book title.
     */
    protected static function get_reading_stats($user_id, $book_title)
    {
        global $wpdb;
        $stats = ['sessions' => 0, 'pages_read' => 0];

        $books_table = $wpdb->prefix . 'politeia_books';
        $user_books_table = $wpdb->prefix . 'politeia_user_books';
        $sessions_table = $wpdb->prefix . 'politeia_reading_sessions';

        // Check if tables exist (Politeia Bookshelf active)
        if ($wpdb->get_var("SHOW TABLES LIKE '$books_table'") !== $books_table) {
            return $stats;
        }

        // 1. Find the canonical book ID by title
        $book_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $books_table WHERE title = %s LIMIT 1",
            $book_title
        ));

        if (!$book_id) {
            $normalized_title = strtolower(trim($book_title));
            $book_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $books_table WHERE normalized_title = %s LIMIT 1",
                $normalized_title
            ));
        }

        if (!$book_id) {
            return $stats;
        }

        // 2. Find the user_book_id for this user
        $user_book_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $user_books_table WHERE user_id = %d AND book_id = %d AND deleted_at IS NULL LIMIT 1",
            $user_id,
            $book_id
        ));

        if (!$user_book_id) {
            return $stats;
        }

        // 3. Count sessions and sum pages
        $data = $wpdb->get_row($wpdb->prepare(
            "SELECT COUNT(*) as sessions, SUM(GREATEST(0, CAST(end_page AS SIGNED) - CAST(start_page AS SIGNED) + 1)) as pages_read 
             FROM $sessions_table 
             WHERE user_id = %d AND user_book_id = %d AND deleted_at IS NULL",
            $user_id,
            $user_book_id
        ));

        if ($data) {
            $stats['sessions'] = (int) $data->sessions;
            $stats['pages_read'] = (int) $data->pages_read;
        }

        return $stats;
    }

    private static function get_best_quiz_score(int $user_id, int $course_id, int $quiz_id): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'learni_quiz_attempts';

        // We want the highest score. If tied, we'll take the earliest completion of that score.
        $query = $wpdb->prepare(
            "SELECT score, submitted_at
             FROM {$table}
             WHERE user_id = %d
               AND quiz_id = %d
               AND status = 'submitted'
             ORDER BY score DESC, submitted_at ASC
             LIMIT 1",
            $user_id,
            $quiz_id
        );

        $row = $wpdb->get_row($query);

        if (!$row) {
            return [
                'score' => '—',
                'date' => '',
                'timestamp' => 0
            ];
        }

        $date_str = '';
        $ts = strtotime((string) $row->submitted_at);
        if ($ts > 0) {
            $date_str = date_i18n(get_option('date_format'), $ts);
        }

        return [
            'score' => round((float) $row->score) . '%',
            'date' => $date_str,
            'timestamp' => $ts
        ];
    }
}
