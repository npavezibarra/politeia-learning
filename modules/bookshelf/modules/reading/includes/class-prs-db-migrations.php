<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles database schema alterations and migrations.
 */
class Politeia_Reading_DB_Migrations {

	/**
	 * Initialize migrations on plugins_loaded.
	 */
	public static function init() {
		add_action( 'plugins_loaded', array( __CLASS__, 'maybe_alter_user_books' ) );
		add_action( 'plugins_loaded', array( __CLASS__, 'maybe_create_loans_table' ) );
	}

	/**
	 * Alter user_books table if columns are missing.
	 */
	public static function maybe_alter_user_books() {
		global $wpdb;
		$t = $wpdb->prefix . 'politeia_user_books';

		$cols = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s',
				DB_NAME,
				$t
			)
		);
		$has  = array_map( 'strtolower', (array) $cols );

		$alters = array();

		$has_type_book = in_array( 'type_book', $has, true );
		if ( ! $has_type_book ) {
			$alters[]      = "ADD COLUMN type_book ENUM('p','d') NULL DEFAULT NULL AFTER owning_status";
			$has_type_book = true;
		}

		if ( ! in_array( 'pages', $has, true ) ) {
			$after_column = $has_type_book ? 'type_book' : 'owning_status';
			$alters[]     = sprintf( 'ADD COLUMN pages INT UNSIGNED NULL AFTER %s', $after_column );
		}
		if ( ! in_array( 'purchase_date', $has, true ) ) {
			$alters[] = 'ADD COLUMN purchase_date DATE NULL';
		}
		if ( ! in_array( 'purchase_channel', $has, true ) ) {
			$alters[] = "ADD COLUMN purchase_channel ENUM('online','store') NULL";
		}
		if ( ! in_array( 'purchase_place', $has, true ) ) {
			$alters[] = 'ADD COLUMN purchase_place VARCHAR(255) NULL';
		}
		if ( ! in_array( 'counterparty_name', $has, true ) ) {
			$alters[] = 'ADD COLUMN counterparty_name VARCHAR(255) NULL';
		}
		if ( ! in_array( 'counterparty_email', $has, true ) ) {
			$alters[] = 'ADD COLUMN counterparty_email VARCHAR(190) NULL';
		}

		if ( $alters ) {
			$wpdb->query( "ALTER TABLE {$t} " . implode( ', ', $alters ) );
		}
	}

	/**
	 * Create loans table if it doesn't exist.
	 */
	public static function maybe_create_loans_table() {
		global $wpdb;
		$table = $wpdb->prefix . 'politeia_loans';

		$exists = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s',
				DB_NAME,
				$table
			)
		);

		if ( ! $exists ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			$charset_collate = $wpdb->get_charset_collate();
			$sql             = "CREATE TABLE {$table} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				user_id BIGINT UNSIGNED NOT NULL,
				book_id BIGINT UNSIGNED NOT NULL,
				counterparty_name  VARCHAR(255) NULL,
				counterparty_email VARCHAR(190) NULL,
				amount DECIMAL(10,2) NULL,
				start_date DATETIME NOT NULL,
				end_date   DATETIME NULL,
				notes TEXT NULL,
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY idx_user_book (user_id, book_id),
				KEY idx_active (user_id, book_id, end_date)
			) {$charset_collate};";
			dbDelta( $sql );
		}

		$columns = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s',
				DB_NAME,
				$table
			)
		);

		if ( $columns && ! in_array( 'amount', array_map( 'strtolower', $columns ), true ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN amount DECIMAL(10,2) NULL AFTER counterparty_email" );
		}
	}
}
