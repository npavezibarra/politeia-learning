<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Flow API client (Chile) for recurring billing.
 *
 * Reference: https://developers.flow.cl/api
 *
 * Phase 1 scope:
 * - Parameter signing (HMAC-SHA256)
 * - GET/POST request helper
 * - Minimal "connectivity test" via a safe endpoint (customer/list)
 */
class Politeia_PPS_Flow_Client {
	const PROD_BASE_URL    = 'https://www.flow.cl/api';
	const SANDBOX_BASE_URL = 'https://sandbox.flow.cl/api';

	/**
	 * @param array $params (without "s")
	 * @param string $secret_key
	 * @return string hex HMAC-SHA256 signature
	 */
	public static function sign_params( array $params, $secret_key ) {
		unset( $params['s'] );
		$keys = array_keys( $params );
		sort( $keys, SORT_STRING );

		$to_sign = '';
		foreach ( $keys as $key ) {
			$value   = $params[ $key ];
			$to_sign .= (string) $key . (string) $value;
		}

		return hash_hmac( 'sha256', $to_sign, (string) $secret_key );
	}

	/**
	 * @param string $mode test|live
	 * @return string
	 */
	public static function base_url( $mode ) {
		return 'live' === $mode ? self::PROD_BASE_URL : self::SANDBOX_BASE_URL;
	}

	/**
	 * Perform a Flow API call.
	 *
	 * @param string $method GET|POST
	 * @param string $path   e.g. '/customer/list'
	 * @param array  $params parameters without signature
	 * @param string $api_key
	 * @param string $secret_key
	 * @param string $mode test|live
	 * @return array {ok,status,body,raw,url}
	 */
	public function request( $method, $path, array $params, $api_key, $secret_key, $mode ) {
		$method = strtoupper( (string) $method );
		$mode   = 'live' === $mode ? 'live' : 'test';

		$params          = array_merge( $params, array( 'apiKey' => (string) $api_key ) );
		$params['s']     = self::sign_params( $params, (string) $secret_key );
		$base_url        = self::base_url( $mode );
		$path            = '/' . ltrim( (string) $path, '/' );
		$url             = $base_url . $path;
		$request_options = array(
			'timeout' => 20,
		);

		$this->debug(
			'request',
			array(
				'method' => $method,
				'path'   => $path,
				'mode'   => $mode,
				'has_s'  => isset( $params['s'] ) && '' !== (string) $params['s'],
			)
		);

		if ( 'GET' === $method ) {
			$url = add_query_arg( $params, $url );
			$res = wp_remote_get( $url, $request_options );
		} else {
			$request_options['headers'] = array(
				'Content-Type' => 'application/x-www-form-urlencoded',
			);
			$request_options['body']    = $params;
			$res                        = wp_remote_post( $url, $request_options );
		}

		if ( is_wp_error( $res ) ) {
			$this->debug( 'http_error', array( 'error' => $res->get_error_message(), 'url' => $url ) );
			return array(
				'ok'     => false,
				'status' => 0,
				'body'   => null,
				'raw'    => $res->get_error_message(),
				'url'    => $url,
			);
		}

		$status = (int) wp_remote_retrieve_response_code( $res );
		$raw    = (string) wp_remote_retrieve_body( $res );
		$body   = null;
		if ( '' !== $raw ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				$body = $decoded;
			}
		}

		$ok = 200 === $status;
		$this->debug(
			'response',
			array(
				'method' => $method,
				'url'    => $url,
				'status' => $status,
				'ok'     => $ok,
			)
		);

		return array(
			'ok'     => $ok,
			'status' => $status,
			'body'   => $body,
			'raw'    => $raw,
			'url'    => $url,
		);
	}

	/**
	 * Simple connectivity test.
	 *
	 * Calls `GET /customer/list` with minimal pagination params.
	 *
	 * @param string $api_key
	 * @param string $secret_key
	 * @param string $mode test|live
	 * @return array
	 */
	public function test_connection( $api_key, $secret_key, $mode ) {
		return $this->request(
			'GET',
			'/customer/list',
			array(
				'start' => 0,
				'limit' => 1,
			),
			$api_key,
			$secret_key,
			$mode
		);
	}

	/**
	 * Flow callback tokens should be resolved via payment/getStatusExtended.
	 *
	 * @param string $token
	 * @param string $api_key
	 * @param string $secret_key
	 * @param string $mode
	 * @return array
	 */
	public function get_payment_status_extended( $token, $api_key, $secret_key, $mode ) {
		return $this->request(
			'GET',
			'/payment/getStatusExtended',
			array(
				'token' => (string) $token,
			),
			$api_key,
			$secret_key,
			$mode
		);
	}

	/**
	 * Cancel a subscription in Flow.
	 *
	 * Endpoint: POST /subscription/cancel
	 *
	 * @param string $subscription_id
	 * @param int $at_period_end 0 immediate, 1 at period end
	 * @param string $api_key
	 * @param string $secret_key
	 * @param string $mode
	 * @return array
	 */
	public function cancel_subscription( $subscription_id, $at_period_end, $api_key, $secret_key, $mode ) {
		return $this->request(
			'POST',
			'/subscription/cancel',
			array(
				'subscriptionId' => (string) $subscription_id,
				'at_period_end'  => (int) $at_period_end,
			),
			$api_key,
			$secret_key,
			$mode
		);
	}

	private function debug( $event, array $context = array() ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[PPS][Flow] ' . sanitize_key( (string) $event ) . ' ' . wp_json_encode( $context ) );
		}
	}
}
