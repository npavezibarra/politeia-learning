<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Politeia_PPS_Commission {
	/**
	 * Computes the ledger breakdown:
	 * Total Paid -> MP Fee -> IVA -> Platform Commission -> Creator Net
	 */
	public static function breakdown( $gross_amount_minor, $mp_fee_minor, $currency = 'CLP' ) {
		$gross_amount_minor = (int) $gross_amount_minor;
		$mp_fee_minor       = max( 0, (int) $mp_fee_minor );

		$commission_rate = (float) Politeia_PPS_Settings::get( 'platform_commission_rate', 0.10 );
		$iva_rate        = (float) Politeia_PPS_Settings::get( 'iva_rate', 0.19 );
		$iva_over_mp_fee = (bool) Politeia_PPS_Settings::get( 'mp_fee_includes_iva', true );

		$iva_minor = 0;
		if ( $iva_over_mp_fee ) {
			$iva_minor = (int) round( $mp_fee_minor * $iva_rate );
		}

		$base_minor = $gross_amount_minor - $mp_fee_minor - $iva_minor;
		if ( $base_minor < 0 ) {
			$base_minor = 0;
		}

		$platform_commission_minor = (int) round( $base_minor * $commission_rate );
		if ( $platform_commission_minor < 0 ) {
			$platform_commission_minor = 0;
		}
		if ( $platform_commission_minor > $base_minor ) {
			$platform_commission_minor = $base_minor;
		}

		$creator_net_minor = $base_minor - $platform_commission_minor;
		if ( $creator_net_minor < 0 ) {
			$creator_net_minor = 0;
		}

		return array(
			'currency'                  => strtoupper( $currency ),
			'gross_amount_minor'        => $gross_amount_minor,
			'mp_fee_minor'              => $mp_fee_minor,
			'iva_minor'                 => $iva_minor,
			'platform_commission_minor' => $platform_commission_minor,
			'creator_net_minor'         => $creator_net_minor,
		);
	}
}

