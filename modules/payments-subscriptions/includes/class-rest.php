<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Politeia_PPS_REST {
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register' ) );
	}

	public static function register() {
		register_rest_route(
			'politeia/v1',
			'/subscriptions/tiers',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'get_tiers' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'create_tier' ),
					'permission_callback' => function () {
						return is_user_logged_in();
					},
				),
			)
		);

		register_rest_route(
			'politeia/v1',
			'/subscriptions/subscribe',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'subscribe' ),
				'permission_callback' => function () {
					return is_user_logged_in();
				},
			)
		);

		register_rest_route(
			'politeia/v1',
			'/subscriptions/me',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'me' ),
				'permission_callback' => function () {
					return is_user_logged_in();
				},
			)
		);

		register_rest_route(
			'politeia/v1',
			'/subscriptions/cancel',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'cancel' ),
				'permission_callback' => function () {
					return is_user_logged_in();
				},
			)
		);
	}

	public static function get_tiers( WP_REST_Request $req ) {
		$creator_id = (int) ( $req['creator_id'] ?? 0 );
		if ( $creator_id <= 0 ) {
			return new WP_REST_Response( array( 'error' => 'creator_id_required' ), 400 );
		}

		$tiers = Politeia_PPS_Subscription_Engine::get_creator_tiers( $creator_id );
		return rest_ensure_response(
			array(
				'creator_id' => $creator_id,
				'currency'   => Politeia_PPS_Locale::default_currency_for_locale(),
				'items'      => $tiers,
			)
		);
	}

	public static function create_tier( WP_REST_Request $req ) {
		if ( ! wp_verify_nonce( $req->get_header( 'X-WP-Nonce' ) ?: ( $req['nonce'] ?? '' ), 'wp_rest' ) ) {
			return new WP_REST_Response( array( 'error' => 'invalid_nonce' ), 403 );
		}

		$user_id = get_current_user_id();
		$params  = $req->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = array();
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[PPS][DEBUG][rest_create_tier] user_id=' . $user_id . ' body=' . wp_json_encode( $params ) );
		}

		$tier_id = Politeia_PPS_Subscription_Engine::create_tier( $user_id, $params );
		if ( is_wp_error( $tier_id ) ) {
			return new WP_REST_Response( array( 'error' => $tier_id->get_error_code(), 'message' => $tier_id->get_error_message() ), 400 );
		}

		return rest_ensure_response( array( 'ok' => true, 'tier_id' => (int) $tier_id ) );
	}

	public static function subscribe( WP_REST_Request $req ) {
		if ( ! wp_verify_nonce( $req->get_header( 'X-WP-Nonce' ) ?: ( $req['nonce'] ?? '' ), 'wp_rest' ) ) {
			return new WP_REST_Response( array( 'error' => 'invalid_nonce' ), 403 );
		}

		$user_id = get_current_user_id();
		$params  = $req->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = array();
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[PPS][DEBUG][rest_subscribe] user_id=' . $user_id . ' body=' . wp_json_encode( $params ) );
		}

		$tier_id     = (int) ( $params['tier_id'] ?? 0 );
		$payer_email = sanitize_email( $params['payer_email'] ?? '' );
		$gateway       = sanitize_key( (string) ( $params['gateway'] ?? '' ) );
		$card_token_id = sanitize_text_field( $params['card_token_id'] ?? '' );
		$payment_method_id = sanitize_text_field( $params['payment_method_id'] ?? '' );
		$issuer_id = sanitize_text_field( $params['issuer_id'] ?? '' );
		if ( $tier_id <= 0 ) {
			return new WP_REST_Response( array( 'error' => 'tier_id_required' ), 400 );
		}

		$res = Politeia_PPS_Subscription_Engine::subscribe(
			$user_id,
			$tier_id,
			$payer_email,
			array(
				'gateway'           => $gateway,
				'card_token_id'     => $card_token_id,
				'payment_method_id' => $payment_method_id,
				'issuer_id'         => $issuer_id,
			)
		);
		if ( is_wp_error( $res ) ) {
			$data   = $res->get_error_data();
			$status = 400;
			if ( is_array( $data ) && isset( $data['status'] ) ) {
				$status = (int) $data['status'];
			}
			return new WP_REST_Response(
				array(
					'error'   => $res->get_error_code(),
					'message' => $res->get_error_message(),
					'data'    => $data,
				),
				$status
			);
		}

		return rest_ensure_response( $res );
	}

	public static function me( WP_REST_Request $req ) {
		$user_id = get_current_user_id();
		$subs    = Politeia_PPS_Subscription_Engine::get_subscriptions_for_user( $user_id );
		return rest_ensure_response( array( 'user_id' => $user_id, 'items' => $subs ) );
	}

	public static function cancel( WP_REST_Request $req ) {
		if ( ! wp_verify_nonce( $req->get_header( 'X-WP-Nonce' ) ?: ( $req['nonce'] ?? '' ), 'wp_rest' ) ) {
			return new WP_REST_Response( array( 'error' => 'invalid_nonce' ), 403 );
		}

		$user_id = get_current_user_id();
		$params  = $req->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = array();
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[PPS][DEBUG][rest_subscribe] user_id=' . $user_id . ' body=' . wp_json_encode( $params ) );
		}

		$mp_preapproval_id = sanitize_text_field( $params['mp_preapproval_id'] ?? '' );
		if ( ! $mp_preapproval_id ) {
			return new WP_REST_Response( array( 'error' => 'mp_preapproval_id_required' ), 400 );
		}

		$res = Politeia_PPS_Subscription_Engine::cancel_subscription( $user_id, $mp_preapproval_id );
		if ( is_wp_error( $res ) ) {
			$data   = $res->get_error_data();
			$status = 400;
			if ( is_array( $data ) && isset( $data['status'] ) ) {
				$status = (int) $data['status'];
			}
			return new WP_REST_Response(
				array(
					'error'   => $res->get_error_code(),
					'message' => $res->get_error_message(),
					'data'    => $data,
				),
				$status
			);
		}

		return rest_ensure_response( $res );
	}
}
