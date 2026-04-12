<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Politeia_PPS_Webhooks {
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register' ) );
	}

	public static function register() {
		register_rest_route(
			'politeia/v1',
			'/mercadopago/webhook',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public static function handle( WP_REST_Request $request ) {
		$raw_body = $request->get_body();
		$payload  = json_decode( $raw_body, true );
		if ( ! is_array( $payload ) ) {
			$payload = array();
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[PPS][DEBUG][webhook_received] headers=' . wp_json_encode( $request->get_headers() ) . ' body=' . $raw_body );
		}

		if ( ! self::verify_signature( $request, $raw_body ) ) {
			return new WP_REST_Response( array( 'error' => 'invalid_signature' ), 401 );
		}

		$event_type  = sanitize_text_field( $payload['type'] ?? $payload['topic'] ?? 'unknown' );
		$resource_id = sanitize_text_field( $payload['data']['id'] ?? $payload['id'] ?? '' );
		$event_id    = sanitize_text_field( $payload['action'] ?? $payload['event_id'] ?? '' );

		$stored_id = self::store_event( $event_type, $resource_id, $event_id, $payload );
		if ( is_wp_error( $stored_id ) ) {
			return new WP_REST_Response( array( 'error' => 'store_failed', 'details' => $stored_id->get_error_message() ), 500 );
		}

		/**
		 * Process asynchronously by default; allow immediate processing via filter for dev environments.
		 */
		$process_now = (bool) apply_filters( 'politeia_pps_process_webhook_immediately', false, $payload );
		if ( $process_now ) {
			self::process_event_id( (int) $stored_id );
		}

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	private static function verify_signature( WP_REST_Request $request, $raw_body ) {
		$secret = Politeia_PPS_Settings::get( 'mp_webhook_secret', '' );
		if ( ! $secret ) {
			// If no secret configured, accept but still store payload; dev-friendly default.
			return true;
		}

		$header = $request->get_header( 'X-Signature' );
		if ( ! $header ) {
			return false;
		}

		$calc = hash_hmac( 'sha256', (string) $raw_body, (string) $secret );
		return hash_equals( $calc, (string) $header );
	}

	private static function store_event( $event_type, $resource_id, $event_id, $payload ) {
		global $wpdb;
		$table = Politeia_PPS_Subscription_Engine::webhook_events_table();
		$now   = current_time( 'mysql' );

		$ok = $wpdb->insert(
			$table,
			array(
				'event_id'     => $event_id ? $event_id : null,
				'event_type'   => $event_type,
				'resource_id'  => $resource_id ? $resource_id : null,
				'processed'    => 0,
				'received_at'  => $now,
				'processed_at' => null,
				'payload'      => wp_json_encode( $payload ),
			),
			array( '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);

		if ( ! $ok ) {
			return new WP_Error( 'db_insert_failed', 'Failed to store webhook event.', array( 'error' => $wpdb->last_error ) );
		}

		return (int) $wpdb->insert_id;
	}

	public static function process_pending( $limit = 25 ) {
		global $wpdb;
		$table = Politeia_PPS_Subscription_Engine::webhook_events_table();
		$rows  = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE processed = 0 ORDER BY received_at ASC LIMIT %d", (int) $limit ),
			ARRAY_A
		);

		foreach ( $rows as $row ) {
			self::process_event_id( (int) $row['id'] );
		}
	}

	private static function process_event_id( $id ) {
		global $wpdb;
		$table = Politeia_PPS_Subscription_Engine::webhook_events_table();

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ), ARRAY_A );
		if ( ! $row || (int) $row['processed'] === 1 ) {
			return;
		}

		$payload = json_decode( (string) ( $row['payload'] ?? '' ), true );
		if ( ! is_array( $payload ) ) {
			$payload = array();
		}

		/**
		 * Minimal handler: if a preapproval status is included, persist it.
		 * In production this should fetch resource details from MP API based on topic/type.
		 */
		$mp_preapproval_id = sanitize_text_field( $payload['data']['id'] ?? $payload['id'] ?? '' );
		$new_status        = sanitize_text_field( $payload['status'] ?? '' );

		if ( $mp_preapproval_id && $new_status ) {
			// Update internal subscription status and notify.
			$wpdb->update(
				Politeia_PPS_Subscription_Engine::subs_table(),
				array(
					'status'     => $new_status,
					'updated_at' => current_time( 'mysql' ),
				),
				array( 'mp_preapproval_id' => $mp_preapproval_id )
			);
			do_action( 'politeia_pps_subscription_status_changed', $mp_preapproval_id, null, $new_status, $payload );
		}

		$wpdb->update(
			$table,
			array(
				'processed'    => 1,
				'processed_at' => current_time( 'mysql' ),
			),
			array( 'id' => (int) $id )
		);
	}
}
