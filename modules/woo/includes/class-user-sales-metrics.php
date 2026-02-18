<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX endpoint for the creator "VENTAS" dashboard.
 *
 * Computes totals for orders that contain products whose meta `product_owner`
 * equals the current user. Buckets by product category names:
 * - Cursos
 * - Libros
 * - Patronage
 */
class PL_Woo_User_Sales_Metrics
{
    const AJAX_ACTION = 'pl_get_user_sales_metrics';
    const NONCE_ACTION = 'pl_user_sales_metrics';

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

            // Expected: YYYY-MM-DD
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
            // wc_get_orders() commonly expects statuses without the "wc-" prefix.
            // Normalize to unprefixed slugs (completed, processing, etc.).
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
            if ($slug === 'patronage') {
                return 'patronage';
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
            'total' => 0.0,
            'courses' => 0.0,
            'books' => 0.0,
            'patronage' => 0.0,
        ];

        $series = []; // date => buckets

        foreach ($order_ids as $oid) {
            $order = wc_get_order($oid);
            if (!$order) {
                continue;
            }

            $created = $order->get_date_created();

            if (!$created) {
                continue;
            }

            // WC_DateTime mutates on setTimezone, so clone.
            $created_dt = (clone $created);
            $created_dt->setTimezone($tz);
            $day_key = $created_dt->format('Y-m-d');

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

                // --- Chile-specific Financial Breakdown ---
                // 1. Get total amount paid by customer for this line (Gross)
                $line_total_net = (float) $item->get_total();
                $line_total_tax = (float) $item->get_total_tax();
                $gross_price = $line_total_net + $line_total_tax;

                // 2. Exclude IVA (19%) manually to get the real Net Base
                $manual_net_base = $gross_price / 1.19;

                // 3. Transaction Fee (Flow: 3.0% of Gross)
                $flow_fee = $gross_price * 0.03;

                // 4. Platform Commission (e.g. 25% of Net Base)
                $politeia_rate = get_user_meta($owner_id, '_pl_commission_rate', true);
                if ($politeia_rate === '') {
                    $politeia_rate = 25.0;
                }
                $politeia_rate = (float) $politeia_rate;

                // 5. Calculate Shares
                $user_revenue_share = $manual_net_base * ((100 - $politeia_rate) / 100);

                // 6. Final User Gain (Revenue Share - Transaction Fee)
                $line_total = $user_revenue_share - $flow_fee;

                $totals[$bucket] += $line_total;
                $totals['total'] += $line_total;

                if (!isset($series[$day_key])) {
                    $series[$day_key] = ['courses' => 0.0, 'books' => 0.0, 'patronage' => 0.0];
                }
                $series[$day_key][$bucket] += $line_total;
            }
        }

        // Fill missing dates for chart continuity.
        $filled = [];
        $cursor = $start;
        while ($cursor <= $end) {
            $k = $cursor->format('Y-m-d');
            $filled[] = [
                'date' => $k,
                'courses' => (float) ($series[$k]['courses'] ?? 0),
                'books' => (float) ($series[$k]['books'] ?? 0),
                'patronage' => (float) ($series[$k]['patronage'] ?? 0),
            ];
            $cursor = $cursor->add(new DateInterval('P1D'));
        }

        $locale = str_replace('_', '-', (string) get_locale());
        $currency = function_exists('get_woocommerce_currency') ? (string) get_woocommerce_currency() : 'USD';

        wp_send_json_success([
            'timeframe' => $timeframe,
            'range' => [
                'start' => $start->format('Y-m-d'),
                'end' => $end->format('Y-m-d'),
            ],
            'currency' => $currency,
            'locale' => $locale,
            'totals' => [
                'total' => (float) round($totals['total']),
                'courses' => (float) round($totals['courses']),
                'books' => (float) round($totals['books']),
                'patronage' => (float) round($totals['patronage']),
            ],
            'series' => $filled,
        ]);
    }
}
