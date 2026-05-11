<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Flow server-to-server callbacks for subscription payments.
 *
 * Flow posts `token=...` (application/x-www-form-urlencoded) to `urlCallback` configured in the plan.
 * We must resolve the token via `GET /payment/getStatusExtended` and then update our local state.
 *
 * Phase 4 scope:
 * - Ingest callback tokens
 * - Resolve payment status
 * - Best-effort map to a local subscription by `flow_subscription_id`
 * - Write a ledger entry + renew access on paid status
 */
class Politeia_PPS_Flow_Callbacks {
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes() {
		register_rest_route(
			'politeia/v1',
			'/flow/callback',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_callback' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public static function callback_url() {
		return rest_url( 'politeia/v1/flow/callback' );
	}

	public static function handle_callback( WP_REST_Request $req ) {
		$token = '';
		$body_params = $req->get_body_params();
		if ( is_array( $body_params ) && isset( $body_params['token'] ) ) {
			$token = sanitize_text_field( (string) $body_params['token'] );
		}
		if ( '' === $token ) {
			// Some clients may POST raw body like "token=...".
			$raw = (string) $req->get_body();
			if ( preg_match( '/(?:^|&)token=([^&]+)/', $raw, $m ) ) {
				$token = sanitize_text_field( urldecode( (string) $m[1] ) );
			}
		}

		if ( '' === $token ) {
			return new WP_REST_Response( array( 'ok' => false, 'error' => 'missing_token' ), 400 );
		}

		if ( ! class_exists( 'Politeia_PPS_Settings' ) || ! class_exists( 'Politeia_PPS_Subscription_Engine' ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'error' => 'missing_dependencies' ), 500 );
		}

		$mode   = Politeia_PPS_Settings::get_mode();
		$api    = Politeia_PPS_Settings::get_flow_api_key( $mode );
		$secret = Politeia_PPS_Settings::get_flow_secret( $mode );

		$client = new Politeia_PPS_Flow_Client();
		$res    = $client->get_payment_status_extended( $token, $api, $secret, $mode );

		if ( empty( $res['ok'] ) || ! is_array( $res['body'] ) ) {
			self::debug( 'callback_resolve_failed', array( 'token' => $token, 'res' => $res ) );
			// Always return 200 to prevent repeated callback storms; we keep debug logs for remediation.
			return new WP_REST_Response( array( 'ok' => true, 'resolved' => false ), 200 );
		}

		$payment = $res['body'];
		self::debug( 'callback_resolved', array( 'token' => $token, 'status' => $payment['status'] ?? null ) );

		// Try to locate subscriptionId in the resolved payload.
		$subscription_id = self::extract_subscription_id( $payment );
		if ( '' === $subscription_id ) {
			self::debug( 'callback_missing_subscription_id', array( 'token' => $token, 'payment' => $payment ) );
			return new WP_REST_Response( array( 'ok' => true, 'resolved' => true, 'mapped' => false ), 200 );
		}

		$sub = Politeia_PPS_Subscription_Engine::get_subscription_by_flow_id( $subscription_id );
		if ( ! is_array( $sub ) ) {
			self::debug( 'callback_subscription_not_found', array( 'subscription_id' => $subscription_id ) );
			return new WP_REST_Response( array( 'ok' => true, 'resolved' => true, 'mapped' => false ), 200 );
		}

		// Determine "paid" status. Flow payment/getStatus uses:
		// 1 pending, 2 paid, 3 rejected, 4 cancelled.
		$status = (int) ( $payment['status'] ?? 0 );
		if ( 2 !== $status ) {
			// Not paid: ignore for access/ledger, keep debug trail.
			return new WP_REST_Response( array( 'ok' => true, 'resolved' => true, 'mapped' => true, 'paid' => false ), 200 );
		}

		// Write ledger entry (gateway=flow stored in raw_payload/event_source for now).
		$tier = Politeia_PPS_Subscription_Engine::get_tier( (int) ( $sub['tier_id'] ?? 0 ) );
		$amount_minor = $tier ? (int) ( $tier['amount_minor'] ?? 0 ) : 0;
		$currency     = $tier ? (string) ( $tier['currency'] ?? 'CLP' ) : 'CLP';

		$fee_minor = 0;
		if ( isset( $payment['paymentData']['fee'] ) ) {
			$fee_minor = (int) $payment['paymentData']['fee'];
		}

		$breakdown = class_exists( 'Politeia_PPS_Commission' )
			? Politeia_PPS_Commission::breakdown( $amount_minor, $fee_minor )
			: array(
				'iva_minor'                 => 0,
				'platform_commission_minor' => 0,
				'creator_net_minor'         => $amount_minor,
			);

		$ledger_id = Politeia_PPS_Subscription_Engine::record_ledger_entry(
			array(
				'creator_user_id'           => (int) ( $sub['creator_user_id'] ?? 0 ),
				'subscriber_user_id'        => (int) ( $sub['subscriber_user_id'] ?? 0 ),
				'tier_id'                   => (int) ( $sub['tier_id'] ?? 0 ),
				'currency'                  => $currency,
				'gross_amount_minor'        => $amount_minor,
				'mp_fee_minor'              => $fee_minor,
				'iva_minor'                 => (int) ( $breakdown['iva_minor'] ?? 0 ),
				'platform_commission_minor' => (int) ( $breakdown['platform_commission_minor'] ?? 0 ),
				'creator_net_minor'         => (int) ( $breakdown['creator_net_minor'] ?? $amount_minor ),
				'event_source'              => 'flow_callback',
				'raw_payload'               => wp_json_encode(
					array(
						'token'         => $token,
						'subscriptionId' => $subscription_id,
						'payment'        => $payment,
					)
				),
			)
		);

		if ( is_wp_error( $ledger_id ) ) {
			self::debug( 'ledger_failed', array( 'error' => $ledger_id->get_error_message(), 'data' => $ledger_id->get_error_data() ) );
			return new WP_REST_Response( array( 'ok' => true, 'resolved' => true, 'mapped' => true, 'paid' => true, 'ledger' => false ), 200 );
		}

		// Renew access.
		do_action(
			'pl_subscription_payment_completed',
			(int) ( $sub['subscriber_user_id'] ?? 0 ),
			(int) ( $sub['creator_user_id'] ?? 0 ),
			null,
			array(
				'gateway'               => 'flow',
				'flow_subscription_id'  => $subscription_id,
				'flow_callback_token'   => $token,
				'ledger_id'             => (int) $ledger_id,
			)
		);

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	private static function extract_subscription_id( array $payment ) {
		// First: explicit key.
		foreach ( array( 'subscriptionId', 'subscription_id' ) as $key ) {
			if ( isset( $payment[ $key ] ) ) {
				$val = sanitize_text_field( (string) $payment[ $key ] );
				if ( '' !== $val ) {
					return $val;
				}
			}
		}

		// Then: optional object.
		if ( isset( $payment['optional'] ) && is_array( $payment['optional'] ) ) {
			foreach ( array( 'subscriptionId', 'subscription_id' ) as $key ) {
				if ( isset( $payment['optional'][ $key ] ) ) {
					$val = sanitize_text_field( (string) $payment['optional'][ $key ] );
					if ( '' !== $val ) {
						return $val;
					}
				}
			}
		}

		// Heuristic: scan JSON for sus_ pattern.
		$json = wp_json_encode( $payment );
		if ( preg_match( '/\\b(sus_[A-Za-z0-9]+)\\b/', (string) $json, $m ) ) {
			return sanitize_text_field( (string) $m[1] );
		}

		return '';
	}

	private static function debug( $event, array $context = array() ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[PPS][Flow][callback] ' . sanitize_key( (string) $event ) . ' ' . wp_json_encode( $context ) );
		}
	}
}

