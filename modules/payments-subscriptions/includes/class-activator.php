<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Politeia_PPS_Activator {
	const DB_VERSION_OPTION = 'politeia_pps_db_version';

	public static function activate() {
		self::create_or_update_tables();

		if ( get_option( self::DB_VERSION_OPTION ) === false ) {
			add_option( self::DB_VERSION_OPTION, POLITEIA_PPS_VERSION );
		} else {
			update_option( self::DB_VERSION_OPTION, POLITEIA_PPS_VERSION );
		}
	}

	public static function maybe_upgrade() {
		$stored = get_option( self::DB_VERSION_OPTION );
		if ( $stored !== POLITEIA_PPS_VERSION ) {
			self::create_or_update_tables();
			update_option( self::DB_VERSION_OPTION, POLITEIA_PPS_VERSION );
		}
	}

	private static function create_or_update_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$tiers_table         = $wpdb->prefix . 'politeia_subscription_meta';
		$subs_table          = $wpdb->prefix . 'politeia_subscriptions';
		$ledger_table        = $wpdb->prefix . 'politeia_transaction_ledger';
		$webhook_events_table = $wpdb->prefix . 'politeia_mp_webhook_events';

		$sql_tiers = "CREATE TABLE {$tiers_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			creator_user_id BIGINT UNSIGNED NOT NULL,
			tier_slug VARCHAR(190) NOT NULL DEFAULT '',
			tier_name VARCHAR(190) NOT NULL DEFAULT '',
			amount_minor BIGINT NOT NULL,
			currency CHAR(3) NOT NULL,
			interval_unit VARCHAR(20) NOT NULL,
			interval_count SMALLINT UNSIGNED NOT NULL DEFAULT 1,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			mp_plan_id VARCHAR(120) NULL,
			flow_plan_id VARCHAR(120) NULL,
			external_reference VARCHAR(190) NOT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uniq_external_reference (external_reference),
			KEY idx_creator (creator_user_id),
			KEY idx_status (status)
		) {$charset_collate};";

		$sql_subs = "CREATE TABLE {$subs_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			creator_user_id BIGINT UNSIGNED NOT NULL,
			subscriber_user_id BIGINT UNSIGNED NOT NULL,
			tier_id BIGINT UNSIGNED NOT NULL,
			gateway VARCHAR(30) NOT NULL DEFAULT 'mercadopago',
			mp_preapproval_id VARCHAR(120) NULL,
			flow_subscription_id VARCHAR(120) NULL,
			status VARCHAR(30) NOT NULL DEFAULT 'pending',
			current_period_end DATETIME NULL,
			cancel_at_period_end TINYINT(1) NOT NULL DEFAULT 0,
			cancelled_at DATETIME NULL,
			gateway_cancelled_at DATETIME NULL,
			cancellation_reason VARCHAR(255) NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uniq_mp_preapproval (mp_preapproval_id),
			UNIQUE KEY uniq_flow_subscription (flow_subscription_id),
			KEY idx_subscriber (subscriber_user_id),
			KEY idx_creator (creator_user_id),
			KEY idx_tier (tier_id),
			KEY idx_status (status),
			KEY idx_gateway (gateway)
		) {$charset_collate};";

		$sql_ledger = "CREATE TABLE {$ledger_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			creator_user_id BIGINT UNSIGNED NOT NULL,
			subscriber_user_id BIGINT UNSIGNED NULL,
			tier_id BIGINT UNSIGNED NULL,
			mp_payment_id VARCHAR(120) NULL,
			mp_preapproval_id VARCHAR(120) NULL,
			mp_status VARCHAR(60) NULL,
			currency CHAR(3) NOT NULL,
			gross_amount_minor BIGINT NOT NULL,
			mp_fee_minor BIGINT NOT NULL DEFAULT 0,
			iva_minor BIGINT NOT NULL DEFAULT 0,
			platform_commission_minor BIGINT NOT NULL DEFAULT 0,
			creator_net_minor BIGINT NOT NULL DEFAULT 0,
			exchange_rate DECIMAL(18,8) NULL,
			locale VARCHAR(10) NULL,
			event_source VARCHAR(50) NULL,
			occurred_at DATETIME NOT NULL,
			raw_payload LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY idx_creator (creator_user_id),
			KEY idx_subscriber (subscriber_user_id),
			KEY idx_tier (tier_id),
			KEY idx_occurred (occurred_at),
			KEY idx_mp_payment (mp_payment_id),
			KEY idx_mp_preapproval (mp_preapproval_id)
		) {$charset_collate};";

		$sql_webhook_events = "CREATE TABLE {$webhook_events_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			event_id VARCHAR(190) NULL,
			event_type VARCHAR(120) NOT NULL,
			resource_id VARCHAR(120) NULL,
			processed TINYINT(1) NOT NULL DEFAULT 0,
			received_at DATETIME NOT NULL,
			processed_at DATETIME NULL,
			payload LONGTEXT NULL,
			PRIMARY KEY (id),
			KEY idx_type (event_type),
			KEY idx_processed (processed),
			KEY idx_received (received_at),
			KEY idx_resource (resource_id)
		) {$charset_collate};";

		dbDelta( $sql_tiers );
		dbDelta( $sql_subs );
		dbDelta( $sql_ledger );
		dbDelta( $sql_webhook_events );
	}
}
