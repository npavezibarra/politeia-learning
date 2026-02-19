<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX endpoint for the creator "VENTAS > LIST" tables.
 *
 * Returns transaction-level rows (order line items) for products whose meta
 * `product_owner` equals the current user.
 */
class PL_Woo_User_Sales_Table
{
    const AJAX_ACTION = 'pl_get_user_sales_table';
    const NONCE_ACTION = 'pl_user_sales_table';

    public static function init(): void
    {
        add_action('wp_ajax_' . self::AJAX_ACTION, [__CLASS__, 'handle']);
        add_action('wp_ajax_nopriv_' . self::AJAX_ACTION, [__CLASS__, 'handle_nopriv']);
    }

    public static function handle_nopriv(): void
    {
        wp_send_json_error(['message' => 'unauthorized'], 401);
    }

    private static function all_statuses(): array
    {
        $out = [];
        if (function_exists('wc_get_order_statuses')) {
            foreach (array_keys((array) wc_get_order_statuses()) as $s) {
                $s = (string) $s;
                $s = preg_replace('/^wc-/', '', $s);
                if ($s !== '') {
                    $out[] = $s;
                }
            }
        }

        $out = array_values(array_unique($out));
        return !empty($out) ? $out : ['pending', 'processing', 'completed', 'on-hold', 'cancelled', 'refunded', 'failed'];
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
                return 'course';
            }
            if ($slug === 'libros') {
                return 'book';
            }
            if ($slug === 'patronage') {
                return 'patronage';
            }
        }

        return null;
    }

    private static function simplified_status(string $wc_status): string
    {
        $s = preg_replace('/^wc-/', '', strtolower($wc_status));
        if ($s === 'refunded') {
            return 'refunded';
        }

        if (in_array($s, self::paid_statuses(), true)) {
            return 'paid';
        }

        if (in_array($s, ['pending', 'on-hold', 'failed', 'cancelled'], true)) {
            return 'pending';
        }

        return $s ?: 'unknown';
    }

    private static function customer_full_name(int $customer_id, string $fallback_name, string $email): string
    {
        $name = trim($fallback_name);

        if ($customer_id > 0) {
            $u = get_user_by('id', $customer_id);
            if ($u) {
                $first = trim((string) get_user_meta($customer_id, 'first_name', true));
                $last = trim((string) get_user_meta($customer_id, 'last_name', true));
                $full = trim($first . ' ' . $last);
                if ($full !== '') {
                    return $full;
                }

                $display = trim((string) $u->display_name);
                if ($display !== '') {
                    $name = $display;
                }
            }
        }

        if ($name !== '') {
            return $name;
        }

        if ($email !== '') {
            $local = strstr($email, '@', true);
            return $local !== false && $local !== '' ? $local : $email;
        }

        return __('Cliente', 'politeia-learning');
    }

    private static function line_creator_revenue(WC_Order_Item_Product $item, int $owner_id): float
    {
        $product_id = (int) $item->get_product_id();
        $parent_id = (int) wp_get_post_parent_id($product_id);
        $base_product_id = $parent_id > 0 ? $parent_id : $product_id;

        $owner_meta = (int) get_post_meta($base_product_id, 'product_owner', true);
        if ($owner_meta !== (int) $owner_id) {
            return 0.0;
        }

        $line_total_net = (float) $item->get_total();
        $line_total_tax = (float) $item->get_total_tax();
        $gross_price = $line_total_net + $line_total_tax;

        $iva_rate = (float) get_option('pl_financial_iva', 19);
        $iva_divisor = 1 + ($iva_rate / 100);
        $manual_net_base = $iva_divisor > 0 ? ($gross_price / $iva_divisor) : $gross_price;

        $gateway_rate = (float) get_option('pl_financial_gateway_fee', 3);
        $flow_fee = $gross_price * ($gateway_rate / 100);

        $politeia_rate = get_user_meta($owner_id, '_pl_commission_rate', true);
        if ($politeia_rate === '') {
            $politeia_rate = 25.0;
        }
        $politeia_rate = (float) $politeia_rate;

        $user_revenue_share = $manual_net_base * ((100 - $politeia_rate) / 100);
        $line_total = $user_revenue_share - $flow_fee;

        return (float) $line_total;
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

        $owner_id = get_current_user_id();
        $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');

        $order_ids = wc_get_orders([
            'limit' => 300,
            'return' => 'ids',
            'type' => 'shop_order',
            'status' => self::all_statuses(),
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        $rows = [];
        foreach ($order_ids as $oid) {
            $order = wc_get_order($oid);
            if (!$order) {
                continue;
            }

            $created = $order->get_date_created();
            $created_str = '';
            if ($created) {
                $created_dt = (clone $created);
                $created_dt->setTimezone($tz);
                $created_str = $created_dt->format('Y-m-d');
            }

            $customer_id = (int) $order->get_customer_id();
            $email = (string) $order->get_billing_email();
            if ($email === '' && $customer_id > 0) {
                $u = get_user_by('id', $customer_id);
                if ($u) {
                    $email = (string) $u->user_email;
                }
            }

            $billing_name = trim((string) $order->get_formatted_billing_full_name());
            $name = self::customer_full_name($customer_id, $billing_name, $email);

            $order_status = (string) $order->get_status();
            $status = self::simplified_status($order_status);

            foreach ($order->get_items('line_item') as $item) {
                if (!is_a($item, 'WC_Order_Item_Product')) {
                    continue;
                }

                $product_id = (int) $item->get_product_id();
                $parent_id = (int) wp_get_post_parent_id($product_id);
                $base_product_id = $parent_id > 0 ? $parent_id : $product_id;
                $owner_meta = (int) get_post_meta($base_product_id, 'product_owner', true);
                if ($owner_meta !== (int) $owner_id) {
                    continue;
                }

                $bucket = self::bucket_for_product($base_product_id);
                if (!$bucket) {
                    continue;
                }

                $rows[] = [
                    'userId' => $customer_id,
                    'customerKey' => $customer_id > 0 ? (string) $customer_id : $email,
                    'name' => $name,
                    'email' => $email,
                    'product' => (string) $item->get_name(),
                    'productType' => $bucket,
                    'orderId' => (string) $order->get_order_number(),
                    'status' => $status,
                    'paid' => (float) round(self::line_creator_revenue($item, $owner_id)),
                    'currency' => function_exists('get_woocommerce_currency') ? (string) get_woocommerce_currency() : 'USD',
                    'date' => $created_str,
                ];
            }
        }

        $locale = str_replace('_', '-', (string) get_locale());
        $currency = function_exists('get_woocommerce_currency') ? (string) get_woocommerce_currency() : 'USD';

        wp_send_json_success([
            'locale' => $locale,
            'currency' => $currency,
            'rows' => $rows,
        ]);
    }
}
