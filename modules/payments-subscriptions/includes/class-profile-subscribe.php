<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bridge between the public profile "Suscribirme" CTA and the Mercado Pago subscription engine.
 *
 * The member profile template expects a URL from the `pl_subscribe_checkout_url` filter.
 * We return an admin-post URL that:
 * - validates a nonce
 * - resolves the creator monthly tier
 * - calls Politeia_PPS_Subscription_Engine::subscribe()
 * - redirects to Mercado Pago checkout (hosted flow) or back to profile with a message.
 */
class Politeia_PPS_Profile_Subscribe {
	const ACTION        = 'pl_pps_subscribe_creator';
	const NONCE_ACTION  = 'pl_pps_subscribe_creator';
	const CREATOR_PARAM = 'creator_user_id';
	const DEBUG_PARAM   = 'pl_pps_debug';

	public static function init() {
		add_filter( 'pl_subscribe_checkout_url', array( __CLASS__, 'filter_checkout_url' ), 10, 3 );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
	}

	public static function filter_checkout_url( $url, $creator_user_id, $viewer_user_id ) {
		$creator_user_id = (int) $creator_user_id;
		$viewer_user_id  = (int) $viewer_user_id;

		if ( ! is_user_logged_in() || $viewer_user_id <= 0 ) {
			self::debug( 'cta_hidden_not_logged_in', array( 'creator_user_id' => $creator_user_id, 'viewer_user_id' => $viewer_user_id ) );
			return '';
		}
		if ( $creator_user_id <= 0 || $creator_user_id === $viewer_user_id ) {
			self::debug( 'cta_hidden_invalid_creator', array( 'creator_user_id' => $creator_user_id, 'viewer_user_id' => $viewer_user_id ) );
			return '';
		}
		if ( ! class_exists( 'Politeia_PPS_Subscription_Engine' ) || ! class_exists( 'Politeia_PPS_Settings' ) ) {
			self::debug( 'cta_hidden_missing_classes', array( 'creator_user_id' => $creator_user_id, 'viewer_user_id' => $viewer_user_id ) );
			return '';
		}

		// Require MP configured; otherwise hide the CTA.
		$token = (string) Politeia_PPS_Settings::get_access_token();
		if ( $token === '' ) {
			self::debug(
				'cta_hidden_missing_token',
				array(
					'creator_user_id' => $creator_user_id,
					'viewer_user_id'  => $viewer_user_id,
					'mode'            => method_exists( 'Politeia_PPS_Settings', 'get_mode' ) ? Politeia_PPS_Settings::get_mode() : 'unknown',
				)
			);
			return '';
		}

		// Require the creator monthly tier to exist.
		if ( ! method_exists( 'Politeia_PPS_Subscription_Engine', 'get_creator_tier_by_slug' ) ) {
			self::debug( 'cta_hidden_missing_method', array( 'creator_user_id' => $creator_user_id, 'viewer_user_id' => $viewer_user_id ) );
			return '';
		}
		$tier = Politeia_PPS_Subscription_Engine::get_creator_tier_by_slug( $creator_user_id, 'monthly' );
		if ( ! is_array( $tier ) || empty( $tier['id'] ) ) {
			self::debug( 'cta_hidden_missing_tier', array( 'creator_user_id' => $creator_user_id, 'viewer_user_id' => $viewer_user_id ) );
			return '';
		}

		$args = array(
			'action'                 => self::ACTION,
			self::CREATOR_PARAM      => $creator_user_id,
			'_wpnonce'               => wp_create_nonce( self::NONCE_ACTION ),
		);

		$url = (string) add_query_arg( $args, admin_url( 'admin-post.php' ) );
		self::debug( 'cta_visible', array( 'creator_user_id' => $creator_user_id, 'viewer_user_id' => $viewer_user_id, 'tier_id' => (int) $tier['id'] ) );
		return $url;
	}

	public static function handle(): void {
		$creator_user_id = isset( $_GET[ self::CREATOR_PARAM ] ) ? (int) $_GET[ self::CREATOR_PARAM ] : 0;

		if ( ! is_user_logged_in() ) {
			$redirect = self::creator_profile_url( $creator_user_id );
			wp_safe_redirect( wp_login_url( $redirect ) );
			exit;
		}

		if ( ! wp_verify_nonce( (string) ( $_GET['_wpnonce'] ?? '' ), self::NONCE_ACTION ) ) {
			self::redirect_back( $creator_user_id, 'invalid_nonce' );
		}

		$subscriber_user_id = (int) get_current_user_id();
		if ( $creator_user_id <= 0 || $subscriber_user_id <= 0 || $creator_user_id === $subscriber_user_id ) {
			self::redirect_back( $creator_user_id, 'invalid_request' );
		}

		if ( ! class_exists( 'Politeia_PPS_Subscription_Engine' ) || ! class_exists( 'Politeia_PPS_Settings' ) ) {
			self::redirect_back( $creator_user_id, 'not_available' );
		}

		$tier = Politeia_PPS_Subscription_Engine::get_creator_tier_by_slug( $creator_user_id, 'monthly' );
		if ( ! is_array( $tier ) || empty( $tier['id'] ) ) {
			self::redirect_back( $creator_user_id, 'tier_not_found' );
		}

		$res = Politeia_PPS_Subscription_Engine::subscribe( $subscriber_user_id, (int) $tier['id'] );
		if ( is_wp_error( $res ) ) {
			$redirect_code = $res->get_error_code();
			$data          = $res->get_error_data();
			if ( $redirect_code === 'mp_api_error' && is_array( $data ) ) {
				$status = isset( $data['status'] ) ? (int) $data['status'] : 0;
				$body   = isset( $data['body'] ) ? $data['body'] : null;
				$body_code = ( is_array( $body ) && isset( $body['code'] ) ) ? (string) $body['code'] : '';
				$body_message = ( is_array( $body ) && isset( $body['message'] ) ) ? (string) $body['message'] : '';
				if ( 403 === $status && $body_code === 'PA_UNAUTHORIZED_RESULT_FROM_POLICIES' ) {
					$redirect_code = 'mp_policy_blocked';
				} elseif ( 400 === $status && $body_message === 'Back url is required' ) {
					$redirect_code = 'mp_back_url_required';
				} elseif ( 400 === $status && $body_message === 'card_token_id is required' ) {
					$redirect_code = 'mp_card_token_required';
				} elseif ( 400 === $status && $body_message === 'Both payer and collector must be real or test users' ) {
					$redirect_code = 'mp_payer_collector_mismatch';
				} elseif ( 400 === $status && $body_message === 'payer_email is required' ) {
					$redirect_code = 'mp_payer_email_required';
				}
			}

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log(
					'[PPS][DEBUG][profile_subscribe_error] ' . wp_json_encode(
						array(
							'creator_user_id'    => $creator_user_id,
							'subscriber_user_id' => $subscriber_user_id,
							'tier_id'            => (int) $tier['id'],
							'error'              => $res->get_error_code(),
							'message'            => $res->get_error_message(),
							'data'               => $res->get_error_data(),
						)
					)
				);
			}
			self::redirect_back( $creator_user_id, $redirect_code );
		}

		$redirect_url = is_array( $res ) ? (string) ( $res['redirect_url'] ?? '' ) : '';
		if ( $redirect_url !== '' ) {
			wp_safe_redirect( $redirect_url );
			exit;
		}

		// Direct flow or missing redirect: send back to profile.
		self::redirect_back( $creator_user_id, 'no_redirect' );
	}

	private static function redirect_back( int $creator_user_id, string $code ): void {
		$profile_url = self::creator_profile_url( $creator_user_id );
		$profile_url = add_query_arg(
			array(
				'pl_subscribe_error' => sanitize_key( $code ),
			),
			$profile_url
		);
		wp_safe_redirect( $profile_url );
		exit;
	}

	private static function creator_profile_url( int $creator_user_id ): string {
		$u = $creator_user_id > 0 ? get_userdata( $creator_user_id ) : null;
		if ( $u instanceof WP_User ) {
			$slug = (string) $u->user_nicename;
			if ( $slug !== '' ) {
				return (string) home_url( '/profile/' . rawurlencode( $slug ) . '/' );
			}
		}
		return (string) home_url( '/' );
	}

	private static function debug( string $event, array $data = array() ): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}
		$enabled = isset( $_GET[ self::DEBUG_PARAM ] ) && (string) $_GET[ self::DEBUG_PARAM ] === '1';
		if ( ! $enabled ) {
			return;
		}
		error_log( '[PPS][DEBUG][profile_subscribe_cta] ' . $event . ' ' . wp_json_encode( $data ) );
	}
}
