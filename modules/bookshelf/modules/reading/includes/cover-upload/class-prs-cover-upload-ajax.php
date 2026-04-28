<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AJAX Handlers for Cover Upload.
 */
class Politeia_Reading_Cover_Upload_AJAX {

	public static function search_google() {
		check_ajax_referer( 'prs_cover_nonce', 'nonce' );

		$title    = isset( $_POST['title'] ) ? sanitize_text_field( $_POST['title'] ) : '';
		$author   = isset( $_POST['author'] ) ? sanitize_text_field( $_POST['author'] ) : '';
		$language = isset( $_POST['language'] ) ? sanitize_text_field( $_POST['language'] ) : '';

		if ( empty( $title ) ) {
			wp_send_json_error( array( 'message' => 'missing_params' ) );
		}

		$results = Politeia_Reading_Book_Utils::search_google_books_covers( $title, $author, $language );
		
		if ( is_wp_error( $results ) ) {
			wp_send_json_error( array( 'message' => $results->get_error_code() ) );
		}

		wp_send_json_success( array( 'items' => $results ) );
	}

	public static function save_cover_url() {
		check_ajax_referer( 'prs_cover_nonce', 'nonce' );

		$user_id   = get_current_user_id();
		$book_id   = isset( $_POST['book_id'] ) ? absint( $_POST['book_id'] ) : 0;
		$cover_url = isset( $_POST['cover_url'] ) ? esc_url_raw( $_POST['cover_url'] ) : '';
		$source    = isset( $_POST['cover_source'] ) ? esc_url_raw( $_POST['cover_source'] ) : '';

		if ( ! $book_id || ! $cover_url ) {
			wp_send_json_error( array( 'message' => 'missing_params' ) );
		}

		$user_book_id = Politeia_Reading_Book_Repository::get_user_book_id( $user_id, $book_id );
		
		if ( ! $user_book_id ) {
			wp_send_json_error( array( 'message' => 'not_found' ) );
		}

		$updated = Politeia_Reading_Cover_Upload_Repository::update_user_book_cover( $user_book_id, array(
			'cover_url'       => $cover_url,
			'cover_reference' => $source,
			'updated_at'      => current_time( 'mysql' ),
		));

		if ( false === $updated ) {
			wp_send_json_error( array( 'message' => 'db_error' ) );
		}

		wp_send_json_success( array( 'src' => $cover_url, 'source' => $source ) );
	}

	public static function remove_cover() {
		check_ajax_referer( 'prs_cover_nonce', 'nonce' );

		$user_id      = get_current_user_id();
		$user_book_id = isset( $_POST['user_book_id'] ) ? absint( $_POST['user_book_id'] ) : 0;
		$book_id      = isset( $_POST['book_id'] ) ? absint( $_POST['book_id'] ) : 0;

		if ( ! $user_book_id && $book_id ) {
			$user_book_id = Politeia_Reading_Book_Repository::get_user_book_id( $user_id, $book_id );
		}

		if ( ! $user_book_id ) {
			wp_send_json_error( array( 'message' => 'not_found' ) );
		}

		$updated = Politeia_Reading_Cover_Upload_Repository::update_user_book_cover( $user_book_id, array(
			'cover_attachment_id_user' => 0,
			'cover_reference'          => '',
			'cover_url'                => '',
			'updated_at'               => current_time( 'mysql' ),
		));

		if ( false === $updated ) {
			wp_send_json_error( array( 'message' => 'db_error' ) );
		}

		Politeia_Reading_Cover_Upload_Repository::cleanup_cover_attachments( $user_id, $user_book_id, 0 );

		wp_send_json_success( array( 'removed' => true ) );
	}
}
