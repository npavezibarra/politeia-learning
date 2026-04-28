<?php
/**
 * Cover Handlers for User Books
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Politeia_Reading_User_Books_Covers {

	/**
	 * AJAX: busca cubiertas en Google Books para el libro actual.
	 */
	public static function ajax_search_cover() {
		if ( ! is_user_logged_in() ) {
			Politeia_Reading_User_Books_Utils::json_error( 'auth', 401 );
		}

		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'politeia_bookshelf_cover_actions' ) ) {
			Politeia_Reading_User_Books_Utils::json_error( 'bad_nonce', 403 );
		}

		$book_id      = isset( $_POST['book_id'] ) ? absint( $_POST['book_id'] ) : 0;
		$user_book_id = isset( $_POST['user_book_id'] ) ? absint( $_POST['user_book_id'] ) : 0;

		if ( ! $book_id ) {
			Politeia_Reading_User_Books_Utils::json_error( 'invalid_book', 400 );
		}

		$user_id = get_current_user_id();
		if ( $user_book_id ) {
			$row = Politeia_Reading_User_Books_Utils::get_user_book_row( $user_book_id, $user_id );
		} else {
			$row = Politeia_Reading_User_Books_Utils::get_user_book_by_book( $user_id, $book_id );
		}

		if ( ! $row ) {
			Politeia_Reading_User_Books_Utils::json_error( 'forbidden', 403 );
		}

		global $wpdb;
		$books_table   = $wpdb->prefix . 'politeia_books';
		$authors_table = $wpdb->prefix . 'politeia_authors';
		$pivot_table   = $wpdb->prefix . 'politeia_book_authors';
		$book          = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT b.id, b.title,
						(
								SELECT GROUP_CONCAT(a.display_name ORDER BY ba.sort_order ASC SEPARATOR ', ')
								FROM {$pivot_table} ba
								LEFT JOIN {$authors_table} a ON a.id = ba.author_id
								WHERE ba.book_id = b.id
						) AS authors
				 FROM {$books_table} b
				 WHERE b.id=%d
				 LIMIT 1",
				$book_id
			)
		);

		if ( ! $book ) {
			Politeia_Reading_User_Books_Utils::json_error( 'not_found', 404 );
		}

		$title_raw  = isset( $book->title ) ? (string) $book->title : '';
		$author_raw = isset( $book->authors ) ? (string) $book->authors : '';

		$title  = $title_raw ? wp_strip_all_tags( $title_raw ) : '';
		$author = $author_raw ? wp_strip_all_tags( $author_raw ) : '';

		$title  = trim( str_replace( "\"", '', $title ) );
		$author = trim( str_replace( "\"", '', $author ) );

		// --- Normalize metadata for Google Books ---
		$title  = preg_replace( '/:.*/', '', $title );
		$title  = preg_replace( '/\s+/', ' ', $title );
		$author = preg_replace( '/\([^)]*\)/', '', $author );
		$author = preg_replace( '/II|III|IV|V/', '', $author );
		$author = preg_replace( '/\s+/', ' ', $author );
		$author = trim( $author );

		if ( '' === $title && '' === $author ) {
			Politeia_Reading_User_Books_Utils::json_error( 'missing_metadata', 400 );
		}

		$api_key = function_exists( 'politeia_bookshelf_get_google_books_api_key' )
			? politeia_bookshelf_get_google_books_api_key()
			: '';
		$api_key = is_string( $api_key ) ? trim( $api_key ) : '';

		if ( '' === $api_key ) {
			Politeia_Reading_User_Books_Utils::json_error( 'missing_api_key', 400 );
		}

		$title_clean  = trim( $title );
		$author_clean = trim( $author );

		$post_title  = sanitize_text_field( isset( $_POST['title'] ) ? wp_unslash( $_POST['title'] ) : '' );
		$post_author = sanitize_text_field( isset( $_POST['author'] ) ? wp_unslash( $_POST['author'] ) : '' );

		if ( '' === $post_title ) {
			$post_title = $title_clean;
		}
		if ( '' === $post_author ) {
			$post_author = $author_clean;
		}

		// --- STEP 1: Sanitize and normalize input ---
		$title_input  = sanitize_text_field( $post_title );
		$author_input = sanitize_text_field( $post_author );

		$authors = array_filter( array_map( 'trim', explode( ',', $author_input ) ) );

		// --- STEP 2: Build up to 3 distinct query URLs ---
		$queries = array();

		if ( '' !== $title_input ) {
			$queries[] = 'https://www.googleapis.com/books/v1/volumes?q=' . rawurlencode( $title_input ) .
				'&maxResults=1&printType=books&projection=lite&key=' . $api_key;
		}

		if ( ! empty( $authors ) ) {
			$first_author = $authors[0];
			$queries[]    = 'https://www.googleapis.com/books/v1/volumes?q=' .
				rawurlencode( trim( $title_input . ' ' . $first_author ) ) .
				'&maxResults=1&printType=books&projection=lite&key=' . $api_key;
		}

		if ( count( $authors ) > 1 ) {
			$queries[] = 'https://www.googleapis.com/books/v1/volumes?q=' .
				rawurlencode( trim( $title_input . ' ' . implode( ' ', $authors ) ) ) .
				'&maxResults=1&printType=books&projection=lite&key=' . $api_key;
		}

		$options_html = '';
		$used_images  = array();

		foreach ( $queries as $url ) {
			error_log( '🔍 [GoogleBooks] Request URL: ' . $url );

			$response = wp_remote_get( $url, array( 'timeout' => 10 ) );

			if ( is_wp_error( $response ) ) {
				error_log( '❌ [GoogleBooks] Request failed: ' . $response->get_error_message() );
				continue;
			}

			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );

			if ( empty( $data['items'] ) ) {
				error_log( '⚠️ [GoogleBooks] No results for query: ' . $url );
				continue;
			}

			$item = $data['items'][0];
			$info = ( isset( $item['volumeInfo'] ) && is_array( $item['volumeInfo'] ) ) ? $item['volumeInfo'] : array();

			if ( isset( $info['title'] ) ) {
				error_log( '✅ [GoogleBooks] First result: ' . $info['title'] );
			}

			$image_links = ( isset( $info['imageLinks'] ) && is_array( $info['imageLinks'] ) ) ? $info['imageLinks'] : array();
			$image_url   = '';

			if ( ! empty( $image_links['thumbnail'] ) ) {
				$image_url = $image_links['thumbnail'];
			} elseif ( ! empty( $image_links['smallThumbnail'] ) ) {
				$image_url = $image_links['smallThumbnail'];
			}

			if ( ! $image_url ) {
				continue;
			}

			$image_url = str_replace( 'http://', 'https://', $image_url );
			$image_url = preg_replace( '/zoom=\d+/', 'zoom=3', $image_url );

			$normalized = esc_url( $image_url );

			if ( '' === $normalized ) {
				continue;
			}

			if ( in_array( $normalized, $used_images, true ) ) {
				error_log( '⚠️ [GoogleBooks] Duplicate skipped: ' . $normalized );
				continue;
			}

			$used_images[] = $normalized;

			$title_opt  = esc_html( isset( $info['title'] ) ? $info['title'] : '' );
			$author_opt = esc_html( implode( ', ', isset( $info['authors'] ) && is_array( $info['authors'] ) ? $info['authors'] : array() ) );

			$options_html .= '<div class="prs-cover-option" role="button" tabindex="0"'
				. ' data-cover-title="' . $title_opt . '"'
				. ' data-cover-author="' . $author_opt . '"'
				. ' data-cover-url="' . $normalized . '"'
				. ' data-image-url="' . $normalized . '">'
				. '<img src="' . $normalized . '" alt="' . $title_opt . '" class="prs-cover-image" loading="lazy" />'
				. '</div>';

			if ( count( $used_images ) >= 3 ) {
				break;
			}
		}

		if ( empty( $options_html ) ) {
			$options_html = '<p>No covers found in Google Books for this title/author. Check spelling or try another title.</p>';
		}

		Politeia_Reading_User_Books_Utils::json_success(
			array(
				'html' => $options_html,
			)
		);
	}

	/**
	 * AJAX: guarda la URL de la cubierta seleccionada.
	 */
	public static function ajax_save_cover() {
		if ( ! is_user_logged_in() ) {
			Politeia_Reading_User_Books_Utils::json_error( 'auth', 401 );
		}

		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'politeia_bookshelf_cover_actions' ) ) {
			Politeia_Reading_User_Books_Utils::json_error( 'bad_nonce', 403 );
		}

		$book_id      = isset( $_POST['book_id'] ) ? absint( $_POST['book_id'] ) : 0;
		$user_book_id = isset( $_POST['user_book_id'] ) ? absint( $_POST['user_book_id'] ) : 0;
		$cover_raw    = isset( $_POST['cover_url'] ) ? wp_unslash( $_POST['cover_url'] ) : '';

		$cover_raw = is_string( $cover_raw ) ? trim( $cover_raw ) : '';

		if ( '' === $cover_raw ) {
			Politeia_Reading_User_Books_Utils::json_error( 'invalid_cover', 400 );
		}

		$cover_url = self::normalize_cover_url( $cover_raw );
		$cover_url = esc_url_raw( $cover_url );

		if ( ! $cover_url ) {
			Politeia_Reading_User_Books_Utils::json_error( 'invalid_cover', 400 );
		}

		if ( ! $user_book_id && ! $book_id ) {
			Politeia_Reading_User_Books_Utils::json_error( 'invalid_book', 400 );
		}

		$user_id = get_current_user_id();
		if ( $user_book_id ) {
			$row = Politeia_Reading_User_Books_Utils::get_user_book_row( $user_book_id, $user_id );
		} else {
			$row = Politeia_Reading_User_Books_Utils::get_user_book_by_book( $user_id, $book_id );
		}

		if ( ! $row ) {
			Politeia_Reading_User_Books_Utils::json_error( 'forbidden', 403 );
		}

		if ( $book_id && (int) $row->book_id !== $book_id ) {
			Politeia_Reading_User_Books_Utils::json_error( 'forbidden', 403 );
		}

		$reference = maybe_serialize(
			array(
				'external_cover' => $cover_url,
			)
		);

		Politeia_Reading_User_Books_Utils::update_user_book(
			(int) $row->id,
			array(
				'cover_attachment_id_user' => 0,
				'cover_reference'          => $reference,
				'cover_url'                => $cover_url,
				'cover_source'             => '',
			)
		);

		Politeia_Reading_User_Books_Utils::json_success(
			array(
				'cover_url'       => $cover_url,
				'cover_reference' => $reference,
				'user_book_id'    => (int) $row->id,
			)
		);
	}

	private static function normalize_cover_url( $url ) {
		if ( ! is_string( $url ) ) {
			return '';
		}

		$trimmed = trim( $url );
		if ( '' === $trimmed ) {
			return '';
		}

		$normalized = preg_replace( '#^http://#i', 'https://', $trimmed );
		if ( ! $normalized ) {
			return '';
		}

		$parts = wp_parse_url( $normalized );
		$host  = isset( $parts['host'] ) ? strtolower( $parts['host'] ) : '';

		if ( $host && false !== strpos( $host, 'books.google' ) && false !== stripos( $normalized, '/books/content' ) ) {
			if ( preg_match( '/([?&])zoom=\d+/i', $normalized ) ) {
				$normalized = preg_replace( '/([?&]zoom=)(\d+)/i', '$13', $normalized, 1 );
			} else {
				$normalized .= ( false === strpos( $normalized, '?' ) ? '?' : '&' ) . 'zoom=3';
			}
		}

		return $normalized;
	}
}
