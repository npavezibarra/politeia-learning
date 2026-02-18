<?php
/**
 * Module Name: Woo Tweaks
 * Description: WooCommerce related tweaks and extensions for Politeia Learning.
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/includes/class-product-owner-metabox.php';
require_once __DIR__ . '/includes/class-user-sales-metrics.php';
require_once __DIR__ . '/includes/class-user-profile-settings.php';
require_once __DIR__ . '/includes/class-financial-settings.php';

PL_Woo_Product_Owner_Metabox::init();
PL_Woo_User_Sales_Metrics::init();
PL_Woo_User_Profile_Settings::init();
PL_Woo_Financial_Settings::init();
