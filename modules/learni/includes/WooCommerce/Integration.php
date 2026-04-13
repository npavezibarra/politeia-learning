<?php

namespace Learni\WooCommerce;

use Learni\Database\Enrollments;

final class Integration
{
    public const PRODUCT_META_COURSE_ID = '_learni_course_id';

    public static function maybe_init(): void
    {
        if (!class_exists('WooCommerce')) {
            return;
        }

        add_action('woocommerce_order_status_processing', [__CLASS__, 'handle_paid_order']);
        add_action('woocommerce_order_status_completed', [__CLASS__, 'handle_paid_order']);
    }

    public static function handle_paid_order(int $order_id): void
    {
        if ($order_id <= 0 || !function_exists('wc_get_order')) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $user_id = (int) $order->get_user_id();
        if ($user_id <= 0) {
            return;
        }

        $course_ids = self::courses_from_order($order);
        if (empty($course_ids)) {
            return;
        }

        foreach ($course_ids as $course_id) {
            Enrollments::upsert(
                $user_id,
                $course_id,
                [
                    'status' => Enrollments::STATUS_ACTIVE,
                    'source' => Enrollments::SOURCE_WOOCOMMERCE,
                    'woocommerce_order_id' => $order_id,
                    'payment_provider' => 'woocommerce',
                    'payment_reference' => (string) $order_id,
                    'payment_currency' => method_exists($order, 'get_currency') ? (string) $order->get_currency() : null,
                    'started_at' => current_time('mysql'),
                ]
            );
        }
    }

    /**
     * @return int[]
     */
    private static function courses_from_order(\WC_Order $order): array
    {
        $course_ids = [];
        foreach ($order->get_items('line_item') as $item) {
            $product = $item->get_product();
            if (!$product) {
                continue;
            }

            $product_id = (int) $product->get_id();
            if ($product_id <= 0) {
                continue;
            }

            $course_id = (int) get_post_meta($product_id, self::PRODUCT_META_COURSE_ID, true);
            if ($course_id > 0) {
                $course_ids[] = $course_id;
            }
        }

        return array_values(array_unique(array_map('absint', $course_ids)));
    }
}

