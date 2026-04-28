<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles all AJAX requests for the Add Book module.
 */
class Politeia_Reading_Ajax_Add_Book {

	/**
	 * Initialize AJAX hooks.
	 */
	public static function init() {
		add_action( 'wp_ajax_prs_canonical_title_search', array( __CLASS__, 'canonical_title_search' ) );
		add_action( 'wp_ajax_nopriv_prs_canonical_title_search', array( __CLASS__, 'canonical_title_search' ) );
		add_action( 'wp_ajax_prs_check_user_book', array( __CLASS__, 'check_user_book_status' ) );
		add_action( 'wp_ajax_prs_check_user_book_identity', array( __CLASS__, 'check_user_book_identity' ) );
	}

	/**
	 * Search for books in the canonical database for autocomplete.
	 */
	public static function canonical_title_search() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Login required.', 'politeia-reading' ) ), 403 );
		}

		$user_id = get_current_user_id();

		$nonce = isset( $_POST['nonce'] ) ? wp_unslash( $_POST['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'prs_canonical_title_search' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce.', 'politeia-reading' ) ), 403 );
		}

		$query = isset( $_POST['query'] ) ? wp_unslash( $_POST['query'] ) : '';
		$query = function_exists( 'prs_normalize_title' ) ? prs_normalize_title( $query ) : $query;

		if ( '' === $query ) {
			wp_send_json(
				array(
					'source' => 'canonical',
					'items'  => array(),
				)
			);
		}

		global $wpdb;
		$user_books_table   = $wpdb->prefix . 'politeia_user_books';
		$books_table        = $wpdb->prefix . 'politeia_books';
		$book_authors_table = $wpdb->prefix . 'politeia_book_authors';
		$authors_table      = $wpdb->prefix . 'politeia_authors';
		$like               = $wpdb->esc_like( $query ) . '%';

		$books_has_total_pages = (bool) $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$books_table} LIKE %s", 'total_pages' ) );
		$book_pages_select     = $books_has_total_pages ? 'b.total_pages' : 'NULL';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT b.id, b.title, b.year, b.slug, COALESCE(ub.pages, {$book_pages_select}) AS pages, ub.cover_reference, b.cover_attachment_id
				FROM {$books_table} b
				LEFT JOIN {$user_books_table} ub ON ub.book_id = b.id AND ub.user_id = %d AND ub.deleted_at IS NULL
				WHERE b.normalized_title LIKE %s
				ORDER BY b.year DESC
				LIMIT 10",
				$user_id,
				$like
			),
			ARRAY_A
		);

		$author_map = array();
		if ( $rows ) {
			$book_ids = array();
			foreach ( $rows as $row ) {
				if ( ! empty( $row['id'] ) ) {
					$book_ids[] = (int) $row['id'];
				}
			}

			if ( $book_ids ) {
				$placeholders = implode( ',', array_fill( 0, count( $book_ids ), '%d' ) );
				$author_rows  = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT ba.book_id, a.display_name AS name FROM {$book_authors_table} ba INNER JOIN {$authors_table} a ON a.id = ba.author_id WHERE ba.book_id IN ({$placeholders}) ORDER BY a.display_name ASC",
						$book_ids
					),
					ARRAY_A
				);

				if ( $author_rows ) {
					foreach ( $author_rows as $author_row ) {
						$book_id = isset( $author_row['book_id'] ) ? (int) $author_row['book_id'] : 0;
						$name    = isset( $author_row['name'] ) ? (string) $author_row['name'] : '';
						if ( $book_id && '' !== $name ) {
							if ( ! isset( $author_map[ $book_id ] ) ) {
								$author_map[ $book_id ] = array();
							}
							$author_map[ $book_id ][] = $name;
						}
					}
				}
			}
		}

		$items = array();
		if ( $rows ) {
			foreach ( $rows as $row ) {
				$book_id        = isset( $row['id'] ) ? (int) $row['id'] : 0;
				$year           = isset( $row['year'] ) ? (int) $row['year'] : 0;
				$cover_url      = '';
				$user_cover_raw = isset( $row['cover_reference'] ) ? $row['cover_reference'] : '';
				if ( null !== $user_cover_raw && '' !== $user_cover_raw ) {
					if ( is_numeric( $user_cover_raw ) ) {
						$cover_url = wp_get_attachment_image_url( (int) $user_cover_raw, 'medium' );
					} else {
						$cover_url = esc_url_raw( $user_cover_raw );
					}
				}
				if ( ! $cover_url && ! empty( $row['cover_attachment_id'] ) ) {
					$cover_url = wp_get_attachment_image_url( (int) $row['cover_attachment_id'], 'medium' );
				}
				$items[] = array(
					'id'      => $book_id,
					'title'   => isset( $row['title'] ) ? (string) $row['title'] : '',
					'year'    => $year > 0 ? $year : '',
					'slug'    => isset( $row['slug'] ) ? (string) $row['slug'] : '',
					'pages'   => isset( $row['pages'] ) ? (int) $row['pages'] : '',
					'cover'   => $cover_url ? $cover_url : '',
					'authors' => isset( $author_map[ $book_id ] ) ? array_values( $author_map[ $book_id ] ) : array(),
				);
			}
		}

		wp_send_json(
			array(
				'source' => 'canonical',
				'items'  => $items,
			)
		);
	}

	/**
	 * Check if a specific book already exists in the user's library.
	 */
	public static function check_user_book_status() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Login required.', 'politeia-reading' ) ), 403 );
		}

		$nonce = isset( $_POST['nonce'] ) ? wp_unslash( $_POST['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'prs_canonical_title_search' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce.', 'politeia-reading' ) ), 403 );
		}

		$book_id = isset( $_POST['book_id'] ) ? absint( $_POST['book_id'] ) : 0;
		if ( $book_id <= 0 ) {
			wp_send_json_success(
				array(
					'exists'  => false,
					'allowed' => true,
				)
			);
		}

		$user_id = get_current_user_id();
		global $wpdb;
		$table    = $wpdb->prefix . 'politeia_user_books';
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE user_id = %d AND book_id = %d AND deleted_at IS NULL LIMIT 1",
				$user_id,
				$book_id
			)
		);

		if ( $existing ) {
			wp_send_json_success(
				array(
					'exists'  => true,
					'allowed' => false,
					'message' => __( 'Already in your library', 'politeia-reading' ),
				)
			);
		}

		wp_send_json_success(
			array(
				'exists'  => false,
				'allowed' => true,
			)
		);
	}

	/**
	 * Perform a deep check to identify if a book identity (Title, Authors, Year) already exists.
	 */
	public static function check_user_book_identity() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Login required.', 'politeia-reading' ) ), 403 );
		}

		$nonce = isset( $_POST['nonce'] ) ? wp_unslash( $_POST['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'prs_canonical_title_search' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce.', 'politeia-reading' ) ), 403 );
		}

		$title   = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$year    = isset( $_POST['year'] ) && '' !== $_POST['year'] ? absint( $_POST['year'] ) : null;
		$isbn    = isset( $_POST['isbn'] ) ? sanitize_text_field( wp_unslash( $_POST['isbn'] ) ) : '';
		$authors = array();
		if ( isset( $_POST['authors'] ) ) {
			$raw_authors = wp_unslash( $_POST['authors'] );
			if ( is_array( $raw_authors ) ) {
				$authors = $raw_authors;
			} elseif ( is_string( $raw_authors ) ) {
				$authors = preg_split( '/[;,\|]+/', (string) $raw_authors );
			}
		}

		if ( '' === $title || empty( $authors ) ) {
			wp_send_json_success(
				array(
					'exists'  => false,
					'allowed' => true,
				)
			);
		}

		$book_id = 0;
		$isbn    = function_exists( 'prs_normalize_isbn' ) ? prs_normalize_isbn( $isbn ) : $isbn;
		if ( $isbn ) {
			$book_id = (int) ( function_exists( 'prs_get_book_id_by_isbn' ) ? prs_get_book_id_by_isbn( $isbn ) : 0 );
		}
		if ( ! $book_id ) {
			$book_id = (int) ( function_exists( 'prs_get_book_id_by_identity' ) ? prs_get_book_id_by_identity( $title, $authors, $year ) : 0 );
		}

		if ( ! $book_id ) {
			wp_send_json_success(
				array(
					'exists'  => false,
					'allowed' => true,
				)
			);
		}

		$user_id = get_current_user_id();
		global $wpdb;
		$table    = $wpdb->prefix . 'politeia_user_books';
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE user_id = %d AND book_id = %d AND deleted_at IS NULL LIMIT 1",
				$user_id,
				$book_id
			)
		);

		if ( $existing ) {
			$canonical_isbn  = function_exists( 'prs_get_book_isbn' ) ? prs_get_book_isbn( $book_id ) : '';
			$allow_duplicate = ( '' !== $isbn && '' !== $canonical_isbn && $isbn !== $canonical_isbn );
			if ( ! $allow_duplicate ) {
				wp_send_json_success(
					array(
						'exists'  => true,
						'allowed' => false,
						'message' => __( 'Already in your library', 'politeia-reading' ),
					)
				);
			}
		}

		wp_send_json_success(
			array(
				'exists'  => false,
				'allowed' => true,
			)
		);
	}
}
