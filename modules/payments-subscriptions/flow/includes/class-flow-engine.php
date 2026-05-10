<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Flow business logic for tier plan synchronization.
 *
 * Phase 2 scope:
 * - Create/update a Flow "plan" that matches the creator's monthly tier.
 * - Persist the Flow plan id in `wp_politeia_subscription_meta.flow_plan_id`.
 */
class Politeia_PPS_Flow_Engine {
	/**
	 * Sync a tier into Flow.
	 *
	 * @param int $tier_id
	 * @return array|WP_Error
	 */
	public static function upsert_plan_for_tier( $tier_id ) {
		global $wpdb;

		$tier_id = (int) $tier_id;
		if ( $tier_id <= 0 ) {
			return new WP_Error( 'invalid_tier', 'Invalid tier id.' );
		}
		if ( ! class_exists( 'Politeia_PPS_Settings' ) || ! class_exists( 'Politeia_PPS_Subscription_Engine' ) ) {
			return new WP_Error( 'missing_dependencies', 'Missing PPS dependencies.' );
		}

		$mode   = Politeia_PPS_Settings::get_mode();
		$api    = Politeia_PPS_Settings::get_flow_api_key( $mode );
		$secret = Politeia_PPS_Settings::get_flow_secret( $mode );

		if ( '' === trim( (string) $api ) || '' === trim( (string) $secret ) ) {
			// Not configured: don't block tier saves.
			return array(
				'ok'      => false,
				'skipped' => true,
				'reason'  => 'flow_not_configured',
			);
		}

		$tiers_table = Politeia_PPS_Subscription_Engine::tiers_table();
		$tier        = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$tiers_table} WHERE id = %d LIMIT 1", $tier_id ),
			ARRAY_A
		);
		if ( ! is_array( $tier ) ) {
			return new WP_Error( 'tier_not_found', 'Tier not found.' );
		}

		$creator_user_id = (int) ( $tier['creator_user_id'] ?? 0 );
		$tier_slug       = sanitize_title( (string) ( $tier['tier_slug'] ?? '' ) );
		$amount_minor    = (int) ( $tier['amount_minor'] ?? 0 );
		$currency        = strtoupper( sanitize_text_field( (string) ( $tier['currency'] ?? 'CLP' ) ) );
		$tier_name       = sanitize_text_field( (string) ( $tier['tier_name'] ?? '' ) );

		if ( $creator_user_id <= 0 || '' === $tier_slug || $amount_minor <= 0 ) {
			return new WP_Error( 'invalid_tier_row', 'Tier row missing required fields.' );
		}

		$plan_id = sanitize_text_field( (string) ( $tier['flow_plan_id'] ?? '' ) );
		if ( '' === $plan_id ) {
			$plan_id = self::build_flow_plan_id( $creator_user_id, $tier_slug, $mode );
		}

		$params = array(
			'planId'         => $plan_id,
			'name'           => '' !== $tier_name ? $tier_name : ( 'Politeia ' . $tier_slug ),
			'amount'         => $amount_minor,
			'currency'       => $currency,
			'interval'       => 3, // mensual
			'interval_count' => 1,
		);

		$client = new Politeia_PPS_Flow_Client();

		// If we already have a plan id stored, try edit first. Otherwise create.
		$is_edit = '' !== (string) ( $tier['flow_plan_id'] ?? '' );
		$path    = $is_edit ? '/plans/edit' : '/plans/create';
		$res     = $client->request( 'POST', $path, $params, $api, $secret, $mode );

		// If create fails because the plan already exists, attempt edit as a fallback.
		if ( ! $res['ok'] && ! $is_edit && self::looks_like_plan_exists_error( $res ) ) {
			$res = $client->request( 'POST', '/plans/edit', $params, $api, $secret, $mode );
		}

		if ( empty( $res['ok'] ) ) {
			return new WP_Error(
				'flow_plan_sync_failed',
				'Flow plan sync failed.',
				array(
					'status' => $res['status'] ?? null,
					'url'    => $res['url'] ?? null,
					'body'   => $res['body'] ?? null,
					'raw'    => $res['raw'] ?? null,
				)
			);
		}

		// Persist plan id locally (idempotent).
		$wpdb->update(
			$tiers_table,
			array(
				'flow_plan_id' => $plan_id,
				'updated_at'   => current_time( 'mysql' ),
			),
			array( 'id' => $tier_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return array(
			'ok'      => true,
			'plan_id' => $plan_id,
			'mode'    => $mode,
			'flow'    => $res['body'] ?? null,
		);
	}

	private static function build_flow_plan_id( $creator_user_id, $tier_slug, $mode ) {
		$creator_user_id = (int) $creator_user_id;
		$tier_slug       = sanitize_title( (string) $tier_slug );
		$prefix          = 'live' === $mode ? 'pps' : 'pps_test';
		return $prefix . '_' . $creator_user_id . '_' . $tier_slug;
	}

	private static function looks_like_plan_exists_error( array $res ) {
		$body = $res['body'] ?? null;
		if ( is_array( $body ) ) {
			$msg = (string) ( $body['message'] ?? '' );
			$err = (string) ( $body['error'] ?? '' );
			$txt = strtolower( $msg . ' ' . $err );
			return false !== strpos( $txt, 'exist' ) || false !== strpos( $txt, 'ya existe' );
		}
		$raw = strtolower( (string) ( $res['raw'] ?? '' ) );
		return false !== strpos( $raw, 'exist' ) || false !== strpos( $raw, 'ya existe' );
	}
}

