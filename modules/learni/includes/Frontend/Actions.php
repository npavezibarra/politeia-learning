<?php
/**
 * Frontend Actions logic for Learni.
 */

if (!defined('ABSPATH')) {
    exit;
}

final class PL_Learni_Frontend_Actions
{
    public static function checkout_course_url(int $course_id): string
    {
        return PL_Learni_Frontend_Templates::checkout_course_url($course_id);
    }

    public static function handle_checkout_course_nopriv(): void
    {
        $course_id = isset($_GET['course_id']) ? (int) $_GET['course_id'] : 0;
        $redirect = $course_id > 0 ? self::checkout_course_url($course_id) : home_url('/');
        wp_safe_redirect(wp_login_url($redirect));
        exit;
    }

    public static function handle_checkout_course(): void
    {
        if (!is_user_logged_in()) {
            self::handle_checkout_course_nopriv();
        }

        $course_id = isset($_GET['course_id']) ? (int) $_GET['course_id'] : 0;
        if ($course_id <= 0 || get_post_type($course_id) !== \Learni\PostTypes\Course::POST_TYPE) {
            wp_safe_redirect(home_url('/'));
            exit;
        }

        $product_id = (int) get_post_meta($course_id, 'learni_wc_product_id', true);
        if ($product_id <= 0 || !class_exists('WooCommerce') || !function_exists('wc_get_checkout_url')) {
            wp_safe_redirect((string) get_permalink($course_id));
            exit;
        }

        if (function_exists('wc_load_cart')) {
            wc_load_cart();
        }

        if (function_exists('WC') && WC() && isset(WC()->cart) && WC()->cart) {
            $cart_id = WC()->cart->generate_cart_id($product_id);
            $existing_key = $cart_id ? WC()->cart->find_product_in_cart($cart_id) : '';
            if (!$existing_key) {
                WC()->cart->add_to_cart($product_id, 1);
            }
        }

        wp_safe_redirect(wc_get_checkout_url());
        exit;
    }

    public static function handle_mark_lesson_complete_nopriv(): void
    {
        $redirect = isset($_POST['redirect_to']) ? (string) wp_unslash($_POST['redirect_to']) : '';
        if ($redirect === '') {
            $redirect = home_url('/');
        }
        wp_safe_redirect(wp_login_url($redirect));
        exit;
    }

    public static function handle_enroll_course_nopriv(): void
    {
        $redirect = isset($_POST['redirect_to']) ? (string) wp_unslash($_POST['redirect_to']) : '';
        if ($redirect === '') {
            $redirect = home_url('/');
        }
        wp_safe_redirect(wp_login_url($redirect));
        exit;
    }

    public static function handle_enroll_course(): void
    {
        $user_id = (int) get_current_user_id();
        $course_id = isset($_POST['course_id']) ? (int) $_POST['course_id'] : 0;
        $redirect = isset($_POST['redirect_to']) ? (string) wp_unslash($_POST['redirect_to']) : '';

        if ($redirect === '') {
            $redirect = wp_get_referer() ?: home_url('/');
        }

        if ($user_id <= 0) {
            wp_safe_redirect(wp_login_url($redirect));
            exit;
        }

        if ($course_id <= 0) {
            wp_safe_redirect($redirect);
            exit;
        }

        check_admin_referer('pl_learni_enroll_course_' . $course_id);

        $price = (float) get_post_meta($course_id, 'learni_price', true);
        if ($price > 0) {
            wp_safe_redirect($redirect);
            exit;
        }

        if (class_exists('\\Learni\\Database\\Enrollments')) {
            \Learni\Database\Enrollments::upsert(
                $user_id,
                $course_id,
                [
                    'status' => \Learni\Database\Enrollments::STATUS_ACTIVE,
                    'source' => \Learni\Database\Enrollments::SOURCE_DIRECT,
                ]
            );
        }

        wp_safe_redirect($redirect);
        exit;
    }

    public static function handle_mark_lesson_complete(): void
    {
        $user_id = (int) get_current_user_id();
        $lesson_id = isset($_POST['lesson_id']) ? (int) $_POST['lesson_id'] : 0;
        $redirect = isset($_POST['redirect_to']) ? (string) wp_unslash($_POST['redirect_to']) : '';

        if ($redirect === '') {
            $redirect = wp_get_referer() ?: home_url('/');
        }

        if ($user_id <= 0) {
            wp_safe_redirect(wp_login_url($redirect));
            exit;
        }

        if ($lesson_id <= 0) {
            wp_safe_redirect($redirect);
            exit;
        }

        check_admin_referer('pl_learni_complete_lesson_' . $lesson_id);

        $course_id = 0;
        global $wpdb;
        if ($wpdb) {
            $course_id = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT course_post_id
                     FROM {$wpdb->prefix}learni_course_items
                     WHERE item_type = %s AND item_ref_id = %d
                     ORDER BY id DESC
                     LIMIT 1",
                    'lesson',
                    $lesson_id
                )
            );
        }

        if ($course_id <= 0) {
            wp_safe_redirect($redirect);
            exit;
        }

        if (class_exists('\\Learni\\Access\\Access') && !\Learni\Access\Access::user_can_access_course($user_id, $course_id)) {
            wp_safe_redirect($redirect);
            exit;
        }

        $linear_order = PL_Learni_Frontend_Templates::course_linear_order_enabled($course_id);
        if ($linear_order && class_exists('\\Learni\\Courses\\Outline') && class_exists('\\Learni\\Database\\Progress')) {
            $lesson_ids = \Learni\Courses\Outline::lesson_ids($course_id);
            $lesson_index = PL_Learni_Frontend_Templates::lesson_index_map($lesson_ids);
            $pos = isset($lesson_index[$lesson_id]) ? (int) $lesson_index[$lesson_id] : -1;
            $completed = array_flip(\Learni\Database\Progress::completed_lesson_ids($user_id, $course_id));
            $max_unlocked = PL_Learni_Frontend_Templates::max_unlocked_lesson_index($lesson_ids, $completed, true);
            if ($pos >= 0 && $max_unlocked >= 0 && $pos > $max_unlocked) {
                wp_safe_redirect($redirect);
                exit;
            }
        }

        $now = current_time('mysql');
        $table = $wpdb->prefix . 'learni_progress';

        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$table} (user_id, course_post_id, lesson_post_id, status, completed_at, updated_at)
                 VALUES (%d, %d, %d, %s, %s, %s)
                 ON DUPLICATE KEY UPDATE course_post_id = VALUES(course_post_id), status = VALUES(status), completed_at = VALUES(completed_at), updated_at = VALUES(updated_at)",
                $user_id,
                $course_id,
                $lesson_id,
                \Learni\Database\Progress::STATUS_COMPLETE,
                $now,
                $now
            )
        );

        wp_safe_redirect($redirect);
        exit;
    }
}
