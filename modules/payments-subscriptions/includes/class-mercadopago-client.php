<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Politeia_PPS_MercadoPago_Client {
	const API_BASE = 'https://api.mercadopago.com';

	private $access_token;

	public function __construct( $access_token = null ) {
		$this->access_token = $access_token ? $access_token : Politeia_PPS_Settings::get_access_token();
	}

	public function is_configured() {
		return is_string( $this->access_token ) && $this->access_token !== '';
	}

	private function request( $method, $path, $body = null, $query = array() ) {
		if ( ! $this->is_configured() ) {
			error_log( '[PPS][MP] request aborted: access token missing' );
			return new WP_Error( 'mp_not_configured', 'Mercado Pago access token not configured.' );
		}

		$method = strtoupper( (string) $method );
		$url = rtrim( self::API_BASE, '/' ) . '/' . ltrim( $path, '/' );
		if ( $query ) {
			$url = add_query_arg( $query, $url );
		}

		$args = array(
			'method'  => $method,
			'timeout' => 20,
			'user-agent' => 'PoliteiaPPS/' . ( defined( 'POLITEIA_PPS_VERSION' ) ? POLITEIA_PPS_VERSION : 'dev' ) . '; WordPress/' . get_bloginfo( 'version' ),
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->access_token,
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
			),
		);
		// Some MP endpoints for subscriptions in TEST require X-scope: stage.
		if ( 0 === strpos( $this->access_token, 'TEST-' ) ) {
			$args['headers']['X-scope'] = 'stage';
		}

		// Help safe retries for non-GET requests.
		$idempotency_key = null;
		if ( 'GET' !== $method ) {
			$idempotency_key = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'pps_', true );
			$args['headers']['X-Idempotency-Key'] = $idempotency_key;
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$auth_preview = substr( $this->access_token, 0, 6 ) . '...' . substr( $this->access_token, -4 );
			error_log(
				sprintf(
					'[PPS][MP] request %s %s auth=%s idem=%s body=%s',
					$method,
					$url,
					$auth_preview,
					$idempotency_key ? $idempotency_key : 'none',
					null === $body ? 'null' : substr( wp_json_encode( $body ), 0, 800 )
				)
			);
		}

		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		$attempt      = 0;
		$max_attempts = 2;
		do {
			$attempt++;

			$res = wp_remote_request( $url, $args );
			if ( is_wp_error( $res ) ) {
				error_log( '[PPS][MP] wp_remote_request error: ' . $res->get_error_message() . ' url=' . $url );
				return $res;
			}

			$code    = (int) wp_remote_retrieve_response_code( $res );
			$message = (string) wp_remote_retrieve_response_message( $res );
			$raw     = (string) wp_remote_retrieve_body( $res );
			$headers = wp_remote_retrieve_headers( $res );
			$headers_norm = $headers;
			if ( is_object( $headers_norm ) && method_exists( $headers_norm, 'getAll' ) ) {
				$headers_norm = $headers_norm->getAll();
			} elseif ( $headers_norm instanceof Traversable ) {
				$headers_norm = iterator_to_array( $headers_norm );
			}
			$data    = json_decode( $raw, true );

			if ( $code < 200 || $code >= 300 ) {
				error_log(
					sprintf(
						'[PPS][MP] API error attempt=%d/%d status=%s message=%s url=%s headers=%s body=%s',
						$attempt,
						$max_attempts,
						$code,
						$message,
						$url,
						substr( wp_json_encode( $headers_norm ), 0, 800 ),
						is_string( $raw ) ? substr( $raw, 0, 800 ) : ''
					)
				);

				if ( $attempt < $max_attempts && in_array( $code, array( 500, 502, 503, 504 ), true ) ) {
					usleep( 350000 * $attempt ); // 350ms, 700ms
					continue;
				}

				return new WP_Error(
					'mp_api_error',
					'Mercado Pago API error',
					array(
						'status'  => $code,
						'message' => $message,
						'headers' => $headers,
						'body'    => $data ? $data : $raw,
						'url'     => $url,
					)
				);
			}

			return $data ? $data : array();
		} while ( $attempt < $max_attempts );

		return new WP_Error( 'mp_api_error', 'Mercado Pago API error', array( 'status' => 0, 'url' => $url ) );
	}

	public function create_preapproval_plan( $payload ) {
		return $this->request( 'POST', '/preapproval_plan', $payload );
	}

	public function create_preapproval( $payload ) {
		return $this->request( 'POST', '/preapproval', $payload );
	}

	public function get_preapproval( $preapproval_id ) {
		return $this->request( 'GET', '/preapproval/' . rawurlencode( $preapproval_id ) );
	}

	public function update_preapproval( $preapproval_id, $payload ) {
		return $this->request( 'PUT', '/preapproval/' . rawurlencode( $preapproval_id ), $payload );
	}

	public function get_me() {
		return $this->request( 'GET', '/users/me' );
	}
}
