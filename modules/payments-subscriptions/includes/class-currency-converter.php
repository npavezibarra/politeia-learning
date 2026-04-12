<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Politeia_PPS_Currency_Converter {
	/**
	 * Returns a float exchange rate (from -> to) or WP_Error.
	 * Default implementation is intentionally conservative and can be replaced via filters.
	 */
	public static function get_rate( $from_currency, $to_currency ) {
		$from_currency = strtoupper( sanitize_text_field( $from_currency ) );
		$to_currency   = strtoupper( sanitize_text_field( $to_currency ) );

		$filtered = apply_filters( 'politeia_pps_exchange_rate', null, $from_currency, $to_currency );
		if ( null !== $filtered ) {
			if ( is_wp_error( $filtered ) ) {
				return $filtered;
			}
			return (float) $filtered;
		}

		if ( $from_currency === $to_currency ) {
			return 1.0;
		}

		$provider = Politeia_PPS_Settings::get( 'exchange_rate_provider', '' );
		if ( ! $provider ) {
			return new WP_Error( 'no_provider', 'No exchange rate provider configured.' );
		}

		// Placeholder for future provider implementations.
		return new WP_Error( 'provider_not_implemented', 'Exchange rate provider not implemented: ' . $provider );
	}

	public static function convert_minor( $amount_minor, $from_currency, $to_currency ) {
		$rate = self::get_rate( $from_currency, $to_currency );
		if ( is_wp_error( $rate ) ) {
			return $rate;
		}

		$amount_minor = (int) $amount_minor;

		// We treat minor units as "atomic"; this is an estimate shown to the supporter, not authoritative settlement.
		$converted = (int) round( $amount_minor * (float) $rate );
		return array(
			'amount_minor' => $converted,
			'rate'         => (float) $rate,
		);
	}
}

