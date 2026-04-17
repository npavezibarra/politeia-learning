<?php
/**
 * Class PL_Woo_Templates
 * Handles WooCommerce template overrides for Politeia Learning.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PL_Woo_Templates {

    /**
     * Initialize the class.
     */
    public static function init() {
        $instance = new self();
        add_filter('template_include', [$instance, 'override_woo_templates'], 99);
    }

    /**
     * Override WooCommerce templates with module-based versions.
     *
     * @param string $template The template path.
     * @return string The overridden template path.
     */
    public function override_woo_templates($template) {
        if (!function_exists('is_woocommerce')) {
            return $template;
        }

        $template_dir = plugin_dir_path(__FILE__) . '../templates/';

        // Checkout Page
        if (is_checkout() && !is_wc_endpoint_url('order-received')) {
            $new_template = $template_dir . 'checkout.php';
            if (file_exists($new_template)) {
                return $new_template;
            }
        }

        // Thank You Page (Order Received)
        if (is_wc_endpoint_url('order-received')) {
            $new_template = $template_dir . 'thankyou.php';
            if (file_exists($new_template)) {
                return $new_template;
            }
        }

        // Cart Page
        if (is_cart()) {
            $new_template = $template_dir . 'cart.php';
            if (file_exists($new_template)) {
                return $new_template;
            }
        }

        // My Account Page
        if (is_account_page()) {
            $new_template = $template_dir . 'my-account.php';
            if (file_exists($new_template)) {
                return $new_template;
            }
        }

        // Shop Page
        if (is_shop()) {
            $new_template = $template_dir . 'shop.php';
            if (file_exists($new_template)) {
                return $new_template;
            }
        }

        // Single Product
        if (is_product()) {
            $new_template = $template_dir . 'single-product.php';
            if (file_exists($new_template)) {
                return $new_template;
            }
        }

        return $template;
    }
}
