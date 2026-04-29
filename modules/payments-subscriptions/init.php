<?php
/**
 * Module: Payments Subscriptions
 * Description: Creator-defined paid subscriptions (Mercado Pago / Flow) merged from politeia-payments-subscriptions.
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PL_PPS_IN_POLITEIA_LEARNING', true);
define('PL_PPS_VERSION', '0.1.2');
define('PL_PPS_PATH', plugin_dir_path(__FILE__));
define('PL_PPS_URL', plugin_dir_url(__FILE__));

// Back-compat constants (original standalone plugin names).
if (!defined('POLITEIA_PPS_VERSION')) {
    define('POLITEIA_PPS_VERSION', PL_PPS_VERSION);
}
if (!defined('POLITEIA_PPS_PATH')) {
    define('POLITEIA_PPS_PATH', PL_PPS_PATH);
}
if (!defined('POLITEIA_PPS_URL')) {
    define('POLITEIA_PPS_URL', PL_PPS_URL);
}

// If the standalone plugin was loaded first, avoid redeclaring classes.
if (class_exists('Politeia_PPS_Settings')) {
    return;
}

require_once PL_PPS_PATH . 'includes/class-settings.php';
require_once PL_PPS_PATH . 'includes/class-activator.php';
require_once PL_PPS_PATH . 'includes/class-locale.php';
require_once PL_PPS_PATH . 'includes/class-currency-converter.php';
require_once PL_PPS_PATH . 'includes/class-commission.php';
require_once PL_PPS_PATH . 'includes/class-mercadopago-client.php';
require_once PL_PPS_PATH . 'includes/class-subscription-engine.php';
require_once PL_PPS_PATH . 'includes/class-webhooks.php';
require_once PL_PPS_PATH . 'includes/class-rest.php';
require_once PL_PPS_PATH . 'includes/class-profile-subscribe.php';
require_once PL_PPS_PATH . 'includes/class-relationships-bridge.php';
require_once PL_PPS_PATH . 'includes/class-return-pages.php';

require_once PL_PPS_PATH . 'shortcodes/creator-dashboard.php';
require_once PL_PPS_PATH . 'shortcodes/subscriber-dashboard.php';
require_once PL_PPS_PATH . 'shortcodes/marketplace.php';
require_once PL_PPS_PATH . 'shortcodes/return-pages.php';

// Ensure tables exist and are updated when the module is active.
add_action('plugins_loaded', ['Politeia_PPS_Activator', 'maybe_upgrade'], 20);

// Init settings + REST + webhooks.
add_action('init', static function (): void {
    if (class_exists('Politeia_PPS_Settings')) {
        Politeia_PPS_Settings::init();
    }
    if (class_exists('Politeia_PPS_REST')) {
        Politeia_PPS_REST::init();
    }
    if (class_exists('Politeia_PPS_Webhooks')) {
        Politeia_PPS_Webhooks::init();
    }
    if (class_exists('Politeia_PPS_Profile_Subscribe')) {
        Politeia_PPS_Profile_Subscribe::init();
    }
    if (class_exists('Politeia_PPS_Relationships_Bridge')) {
        Politeia_PPS_Relationships_Bridge::init();
    }
    if (class_exists('Politeia_PPS_Return_Pages')) {
        Politeia_PPS_Return_Pages::init();
    }
}, 0);

// Register frontend assets.
add_action('wp_enqueue_scripts', static function (): void {
	wp_register_style(
		'politeia-pps-poppins',
		'https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap',
		[],
		PL_PPS_VERSION
	);

    wp_register_style(
        'politeia-pps',
        PL_PPS_URL . 'assets/css/politeia-pps.css',
        ['politeia-pps-poppins'],
        PL_PPS_VERSION
    );

	wp_register_script(
		'politeia-pps-marketplace',
		PL_PPS_URL . 'assets/js/marketplace.js',
		[],
		PL_PPS_VERSION,
		true
	);

	wp_register_script(
		'politeia-pps-profile-subscribe-modal',
		PL_PPS_URL . 'assets/js/profile-subscribe-modal.js',
		[],
		PL_PPS_VERSION,
		true
	);
}, 5);
