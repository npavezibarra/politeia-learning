<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Politeia_PPS_Webhooks {
	const CRON_HOOK = 'politeia_pps_process_pending_webhooks';
	const CRON_RECONCILE_HOOK = 'politeia_pps_reconcile_payments';
	const ADMIN_ACTION_PROCESS_ALL  = 'politeia_pps_process_webhooks_now';
	const ADMIN_ACTION_PROCESS_EVENT = 'politeia_pps_process_webhook_event';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register' ) );

		// Background processing of stored webhook events.
		add_filter( 'cron_schedules', array( __CLASS__, 'add_cron_schedules' ) );
		add_action( 'init', array( __CLASS__, 'maybe_schedule_processing' ), 20 );
		add_action( self::CRON_HOOK, array( __CLASS__, 'process_pending' ) );
		add_action( self::CRON_RECONCILE_HOOK, array( __CLASS__, 'reconcile_recent_payments' ) );

		// Admin tools.
		add_action( 'admin_post_' . self::ADMIN_ACTION_PROCESS_ALL, array( __CLASS__, 'admin_process_all' ) );
		add_action( 'admin_post_' . self::ADMIN_ACTION_PROCESS_EVENT, array( __CLASS__, 'admin_process_event' ) );
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
		} else {
			// Best-effort: enqueue a near-future run (Cron requires traffic).
			if ( function_exists( 'wp_schedule_single_event' ) ) {
				wp_schedule_single_event( time() + 60, self::CRON_HOOK );
			}
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

		$header = trim( (string) $header );

		// Variant A (legacy/dev): header is raw hex HMAC of body.
		$calc_body = hash_hmac( 'sha256', (string) $raw_body, (string) $secret );
		if ( hash_equals( $calc_body, $header ) ) {
			return true;
		}

		// Variant B (common webhook style): "ts=...,v1=...".
		$parts = array();
		foreach ( preg_split( '/\\s*,\\s*/', $header ) as $chunk ) {
			$kv = explode( '=', $chunk, 2 );
			if ( count( $kv ) !== 2 ) {
				continue;
			}
			$k = strtolower( trim( (string) $kv[0] ) );
			$v = trim( (string) $kv[1] );
			if ( $k !== '' && $v !== '' ) {
				$parts[ $k ] = $v;
			}
		}

		$ts = $parts['ts'] ?? '';
		$v1 = $parts['v1'] ?? '';
		if ( $ts !== '' && $v1 !== '' ) {
			// Mercado Pago (current): HMAC over "id:{data.id};request-id:{x-request-id};ts:{ts};"
			$req_id = self::get_request_id_header( $request );
			$body   = json_decode( (string) $raw_body, true );
			if ( is_array( $body ) ) {
				$data_id = '';
				if ( isset( $body['data'] ) && is_array( $body['data'] ) && isset( $body['data']['id'] ) ) {
					$data_id = (string) $body['data']['id'];
				}
				if ( $data_id === '' && isset( $body['id'] ) ) {
					$data_id = (string) $body['id'];
				}
				$data_id = trim( $data_id );
				if ( $data_id !== '' && $req_id !== '' ) {
					$manifest = sprintf( 'id:%s;request-id:%s;ts:%s;', $data_id, $req_id, $ts );
					$calc_mp  = hash_hmac( 'sha256', $manifest, (string) $secret );
					if ( hash_equals( $calc_mp, (string) $v1 ) ) {
						return true;
					}
				}
			}

			// Back-compat attempt: HMAC(ts + '.' + raw_body)
			$calc = hash_hmac( 'sha256', (string) $ts . '.' . (string) $raw_body, (string) $secret );
			if ( hash_equals( $calc, (string) $v1 ) ) {
				return true;
			}
		}

		return false;
	}

	private static function get_request_id_header( WP_REST_Request $request ): string {
		// Try common spellings first.
		$direct = (string) $request->get_header( 'X-Request-Id' );
		if ( $direct !== '' ) {
			return trim( $direct );
		}
		$direct2 = (string) $request->get_header( 'X-Request-ID' );
		if ( $direct2 !== '' ) {
			return trim( $direct2 );
		}

		// Fall back to scanning normalized header map (WP may normalize dashes to underscores).
		$headers = $request->get_headers();
		if ( is_array( $headers ) ) {
			foreach ( array( 'x-request-id', 'x_request_id', 'x_requestid', 'x_request_id' ) as $k ) {
				if ( isset( $headers[ $k ] ) ) {
					$v = $headers[ $k ];
					if ( is_array( $v ) ) {
						$v = $v[0] ?? '';
					}
					$v = is_string( $v ) ? trim( $v ) : '';
					if ( $v !== '' ) {
						return $v;
					}
				}
			}
		}
		return '';
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

	public static function admin_process_all(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden', 403 );
		}
		check_admin_referer( 'politeia_pps_process_webhooks' );

		self::process_pending( 250 );

		$ref = wp_get_referer();
		if ( ! is_string( $ref ) || $ref === '' ) {
			$ref = admin_url( 'admin.php?page=' . Politeia_PPS_Settings::MENU_SLUG );
		}
		wp_safe_redirect( add_query_arg( array( 'pps_webhooks_notice' => 'processed' ), $ref ) );
		exit;
	}

	public static function admin_process_event(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden', 403 );
		}
		check_admin_referer( 'politeia_pps_process_webhook_event' );

		$id    = isset( $_GET['event_id'] ) ? (int) $_GET['event_id'] : 0;
		$force = isset( $_GET['force'] ) && (string) $_GET['force'] === '1';
		if ( $id <= 0 ) {
			wp_die( 'Missing event_id', 400 );
		}

		if ( $force ) {
			global $wpdb;
			$table = Politeia_PPS_Subscription_Engine::webhook_events_table();
			$wpdb->update(
				$table,
				array(
					'processed'    => 0,
					'processed_at' => null,
				),
				array( 'id' => $id )
			);
		}

		self::process_event_id_public( $id );

		$ref = wp_get_referer();
		if ( ! is_string( $ref ) || $ref === '' ) {
			$ref = admin_url( 'admin.php?page=' . Politeia_PPS_Settings::MENU_SLUG );
		}
		wp_safe_redirect( add_query_arg( array( 'pps_webhooks_notice' => 'event_processed' ), $ref ) );
		exit;
	}

	public static function maybe_schedule_processing(): void {
		if ( ! function_exists( 'wp_next_scheduled' ) || ! function_exists( 'wp_schedule_event' ) ) {
			return;
		}
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			// Every 5 minutes would be ideal, but we rely on built-in schedules by default.
			// If a 'five_minutes' schedule exists, use it; otherwise fall back to hourly.
			$schedules = function_exists( 'wp_get_schedules' ) ? wp_get_schedules() : array();
			$recurrence = isset( $schedules['five_minutes'] ) ? 'five_minutes' : 'hourly';
			wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, $recurrence, self::CRON_HOOK );
		}

		if ( ! wp_next_scheduled( self::CRON_RECONCILE_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_RECONCILE_HOOK );
		}
	}

	/**
	 * @param array<string,array<string,mixed>> $schedules
	 * @return array<string,array<string,mixed>>
	 */
	public static function add_cron_schedules( $schedules ) {
		if ( ! is_array( $schedules ) ) {
			$schedules = array();
		}
		if ( ! isset( $schedules['five_minutes'] ) ) {
			$schedules['five_minutes'] = array(
				'interval' => 5 * MINUTE_IN_SECONDS,
				'display'  => __( 'Every 5 Minutes', 'politeia-payments-subscriptions' ),
			);
		}
		return $schedules;
	}

	/**
	 * Best-effort reconciliation:
	 * - For active subscriptions, search payments by external_reference and backfill missing ledger entries.
	 *
	 * This is a safety net for missed webhooks.
	 */
	public static function reconcile_recent_payments(): void {
		if ( ! class_exists( 'Politeia_PPS_Subscription_Engine' ) ) {
			return;
		}

		global $wpdb;
		if ( ! $wpdb ) {
			return;
		}

		$subs_table  = Politeia_PPS_Subscription_Engine::subs_table();
		$tiers_table = Politeia_PPS_Subscription_Engine::tiers_table();

		$limit = (int) apply_filters( 'politeia_pps_reconcile_limit', 50 );
		$days  = (int) apply_filters( 'politeia_pps_reconcile_days', 45 );
		if ( $limit <= 0 ) {
			return;
		}
		if ( $days <= 0 ) {
			$days = 45;
		}

		$since = gmdate( 'Y-m-d\\TH:i:s.000\\Z', time() - ( $days * DAY_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.id, s.creator_user_id, s.subscriber_user_id, s.tier_id, s.mp_preapproval_id, s.status, t.external_reference
				 FROM {$subs_table} s
				 JOIN {$tiers_table} t ON t.id = s.tier_id
				 WHERE s.status IN ('authorized','active','approved')
				 ORDER BY s.updated_at DESC
				 LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		if ( ! $rows ) {
			return;
		}

		$client = new Politeia_PPS_MercadoPago_Client();

		foreach ( $rows as $row ) {
			$external_reference = isset( $row['external_reference'] ) ? (string) $row['external_reference'] : '';
			if ( $external_reference === '' ) {
				continue;
			}

			// Search payments by external_reference; MP supports this filter.
			$res = $client->search_payments(
				array(
					'external_reference' => $external_reference,
					'begin_date'         => $since,
					'sort'               => 'date_created',
					'criteria'           => 'desc',
					'limit'              => 30,
					'offset'             => 0,
				)
			);

			if ( is_wp_error( $res ) || ! is_array( $res ) ) {
				continue;
			}

			$results = isset( $res['results'] ) && is_array( $res['results'] ) ? $res['results'] : array();
			if ( ! $results ) {
				continue;
			}

			foreach ( $results as $p ) {
				if ( ! is_array( $p ) ) {
					continue;
				}
				$payment_id = isset( $p['id'] ) ? preg_replace( '/[^0-9]/', '', (string) $p['id'] ) : '';
				if ( $payment_id === '' ) {
					continue;
				}
				if ( self::ledger_has_payment( $payment_id ) ) {
					continue;
				}

				// Backfill by reusing the same payment sync pipeline.
				self::sync_payment_from_mp( $payment_id, array( 'event' => 'reconcile', 'external_reference' => $external_reference ) );
			}
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

		$event_type  = sanitize_text_field( (string) ( $row['event_type'] ?? ( $payload['type'] ?? $payload['topic'] ?? 'unknown' ) ) );
		$resource_id = sanitize_text_field( (string) ( $row['resource_id'] ?? ( $payload['data']['id'] ?? $payload['id'] ?? '' ) ) );

		$handled = false;
		$type_lc = strtolower( $event_type );

		// Preapproval / subscription events: sync from MP API and update our subscription row.
		if ( $resource_id !== '' && ( false !== strpos( $type_lc, 'preapproval' ) || $type_lc === 'subscription' ) ) {
			$handled = self::sync_preapproval_from_mp( $resource_id, $payload );
		}

		// Payment events: write ledger entries and optionally renew access.
		if ( ! $handled && $resource_id !== '' && ( $type_lc === 'payment' || false !== strpos( $type_lc, 'payment' ) ) ) {
			$handled = self::sync_payment_from_mp( $resource_id, $payload );
		}

		// Fallback: if payload contains a preapproval id + status, persist it (dev/sandbox friendliness).
		if ( ! $handled ) {
			$mp_preapproval_id = sanitize_text_field( $payload['data']['id'] ?? $payload['id'] ?? '' );
			$new_status        = sanitize_text_field( $payload['status'] ?? '' );
			if ( $mp_preapproval_id && $new_status ) {
				$wpdb->update(
					Politeia_PPS_Subscription_Engine::subs_table(),
					array(
						'status'     => $new_status,
						'updated_at' => current_time( 'mysql' ),
					),
					array( 'mp_preapproval_id' => $mp_preapproval_id )
				);
				do_action( 'politeia_pps_subscription_status_changed', $mp_preapproval_id, null, $new_status, $payload );
				$handled = true;
			}
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

	public static function process_event_id_public( int $id ): void {
		self::process_event_id( $id );
	}

	private static function sync_preapproval_from_mp( string $mp_preapproval_id, array $payload ): bool {
		$mp_preapproval_id = sanitize_text_field( $mp_preapproval_id );
		if ( $mp_preapproval_id === '' ) {
			return false;
		}

		$client  = new Politeia_PPS_MercadoPago_Client();
		$details = $client->get_preapproval( $mp_preapproval_id );
		if ( is_wp_error( $details ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log(
					'[PPS][DEBUG][webhook_sync_preapproval_error] ' . wp_json_encode(
						array(
							'mp_preapproval_id' => $mp_preapproval_id,
							'error'             => $details->get_error_message(),
							'data'              => $details->get_error_data(),
						)
					)
				);
			}
			return false;
		}
		if ( ! is_array( $details ) ) {
			return false;
		}

		$new_status = sanitize_text_field( (string) ( $details['status'] ?? '' ) );
		$current_period_end_mysql = '';

		$auto = isset( $details['auto_recurring'] ) && is_array( $details['auto_recurring'] ) ? $details['auto_recurring'] : array();
		$end_raw = '';
		if ( isset( $auto['next_payment_date'] ) && is_string( $auto['next_payment_date'] ) ) {
			$end_raw = $auto['next_payment_date'];
		} elseif ( isset( $auto['end_date'] ) && is_string( $auto['end_date'] ) ) {
			$end_raw = $auto['end_date'];
		} elseif ( isset( $details['next_payment_date'] ) && is_string( $details['next_payment_date'] ) ) {
			$end_raw = $details['next_payment_date'];
		}

		if ( $end_raw !== '' ) {
			$ts = strtotime( $end_raw );
			if ( $ts ) {
				$current_period_end_mysql = gmdate( 'Y-m-d H:i:s', $ts );
			}
		}

		global $wpdb;
		$subs_table = Politeia_PPS_Subscription_Engine::subs_table();

		// Fetch the current row for delta + subscriber id.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, subscriber_user_id, status, current_period_end
				 FROM {$subs_table}
				 WHERE mp_preapproval_id = %s
				 LIMIT 1",
				$mp_preapproval_id
			),
			ARRAY_A
		);

		if ( ! is_array( $existing ) || empty( $existing['id'] ) ) {
			// Subscription not found locally (maybe created outside our flow).
			return false;
		}

		$prev_status = sanitize_text_field( (string) ( $existing['status'] ?? '' ) );
		$sub_id      = (int) ( $existing['id'] ?? 0 );

		$update = array(
			'updated_at' => current_time( 'mysql' ),
		);
		if ( $new_status !== '' ) {
			$update['status'] = $new_status;
		}
		if ( $current_period_end_mysql !== '' ) {
			$update['current_period_end'] = $current_period_end_mysql;
		}

		$wpdb->update(
			$subs_table,
			$update,
			array( 'id' => $sub_id )
		);

		if ( $new_status !== '' && $new_status !== $prev_status ) {
			do_action(
				'politeia_pps_subscription_status_changed',
				$mp_preapproval_id,
				isset( $existing['subscriber_user_id'] ) ? (int) $existing['subscriber_user_id'] : null,
				$new_status,
				array(
					'event'   => 'webhook_sync',
					'payload' => $payload,
					'details' => $details,
				)
			);
		}

		return true;
	}

	private static function sync_payment_from_mp( string $mp_payment_id, array $payload ): bool {
		$mp_payment_id = preg_replace( '/[^0-9]/', '', (string) $mp_payment_id );
		if ( ! $mp_payment_id ) {
			return false;
		}

		$client  = new Politeia_PPS_MercadoPago_Client();
		$details = $client->get_payment( $mp_payment_id );
		if ( is_wp_error( $details ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log(
					'[PPS][DEBUG][webhook_sync_payment_error] ' . wp_json_encode(
						array(
							'mp_payment_id' => $mp_payment_id,
							'error'         => $details->get_error_message(),
							'data'          => $details->get_error_data(),
						)
					)
				);
			}
			return false;
		}
		if ( ! is_array( $details ) ) {
			return false;
		}

		$mp_status = sanitize_text_field( (string) ( $details['status'] ?? '' ) );
		$currency  = strtoupper( sanitize_text_field( (string) ( $details['currency_id'] ?? 'CLP' ) ) );

		$gross_major = (float) ( $details['transaction_amount'] ?? 0 );
		$gross_minor = self::major_to_minor( $gross_major, $currency );

		$mp_fee_minor = 0;
		if ( isset( $details['fee_details'] ) && is_array( $details['fee_details'] ) ) {
			foreach ( $details['fee_details'] as $fee ) {
				if ( ! is_array( $fee ) ) {
					continue;
				}
				$amount = isset( $fee['amount'] ) ? (float) $fee['amount'] : 0;
				if ( $amount > 0 ) {
					$mp_fee_minor += self::major_to_minor( $amount, $currency );
				}
			}
		}

		$mp_preapproval_id = self::resolve_preapproval_id_from_payment( $details );
		if ( $mp_preapproval_id === '' ) {
			// Can't link to creator/subscriber without the preapproval id.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log(
					'[PPS][DEBUG][payment_missing_preapproval_link] ' . wp_json_encode(
						array(
							'mp_payment_id' => $mp_payment_id,
							'keys'          => array_keys( $details ),
							'external_reference' => isset( $details['external_reference'] ) ? $details['external_reference'] : null,
						)
					)
				);
			}
			return false;
		}

		$sub_row = self::lookup_subscription_by_mp_id( $mp_preapproval_id );
		if ( ! is_array( $sub_row ) ) {
			return false;
		}

		// Avoid duplicates: do not insert if ledger already has this mp_payment_id.
		if ( self::ledger_has_payment( $mp_payment_id ) ) {
			return true;
		}

		$breakdown = Politeia_PPS_Commission::breakdown( $gross_minor, $mp_fee_minor, $currency );

		$occurred_at = self::extract_payment_occurred_at_mysql( $details );

		$entry = array(
			'creator_user_id'           => (int) ( $sub_row['creator_user_id'] ?? 0 ),
			'subscriber_user_id'        => (int) ( $sub_row['subscriber_user_id'] ?? 0 ),
			'tier_id'                   => (int) ( $sub_row['tier_id'] ?? 0 ),
			'mp_payment_id'             => (string) $mp_payment_id,
			'mp_preapproval_id'         => (string) $mp_preapproval_id,
			'mp_status'                 => $mp_status,
			'currency'                  => $breakdown['currency'],
			'gross_amount_minor'        => (int) $breakdown['gross_amount_minor'],
			'mp_fee_minor'              => (int) $breakdown['mp_fee_minor'],
			'iva_minor'                 => (int) $breakdown['iva_minor'],
			'platform_commission_minor' => (int) $breakdown['platform_commission_minor'],
			'creator_net_minor'         => (int) $breakdown['creator_net_minor'],
			'event_source'              => 'mercadopago_webhook',
			'occurred_at'               => $occurred_at,
			'raw_payload'               => wp_json_encode(
				array(
					'webhook_payload' => $payload,
					'payment'         => $details,
				)
			),
		);

		$ins = Politeia_PPS_Subscription_Engine::record_ledger_entry( $entry );
		if ( is_wp_error( $ins ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log(
					'[PPS][DEBUG][ledger_insert_failed] ' . wp_json_encode(
						array(
							'mp_payment_id'     => $mp_payment_id,
							'mp_preapproval_id' => $mp_preapproval_id,
							'error'             => $ins->get_error_message(),
							'data'              => $ins->get_error_data(),
						)
					)
				);
			}
			return false;
		}

		// Renew access on successful payment.
		if ( $mp_status === 'approved' ) {
			do_action(
				'pl_subscription_payment_completed',
				(int) ( $sub_row['subscriber_user_id'] ?? 0 ),
				(int) ( $sub_row['creator_user_id'] ?? 0 ),
				null,
				array(
					'source'            => 'pps',
					'event'             => 'payment_approved',
					'mp_payment_id'     => $mp_payment_id,
					'mp_preapproval_id' => $mp_preapproval_id,
					'ledger_id'         => (int) $ins,
				)
			);
		}

		return true;
	}

	private static function major_to_minor( float $amount_major, string $currency ): int {
		$currency = strtoupper( $currency );
		$divisor  = in_array( $currency, array( 'CLP' ), true ) ? 1 : 100;
		return (int) round( $amount_major * $divisor );
	}

	private static function resolve_preapproval_id_from_payment( array $payment ): string {
		// 1) Direct fields/metadata.
		$candidates = array(
			$payment['preapproval_id'] ?? '',
			$payment['subscription_id'] ?? '',
		);

		$meta = isset( $payment['metadata'] ) && is_array( $payment['metadata'] ) ? $payment['metadata'] : array();
		$candidates[] = $meta['preapproval_id'] ?? '';
		$candidates[] = $meta['mp_preapproval_id'] ?? '';
		$candidates[] = $meta['subscription_id'] ?? '';
		$candidates[] = $payment['external_reference'] ?? '';
		$candidates[] = $meta['external_reference'] ?? '';

		foreach ( $candidates as $c ) {
			$c = is_string( $c ) ? trim( $c ) : '';
			if ( $c !== '' ) {
				// If this is an MP preapproval id, return it directly.
				if ( self::looks_like_preapproval_id( $c ) ) {
					return sanitize_text_field( $c );
				}
				// If this is an external_reference, attempt resolution.
				$resolved = self::resolve_preapproval_id_from_external_reference( (string) $c, $payment );
				if ( $resolved !== '' ) {
					return $resolved;
				}
			}
		}

		// 2) Merchant order fallback (when available).
		$merchant_order_id = self::extract_merchant_order_id_from_payment( $payment );
		if ( $merchant_order_id !== '' ) {
			$resolved = self::resolve_preapproval_id_from_merchant_order( $merchant_order_id, $payment );
			if ( $resolved !== '' ) {
				return $resolved;
			}
		}

		return '';
	}

	private static function looks_like_preapproval_id( string $value ): bool {
		$value = trim( $value );
		if ( $value === '' ) {
			return false;
		}
		// MP ids are often numeric, but preapproval ids are typically alphanumeric (e.g. "2c938084...").
		// We treat any non-empty non-external-reference "pps:" as a candidate.
		if ( 0 === strpos( $value, 'pps:' ) ) {
			return false;
		}
		return (bool) preg_match( '/^[A-Za-z0-9_-]{8,}$/', $value );
	}

	private static function resolve_preapproval_id_from_external_reference( string $external_reference, array $payment ): string {
		$external_reference = trim( $external_reference );
		if ( $external_reference === '' ) {
			return '';
		}
		// Our own format: pps:{creator_user_id}:{tier_slug}
		if ( 0 !== strpos( $external_reference, 'pps:' ) ) {
			return '';
		}

		$payer_email = self::extract_payer_email_from_payment( $payment );
		if ( $payer_email === '' || ! is_email( $payer_email ) ) {
			return '';
		}
		$user = get_user_by( 'email', $payer_email );
		if ( ! ( $user instanceof WP_User ) ) {
			return '';
		}

		global $wpdb;
		if ( ! $wpdb ) {
			return '';
		}

		$tiers_table = Politeia_PPS_Subscription_Engine::tiers_table();
		$subs_table  = Politeia_PPS_Subscription_Engine::subs_table();

		// Find tier by external_reference (unique in our schema).
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$tier = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, creator_user_id FROM {$tiers_table} WHERE external_reference = %s LIMIT 1",
				$external_reference
			),
			ARRAY_A
		);
		if ( ! is_array( $tier ) || empty( $tier['id'] ) ) {
			return '';
		}

		$creator_user_id    = (int) ( $tier['creator_user_id'] ?? 0 );
		$subscriber_user_id = (int) $user->ID;
		$tier_id            = (int) $tier['id'];

		if ( $creator_user_id <= 0 || $subscriber_user_id <= 0 ) {
			return '';
		}

		// Find most recent subscription row for this creator/subscriber/tier.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT mp_preapproval_id
				 FROM {$subs_table}
				 WHERE creator_user_id = %d AND subscriber_user_id = %d AND tier_id = %d
				 ORDER BY created_at DESC
				 LIMIT 1",
				$creator_user_id,
				$subscriber_user_id,
				$tier_id
			),
			ARRAY_A
		);

		$mp_preapproval_id = is_array( $row ) ? sanitize_text_field( (string) ( $row['mp_preapproval_id'] ?? '' ) ) : '';
		return $mp_preapproval_id;
	}

	private static function extract_payer_email_from_payment( array $payment ): string {
		$candidates = array();

		if ( isset( $payment['payer'] ) && is_array( $payment['payer'] ) ) {
			$candidates[] = $payment['payer']['email'] ?? '';
		}
		$candidates[] = $payment['payer_email'] ?? '';

		$ai = isset( $payment['additional_info'] ) && is_array( $payment['additional_info'] ) ? $payment['additional_info'] : array();
		if ( isset( $ai['payer'] ) && is_array( $ai['payer'] ) ) {
			$candidates[] = $ai['payer']['email'] ?? '';
		}

		foreach ( $candidates as $c ) {
			$c = is_string( $c ) ? trim( $c ) : '';
			if ( $c !== '' ) {
				return sanitize_email( $c );
			}
		}

		return '';
	}

	private static function extract_merchant_order_id_from_payment( array $payment ): string {
		$candidates = array(
			$payment['order']['id'] ?? '',
			$payment['merchant_order_id'] ?? '',
		);
		foreach ( $candidates as $c ) {
			$c = is_scalar( $c ) ? (string) $c : '';
			$c = preg_replace( '/[^0-9]/', '', $c );
			if ( $c !== '' ) {
				return $c;
			}
		}
		return '';
	}

	private static function resolve_preapproval_id_from_merchant_order( string $merchant_order_id, array $payment ): string {
		$merchant_order_id = preg_replace( '/[^0-9]/', '', (string) $merchant_order_id );
		if ( $merchant_order_id === '' ) {
			return '';
		}

		$client = new Politeia_PPS_MercadoPago_Client();
		$res    = $client->get_merchant_order( $merchant_order_id );
		if ( is_wp_error( $res ) || ! is_array( $res ) ) {
			return '';
		}

		// Try known locations first.
		if ( isset( $res['preapproval_id'] ) && is_string( $res['preapproval_id'] ) && $res['preapproval_id'] !== '' ) {
			return sanitize_text_field( $res['preapproval_id'] );
		}

		if ( isset( $res['payments'] ) && is_array( $res['payments'] ) ) {
			foreach ( $res['payments'] as $p ) {
				if ( ! is_array( $p ) ) {
					continue;
				}
				if ( isset( $p['preapproval_id'] ) && is_string( $p['preapproval_id'] ) && $p['preapproval_id'] !== '' ) {
					return sanitize_text_field( $p['preapproval_id'] );
				}
			}
		}

		// Last chance: if merchant_order carries our external_reference, use the same external_reference resolver.
		if ( isset( $res['external_reference'] ) && is_string( $res['external_reference'] ) && $res['external_reference'] !== '' ) {
			$resolved = self::resolve_preapproval_id_from_external_reference( $res['external_reference'], $payment );
			if ( $resolved !== '' ) {
				return $resolved;
			}
		}

		return '';
	}

	private static function extract_payment_occurred_at_mysql( array $payment ): string {
		$candidates = array(
			$payment['date_approved'] ?? '',
			$payment['date_created'] ?? '',
		);
		foreach ( $candidates as $c ) {
			$c = is_string( $c ) ? trim( $c ) : '';
			if ( $c === '' ) {
				continue;
			}
			$ts = strtotime( $c );
			if ( $ts ) {
				return gmdate( 'Y-m-d H:i:s', $ts );
			}
		}
		return current_time( 'mysql' );
	}

	/**
	 * @return array<string,mixed>|null
	 */
	private static function lookup_subscription_by_mp_id( string $mp_preapproval_id ): ?array {
		$mp_preapproval_id = sanitize_text_field( $mp_preapproval_id );
		if ( $mp_preapproval_id === '' || ! class_exists( 'Politeia_PPS_Subscription_Engine' ) ) {
			return null;
		}
		global $wpdb;
		if ( ! $wpdb ) {
			return null;
		}
		$table = Politeia_PPS_Subscription_Engine::subs_table();
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

	private static function ledger_has_payment( string $mp_payment_id ): bool {
		$mp_payment_id = sanitize_text_field( $mp_payment_id );
		if ( $mp_payment_id === '' ) {
			return false;
		}
		global $wpdb;
		if ( ! $wpdb ) {
			return false;
		}
		$table = Politeia_PPS_Subscription_Engine::ledger_table();
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE mp_payment_id = %s",
				$mp_payment_id
			)
		);
		return $count > 0;
	}
}
