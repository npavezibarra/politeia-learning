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

/**
 * Flow card registration return page.
 *
 * Flow redirects/POSTs the token to `url_return`. We use that token to:
 * - Check card registration status (`/customer/getRegisterStatus`)
 * - Create subscription (`/subscription/create`)
 * - Persist local subscription row + grant access
 */
add_shortcode(
	'politeia_pps_flow_return',
	static function (): string {
		if ( ! is_user_logged_in() ) {
			return '<div class="politeia-pps-return politeia-pps-return-error"><h2>Inicia sesión</h2><p>Para completar la suscripción, inicia sesión e intenta nuevamente.</p></div>';
		}

		$token = '';
		if ( isset( $_REQUEST['token'] ) ) {
			$token = sanitize_text_field( wp_unslash( $_REQUEST['token'] ) );
		}
		if ( '' === $token ) {
			return '<div class="politeia-pps-return politeia-pps-return-error"><h2>Error</h2><p>No se recibió el token de Flow.</p></div>';
		}

		if ( ! class_exists( 'Politeia_PPS_Flow_Subscribe' ) ) {
			return '<div class="politeia-pps-return politeia-pps-return-error"><h2>Error</h2><p>El módulo Flow no está disponible.</p></div>';
		}

		$res = Politeia_PPS_Flow_Subscribe::complete_from_register_token( get_current_user_id(), $token );
		if ( is_wp_error( $res ) ) {
			$msg = esc_html( $res->get_error_message() );
			return '<div class="politeia-pps-return politeia-pps-return-error"><h2>No se pudo completar</h2><p>' . $msg . '</p></div>';
		}

		$home  = esc_url( home_url( '/' ) );
		$label = isset( $res['creator_label'] ) ? esc_html( (string) $res['creator_label'] ) : '';
		$link  = isset( $res['creator_url'] ) ? esc_url( (string) $res['creator_url'] ) : '';

		$html = '<div class="politeia-pps-return politeia-pps-return-success"><h2>Suscripción completada</h2><p>Tu suscripción fue creada correctamente.</p>';
		if ( $link !== '' ) {
			$html .= '<p><a href="' . $link . '">Volver al perfil' . ( $label ? ' de ' . $label : '' ) . '</a></p>';
		}
		$html .= '<p><a href="' . $home . '">Volver al inicio</a></p></div>';
		return $html;
	}
);
