<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bridge PPS subscription lifecycle to PL_Relationships (TYPE_SUBSCRIBE).
 *
 * Goal:
 * - When a Mercado Pago subscription becomes active/authorized, grant access in the platform.
 * - When cancelled/paused, revoke access.
 *
 * Notes:
 * - We intentionally keep this minimal and conservative. Webhooks processing will eventually
 *   become the source of truth for recurring renewals; for now we grant on creation when
 *   MP returns an "active-ish" status.
 */
class Politeia_PPS_Relationships_Bridge {
	public static function init(): void {
		// Created from subscribe() call.
		add_action( 'politeia_pps_subscription_created', array( __CLASS__, 'on_created' ), 10, 5 );
		// Status changes from cancel flow or webhooks.
		add_action( 'politeia_pps_subscription_status_changed', array( __CLASS__, 'on_status_changed' ), 10, 4 );
	}

	/**
	 * @param string $mp_preapproval_id
	 * @param int    $subscriber_user_id
	 * @param int    $creator_user_id
	 * @param int    $tier_id
	 * @param mixed  $mp_response
	 */
	public static function on_created( $mp_preapproval_id, $subscriber_user_id, $creator_user_id, $tier_id, $mp_response ): void {
		if ( ! class_exists( 'PL_Relationships' ) ) {
			return;
		}

		$subscriber_user_id = (int) $subscriber_user_id;
		$creator_user_id    = (int) $creator_user_id;
		if ( $subscriber_user_id <= 0 || $creator_user_id <= 0 || $subscriber_user_id === $creator_user_id ) {
			return;
		}

		$status = '';
		if ( is_array( $mp_response ) && isset( $mp_response['status'] ) ) {
			$status = sanitize_text_field( (string) $mp_response['status'] );
		}

		if ( self::is_effective_mp_subscription_status( $status ) ) {
			/**
			 * Standard integration point used by other payment sources (e.g. WooCommerce).
			 * It will upsert TYPE_SUBSCRIBE as accepted with a default expiry (owner meta or 30 days).
			 */
			do_action(
				'pl_subscription_payment_completed',
				$subscriber_user_id,
				$creator_user_id,
				null,
				array(
					'source'            => 'pps',
					'mp_preapproval_id' => sanitize_text_field( (string) $mp_preapproval_id ),
					'tier_id'           => (int) $tier_id,
					'status'            => $status,
				)
			);
		}
	}

	/**
	 * @param string     $mp_preapproval_id
	 * @param int|null   $subscriber_user_id
	 * @param string     $new_status
	 * @param mixed|null $payload
	 */
	public static function on_status_changed( $mp_preapproval_id, $subscriber_user_id, $new_status, $payload = null ): void {
		if ( ! class_exists( 'PL_Relationships' ) ) {
			return;
		}

		$mp_preapproval_id    = sanitize_text_field( (string) $mp_preapproval_id );
		$subscriber_user_id_i = (int) $subscriber_user_id;
		$new_status           = sanitize_text_field( (string) $new_status );

		$creator_user_id = 0;
		if ( $subscriber_user_id_i > 0 ) {
			$row = self::lookup_subscription_by_mp_id( $mp_preapproval_id );
			$creator_user_id = is_array( $row ) ? (int) ( $row['creator_user_id'] ?? 0 ) : 0;
		} else {
			$row = self::lookup_subscription_by_mp_id( $mp_preapproval_id );
			if ( is_array( $row ) ) {
				$subscriber_user_id_i = (int) ( $row['subscriber_user_id'] ?? 0 );
				$creator_user_id      = (int) ( $row['creator_user_id'] ?? 0 );
			}
		}

		if ( $subscriber_user_id_i <= 0 || $creator_user_id <= 0 || $subscriber_user_id_i === $creator_user_id ) {
			return;
		}

		if ( self::is_effective_mp_subscription_status( $new_status ) ) {
			do_action(
				'pl_subscription_payment_completed',
				$subscriber_user_id_i,
				$creator_user_id,
				null,
				array(
					'source'            => 'pps',
					'mp_preapproval_id' => $mp_preapproval_id,
					'status'            => $new_status,
					'event'             => 'status_changed',
				)
			);
			return;
		}

		// Revoke access for cancelled-ish statuses.
		if ( self::is_revoking_mp_subscription_status( $new_status ) ) {
			PL_Relationships::upsert_relationship(
				$subscriber_user_id_i,
				$creator_user_id,
				PL_Relationships::TYPE_SUBSCRIBE,
				PL_Relationships::STATUS_REVOKED,
				null
			);
		}
	}

	private static function is_effective_mp_subscription_status( string $status ): bool {
		$status = strtolower( trim( $status ) );
		return in_array( $status, array( 'authorized', 'active', 'approved' ), true );
	}

	private static function is_revoking_mp_subscription_status( string $status ): bool {
		$status = strtolower( trim( $status ) );
		return in_array( $status, array( 'cancelled', 'canceled', 'paused', 'suspended', 'expired' ), true );
	}

	/**
	 * @return array<string,mixed>|null
	 */
	private static function lookup_subscription_by_mp_id( string $mp_preapproval_id ): ?array {
		if ( $mp_preapproval_id === '' || ! class_exists( 'Politeia_PPS_Subscription_Engine' ) ) {
			return null;
		}

		global $wpdb;
		if ( ! $wpdb ) {
			return null;
		}

		$table = Politeia_PPS_Subscription_Engine::subs_table();
		if ( ! is_string( $table ) || $table === '' ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, creator_user_id, subscriber_user_id, tier_id, mp_preapproval_id, status
				 FROM {$table}
				 WHERE mp_preapproval_id = %s
				 LIMIT 1",
				$mp_preapproval_id
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}
}

