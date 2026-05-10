<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registry/factory for subscription gateways.
 *
 * Phase 0 goal:
 * - Provide a single place where we can switch between providers safely.
 * - Default to Mercado Pago to avoid changing runtime behavior.
 */
class Politeia_PPS_Gateway_Registry {
	/**
	 * @return string
	 */
	public static function get_active_gateway_key() {
		// Phase 0: keep current behavior.
		// Phase 1 will introduce an explicit setting (e.g. pps_settings.subscription_gateway).
		return 'mercadopago';
	}

	/**
	 * @return Politeia_PPS_Gateway_Interface|null
	 */
	public static function get_active_gateway() {
		$key = self::get_active_gateway_key();

		if ( 'mercadopago' === $key && class_exists( 'Politeia_PPS_MercadoPago_Gateway' ) ) {
			return new Politeia_PPS_MercadoPago_Gateway();
		}
		if ( 'flow' === $key && class_exists( 'Politeia_PPS_Flow_Gateway' ) ) {
			return new Politeia_PPS_Flow_Gateway();
		}

		return null;
	}
}

