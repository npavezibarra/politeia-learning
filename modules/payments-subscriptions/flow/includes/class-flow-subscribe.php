<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Flow subscribe flow (Phase 3).
 *
 * Responsibilities:
 * - Ensure Flow customer exists for a WP user (create + persist customerId).
 * - Start card registration (customer/register) and return redirect URL.
 * - Complete the enrollment callback using the register token:
 *   - customer/getRegisterStatus
 *   - subscription/create
 *   - persist local subscription + grant relationship access
 */
class Politeia_PPS_Flow_Subscribe {
	const META_CUSTOMER_ID_PREFIX = 'politeia_pps_flow_customer_id_';

	/**
	 * Start subscription enrollment: create customer (if needed), then register card.
	 *
	 * @param int $subscriber_user_id
	 * @param int $tier_id
	 * @return array|WP_Error
	 */
	public static function start( $subscriber_user_id, $tier_id ) {
		$subscriber_user_id = (int) $subscriber_user_id;
		$tier_id            = (int) $tier_id;

		if ( $subscriber_user_id <= 0 || $tier_id <= 0 ) {
			return new WP_Error( 'invalid_args', 'Invalid subscription arguments.' );
		}
		if ( ! class_exists( 'Politeia_PPS_Settings' ) || ! class_exists( 'Politeia_PPS_Subscription_Engine' ) ) {
			return new WP_Error( 'missing_dependencies', 'Missing PPS dependencies.' );
		}

		$mode   = Politeia_PPS_Settings::get_mode();
		$api    = Politeia_PPS_Settings::get_flow_api_key( $mode );
		$secret = Politeia_PPS_Settings::get_flow_secret( $mode );
		if ( '' === trim( (string) $api ) || '' === trim( (string) $secret ) ) {
			return new WP_Error( 'flow_not_configured', 'Flow is not configured.' );
		}

		$tier = Politeia_PPS_Subscription_Engine::get_tier( $tier_id );
		if ( ! is_array( $tier ) ) {
			return new WP_Error( 'tier_not_found', 'Tier not found.' );
		}
		if ( (int) $tier['creator_user_id'] === $subscriber_user_id ) {
			return new WP_Error( 'cannot_self_subscribe', 'You cannot subscribe to your own tier.' );
		}

		// Ensure tier has a Flow plan id (best-effort sync).
		$plan_id = sanitize_text_field( (string) ( $tier['flow_plan_id'] ?? '' ) );
		if ( '' === $plan_id && class_exists( 'Politeia_PPS_Flow_Engine' ) ) {
			$sync = Politeia_PPS_Flow_Engine::upsert_plan_for_tier( $tier_id );
			if ( is_wp_error( $sync ) ) {
				return $sync;
			}
			$tier    = Politeia_PPS_Subscription_Engine::get_tier( $tier_id );
			$plan_id = sanitize_text_field( (string) ( $tier['flow_plan_id'] ?? '' ) );
		}
		if ( '' === $plan_id ) {
			return new WP_Error( 'missing_flow_plan', 'Flow plan not configured for this tier.' );
		}

		$customer_id = self::get_or_create_customer_id( $subscriber_user_id, $mode, $api, $secret );
		if ( is_wp_error( $customer_id ) ) {
			return $customer_id;
		}

		$return_url = self::flow_return_url();
		if ( '' === $return_url ) {
			return new WP_Error( 'missing_return_url', 'Missing Flow return URL.' );
		}

		$client = new Politeia_PPS_Flow_Client();
		$reg    = $client->request(
			'POST',
			'/customer/register',
			array(
				'customerId' => (string) $customer_id,
				'url_return' => $return_url,
			),
			$api,
			$secret,
			$mode
		);

		if ( empty( $reg['ok'] ) || ! is_array( $reg['body'] ) ) {
			return new WP_Error( 'flow_register_failed', 'Flow card registration failed.', $reg );
		}

		$url   = (string) ( $reg['body']['url'] ?? '' );
		$token = (string) ( $reg['body']['token'] ?? '' );
		if ( '' === $url || '' === $token ) {
			return new WP_Error( 'flow_register_missing', 'Flow register response missing url/token.', $reg['body'] );
		}

		// Create local pending subscription record (keyed by register token).
		$pending_id = Politeia_PPS_Subscription_Engine::upsert_subscription_flow_pending(
			(int) $tier['creator_user_id'],
			$subscriber_user_id,
			$tier_id,
			$token
		);
		if ( is_wp_error( $pending_id ) ) {
			return $pending_id;
		}

		return array(
			'ok'           => true,
			'gateway'       => 'flow',
			'redirect_url'  => $url . '?token=' . rawurlencode( $token ),
			'register_token'=> $token,
			'local_id'      => (int) $pending_id,
			'plan_id'       => $plan_id,
		);
	}

	/**
	 * Complete flow after Flow posts token to our return URL.
	 *
	 * @param int    $subscriber_user_id
	 * @param string $register_token
	 * @return array|WP_Error
	 */
	public static function complete_from_register_token( $subscriber_user_id, $register_token ) {
		$subscriber_user_id = (int) $subscriber_user_id;
		$register_token     = sanitize_text_field( (string) $register_token );

		if ( $subscriber_user_id <= 0 || '' === $register_token ) {
			return new WP_Error( 'invalid_args', 'Invalid token.' );
		}
		if ( ! class_exists( 'Politeia_PPS_Settings' ) || ! class_exists( 'Politeia_PPS_Subscription_Engine' ) ) {
			return new WP_Error( 'missing_dependencies', 'Missing PPS dependencies.' );
		}

		$mode   = Politeia_PPS_Settings::get_mode();
		$api    = Politeia_PPS_Settings::get_flow_api_key( $mode );
		$secret = Politeia_PPS_Settings::get_flow_secret( $mode );
		if ( '' === trim( (string) $api ) || '' === trim( (string) $secret ) ) {
			return new WP_Error( 'flow_not_configured', 'Flow is not configured.' );
		}

		// Find pending subscription by token and ownership.
		$pending = Politeia_PPS_Subscription_Engine::get_flow_pending_by_token( $subscriber_user_id, $register_token );
		if ( ! is_array( $pending ) ) {
			return new WP_Error( 'pending_not_found', 'No pending Flow subscription found for this token.' );
		}

		$client = new Politeia_PPS_Flow_Client();

		$status = $client->request(
			'GET',
			'/customer/getRegisterStatus',
			array(
				'token' => $register_token,
			),
			$api,
			$secret,
			$mode
		);

		if ( empty( $status['ok'] ) || ! is_array( $status['body'] ) ) {
			return new WP_Error( 'flow_register_status_failed', 'Failed to fetch Flow register status.', $status );
		}

		$reg_status = (string) ( $status['body']['status'] ?? '' );
		$customer_id = (string) ( $status['body']['customerId'] ?? '' );
		if ( '1' !== $reg_status || '' === $customer_id ) {
			return new WP_Error( 'flow_register_not_active', 'Card registration was not completed.' , $status['body'] );
		}

		// Persist customer id for the WP user for future charges (best-effort).
		update_user_meta( $subscriber_user_id, self::META_CUSTOMER_ID_PREFIX . $mode, $customer_id );

		$tier = Politeia_PPS_Subscription_Engine::get_tier( (int) ( $pending['tier_id'] ?? 0 ) );
		if ( ! is_array( $tier ) ) {
			return new WP_Error( 'tier_not_found', 'Tier not found.' );
		}

		$plan_id = sanitize_text_field( (string) ( $tier['flow_plan_id'] ?? '' ) );
		if ( '' === $plan_id ) {
			return new WP_Error( 'missing_flow_plan', 'Flow plan not configured for this tier.' );
		}

		$sub = $client->request(
			'POST',
			'/subscription/create',
			array(
				'planId'     => $plan_id,
				'customerId' => $customer_id,
			),
			$api,
			$secret,
			$mode
		);

		if ( empty( $sub['ok'] ) || ! is_array( $sub['body'] ) ) {
			return new WP_Error( 'flow_subscription_failed', 'Failed to create Flow subscription.', $sub );
		}

		$flow_subscription_id = (string) ( $sub['body']['subscriptionId'] ?? '' );
		if ( '' === $flow_subscription_id ) {
			return new WP_Error( 'flow_missing_subscription_id', 'Flow response missing subscriptionId.', $sub['body'] );
		}

		$current_period_end = (string) ( $sub['body']['period_end'] ?? '' );
		$normalized_end     = Politeia_PPS_Subscription_Engine::normalize_datetime( $current_period_end );

		$updated = Politeia_PPS_Subscription_Engine::finalize_flow_subscription(
			(int) $pending['id'],
			$flow_subscription_id,
			$normalized_end
		);
		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		// Grant access (MVP): treat subscription creation as sufficient for unlocking.
		do_action(
			'pl_subscription_payment_completed',
			(int) $subscriber_user_id,
			(int) $tier['creator_user_id'],
			null,
			array(
				'gateway'              => 'flow',
				'flow_subscription_id' => $flow_subscription_id,
				'plan_id'              => $plan_id,
			)
		);

		$creator = get_user_by( 'id', (int) $tier['creator_user_id'] );
		$creator_url = $creator ? home_url( '/profile/' . $creator->user_nicename . '/' ) : '';

		return array(
			'ok'              => true,
			'flow_subscription_id' => $flow_subscription_id,
			'creator_url'     => $creator_url,
			'creator_label'   => $creator ? $creator->display_name : '',
		);
	}

	private static function get_or_create_customer_id( $subscriber_user_id, $mode, $api, $secret ) {
		$key = self::META_CUSTOMER_ID_PREFIX . $mode;
		$existing = (string) get_user_meta( (int) $subscriber_user_id, $key, true );
		if ( '' !== trim( $existing ) ) {
			return $existing;
		}

		$user = get_user_by( 'id', (int) $subscriber_user_id );
		if ( ! $user ) {
			return new WP_Error( 'user_not_found', 'User not found.' );
		}

		$name = trim( (string) $user->display_name );
		if ( '' === $name ) {
			$name = trim( (string) $user->user_login );
		}

		$email = sanitize_email( (string) $user->user_email );
		if ( '' === $email ) {
			return new WP_Error( 'missing_email', 'User email is required for Flow.' );
		}

		$external_id = 'wp:' . (int) $subscriber_user_id . ':' . $mode;

		$client = new Politeia_PPS_Flow_Client();
		$res    = $client->request(
			'POST',
			'/customer/create',
			array(
				'name'       => $name,
				'email'      => $email,
				'externalId' => $external_id,
			),
			$api,
			$secret,
			$mode
		);

		if ( empty( $res['ok'] ) || ! is_array( $res['body'] ) ) {
			return new WP_Error( 'flow_customer_create_failed', 'Failed to create Flow customer.', $res );
		}

		$customer_id = (string) ( $res['body']['customerId'] ?? '' );
		if ( '' === $customer_id ) {
			return new WP_Error( 'flow_customer_missing', 'Flow response missing customerId.', $res['body'] );
		}

		update_user_meta( (int) $subscriber_user_id, $key, $customer_id );
		return $customer_id;
	}

	private static function flow_return_url() {
		$page = get_page_by_path( Politeia_PPS_Return_Pages::FLOW_RETURN_SLUG, OBJECT, 'page' );
		if ( $page instanceof WP_Post ) {
			$link = get_permalink( $page );
			return is_string( $link ) ? $link : '';
		}
		// Fallback: guess by slug.
		return home_url( '/' . Politeia_PPS_Return_Pages::FLOW_RETURN_SLUG . '/' );
	}
}

