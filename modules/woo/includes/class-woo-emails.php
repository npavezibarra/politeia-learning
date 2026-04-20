<?php
/**
 * Custom WooCommerce emails for Politeia Learning.
 *
 * Replaces ALL default WooCommerce emails by:
 * - Disabling built-in Woo email recipients + enabled flags.
 * - Sending our own HTML emails via wp_mail() and module templates.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class PL_Woo_Emails
{
    private const ORDER_META_ACCESS_SENT = '_pl_wc_access_email_sent';
    private const ORDER_META_BACS_SENT = '_pl_wc_bacs_on_hold_email_sent';
    private const ORDER_META_ADMIN_NEW_SENT = '_pl_wc_admin_new_order_email_sent';
    private const ORDER_META_ADMIN_CANCELLED_SENT = '_pl_wc_admin_cancelled_order_email_sent';
    private const ORDER_META_ADMIN_FAILED_SENT = '_pl_wc_admin_failed_order_email_sent';
    private const ORDER_META_CUSTOMER_PARTIAL_REFUND_SENT = '_pl_wc_customer_partial_refund_email_sent';
    private const ORDER_META_CUSTOMER_PAYMENT_RETRY_SENT = '_pl_wc_customer_payment_retry_email_sent';

    private const PRODUCT_META_COURSE_ID = '_learni_course_id';
    private const PRODUCT_META_COURSE_ID_FALLBACK = '_related_course_id';
    private const PRODUCT_META_COURSE_IDS_FALLBACK = '_related_course';

    public static function init(): void
    {
        if (!class_exists('WooCommerce')) {
            return;
        }

        $instance = new self();

        // Disable ALL default WooCommerce emails we are replacing.
        $wc_email_ids = [
            'new_order',
            'cancelled_order',
            'failed_order',
            'customer_on_hold_order',
            'customer_processing_order',
            'customer_completed_order',
            'customer_refunded_order',
            'customer_partially_refunded_order',
            'customer_invoice',
            'customer_note',
            'customer_payment_retry',
            'customer_reset_password',
            'customer_new_account',
            'low_stock',
            'no_stock',
            'backorder',
        ];

        foreach ($wc_email_ids as $id) {
            add_filter("woocommerce_email_recipient_{$id}", '__return_empty_string', 9999);
            add_filter("woocommerce_email_enabled_{$id}", '__return_false', 9999);
        }

        // Customer: bank transfer on-hold.
        add_action('woocommerce_order_status_on-hold', [$instance, 'maybe_send_bank_transfer_on_hold'], 10, 2);

        // Customer: access confirmed (processing/completed).
        add_action('woocommerce_order_status_processing', [$instance, 'maybe_send_access_confirmed'], 10, 2);
        add_action('woocommerce_order_status_completed', [$instance, 'maybe_send_access_confirmed'], 10, 2);

        // Admin: new order.
        add_action('woocommerce_new_order', [$instance, 'maybe_send_admin_new_order'], 20, 1);
        add_action('woocommerce_checkout_order_processed', [$instance, 'maybe_send_admin_new_order'], 20, 1);

        // Admin: cancelled/failed.
        add_action('woocommerce_order_status_cancelled', [$instance, 'maybe_send_admin_cancelled'], 20, 2);
        add_action('woocommerce_order_status_failed', [$instance, 'maybe_send_admin_failed'], 20, 2);

        // Customer: partially refunded.
        add_action('woocommerce_order_partially_refunded', [$instance, 'maybe_send_customer_partially_refunded'], 20, 2);

        // Customer: payment retry.
        add_action('woocommerce_payment_retry', [$instance, 'maybe_send_customer_payment_retry'], 20, 1);
        add_action('woocommerce_order_payment_retry', [$instance, 'maybe_send_customer_payment_retry'], 20, 1);

        // Admin: stock.
        add_action('woocommerce_low_stock', [$instance, 'send_admin_low_stock'], 20, 1);
        add_action('woocommerce_no_stock', [$instance, 'send_admin_no_stock'], 20, 1);
        add_action('woocommerce_product_on_backorder', [$instance, 'send_admin_backorder'], 20, 2);
    }

    private static function templates_dir(): string
    {
        return plugin_dir_path(__FILE__) . '../templates/';
    }

    private static function email_templates_dir(): string
    {
        return self::templates_dir() . 'emails/';
    }

    /**
     * @param array<string,mixed> $vars
     */
    private function render_email_template(string $file, array $vars = []): string
    {
        $file = ltrim($file, '/');
        $path = self::email_templates_dir() . $file;
        if (!file_exists($path)) {
            return '';
        }

        // Email Log attribution.
        if (class_exists('PL_Email_Log_Manager')) {
            PL_Email_Log_Manager::set_last_template_file($path);
        }

        if (!empty($vars)) {
            // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
            extract($vars, EXTR_SKIP);
        }

        ob_start();
        include $path;
        return (string) ob_get_clean();
    }

    private function send_html_mail(string $to, string $subject, string $html): bool
    {
        $to = sanitize_email($to);
        if ($to === '' || !is_email($to) || trim($html) === '') {
            return false;
        }

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        return (bool) wp_mail($to, $subject, $html, $headers);
    }

    private function get_admin_email(): string
    {
        $email = (string) get_option('admin_email');
        return is_email($email) ? $email : '';
    }

    private function get_site_logo_url(): string
    {
        $logo_id = function_exists('get_theme_mod') ? absint(get_theme_mod('custom_logo')) : 0;
        if ($logo_id > 0 && function_exists('wp_get_attachment_image_url')) {
            $url = wp_get_attachment_image_url($logo_id, 'full');
            if (is_string($url) && $url !== '') {
                return $url;
            }
        }

        if (function_exists('get_site_icon_url')) {
            $url = (string) get_site_icon_url(256);
            if ($url !== '') {
                return $url;
            }
        }

        return '';
    }

    private static function is_course_id(int $post_id): bool
    {
        return $post_id > 0 && get_post_type($post_id) === 'learni_course';
    }

    /**
     * @return int[]
     */
    private static function course_ids_from_order(WC_Order $order): array
    {
        $course_ids = [];
        foreach ($order->get_items('line_item') as $item) {
            if (!$item instanceof WC_Order_Item_Product) {
                continue;
            }

            $product = $item->get_product();
            if (!$product) {
                continue;
            }

            $product_id = (int) $product->get_id();
            if ($product_id <= 0) {
                continue;
            }

            $cid = (int) get_post_meta($product_id, self::PRODUCT_META_COURSE_ID, true);
            if ($cid <= 0) {
                $cid = (int) get_post_meta($product_id, self::PRODUCT_META_COURSE_ID_FALLBACK, true);
            }

            if ($cid > 0 && self::is_course_id($cid)) {
                $course_ids[] = $cid;
                continue;
            }

            $fallback = get_post_meta($product_id, self::PRODUCT_META_COURSE_IDS_FALLBACK, true);
            if (is_array($fallback)) {
                foreach ($fallback as $maybe_id) {
                    $maybe_id = absint($maybe_id);
                    if ($maybe_id > 0 && self::is_course_id($maybe_id)) {
                        $course_ids[] = $maybe_id;
                    }
                }
            }
        }

        $course_ids = array_values(array_unique(array_map('absint', $course_ids)));
        return array_filter($course_ids);
    }

    /**
     * @return array<int,array{url:string,label:string,context:string}>
     */
    public static function get_access_links(WC_Order $order): array
    {
        $links = [];
        $course_ids = self::course_ids_from_order($order);
        foreach ($course_ids as $course_id) {
            $url = (string) get_permalink($course_id);
            if ($url === '') {
                continue;
            }
            $links[] = [
                'url' => $url,
                'label' => __('Ir al curso', 'politeia-learning'),
                'context' => (string) get_the_title($course_id),
            ];
        }

        // Fallback: for physical-only orders include a "View order" link.
        if (empty($links) && method_exists($order, 'get_view_order_url')) {
            $view = (string) $order->get_view_order_url();
            if ($view !== '') {
                $links[] = [
                    'url' => $view,
                    'label' => __('Ver pedido', 'politeia-learning'),
                    'context' => '',
                ];
            }
        }

        // Deduplicate by URL.
        $seen = [];
        $unique = [];
        foreach ($links as $link) {
            if ($link['url'] === '' || isset($seen[$link['url']])) {
                continue;
            }
            $seen[$link['url']] = true;
            $unique[] = $link;
        }
        return $unique;
    }

    public static function identify_order_type(WC_Order $order): string
    {
        $has_physical = false;
        $has_course = false;

        foreach ($order->get_items('line_item') as $item) {
            if (!$item instanceof WC_Order_Item_Product) {
                continue;
            }
            $product = $item->get_product();
            if ($product && method_exists($product, 'needs_shipping') && $product->needs_shipping()) {
                $has_physical = true;
            }
        }

        $has_course = !empty(self::course_ids_from_order($order));

        // Priority: show course design when any course exists.
        if ($has_course) {
            return 'course';
        }
        if ($has_physical) {
            return 'physical';
        }
        return 'physical';
    }

    public function maybe_send_bank_transfer_on_hold(int $order_id, $order = null): void
    {
        $order = $order instanceof WC_Order ? $order : (function_exists('wc_get_order') ? wc_get_order($order_id) : null);
        if (!$order) {
            return;
        }

        if ($order->get_meta(self::ORDER_META_BACS_SENT) === '1') {
            return;
        }

        $method_id = (string) $order->get_payment_method();
        if ($method_id !== 'bacs') {
            return;
        }

        $bank_details = [];
        if (function_exists('WC') && WC() && isset(WC()->payment_gateways) && method_exists(WC()->payment_gateways, 'get_available_payment_gateways')) {
            $gateways = WC()->payment_gateways->get_available_payment_gateways();
            if (is_array($gateways) && isset($gateways['bacs'])) {
                $bacs = $gateways['bacs'];
                $accounts = is_object($bacs) && isset($bacs->account_details) ? $bacs->account_details : null;
                if (is_array($accounts) && !empty($accounts) && is_array($accounts[0])) {
                    $bank_details = $accounts[0];
                }
            }
        }

        $to = (string) $order->get_billing_email();
        if (!is_email($to)) {
            return;
        }

        $logo_url = $this->get_site_logo_url();
        $subject = sprintf(__('Recibimos tu pedido #%s - Pendiente de transferencia', 'politeia-learning'), $order->get_order_number());
        $html = $this->render_email_template('wc-bank-transfer-on-hold.php', [
            'order' => $order,
            'logo_url' => $logo_url,
            'access_links' => self::get_access_links($order),
            'bank_details' => $bank_details,
        ]);

        if ($this->send_html_mail($to, $subject, $html)) {
            $order->update_meta_data(self::ORDER_META_BACS_SENT, '1');
            $order->save();
        }
    }

    public function maybe_send_access_confirmed(int $order_id, $order = null): void
    {
        $order = $order instanceof WC_Order ? $order : (function_exists('wc_get_order') ? wc_get_order($order_id) : null);
        if (!$order) {
            return;
        }

        if ($order->get_meta(self::ORDER_META_ACCESS_SENT) === '1') {
            return;
        }

        $to = (string) $order->get_billing_email();
        if (!is_email($to)) {
            return;
        }

        $logo_url = $this->get_site_logo_url();
        $subject = sprintf(__('Pedido confirmado #%s', 'politeia-learning'), $order->get_order_number());
        $html = $this->render_email_template('wc-new-order-custom.php', [
            'order' => $order,
            'logo_url' => $logo_url,
            'view' => self::identify_order_type($order),
            'access_links' => self::get_access_links($order),
        ]);

        if ($this->send_html_mail($to, $subject, $html)) {
            $order->update_meta_data(self::ORDER_META_ACCESS_SENT, '1');
            $order->save();
        }
    }

    public function maybe_send_admin_new_order(int $order_id): void
    {
        if ($order_id <= 0 || !function_exists('wc_get_order')) {
            return;
        }
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        if ($order->get_meta(self::ORDER_META_ADMIN_NEW_SENT) === '1') {
            return;
        }

        $to = $this->get_admin_email();
        if ($to === '') {
            return;
        }

        $logo_url = $this->get_site_logo_url();
        $subject = sprintf(__('Nueva venta #%s', 'politeia-learning'), $order->get_order_number());
        $html = $this->render_email_template('wc-admin-new-order.php', [
            'order' => $order,
            'logo_url' => $logo_url,
        ]);

        if ($this->send_html_mail($to, $subject, $html)) {
            $order->update_meta_data(self::ORDER_META_ADMIN_NEW_SENT, '1');
            $order->save();
        }
    }

    public function maybe_send_admin_cancelled(int $order_id, $order = null): void
    {
        $order = $order instanceof WC_Order ? $order : (function_exists('wc_get_order') ? wc_get_order($order_id) : null);
        if (!$order) {
            return;
        }

        if ($order->get_meta(self::ORDER_META_ADMIN_CANCELLED_SENT) === '1') {
            return;
        }

        $to = $this->get_admin_email();
        if ($to === '') {
            return;
        }

        $logo_url = $this->get_site_logo_url();
        $subject = sprintf(__('Pedido cancelado #%s', 'politeia-learning'), $order->get_order_number());
        $html = $this->render_email_template('wc-admin-cancelled-order.php', [
            'order' => $order,
            'logo_url' => $logo_url,
        ]);

        if ($this->send_html_mail($to, $subject, $html)) {
            $order->update_meta_data(self::ORDER_META_ADMIN_CANCELLED_SENT, '1');
            $order->save();
        }
    }

    public function maybe_send_admin_failed(int $order_id, $order = null): void
    {
        $order = $order instanceof WC_Order ? $order : (function_exists('wc_get_order') ? wc_get_order($order_id) : null);
        if (!$order) {
            return;
        }

        if ($order->get_meta(self::ORDER_META_ADMIN_FAILED_SENT) === '1') {
            return;
        }

        $to = $this->get_admin_email();
        if ($to === '') {
            return;
        }

        $logo_url = $this->get_site_logo_url();
        $subject = sprintf(__('Pedido fallido #%s', 'politeia-learning'), $order->get_order_number());
        $html = $this->render_email_template('wc-admin-failed-order.php', [
            'order' => $order,
            'logo_url' => $logo_url,
        ]);

        if ($this->send_html_mail($to, $subject, $html)) {
            $order->update_meta_data(self::ORDER_META_ADMIN_FAILED_SENT, '1');
            $order->save();
        }
    }

    public function maybe_send_customer_partially_refunded(int $order_id, $refund_id = 0): void
    {
        if ($order_id <= 0 || !function_exists('wc_get_order')) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        if ($order->get_meta(self::ORDER_META_CUSTOMER_PARTIAL_REFUND_SENT) === '1') {
            return;
        }

        $to = (string) $order->get_billing_email();
        if (!is_email($to)) {
            return;
        }

        $refund = ($refund_id > 0 && function_exists('wc_get_order')) ? wc_get_order($refund_id) : null;

        $logo_url = $this->get_site_logo_url();
        $subject = sprintf(__('Reembolso parcial del pedido #%s', 'politeia-learning'), $order->get_order_number());
        $html = $this->render_email_template('wc-customer-partially-refunded-order.php', [
            'order' => $order,
            'refund' => $refund,
            'logo_url' => $logo_url,
        ]);

        if ($this->send_html_mail($to, $subject, $html)) {
            $order->update_meta_data(self::ORDER_META_CUSTOMER_PARTIAL_REFUND_SENT, '1');
            $order->save();
        }
    }

    public function maybe_send_customer_payment_retry(int $order_id): void
    {
        if ($order_id <= 0 || !function_exists('wc_get_order')) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        if ($order->get_meta(self::ORDER_META_CUSTOMER_PAYMENT_RETRY_SENT) === '1') {
            return;
        }

        $to = (string) $order->get_billing_email();
        if (!is_email($to)) {
            return;
        }

        $payment_url = method_exists($order, 'get_checkout_payment_url') ? (string) $order->get_checkout_payment_url() : '';
        $logo_url = $this->get_site_logo_url();
        $subject = sprintf(__('Pago pendiente - Pedido #%s', 'politeia-learning'), $order->get_order_number());
        $html = $this->render_email_template('wc-customer-payment-retry.php', [
            'order' => $order,
            'logo_url' => $logo_url,
            'payment_url' => $payment_url,
        ]);

        if ($this->send_html_mail($to, $subject, $html)) {
            $order->update_meta_data(self::ORDER_META_CUSTOMER_PAYMENT_RETRY_SENT, '1');
            $order->save();
        }
    }

    public function send_admin_low_stock($product): void
    {
        if (!$product instanceof WC_Product) {
            return;
        }

        $to = $this->get_admin_email();
        if ($to === '') {
            return;
        }

        $logo_url = $this->get_site_logo_url();
        $subject = sprintf(__('Stock bajo: %s', 'politeia-learning'), $product->get_name());
        $html = $this->render_email_template('wc-admin-low-stock.php', [
            'product' => $product,
            'logo_url' => $logo_url,
        ]);
        $this->send_html_mail($to, $subject, $html);
    }

    public function send_admin_no_stock($product): void
    {
        if (!$product instanceof WC_Product) {
            return;
        }

        $to = $this->get_admin_email();
        if ($to === '') {
            return;
        }

        $logo_url = $this->get_site_logo_url();
        $subject = sprintf(__('Sin stock: %s', 'politeia-learning'), $product->get_name());
        $html = $this->render_email_template('wc-admin-no-stock.php', [
            'product' => $product,
            'logo_url' => $logo_url,
        ]);
        $this->send_html_mail($to, $subject, $html);
    }

    public function send_admin_backorder($product, $order_id = 0): void
    {
        if (!$product instanceof WC_Product) {
            return;
        }

        $to = $this->get_admin_email();
        if ($to === '') {
            return;
        }

        $order = ($order_id > 0 && function_exists('wc_get_order')) ? wc_get_order((int) $order_id) : null;
        $logo_url = $this->get_site_logo_url();
        $subject = sprintf(__('Backorder: %s', 'politeia-learning'), $product->get_name());
        $html = $this->render_email_template('wc-admin-backorder.php', [
            'product' => $product,
            'order' => $order,
            'logo_url' => $logo_url,
        ]);
        $this->send_html_mail($to, $subject, $html);
    }
}
