<?php
/**
 * Module Name: Woo Tweaks
 * Description: WooCommerce related tweaks and extensions for Politeia Learning.
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/includes/class-product-owner-metabox.php';

PL_Woo_Product_Owner_Metabox::init();

