<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Politeia_PPS_Subscription_Engine {
	public static function tiers_table() {
		global $wpdb;
		return $wpdb->prefix . 'politeia_subscription_meta';
	}

	public static function subs_table() {
		global $wpdb;
		return $wpdb->prefix . 'politeia_subscriptions';
	}

	public static function ledger_table() {
		global $wpdb;
		return $wpdb->prefix . 'politeia_transaction_ledger';
	}

	public static function webhook_events_table() {
		global $wpdb;
		return $wpdb->prefix . 'politeia_mp_webhook_events';
	}

	public static function create_tier( $creator_user_id, $tier ) {
		global $wpdb;
		$table = self::tiers_table();

		$creator_user_id = (int) $creator_user_id;

		$tier_name      = sanitize_text_field( $tier['tier_name'] ?? '' );
		$tier_slug      = sanitize_title( $tier['tier_slug'] ?? $tier_name );
		$currency       = strtoupper( sanitize_text_field( $tier['currency'] ?? Politeia_PPS_Locale::default_currency_for_locale() ) );
		$interval_unit  = sanitize_text_field( $tier['interval_unit'] ?? 'month' );
		$interval_count = max( 1, (int) ( $tier['interval_count'] ?? 1 ) );
		$amount_minor   = (int) ( $tier['amount_minor'] ?? 0 );

		if ( ! $creator_user_id || ! $tier_name || $amount_minor <= 0 ) {
			return new WP_Error( 'invalid_tier', 'Missing required tier fields.' );
		}

		$external_reference = self::build_external_reference( $creator_user_id, $tier_slug );

		$now = current_time( 'mysql' );
		$ok  = $wpdb->insert(
			$table,
			array(
				'creator_user_id'    => $creator_user_id,
				'tier_slug'          => $tier_slug,
				'tier_name'          => $tier_name,
				'amount_minor'       => $amount_minor,
				'currency'           => $currency,
				'interval_unit'      => $interval_unit,
				'interval_count'     => $interval_count,
				'status'             => 'active',
				'mp_plan_id'         => null,
				'external_reference' => $external_reference,
				'created_at'         => $now,
				'updated_at'         => $now,
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( ! $ok ) {
			return new WP_Error( 'db_insert_failed', 'Failed to create tier.', array( 'error' => $wpdb->last_error ) );
		}

		return (int) $wpdb->insert_id;
	}

	public static function get_creator_tiers( $creator_user_id ) {
		global $wpdb;
		$table           = self::tiers_table();
		$creator_user_id = (int) $creator_user_id;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE creator_user_id = %d AND status = 'active' ORDER BY amount_minor ASC",
				$creator_user_id
			),
			ARRAY_A
		);
	}

	/**
	 * Convenience helpers for the "single tier monthly" MVP.
	 */
	public static function get_creator_tier_by_slug( $creator_user_id, $tier_slug ) {
		global $wpdb;
		$table           = self::tiers_table();
		$creator_user_id = (int) $creator_user_id;
		$tier_slug       = sanitize_title( (string) $tier_slug );

		if ( ! $creator_user_id || $tier_slug === '' ) {
			return null;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE creator_user_id = %d AND tier_slug = %s AND status = 'active' ORDER BY id DESC LIMIT 1",
				$creator_user_id,
				$tier_slug
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Create or update a creator's single monthly tier (tier_slug = "monthly").
	 *
	 * Notes:
	 * - This is intended for the Center/Profile setting screen (fixed monthly period).
	 * - If the amount changes and the tier had an mp_plan_id, we clear mp_plan_id so
	 *   new subscribers will generate a new plan on next subscribe flow.
	 *
	 * @return int|WP_Error Tier id
	 */
	public static function upsert_creator_monthly_tier( $creator_user_id, $amount_minor, $currency = '' ) {
		global $wpdb;
		$table           = self::tiers_table();
		$creator_user_id = (int) $creator_user_id;
		$amount_minor    = (int) $amount_minor;

		if ( ! $creator_user_id || $amount_minor <= 0 ) {
			return new WP_Error( 'invalid_amount', 'Invalid monthly amount.' );
		}

		$tier_slug = 'monthly';
		$tier_name = __( 'Suscripción mensual', 'politeia-payments-subscriptions' );

		$currency = strtoupper( sanitize_text_field( (string) $currency ) );
		if ( $currency === '' ) {
			$currency = Politeia_PPS_Locale::default_currency_for_locale();
		}

		$existing = self::get_creator_tier_by_slug( $creator_user_id, $tier_slug );
		if ( ! $existing ) {
			$tier_id = self::create_tier(
				$creator_user_id,
				array(
					'tier_name'      => $tier_name,
					'tier_slug'      => $tier_slug,
					'amount_minor'   => $amount_minor,
					'currency'       => $currency,
					'interval_unit'  => 'month',
					'interval_count' => 1,
				)
			);

			if ( is_wp_error( $tier_id ) ) {
				return $tier_id;
			}

			// Phase 2: sync Flow plan if Flow is configured (best-effort unless configured and failing).
			if ( class_exists( 'Politeia_PPS_Flow_Engine' ) ) {
				$flow_res = Politeia_PPS_Flow_Engine::upsert_plan_for_tier( (int) $tier_id );
				if ( is_wp_error( $flow_res ) ) {
					return $flow_res;
				}
			}

			return (int) $tier_id;
		}

		$now          = current_time( 'mysql' );
		$amount_delta = (int) ( $existing['amount_minor'] ?? 0 ) !== $amount_minor;

		// Price change policy (option 2): cancel existing subscribers at period end and require re-subscribe.
		if ( $amount_delta ) {
			self::schedule_price_change_cancellation_at_period_end( (int) $existing['id'] );
		}

		$data = array(
			'tier_name'      => $tier_name,
			'amount_minor'   => $amount_minor,
			'currency'       => $currency,
			'interval_unit'  => 'month',
			'interval_count' => 1,
			'status'         => 'active',
			'updated_at'     => $now,
		);

		$format = array( '%s', '%d', '%s', '%s', '%d', '%s', '%s' );

		// If amount changed, clear plan id so the next subscribe creates a fresh plan.
		if ( $amount_delta && ! empty( $existing['mp_plan_id'] ) ) {
			$data['mp_plan_id'] = null;
			$format[]           = '%s';
		}

		$ok = $wpdb->update(
			$table,
			$data,
			array( 'id' => (int) $existing['id'] ),
			$format,
			array( '%d' )
		);

		if ( $ok === false ) {
			return new WP_Error( 'db_update_failed', 'Failed to update tier.', array( 'error' => $wpdb->last_error ) );
		}

		// Phase 2: sync Flow plan if Flow is configured (best-effort unless configured and failing).
		if ( class_exists( 'Politeia_PPS_Flow_Engine' ) ) {
			$flow_res = Politeia_PPS_Flow_Engine::upsert_plan_for_tier( (int) $existing['id'] );
			if ( is_wp_error( $flow_res ) ) {
				return $flow_res;
			}
		}

		return (int) $existing['id'];
	}

	/**
	 * When a tier price changes, we do not migrate existing subscriptions to the new price.
	 * Instead, we schedule a cancellation at the current period end so the subscriber must
	 * actively re-subscribe to the updated tier.
	 *
	 * Implementation notes:
	 * - We attempt to set Mercado Pago preapproval auto_recurring.end_date to the next payment date.
	 * - We also mark the internal subscription row with cancel_at_period_end=1 and store current_period_end when available.
	 */
	private static function schedule_price_change_cancellation_at_period_end( int $tier_id ): void {
		global $wpdb;

		if ( $tier_id <= 0 ) {
			return;
		}

		$subs_table = self::subs_table();
		$rows       = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, mp_preapproval_id, status FROM {$subs_table} WHERE tier_id = %d AND status IN ('authorized','active','approved') AND cancel_at_period_end = 0",
				$tier_id
			),
			ARRAY_A
		);

		if ( ! $rows ) {
			return;
		}

		$client = new Politeia_PPS_MercadoPago_Client();
		$now    = current_time( 'mysql' );

		foreach ( $rows as $row ) {
			$sub_id          = (int) ( $row['id'] ?? 0 );
			$mp_preapproval  = sanitize_text_field( (string) ( $row['mp_preapproval_id'] ?? '' ) );
			$current_end_raw = '';

			if ( $sub_id <= 0 || $mp_preapproval === '' ) {
				continue;
			}

			$details = $client->get_preapproval( $mp_preapproval );
			if ( ! is_wp_error( $details ) && is_array( $details ) ) {
				$current_end_raw = (string) self::extract_preapproval_period_end( $details );

				if ( $current_end_raw !== '' ) {
					$update_payload = array(
						'auto_recurring' => array(
							'end_date' => $current_end_raw,
						),
					);

					$upd = $client->update_preapproval( $mp_preapproval, $update_payload );
					if ( is_wp_error( $upd ) ) {
						self::debug(
							'price_change_end_date_update_failed',
							array(
								'tier_id'          => $tier_id,
								'mp_preapproval_id'=> $mp_preapproval,
								'error'            => $upd->get_error_message(),
								'data'             => $upd->get_error_data(),
								'end_date'         => $current_end_raw,
							)
						);
					}
				}
			}

			$current_end_mysql = '';
			if ( $current_end_raw !== '' ) {
				$ts = strtotime( $current_end_raw );
				if ( $ts ) {
					$current_end_mysql = date( 'Y-m-d H:i:s', $ts );
				}
			}

			$data = array(
				'cancel_at_period_end' => 1,
				'updated_at'           => $now,
			);
			$format = array( '%d', '%s' );
			if ( $current_end_mysql !== '' ) {
				$data['current_period_end'] = $current_end_mysql;
				$format[]                   = '%s';
			}

			$wpdb->update(
				$subs_table,
				$data,
				array( 'id' => $sub_id ),
				$format,
				array( '%d' )
			);
		}
	}

	private static function extract_preapproval_period_end( array $details ): string {
		$auto = isset( $details['auto_recurring'] ) && is_array( $details['auto_recurring'] ) ? $details['auto_recurring'] : array();

		$candidates = array(
			$auto['end_date'] ?? '',
			$auto['next_payment_date'] ?? '',
			$details['next_payment_date'] ?? '',
		);

		foreach ( $candidates as $c ) {
			$c = is_string( $c ) ? trim( $c ) : '';
			if ( $c !== '' ) {
				return $c;
			}
		}

		return '';
	}

	public static function get_tier( $tier_id ) {
		global $wpdb;
		$table   = self::tiers_table();
		$tier_id = (int) $tier_id;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $tier_id ), ARRAY_A );
	}

	/**
	 * Convert Flow datetime strings into MySQL DATETIME (best-effort).
	 *
	 * @param string $value
	 * @return string|null
	 */
	public static function normalize_datetime( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return null;
		}
		// Flow uses "YYYY-MM-DD HH:MM:SS"
		if ( preg_match( '/^\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}:\\d{2}$/', $value ) ) {
			return $value;
		}
		$ts = strtotime( $value );
		if ( ! $ts ) {
			return null;
		}
		return gmdate( 'Y-m-d H:i:s', $ts );
	}

	public static function subscribe( $subscriber_user_id, $tier_id, $payer_email = '', $payment = array() ) {
		// Flow gateway: we only support redirect enrollment (card registration) for now.
		$payment = is_array( $payment ) ? $payment : array();
		$gateway = sanitize_key( (string) ( $payment['gateway'] ?? '' ) );
		if ( 'flow' === $gateway && class_exists( 'Politeia_PPS_Flow_Subscribe' ) ) {
			return Politeia_PPS_Flow_Subscribe::start( (int) $subscriber_user_id, (int) $tier_id );
		}

		$subscriber_user_id = (int) $subscriber_user_id;
		$tier               = self::get_tier( $tier_id );
		if ( ! $tier ) {
			return new WP_Error( 'tier_not_found', 'Tier not found.' );
		}

		if ( (int) $tier['creator_user_id'] === (int) $subscriber_user_id ) {
			return new WP_Error( 'cannot_self_subscribe', 'You cannot subscribe to your own tier.' );
		}

		$client = new Politeia_PPS_MercadoPago_Client();

		// Decide subscription flow.
		// - hosted: redirect the payer to Mercado Pago (init_point/sandbox_init_point)
		// - direct: create the subscription with an authorized card_token_id (no redirect)
		$payment       = is_array( $payment ) ? $payment : array();
		$card_token_id = sanitize_text_field( $payment['card_token_id'] ?? '' );
		$flow          = $card_token_id !== '' ? 'direct' : (string) Politeia_PPS_Settings::get( 'subscription_flow', 'hosted' );
		if ( ! in_array( $flow, array( 'hosted', 'direct' ), true ) ) {
			$flow = 'hosted';
		}
		if ( 'direct' === $flow && $card_token_id === '' ) {
			// Prevent direct flow without token to avoid confusing MP errors.
			$flow = 'hosted';
		}

		// Determine payer_email:
		// - In TEST, admin may configure an override (useful for MP test buyers).
		// - Otherwise default to the logged-in WP user's email.
		//
		// IMPORTANT:
		// - For HOSTED checkout we intentionally avoid sending payer_email in LIVE, because Mercado Pago
		//   can reject preapproval creation with "Both payer and collector must be real or test users"
		//   based solely on payer_email validation. In hosted checkout MP collects/validates payer data.
		// - For DIRECT (tokenized) flow, payer_email is expected to be provided (from Brick form or WP user).
		$override_email = (string) Politeia_PPS_Settings::get( 'payer_email_override', '' );
		$mode           = method_exists( 'Politeia_PPS_Settings', 'get_mode' ) ? (string) Politeia_PPS_Settings::get_mode() : 'test';
		$mode           = in_array( $mode, array( 'test', 'live' ), true ) ? $mode : 'test';

		// Safety: payer_email_override is intended only for TEST buyers.
		// In LIVE, never override the payer email (prevents payer/collector env mismatch).
		if ( $mode === 'test' && $override_email !== '' && is_email( $override_email ) ) {
			$payer_email = $override_email;
		}
		if ( ! $payer_email ) {
			$user = get_user_by( 'id', $subscriber_user_id );
			if ( $user && ! empty( $user->user_email ) ) {
				$payer_email = (string) $user->user_email;
			}
		}

		$access_token = (string) Politeia_PPS_Settings::get_access_token();
		$is_test      = 0 === strpos( $access_token, 'TEST-' );

		$mp_plan_id = isset( $tier['mp_plan_id'] ) ? (string) $tier['mp_plan_id'] : '';
		$back_url   = (string) Politeia_PPS_Settings::get( 'success_url', '' );
		if ( $back_url === '' ) {
			$back_url = home_url( '/' );
		}

		// Important: In Mercado Pago, subscriptions with an associated plan require `card_token_id` (direct).
		// For hosted redirects, create the subscription WITHOUT a plan so MP can present checkout.
		$mp_plan_id = '';

		$payload = array(
			'reason'             => $tier['tier_name'],
			'external_reference' => $tier['external_reference'],
			'auto_recurring'     => array(
				'frequency'          => (int) $tier['interval_count'],
				'frequency_type'     => self::map_interval_unit( $tier['interval_unit'] ),
				'transaction_amount' => self::minor_to_major_amount( (int) $tier['amount_minor'], $tier['currency'] ),
				'currency_id'        => $tier['currency'],
			),
			'back_url'           => $back_url,
		);

		// Only include payer_email when it helps (DIRECT, or TEST override). For HOSTED in LIVE, omit it.
		$should_send_payer_email = ( 'direct' === $flow ) || ( $mode === 'test' && $override_email !== '' );
		if ( $should_send_payer_email && $payer_email ) {
			$payload['payer_email'] = sanitize_email( $payer_email );
		}

		if ( 'direct' === $flow ) {
			$payload['card_token_id'] = $card_token_id;
			// Required by Mercado Pago: subscriptions with authorized payment must set status=authorized.
			$payload['status'] = 'authorized';

			$payment_method_id = sanitize_text_field( $payment['payment_method_id'] ?? '' );
			$issuer_id         = sanitize_text_field( $payment['issuer_id'] ?? '' );
			if ( $payment_method_id !== '' ) {
				$payload['payment_method_id'] = $payment_method_id;
			}
			if ( $issuer_id !== '' ) {
				$payload['issuer_id'] = $issuer_id;
			}
		}

		self::debug(
			'preapproval_create_request',
			array(
				'subscriber_user_id' => $subscriber_user_id,
				'creator_user_id'    => (int) $tier['creator_user_id'],
				'tier_id'            => (int) $tier['id'],
				'flow'               => $flow,
				'mp_plan_id'         => $mp_plan_id ? $mp_plan_id : null,
				'has_card_token'     => (bool) $card_token_id,
				'external_reference' => $payload['external_reference'],
				'amount_major'       => $payload['auto_recurring']['transaction_amount'],
				'currency'           => $payload['auto_recurring']['currency_id'],
			)
		);

		$res = $client->create_preapproval( $payload );
		if ( is_wp_error( $res ) ) {
			self::debug(
				'preapproval_create_error',
				array(
					'error' => $res->get_error_message(),
					'data'  => $res->get_error_data(),
				)
			);
			return $res;
		}

		$mp_preapproval_id = isset( $res['id'] ) ? (string) $res['id'] : '';
		if ( ! $mp_preapproval_id ) {
			return new WP_Error( 'mp_missing_id', 'Mercado Pago response missing id.', $res );
		}

		$collector_id = isset( $res['collector_id'] ) ? (string) $res['collector_id'] : '';
		$expected_id  = (string) Politeia_PPS_Settings::get( 'expected_seller_user_id', '' );
		if ( $expected_id && $collector_id && $collector_id !== $expected_id ) {
			self::debug( 'collector_mismatch', array( 'collector_id' => $collector_id, 'expected' => $expected_id, 'is_test' => 0 === strpos( Politeia_PPS_Settings::get_access_token(), 'TEST-' ), 'preapproval' => $mp_preapproval_id ) );
			return new WP_Error( 'collector_mismatch', 'El access token pertenece a otro usuario (collector_id). Verifica que el token y el Expected Seller User ID correspondan al mismo entorno (test o live).', array( 'collector_id' => $collector_id, 'expected' => $expected_id ) );
		}

		$details = null;
		if ( 'hosted' === $flow ) {
			// Fetch preapproval details to obtain init URLs (especially sandbox_init_point).
			$details = $client->get_preapproval( $mp_preapproval_id );
			if ( is_wp_error( $details ) ) {
				self::debug(
					'preapproval_fetch_error',
					array(
						'mp_preapproval_id' => $mp_preapproval_id,
						'error'             => $details->get_error_message(),
						'data'              => $details->get_error_data(),
					)
				);
				$details = null;
			}
		}

		$upsert = self::upsert_subscription(
			(int) $tier['creator_user_id'],
			$subscriber_user_id,
			(int) $tier_id,
			$mp_preapproval_id,
			isset( $res['status'] ) ? (string) $res['status'] : 'pending'
		);
		if ( is_wp_error( $upsert ) ) {
			return $upsert;
		}

		/**
		 * Allow other plugins (politeia-learning/reading/bookshelf) to react to status changes.
		 */
		do_action( 'politeia_pps_subscription_created', $mp_preapproval_id, $subscriber_user_id, (int) $tier['creator_user_id'], (int) $tier_id, $res );

		$init_point         = isset( $details['init_point'] ) ? $details['init_point'] : ( isset( $res['init_point'] ) ? $res['init_point'] : null );
		$sandbox_init_point = isset( $details['sandbox_init_point'] ) ? $details['sandbox_init_point'] : ( isset( $res['sandbox_init_point'] ) ? $res['sandbox_init_point'] : null );

		// Prefer the sandbox_init_point when Mercado Pago provides it; otherwise fall back to init_point.
		$redirect_url = $sandbox_init_point ? $sandbox_init_point : $init_point;
		if ( 'hosted' === $flow && $is_test && ! $sandbox_init_point ) {
			self::debug(
				'hosted_missing_sandbox_init_point',
				array(
					'mp_preapproval_id'  => $mp_preapproval_id,
					'init_point'         => $init_point,
					'sandbox_init_point' => $sandbox_init_point,
				)
			);
			return new WP_Error(
				'mp_missing_sandbox_init_point',
				'Mercado Pago no entregó sandbox_init_point para modo TEST. El checkout hosted termina en producción y falla al usar cuentas/tarjetas de prueba. Cambia a LIVE o usa Direct (tokenización).',
				array(
					'mp_preapproval_id'  => $mp_preapproval_id,
					'init_point'         => $init_point,
					'sandbox_init_point' => $sandbox_init_point,
					'status'             => isset( $res['status'] ) ? $res['status'] : null,
				)
			);
		}
		if ( 'hosted' === $flow && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$host = $redirect_url ? wp_parse_url( $redirect_url, PHP_URL_HOST ) : '';
			error_log(
				sprintf(
					'[PPS][DEBUG][redirect_resolution] is_test=%d host=%s sandbox_init_point=%s init_point=%s',
					$is_test ? 1 : 0,
					$host ? $host : 'none',
					$sandbox_init_point ? $sandbox_init_point : 'null',
					$init_point ? $init_point : 'null'
				)
			);
		}

		self::debug(
			'preapproval_created',
			array(
				'mp_preapproval_id' => $mp_preapproval_id,
				'status'            => isset( $res['status'] ) ? $res['status'] : null,
				'is_test'           => $is_test,
				'init_point'        => $init_point,
				'sandbox_init_point'=> $sandbox_init_point,
				'redirect_url'      => $redirect_url,
				'raw_status'        => $res,
				'raw_details'       => $details,
			)
		);

		return array(
			'mp_preapproval_id'  => $mp_preapproval_id,
			'init_point'         => $init_point,
			'sandbox_init_point' => $sandbox_init_point,
			'redirect_url'       => 'hosted' === $flow ? $redirect_url : '',
			'flow'               => $flow,
			'is_test'            => $is_test,
			'status'             => isset( $res['status'] ) ? $res['status'] : null,
			'raw'                => $res,
			'details'            => $details,
		);
	}

	public static function cancel_subscription( $subscriber_user_id, $args ) {
		$subscriber_user_id = (int) $subscriber_user_id;
		$args               = is_array( $args ) ? $args : array();

		$gateway       = sanitize_key( (string) ( $args['gateway'] ?? 'mercadopago' ) );
		$at_period_end = isset( $args['at_period_end'] ) ? (int) $args['at_period_end'] : 0;
		$reason        = sanitize_text_field( (string) ( $args['reason'] ?? '' ) );

		if ( 'flow' === $gateway ) {
			$flow_subscription_id = sanitize_text_field( (string) ( $args['flow_subscription_id'] ?? '' ) );
			if ( '' === $flow_subscription_id ) {
				return new WP_Error( 'flow_subscription_id_required', 'flow_subscription_id_required' );
			}

			return self::cancel_flow_subscription( $subscriber_user_id, $flow_subscription_id, $at_period_end, $reason );
		}

		$mp_preapproval_id = sanitize_text_field( (string) ( $args['mp_preapproval_id'] ?? '' ) );
		if ( ! $mp_preapproval_id ) {
			return new WP_Error( 'mp_preapproval_id_required', 'mp_preapproval_id_required' );
		}

		$client = new Politeia_PPS_MercadoPago_Client();
		$res    = $client->update_preapproval( $mp_preapproval_id, array( 'status' => 'cancelled' ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}

		self::set_subscription_status_by_mp_id( $mp_preapproval_id, 'cancelled' );
		do_action( 'politeia_pps_subscription_status_changed', $mp_preapproval_id, $subscriber_user_id, 'cancelled', $res );
		return array( 'ok' => true, 'raw' => $res );
	}

	private static function cancel_flow_subscription( $subscriber_user_id, $flow_subscription_id, $at_period_end, $reason ) {
		global $wpdb;

		$subscriber_user_id   = (int) $subscriber_user_id;
		$flow_subscription_id = sanitize_text_field( (string) $flow_subscription_id );
		$at_period_end        = (int) $at_period_end ? 1 : 0;
		$reason               = sanitize_text_field( (string) $reason );

		$table = self::subs_table();
		$sub   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE gateway = 'flow' AND flow_subscription_id = %s LIMIT 1",
				$flow_subscription_id
			),
			ARRAY_A
		);

		if ( ! is_array( $sub ) ) {
			return new WP_Error( 'subscription_not_found', 'Subscription not found.' );
		}
		if ( (int) ( $sub['subscriber_user_id'] ?? 0 ) !== $subscriber_user_id ) {
			return new WP_Error( 'forbidden', 'Forbidden', array( 'status' => 403 ) );
		}

		if ( ! class_exists( 'Politeia_PPS_Settings' ) || ! class_exists( 'Politeia_PPS_Flow_Client' ) ) {
			return new WP_Error( 'missing_dependencies', 'Missing Flow dependencies.' );
		}

		$mode   = Politeia_PPS_Settings::get_mode();
		$api    = Politeia_PPS_Settings::get_flow_api_key( $mode );
		$secret = Politeia_PPS_Settings::get_flow_secret( $mode );
		if ( '' === trim( (string) $api ) || '' === trim( (string) $secret ) ) {
			return new WP_Error( 'flow_not_configured', 'Flow is not configured.' );
		}

		$client = new Politeia_PPS_Flow_Client();
		$res    = $client->cancel_subscription( $flow_subscription_id, $at_period_end, $api, $secret, $mode );

		if ( empty( $res['ok'] ) ) {
			return new WP_Error( 'flow_cancel_failed', 'Flow cancel failed.', $res );
		}

		$now = current_time( 'mysql' );

		$update = array(
			'cancel_at_period_end' => $at_period_end,
			'updated_at'           => $now,
		);
		$formats = array( '%d', '%s' );

		if ( 0 === $at_period_end ) {
			$update['status']       = 'cancelled';
			$update['cancelled_at'] = $now;
			$formats[]              = '%s';
			$formats[]              = '%s';
		} else {
			// Keep as active but mark cancellation scheduled.
			$update['status'] = 'active';
			$formats[]        = '%s';
		}

		if ( $reason !== '' ) {
			$update['cancellation_reason'] = $reason;
			$formats[]                    = '%s';
		}

		$gateway_cancel_at = null;
		if ( is_array( $res['body'] ?? null ) ) {
			$gateway_cancel_at = self::normalize_datetime( (string) ( $res['body']['cancel_at'] ?? '' ) );
		}
		if ( $gateway_cancel_at ) {
			$update['gateway_cancelled_at'] = $gateway_cancel_at;
			$formats[]                      = '%s';
		}

		$ok = $wpdb->update( $table, $update, array( 'id' => (int) $sub['id'] ), $formats, array( '%d' ) );
		if ( $ok === false ) {
			return new WP_Error( 'db_update_failed', 'Failed to update subscription.', array( 'error' => $wpdb->last_error ) );
		}

		do_action( 'politeia_pps_subscription_status_changed', $flow_subscription_id, $subscriber_user_id, $at_period_end ? 'cancel_scheduled' : 'cancelled', $res );

		return array(
			'ok'                   => true,
			'gateway'               => 'flow',
			'flow_subscription_id'  => $flow_subscription_id,
			'at_period_end'         => $at_period_end,
			'raw'                  => $res,
		);
	}

	public static function record_ledger_entry( $entry ) {
		global $wpdb;
		$table = self::ledger_table();

		$defaults = array(
			'creator_user_id'            => 0,
			'subscriber_user_id'         => null,
			'tier_id'                    => null,
			'mp_payment_id'              => null,
			'mp_preapproval_id'          => null,
			'mp_status'                  => null,
			'currency'                   => 'CLP',
			'gross_amount_minor'         => 0,
			'mp_fee_minor'               => 0,
			'iva_minor'                  => 0,
			'platform_commission_minor'  => 0,
			'creator_net_minor'          => 0,
			'exchange_rate'              => null,
			'locale'                     => Politeia_PPS_Locale::get_locale(),
			'event_source'               => null,
			'occurred_at'                => current_time( 'mysql' ),
			'raw_payload'                => null,
			'created_at'                 => current_time( 'mysql' ),
		);

		$data = array_merge( $defaults, is_array( $entry ) ? $entry : array() );

		$ok = $wpdb->insert( $table, $data );
		if ( ! $ok ) {
			return new WP_Error( 'ledger_insert_failed', 'Failed to write ledger entry.', array( 'error' => $wpdb->last_error ) );
		}

		return (int) $wpdb->insert_id;
	}

	public static function get_subscriptions_for_user( $subscriber_user_id ) {
		global $wpdb;
		$table             = self::subs_table();
		$subscriber_user_id = (int) $subscriber_user_id;
		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE subscriber_user_id = %d ORDER BY created_at DESC", $subscriber_user_id ),
			ARRAY_A
		);
	}

	/**
	 * Lookup a Flow subscription row by Flow subscription id.
	 *
	 * @param string $flow_subscription_id
	 * @return array|null
	 */
	public static function get_subscription_by_flow_id( $flow_subscription_id ) {
		global $wpdb;
		$table = self::subs_table();
		$flow_subscription_id = sanitize_text_field( (string) $flow_subscription_id );
		if ( '' === $flow_subscription_id ) {
			return null;
		}
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE gateway = 'flow' AND flow_subscription_id = %s ORDER BY id DESC LIMIT 1",
				$flow_subscription_id
			),
			ARRAY_A
		);
		return is_array( $row ) ? $row : null;
	}

	public static function is_active_subscription( $subscriber_user_id, $creator_user_id = null ) {
		global $wpdb;
		$table              = self::subs_table();
		$subscriber_user_id = (int) $subscriber_user_id;

		$where = "subscriber_user_id = %d AND status IN ('authorized','active','approved')";
		$args  = array( $subscriber_user_id );
		if ( null !== $creator_user_id ) {
			$where .= ' AND creator_user_id = %d';
			$args[] = (int) $creator_user_id;
		}

		$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where}", $args ) );
		return $count > 0;
	}

	private static function upsert_subscription( $creator_user_id, $subscriber_user_id, $tier_id, $mp_preapproval_id, $status ) {
		global $wpdb;
		$table = self::subs_table();

		$now = current_time( 'mysql' );
		$existing_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE mp_preapproval_id = %s", $mp_preapproval_id ) );

		$data = array(
			'creator_user_id'    => (int) $creator_user_id,
			'subscriber_user_id' => (int) $subscriber_user_id,
			'tier_id'            => (int) $tier_id,
			'mp_preapproval_id'  => $mp_preapproval_id,
			'status'             => sanitize_text_field( $status ),
			'updated_at'         => $now,
		);

		if ( $existing_id ) {
			$ok = $wpdb->update( $table, $data, array( 'id' => (int) $existing_id ) );
			if ( $ok === false ) {
				return new WP_Error( 'db_update_failed', 'Failed to update subscription.', array( 'error' => $wpdb->last_error ) );
			}
			return (int) $existing_id;
		}

		$data['created_at'] = $now;
		$ok = $wpdb->insert( $table, $data );
		if ( ! $ok ) {
			return new WP_Error( 'db_insert_failed', 'Failed to create subscription.', array( 'error' => $wpdb->last_error ) );
		}
		return (int) $wpdb->insert_id;
	}

	/**
	 * Create or update a pending Flow subscription (keyed by flow_register_token).
	 *
	 * @return int|WP_Error
	 */
	public static function upsert_subscription_flow_pending( $creator_user_id, $subscriber_user_id, $tier_id, $register_token ) {
		global $wpdb;
		$table = self::subs_table();

		$register_token = sanitize_text_field( (string) $register_token );
		if ( '' === $register_token ) {
			return new WP_Error( 'invalid_token', 'Missing Flow register token.' );
		}

		$now = current_time( 'mysql' );
		$existing_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE flow_register_token = %s", $register_token ) );

		$data = array(
			'creator_user_id'      => (int) $creator_user_id,
			'subscriber_user_id'   => (int) $subscriber_user_id,
			'tier_id'              => (int) $tier_id,
			'gateway'              => 'flow',
			'mp_preapproval_id'    => null,
			'flow_subscription_id' => null,
			'flow_register_token'  => $register_token,
			'status'               => 'pending',
			'updated_at'           => $now,
		);

		if ( $existing_id ) {
			$ok = $wpdb->update( $table, $data, array( 'id' => (int) $existing_id ) );
			if ( $ok === false ) {
				return new WP_Error( 'db_update_failed', 'Failed to update Flow subscription.', array( 'error' => $wpdb->last_error ) );
			}
			return (int) $existing_id;
		}

		$data['created_at'] = $now;
		$ok = $wpdb->insert( $table, $data );
		if ( ! $ok ) {
			return new WP_Error( 'db_insert_failed', 'Failed to create Flow subscription.', array( 'error' => $wpdb->last_error ) );
		}
		return (int) $wpdb->insert_id;
	}

	/**
	 * @param int $subscriber_user_id
	 * @param string $register_token
	 * @return array|null
	 */
	public static function get_flow_pending_by_token( $subscriber_user_id, $register_token ) {
		global $wpdb;
		$table = self::subs_table();

		$subscriber_user_id = (int) $subscriber_user_id;
		$register_token     = sanitize_text_field( (string) $register_token );

		if ( $subscriber_user_id <= 0 || '' === $register_token ) {
			return null;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE gateway = 'flow' AND subscriber_user_id = %d AND flow_register_token = %s ORDER BY id DESC LIMIT 1",
				$subscriber_user_id,
				$register_token
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Finalize a Flow subscription by writing flow_subscription_id and activating.
	 *
	 * @param int $local_id
	 * @param string $flow_subscription_id
	 * @param string|null $current_period_end
	 * @return true|WP_Error
	 */
	public static function finalize_flow_subscription( $local_id, $flow_subscription_id, $current_period_end ) {
		global $wpdb;
		$table = self::subs_table();

		$local_id            = (int) $local_id;
		$flow_subscription_id = sanitize_text_field( (string) $flow_subscription_id );
		if ( $local_id <= 0 || '' === $flow_subscription_id ) {
			return new WP_Error( 'invalid_args', 'Invalid finalize args.' );
		}

		$data = array(
			'flow_subscription_id' => $flow_subscription_id,
			'status'               => 'active',
			'flow_register_token'  => null,
			'updated_at'           => current_time( 'mysql' ),
		);
		$format = array( '%s', '%s', '%s', '%s' );

		if ( $current_period_end ) {
			$data['current_period_end'] = $current_period_end;
			$format[]                   = '%s';
		}

		$ok = $wpdb->update( $table, $data, array( 'id' => $local_id ), $format, array( '%d' ) );
		if ( $ok === false ) {
			return new WP_Error( 'db_update_failed', 'Failed to finalize Flow subscription.', array( 'error' => $wpdb->last_error ) );
		}

		return true;
	}

	private static function set_subscription_status_by_mp_id( $mp_preapproval_id, $status ) {
		global $wpdb;
		$table = self::subs_table();
		$wpdb->update(
			$table,
			array(
				'status'     => sanitize_text_field( $status ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'mp_preapproval_id' => sanitize_text_field( $mp_preapproval_id ) )
		);
	}

	private static function build_external_reference( $creator_user_id, $tier_slug ) {
		$base = 'pps:' . (int) $creator_user_id . ':' . sanitize_title( $tier_slug );
		return apply_filters( 'politeia_pps_external_reference', $base, (int) $creator_user_id, $tier_slug );
	}

	private static function set_tier_plan_id( $tier_id, $mp_plan_id ) {
		global $wpdb;
		$table = self::tiers_table();
		$wpdb->update(
			$table,
			array(
				'mp_plan_id' => sanitize_text_field( (string) $mp_plan_id ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => (int) $tier_id )
		);
	}

	private static function map_interval_unit( $interval_unit ) {
		$unit = strtolower( (string) $interval_unit );
		$map  = array(
			'week'      => 'weeks',
			'weeks'     => 'weeks',
			'biweekly'  => 'weeks',
			'month'     => 'months',
			'months'    => 'months',
			'year'      => 'years',
			'years'     => 'years',
			'day'       => 'days',
			'days'      => 'days',
		);
		return isset( $map[ $unit ] ) ? $map[ $unit ] : 'months';
	}

	private static function minor_to_major_amount( $amount_minor, $currency ) {
		$currency = strtoupper( (string) $currency );

		// CLP has no decimals; USD/BRL commonly have 2 decimals.
		$divisor = in_array( $currency, array( 'CLP' ), true ) ? 1 : 100;
		return (float) ( $amount_minor / $divisor );
	}

	/**
	 * Write concise debug info into the WP debug log.
	 *
	 * @param string $event
	 * @param array  $data
	 */
	private static function debug( $event, $data = array() ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$line = '[PPS][DEBUG][' . $event . '] ' . wp_json_encode( $data );
			error_log( $line );
		}
	}
}
