<?php
/**
 * Shared Utils for User Books
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Politeia_Reading_User_Books_Utils {

	public static function allowed_reading_status() {
		return array( 'not_started', 'started', 'finished' );
	}

	public static function allowed_owning_status() {
		return array( 'borrowed', 'borrowing', 'sold', 'lost' );
	}

	public static function get_user_book_row( $user_book_id, $user_id ) {
		global $wpdb;
		$t = $wpdb->prefix . 'politeia_user_books';
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$t} WHERE id=%d AND user_id=%d AND deleted_at IS NULL LIMIT 1",
				$user_book_id,
				$user_id
			)
		);
	}

	public static function get_user_book_by_book( $user_id, $book_id ) {
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

	public static function update_user_book( $user_book_id, $update ) {
		global $wpdb;
		$t                    = $wpdb->prefix . 'politeia_user_books';
		$user_id              = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT user_id FROM {$t} WHERE id = %d LIMIT 1",
				(int) $user_book_id
			)
		);
		$update['updated_at'] = current_time( 'mysql', true ); // UTC
		$wpdb->update( $t, $update, array( 'id' => $user_book_id ) );
		if ( $user_id > 0 && function_exists( 'prs_invalidate_library_cache_for_user' ) ) {
			prs_invalidate_library_cache_for_user( $user_id );
		}
		return $update;
	}

	public static function loans_table() {
		global $wpdb;
		return $wpdb->prefix . 'politeia_loans';
	}

	public static function get_active_loan_id( $user_id, $book_id ) {
		global $wpdb;
		$t = self::loans_table();
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$t}
				 WHERE user_id=%d AND book_id=%d AND end_date IS NULL AND deleted_at IS NULL
				 ORDER BY id DESC LIMIT 1",
				$user_id,
				$book_id
			)
		);
	}

	public static function verify_nonce( $action, $keys = array( '_ajax_nonce', 'security', 'nonce' ) ) {
		foreach ( (array) $keys as $k ) {
			if ( isset( $_REQUEST[ $k ] ) ) {
				$nonce = $_REQUEST[ $k ];
				return (bool) wp_verify_nonce( $nonce, $action );
			}
		}
		return false;
	}

	public static function verify_nonce_multi( $pairs ) {
		foreach ( (array) $pairs as $p ) {
			$action = isset( $p['action'] ) ? $p['action'] : '';
			$keys   = isset( $p['keys'] ) ? (array) $p['keys'] : array();
			if ( $action && $keys && self::verify_nonce( $action, $keys ) ) {
				return true;
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
}
