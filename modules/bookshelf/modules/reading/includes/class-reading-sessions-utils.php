<?php
/**
 * Shared Utils for Reading Sessions
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Politeia_Reading_Sessions_Utils {

	public static function verify_nonce( $action, $keys = array( '_ajax_nonce', 'security', 'nonce' ) ) {
		foreach ( (array) $keys as $k ) {
			if ( isset( $_REQUEST[ $k ] ) ) {
				return (bool) wp_verify_nonce( $_REQUEST[ $k ], $action );
			}
		}
		return false;
	}

	public static function json_error( $message, $code = 400 ) {
		wp_send_json_error( array( 'message' => $message ), $code );
	}

	public static function json_success( $data ) {
		wp_send_json_success( $data );
	}

	public static function get_user_book_row( $user_id, $book_id ) {
		global $wpdb;
		$t = $wpdb->prefix . 'politeia_user_books';
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$t} WHERE user_id=%d AND book_id=%d AND deleted_at IS NULL LIMIT 1",
				$user_id,
				$book_id
			)
		);
	}

	public static function blocked_by_status( $status ) {
		return in_array( (string) $status, array( 'borrowed', 'lost', 'sold' ), true );
	}

	public static function update_user_book_fields( $user_book_id, $data ) {
		global $wpdb;
		$t = $wpdb->prefix . 'politeia_user_books';

		// Si las columnas extra no existen, no las mandamos
		if ( isset( $data['finish_mode'] ) || isset( $data['finished_at'] ) ) {
			if ( ! self::table_has_columns( 'politeia_user_books', array( 'finish_mode', 'finished_at' ) ) ) {
				unset( $data['finish_mode'], $data['finished_at'] );
			}
		}
		$data['updated_at'] = current_time( 'mysql' );

		$wpdb->update( $t, $data, array( 'id' => (int) $user_book_id ) );
	}

	public static function table_has_columns( $basename, $cols ) {
		global $wpdb;
		$t = $wpdb->prefix . $basename;
		foreach ( (array) $cols as $c ) {
			$found = $wpdb->get_var(
				$wpdb->prepare(
					"SHOW COLUMNS FROM {$t} LIKE %s",
					$c
				)
			);
			if ( ! $found ) {
				return false;
			}
		}
		return true;
	}
}
