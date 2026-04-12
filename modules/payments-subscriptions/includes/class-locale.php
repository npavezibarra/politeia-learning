<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Politeia_PPS_Locale {
	public static function get_locale() {
		$locale = determine_locale();
		if ( ! $locale ) {
			$locale = get_locale();
		}
		return is_string( $locale ) ? $locale : 'en_US';
	}

	public static function get_language() {
		$locale = self::get_locale();
		$lang   = strtolower( substr( $locale, 0, 2 ) );
		if ( in_array( $lang, array( 'es', 'en', 'pt' ), true ) ) {
			return $lang;
		}
		return 'en';
	}

	public static function default_currency_for_locale( $locale = null ) {
		if ( ! $locale ) {
			$locale = self::get_locale();
		}

		$map = array(
			'es_CL' => 'CLP',
			'pt_BR' => 'BRL',
			'en_US' => 'USD',
		);

		$currency = isset( $map[ $locale ] ) ? $map[ $locale ] : 'USD';
		return apply_filters( 'politeia_pps_default_currency', $currency, $locale );
	}
}

