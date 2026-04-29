<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_shortcode(
	'politeia_pps_subscription_success',
	static function (): string {
		$home = esc_url( home_url( '/' ) );
		return '<div class="politeia-pps-return politeia-pps-return-success"><h2>Suscripción completada</h2><p>Tu suscripción fue procesada. Puedes cerrar esta página o volver al inicio.</p><p><a href="' . $home . '">Volver al inicio</a></p></div>';
	}
);

add_shortcode(
	'politeia_pps_subscription_cancel',
	static function (): string {
		$home = esc_url( home_url( '/' ) );
		return '<div class="politeia-pps-return politeia-pps-return-cancel"><h2>Suscripción cancelada</h2><p>No se completó la suscripción. Puedes intentarlo nuevamente cuando quieras.</p><p><a href="' . $home . '">Volver al inicio</a></p></div>';
	}
);

