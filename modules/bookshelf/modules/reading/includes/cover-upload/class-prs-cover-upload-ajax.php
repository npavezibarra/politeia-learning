<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AJAX Handlers for Cover Upload.
 */
class Politeia_Reading_Cover_Upload_AJAX {

	private static function get_user_book_row( $user_id, $book_id = 0, $user_book_id = 0 ) {
		if ( $user_book_id ) {
			return Politeia_Reading_User_Books_Utils::get_user_book_row( $user_book_id, $user_id );
		}

		if ( $book_id ) {
			return Politeia_Reading_User_Books_Utils::get_user_book_by_book( $user_id, $book_id );
		}

		return null;
	}

	private static function build_cover_reference_payload( $attachment_id, $url, $source ) {
		return wp_json_encode(
			array(
				'attachment_id' => (int) $attachment_id,
				'url'           => esc_url_raw( $url ),
				'source'        => sanitize_text_field( (string) $source ),
				'type'          => 'upload',
			)
		);
	}

	private static function persist_uploaded_cover( $row, $user_id, $attachment_id, $url, $source = '' ) {
		if ( ! $row || ! $attachment_id || ! $url ) {
			return new WP_Error( 'invalid_payload', 'invalid_payload' );
		}

		$reference = self::build_cover_reference_payload( $attachment_id, $url, $source );

		Politeia_Reading_User_Books_Utils::update_user_book(
			(int) $row->id,
			array(
				'cover_reference' => $reference,
				'cover_url'       => esc_url_raw( $url ),
				'cover_source'    => $source ? sanitize_text_field( (string) $source ) : 'upload',
			)
		);

		if ( method_exists( 'Politeia_Reading_Cover_Upload_Repository', 'cleanup_cover_attachments' ) ) {
			Politeia_Reading_Cover_Upload_Repository::cleanup_cover_attachments( $user_id, (int) $row->id, (int) $attachment_id );
		}

		return array(
			'attachment_id'   => (int) $attachment_id,
			'cover_url'       => esc_url_raw( $url ),
			'cover_reference' => $reference,
		);
	}

	private static function save_binary_cover( $binary, $mime_type, $extension, $user_id, $row, $source = '' ) {
		$created = Politeia_Reading_Cover_Upload_Repository::create_attachment_from_binary(
			$binary,
			$extension,
			$mime_type,
			$user_id,
			0,
			$row ? (int) $row->id : 0
		);

		if ( is_wp_error( $created ) ) {
			return $created;
		}

		$attachment_id = isset( $created['attachment_id'] ) ? (int) $created['attachment_id'] : 0;
		$url           = isset( $created['url'] ) ? esc_url_raw( $created['url'] ) : '';
		if ( ! $attachment_id || ! $url ) {
			return new WP_Error( 'attachment_failed', 'attachment_failed' );
		}

		$persisted = self::persist_uploaded_cover( $row, $user_id, $attachment_id, $url, $source );
		if ( is_wp_error( $persisted ) ) {
			return $persisted;
		}

		return array_merge( $created, $persisted );
	}

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

	public static function upload_cover_file() {
		check_ajax_referer( 'prs_cover_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'auth' ), 401 );
		}

		$user_id      = get_current_user_id();
		$book_id      = isset( $_POST['book_id'] ) ? absint( $_POST['book_id'] ) : 0;
		$user_book_id = isset( $_POST['user_book_id'] ) ? absint( $_POST['user_book_id'] ) : 0;
		$row          = self::get_user_book_row( $user_id, $book_id, $user_book_id );

		if ( ! $row ) {
			wp_send_json_error( array( 'message' => 'not_found' ), 404 );
		}

		if ( $book_id && (int) $row->book_id !== $book_id ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}

		if ( empty( $_FILES['prs_cover'] ) || empty( $_FILES['prs_cover']['tmp_name'] ) ) {
			wp_send_json_error( array( 'message' => 'missing_params' ), 400 );
		}

		$file = $_FILES['prs_cover'];
		if ( ! empty( $file['error'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			wp_send_json_error( array( 'message' => 'error_upload_dir' ), 400 );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		$check = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
		$mime  = isset( $check['type'] ) ? (string) $check['type'] : '';
		$ext   = isset( $check['ext'] ) ? strtolower( (string) $check['ext'] ) : '';

		$allowed = array(
			'image/jpeg' => 'jpg',
			'image/png'  => 'png',
		);

		if ( ! isset( $allowed[ $mime ] ) ) {
			wp_send_json_error( array( 'message' => 'error_invalid_image_payload' ), 400 );
		}

		$ext = $allowed[ $mime ];
		$binary = file_get_contents( $file['tmp_name'] );
		if ( false === $binary || '' === $binary ) {
			wp_send_json_error( array( 'message' => 'error_no_image_data' ), 400 );
		}

		$result = self::save_binary_cover( $binary, $mime, $ext, $user_id, $row, 'upload' );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_code() ), 400 );
		}

		wp_send_json_success(
			array(
				'url'             => $result['cover_url'],
				'src'             => $result['cover_url'],
				'attachment_id'   => $result['attachment_id'],
				'cover_reference' => $result['cover_reference'],
				'source'          => 'upload',
			)
		);
	}

	public static function save_cropped_cover() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( '' === $nonce ) {
			$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		}
		if ( ! wp_verify_nonce( $nonce, 'prs_cover_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'bad_nonce' ), 403 );
		}

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'auth' ), 401 );
		}

		$user_id      = get_current_user_id();
		$book_id      = isset( $_POST['book_id'] ) ? absint( $_POST['book_id'] ) : 0;
		$user_book_id = isset( $_POST['user_book_id'] ) ? absint( $_POST['user_book_id'] ) : 0;
		$row          = self::get_user_book_row( $user_id, $book_id, $user_book_id );

		if ( ! $row ) {
			wp_send_json_error( array( 'message' => 'not_found' ), 404 );
		}

		if ( $book_id && (int) $row->book_id !== $book_id ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}

		$data_url = isset( $_POST['data'] ) ? (string) wp_unslash( $_POST['data'] ) : '';
		$data_url = trim( $data_url );
		if ( '' === $data_url ) {
			wp_send_json_error( array( 'message' => 'error_no_image_data' ), 400 );
		}

		$mime = isset( $_POST['mime'] ) ? sanitize_text_field( wp_unslash( $_POST['mime'] ) ) : '';
		$ext  = '';

		if ( preg_match( '#^data:(image/(?:jpeg|jpg|png));base64,#i', $data_url, $matches ) ) {
			$mime = strtolower( $matches[1] );
			$ext  = ( 'image/png' === $mime ) ? 'png' : 'jpg';
			$data_url = preg_replace( '#^data:image/(?:jpeg|jpg|png);base64,#i', '', $data_url, 1 );
		}

		$allowed = array(
			'image/jpeg' => 'jpg',
			'image/jpg'  => 'jpg',
			'image/png'  => 'png',
		);

		if ( ! isset( $allowed[ $mime ] ) ) {
			$mime = 'image/jpeg';
		}

		$ext = $ext ? $ext : $allowed[ $mime ];
		$binary = base64_decode( $data_url, true );
		if ( false === $binary || '' === $binary ) {
			wp_send_json_error( array( 'message' => 'error_decode' ), 400 );
		}

		$result = self::save_binary_cover( $binary, $mime, $ext, $user_id, $row, 'upload' );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_code() ), 400 );
		}

		wp_send_json_success(
			array(
				'url'             => $result['cover_url'],
				'attachment_id'   => $result['attachment_id'],
				'cover_reference' => $result['cover_reference'],
				'source'          => 'upload',
			)
		);
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
