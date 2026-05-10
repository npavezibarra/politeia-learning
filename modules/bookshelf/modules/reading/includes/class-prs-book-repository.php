<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles database operations for Books and User-Book relationships.
 */
class Politeia_Reading_Book_Repository {

	/**
	 * Get book ID by identity (title, authors, year).
	 */
	public static function get_book_id_by_identity( $title, $authors, $year = null ) {
		$normalized_title = Politeia_Reading_Book_Utils::normalize_title( $title );
		if ( '' === $normalized_title ) {
			return 0;
		}

		$hashes = Politeia_Reading_Author_Manager::get_author_hashes_from_names( $authors );
		if ( empty( $hashes ) ) {
			return 0;
		}

		global $wpdb;
		$books_table   = $wpdb->prefix . 'politeia_books';
		$pivot_table   = $wpdb->prefix . 'politeia_book_authors';
		$authors_table = $wpdb->prefix . 'politeia_authors';

		$placeholders = implode( ', ', array_fill( 0, count( $hashes ), '%s' ) );
		$year_clause  = '';
		$params       = array_merge( array( $normalized_title ), $hashes );

		if ( null !== $year && '' !== $year ) {
			$year_clause = ' AND b.year = %d';
			$params[]    = (int) $year;
		}

		$params[] = count( $hashes );
		$params[] = count( $hashes );

		$sql = "
			SELECT b.id
			FROM {$books_table} b
			INNER JOIN {$pivot_table} ba ON ba.book_id = b.id
			INNER JOIN {$authors_table} a ON a.id = ba.author_id
			WHERE b.normalized_title = %s
			  AND a.name_hash IN ({$placeholders})
			  {$year_clause}
			GROUP BY b.id
			HAVING COUNT(DISTINCT a.name_hash) = %d
			   AND (SELECT COUNT(*) FROM {$pivot_table} WHERE book_id = b.id) = %d
			LIMIT 1
		";

		$book_id = $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
		return $book_id ? (int) $book_id : 0;
	}

	/**
	 * Find or create a book entry.
	 */
	public static function find_or_create_book( $title, $author, $year = null, $isbn = '', $attachment_id = null, $all_authors = null, $source = 'candidate' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'politeia_books';

		$title  = trim( wp_strip_all_tags( $title ) );
		$author = trim( wp_strip_all_tags( $author ) );
		$isbn   = Politeia_Reading_Book_Utils::normalize_isbn( $isbn );

		if ( $title === '' || $author === '' ) {
			return new WP_Error( 'prs_invalid_book', 'Missing title/author' );
		}

		$normalized_title = Politeia_Reading_Book_Utils::normalize_title( $title );
		$normalized_title = $normalized_title !== '' ? $normalized_title : null;

		$slug = Politeia_Reading_Book_Utils::generate_book_slug( $title, $year );

		$existing_id = null;
		if ( $slug ) {
			$existing_id = Politeia_Reading_Book_Utils::get_book_id_by_slug( $slug );
		}
		$authors_payload = $all_authors;
		if ( $authors_payload instanceof \Traversable ) {
			$authors_payload = iterator_to_array( $authors_payload, false );
		}
		if ( empty( $authors_payload ) ) {
			$authors_payload = array( $author );
		} elseif ( is_array( $authors_payload ) ) {
			$authors_payload[] = $author;
		} else {
			$authors_payload = array( $authors_payload, $author );
		}

		if ( $existing_id ) {
			$book_id = (int) $existing_id;
			if ( $slug && Politeia_Reading_Book_Utils::slugs_table_exists() ) {
				$primary_slug = Politeia_Reading_Book_Utils::get_primary_slug_for_book( $book_id );
				if ( '' === $primary_slug ) {
					Politeia_Reading_Book_Utils::set_primary_book_slug( $book_id, $slug );
				}
			}
			if ( '' === Politeia_Reading_Book_Utils::get_primary_slug_for_book( $book_id ) ) {
				Politeia_Reading_Book_Utils::ensure_primary_book_slug( $book_id, $title, $year );
			}
			if ( 'confirmed' === $source ) {
				Politeia_Reading_Author_Manager::sync_book_author_links( $book_id, $authors_payload, $source );
			}
			if ( $isbn ) {
				Politeia_Reading_Book_Utils::update_book_isbn_if_empty( $book_id, $isbn );
			}
			return $book_id;
		}

		if ( 'confirmed' !== $source ) {
			return new WP_Error( 'prs_canonical_write_blocked', 'Canonical writes require confirmation.' );
		}

		$insert_data = array(
			'title'               => $title,
			'year'                => $year ? (int) $year : null,
			'cover_attachment_id' => $attachment_id ? (int) $attachment_id : null,
			'normalized_title'    => $normalized_title,
			'created_at'          => current_time( 'mysql' ),
			'updated_at'          => current_time( 'mysql' ),
		);
		if ( $slug ) {
			$insert_data['slug'] = $slug;
		}
		if ( $isbn && Politeia_Reading_Book_Utils::has_isbn_column() ) {
			$insert_data['isbn'] = $isbn;
		}

		$inserted = $wpdb->insert(
			$table,
			$insert_data
		);

		if ( false === $inserted ) {
			return new WP_Error( 'prs_insert_failed', $wpdb->last_error ?: 'Could not insert book.' );
		}

		$book_id = (int) $wpdb->insert_id;
		Politeia_Reading_Author_Manager::sync_book_author_links( $book_id, $authors_payload, $source );
		if ( $slug ) {
			Politeia_Reading_Book_Utils::set_primary_book_slug( $book_id, $slug );
		}

		return $book_id;
	}

	/**
	 * Create a book candidate.
	 */
	public static function create_book_candidate( $input, $args = array() ) {
		$defaults = array(
			'user_id'            => 0,
			'input_type'         => 'text',
			'source_note'        => 'candidate',
			'enqueue'            => true,
			'author'             => '',
			'raw_response'       => null,
			'limit_per_provider' => 5,
		);
		$args     = wp_parse_args( $args, $defaults );
		$user_id  = (int) $args['user_id'];
		if ( $user_id <= 0 ) {
			$user_id = get_current_user_id();
		}

		$title          = '';
		$author         = '';
		$year           = null;
		$image          = null;
		$isbn           = '';
		$raw_candidates = array();

		if ( is_array( $input ) ) {
			if ( isset( $input['candidates'] ) && is_array( $input['candidates'] ) ) {
				$raw_candidates = $input['candidates'];
			}
			$title  = isset( $input['title'] ) ? (string) $input['title'] : '';
			$author = isset( $input['author'] ) ? (string) $input['author'] : '';
			$year   = isset( $input['year'] ) ? (int) $input['year'] : null;
			$image  = isset( $input['image'] ) ? (string) $input['image'] : null;
			$isbn   = isset( $input['isbn'] ) ? (string) $input['isbn'] : '';
		} elseif ( is_string( $input ) ) {
			$title  = $input;
			$author = isset( $args['author'] ) ? (string) $args['author'] : '';
		}

		$candidates = array();

		foreach ( $raw_candidates as $cand ) {
			if ( ! is_array( $cand ) ) {
				continue;
			}
			$candidates[] = array(
				'title'  => isset( $cand['title'] ) ? (string) $cand['title'] : '',
				'author' => isset( $cand['author'] ) ? (string) $cand['author'] : '',
				'year'   => isset( $cand['year'] ) ? (int) $cand['year'] : null,
				'image'  => isset( $cand['image'] ) ? (string) $cand['image'] : null,
				'isbn'   => isset( $cand['isbn'] ) ? (string) $cand['isbn'] : '',
				'source' => isset( $cand['source'] ) ? (string) $cand['source'] : 'input',
			);
		}

		if ( '' !== trim( $title ) && '' !== trim( $author ) ) {
			$candidates[] = array(
				'title'  => $title,
				'author' => $author,
				'year'   => $year,
				'isbn'   => $isbn,
				'image'  => $image,
				'source' => 'input',
			);
		}

		$external_best = null;
		if ( '' !== trim( $title ) && '' !== trim( $author ) ) {
			if ( ! class_exists( 'Politeia_Book_External_API' ) && function_exists( 'politeia_chatgpt_safe_require' ) ) {
				politeia_chatgpt_safe_require( 'modules/book-detection/class-book-external-api.php' );
			}

			if ( class_exists( 'Politeia_Book_External_API' ) ) {
				$api           = new Politeia_Book_External_API();
				$external_best = $api->search_best_match(
					$title,
					$author,
					array( 'limit_per_provider' => (int) $args['limit_per_provider'] )
				);
				if ( is_array( $external_best ) && ! empty( $external_best['title'] ) && ! empty( $external_best['author'] ) ) {
					$candidates[] = array(
						'title'  => (string) $external_best['title'],
						'author' => (string) $external_best['author'],
						'year'   => isset( $external_best['year'] ) ? (int) $external_best['year'] : null,
						'isbn'   => isset( $external_best['isbn'] ) ? (string) $external_best['isbn'] : '',
						'image'  => null,
						'source' => isset( $external_best['source'] ) ? (string) $external_best['source'] : 'external',
					);
				}
			}
		}

		$deduped = array();
		$seen    = array();
		foreach ( $candidates as $cand ) {
			$t = isset( $cand['title'] ) ? trim( (string) $cand['title'] ) : '';
			$a = isset( $cand['author'] ) ? trim( (string) $cand['author'] ) : '';
			if ( '' === $t || '' === $a ) {
				continue;
			}
			$key_t = function_exists( 'politeia__normalize_text' ) ? politeia__normalize_text( $t ) : strtolower( $t );
			$key_a = function_exists( 'politeia__normalize_text' ) ? politeia__normalize_text( $a ) : strtolower( $a );
			$key   = $key_t . '|' . $key_a;
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$deduped[]    = $cand;
		}

		$queue_result = array(
			'queued'   => 0,
			'skipped'  => 0,
			'pending'  => array(),
			'in_shelf' => array(),
		);

		if ( $args['enqueue'] && function_exists( 'politeia_chatgpt_queue_confirm_items' ) ) {
			$queue_result = politeia_chatgpt_queue_confirm_items(
				$deduped,
				array(
					'user_id'      => $user_id,
					'input_type'   => (string) $args['input_type'],
					'source_note'  => (string) $args['source_note'],
					'raw_response' => $args['raw_response'],
				)
			);
		}

		return array_merge(
			array(
				'candidates'    => $deduped,
				'external_best' => $external_best,
			),
			$queue_result
		);
	}

	/**
	 * Promote candidate to canonical.
	 */
	public static function promote_candidate_to_canonical( $candidate_id, $user_id, $year_override = null ) {
		global $wpdb;

		$candidate_id = (int) $candidate_id;
		$user_id      = (int) $user_id;

		if ( $candidate_id <= 0 || $user_id <= 0 ) {
			return new WP_Error( 'prs_invalid_candidate', 'Invalid candidate or user.' );
		}

		$tbl_confirm = $wpdb->prefix . 'politeia_book_confirm';
		$row         = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$tbl_confirm} WHERE id=%d AND user_id=%d LIMIT 1",
				$candidate_id,
				$user_id
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return new WP_Error( 'prs_candidate_missing', 'Candidate not found.' );
		}

		$title  = isset( $row['title'] ) ? trim( (string) $row['title'] ) : '';
		$author = isset( $row['author'] ) ? trim( (string) $row['author'] ) : '';

		if ( '' === $title || '' === $author ) {
			return new WP_Error( 'prs_candidate_invalid', 'Candidate is missing title or author.' );
		}

		$raw_response = array();
		if ( ! empty( $row['raw_response'] ) ) {
			$decoded = json_decode( (string) $row['raw_response'], true );
			if ( is_array( $decoded ) ) {
				$raw_response = $decoded;
			}
		}

		$raw_payload = array();
		if ( isset( $raw_response['raw_payload'] ) ) {
			if ( is_array( $raw_response['raw_payload'] ) ) {
				$raw_payload = $raw_response['raw_payload'];
			} elseif ( is_string( $raw_response['raw_payload'] ) && $raw_response['raw_payload'] !== '' ) {
				$decoded = json_decode( $raw_response['raw_payload'], true );
				if ( is_array( $decoded ) ) {
					$raw_payload = $decoded;
				}
			}
		}
		$original = isset( $raw_response['original_input'] ) && is_array( $raw_response['original_input'] )
				? $raw_response['original_input']
				: array();

		$year = null;
		if ( null !== $year_override ) {
			$year = (int) $year_override;
		} elseif ( isset( $original['year'] ) && $original['year'] !== '' ) {
			$year = (int) $original['year'];
		} elseif ( isset( $raw_payload['year'] ) && $raw_payload['year'] !== '' ) {
			$year = (int) $raw_payload['year'];
		}

		$attachment_id = isset( $raw_payload['cover_attachment_id'] ) ? (int) $raw_payload['cover_attachment_id'] : null;
		$all_authors   = isset( $raw_payload['authors'] ) ? $raw_payload['authors'] : null;
		$isbn          = '';
		if ( isset( $raw_payload['isbn'] ) && $raw_payload['isbn'] !== '' ) {
			$isbn = (string) $raw_payload['isbn'];
		} elseif ( isset( $original['isbn'] ) && $original['isbn'] !== '' ) {
			$isbn = (string) $original['isbn'];
		} elseif ( isset( $row['external_isbn'] ) && $row['external_isbn'] !== '' ) {
			$isbn = (string) $row['external_isbn'];
		}

		$books_table = $wpdb->prefix . 'politeia_books';
		$slug        = Politeia_Reading_Book_Utils::generate_book_slug( $title, $year );
		$existing_id = 0;
		if ( $slug ) {
			$existing_id = (int) Politeia_Reading_Book_Utils::get_book_id_by_slug( $slug );
		}

		$book_id = 0;
		if ( $existing_id ) {
			$book_id = (int) $existing_id;
			Politeia_Reading_Author_Manager::sync_book_author_links( $book_id, $all_authors, 'confirmed' );
			if ( $isbn ) {
				Politeia_Reading_Book_Utils::update_book_isbn_if_empty( $book_id, $isbn );
			}
		} else {
			$book_id = self::find_or_create_book( $title, $author, $year, $isbn, $attachment_id, $all_authors, 'confirmed' );
			if ( is_wp_error( $book_id ) ) {
				return $book_id;
			}
			$book_id = (int) $book_id;
		}

		if ( $book_id <= 0 ) {
			return new WP_Error( 'prs_promote_failed', 'Failed to resolve canonical book.' );
		}

		if ( $year ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$books_table} SET year=%d WHERE id=%d AND (year IS NULL OR year=0)",
					$year,
					$book_id
				)
			);
		}

		$user_book_id = self::ensure_user_book( $user_id, $book_id );
		if ( ! $user_book_id ) {
			return new WP_Error( 'prs_user_book_failed', 'Could not attach book to user.' );
		}

		return array(
			'book_id'      => $book_id,
			'user_book_id' => (int) $user_book_id,
			'created'      => ( $existing_id > 0 ) ? false : true,
		);
	}

	/**
	 * Ensure user-book entry exists.
	 */
	public static function ensure_user_book( $user_id, $book_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'politeia_user_books';

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, deleted_at FROM {$table} WHERE user_id = %d AND book_id = %d LIMIT 1",
				$user_id,
				$book_id
			)
		);
		$changed = false;
		if ( $row ) {
			$id = (int) $row->id;
			if ( ! empty( $row->deleted_at ) ) {
				$wpdb->update(
					$table,
					array(
						'deleted_at' => null,
						'updated_at' => current_time( 'mysql' ),
					),
					array( 'id' => $id )
				);
				$changed = true;
			}
			if ( $changed && function_exists( 'prs_invalidate_library_cache_for_user' ) ) {
				prs_invalidate_library_cache_for_user( $user_id );
			}
			return $id;
		}

		$wpdb->insert(
			$table,
			array(
				'user_id'        => (int) $user_id,
				'book_id'        => (int) $book_id,
				'reading_status' => 'not_started',
				'owning_status'  => 'in_shelf',
				'created_at'     => current_time( 'mysql' ),
				'updated_at'     => current_time( 'mysql' ),
			)
		);
		$user_book_id = (int) $wpdb->insert_id;
		if ( $user_book_id > 0 && function_exists( 'prs_invalidate_library_cache_for_user' ) ) {
			prs_invalidate_library_cache_for_user( $user_id );
		}
		return $user_book_id;
	}

	/**
	 * Get user books for library.
	 */
	public static function get_user_books_for_library( $user_id, $args = array() ) {
		global $wpdb;

		$defaults = array(
			'per_page' => 0,
			'offset'   => 0,
			'order'    => 'title_asc',
			'search'   => '',
		);

		$args = wp_parse_args( $args, $defaults );

		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return array();
		}

		$per_page = (int) $args['per_page'];
		$offset   = max( 0, (int) $args['offset'] );
		$order    = isset( $args['order'] ) ? (string) $args['order'] : 'title_asc';
		$search   = isset( $args['search'] ) ? trim( (string) $args['search'] ) : '';

		$ub = $wpdb->prefix . 'politeia_user_books';
		$b  = $wpdb->prefix . 'politeia_books';
		$l  = $wpdb->prefix . 'politeia_loans';
		$ba = $wpdb->prefix . 'politeia_book_authors';
		$a  = $wpdb->prefix . 'politeia_authors';
		$search_params = array();

		static $books_has_total_pages = null;
		if ( null === $books_has_total_pages ) {
			$books_has_total_pages = (bool) $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$b} LIKE %s", 'total_pages' ) );
		}

		$book_pages_select = $books_has_total_pages ? 'b.total_pages' : 'NULL';

		$sql = "
		SELECT
			ub.id AS user_book_id,
			ub.reading_status,
			ub.owning_status,
			ub.type_book,
			ub.pages,
			ub.counterparty_name,
			ub.counterparty_email,
			ub.cover_reference,
			(
				SELECT start_date
				FROM {$l} l
				WHERE l.user_id = ub.user_id
				  AND l.book_id = ub.book_id
				  AND l.end_date IS NULL
				ORDER BY l.id DESC
				LIMIT 1
			) AS active_loan_start,
			b.id AS book_id,
			b.title,
			b.year,
			b.cover_attachment_id,
			b.slug,
			(
				SELECT GROUP_CONCAT(a.display_name ORDER BY ba.sort_order ASC SEPARATOR ', ')
				FROM {$ba} ba
				LEFT JOIN {$a} a ON a.id = ba.author_id
				WHERE ba.book_id = b.id
			) AS authors,
			{$book_pages_select} AS book_total_pages
		FROM {$ub} ub
		JOIN {$b} b ON b.id = ub.book_id
			WHERE ub.user_id = %d
			  AND ub.deleted_at IS NULL
			  AND (ub.owning_status IS NULL OR ub.owning_status != 'deleted')
			";

		if ( '' !== $search ) {
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			$sql .= "
			  AND (
					b.title LIKE %s
					OR EXISTS (
						SELECT 1
						FROM {$ba} ba_search
						INNER JOIN {$a} a_search ON a_search.id = ba_search.author_id
						WHERE ba_search.book_id = b.id
						  AND a_search.display_name LIKE %s
					)
			  )
			";
			$search_params = array( $like, $like );
		}

		if ( 'recent' === $order ) {
			$sql .= ' ORDER BY ub.updated_at DESC, b.title ASC, b.id ASC';
		} else {
			$sql .= ' ORDER BY b.title ASC, b.id ASC';
		}

		$params = array_merge( array( $user_id ), $search_params );

		if ( $per_page > 0 ) {
			$sql      .= ' LIMIT %d OFFSET %d';
			$params[]  = $per_page;
			$params[]  = $offset;
		}

		$prepared = $wpdb->prepare( $sql, $params );
		$books    = $wpdb->get_results( $prepared );

		if ( $books && class_exists( 'Politeia_Reading_Sessions_Stats' ) ) {
			self::hydrate_library_progress( $user_id, $books );
		}

		return $books;
	}

	/**
	 * Attach progress values to library rows in one batch query.
	 *
	 * @param int   $user_id The current user ID.
	 * @param array $books   The library rows returned by the repository.
	 */
	private static function hydrate_library_progress( $user_id, array &$books ) {
		global $wpdb;

		$user_book_ids = array();
		$book_meta     = array();

		foreach ( $books as $book ) {
			if ( ! is_object( $book ) || empty( $book->user_book_id ) ) {
				continue;
			}

			$user_book_id = (int) $book->user_book_id;
			$total_pages  = isset( $book->book_total_pages ) ? (int) $book->book_total_pages : 0;
			if ( $total_pages <= 0 ) {
				$total_pages = isset( $book->pages ) ? (int) $book->pages : 0;
			}

			$user_book_ids[]            = $user_book_id;
			$book_meta[ $user_book_id ] = array(
				'total_pages'    => $total_pages,
				'reading_status' => isset( $book->reading_status ) ? (string) $book->reading_status : '',
			);
		}

		$user_book_ids = array_values( array_unique( array_filter( array_map( 'absint', $user_book_ids ) ) ) );
		if ( empty( $user_book_ids ) ) {
			return;
		}

		$sessions_table = $wpdb->prefix . 'politeia_reading_sessions';
		$placeholders   = implode( ', ', array_fill( 0, count( $user_book_ids ), '%d' ) );
		$params         = array_merge( array( (int) $user_id ), $user_book_ids );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"
				SELECT user_book_id, start_page, end_page
				FROM {$sessions_table}
				WHERE user_id = %d
				  AND user_book_id IN ({$placeholders})
				  AND end_time IS NOT NULL
				  AND deleted_at IS NULL
			",
				$params
			),
			ARRAY_A
		);

		$interval_map = array();
		foreach ( (array) $rows as $row ) {
			$user_book_id = isset( $row['user_book_id'] ) ? (int) $row['user_book_id'] : 0;
			if ( $user_book_id <= 0 ) {
				continue;
			}

			$interval_map[ $user_book_id ][] = array(
				's' => isset( $row['start_page'] ) ? (int) $row['start_page'] : 0,
				'e' => isset( $row['end_page'] ) ? (int) $row['end_page'] : 0,
			);
		}

		foreach ( $books as $book ) {
			if ( ! is_object( $book ) || empty( $book->user_book_id ) ) {
				continue;
			}

			$user_book_id  = (int) $book->user_book_id;
			$meta          = isset( $book_meta[ $user_book_id ] ) ? $book_meta[ $user_book_id ] : array();
			$total_pages   = isset( $meta['total_pages'] ) ? (int) $meta['total_pages'] : 0;
			$reading_state = isset( $meta['reading_status'] ) ? (string) $meta['reading_status'] : '';
			$intervals     = isset( $interval_map[ $user_book_id ] ) ? $interval_map[ $user_book_id ] : array();

			$progress_base = 0;
			if ( $total_pages > 0 ) {
				$progress_base = Politeia_Reading_Sessions_Stats::calculate_progress_percent_from_intervals( $intervals, $total_pages );
			}

			$book->progress_base_percent = $progress_base;
			$book->progress_percent      = ( 'finished' === $reading_state ) ? 100 : $progress_base;
		}
	}

	/**
	 * Count books in the user's library using the same filters as the list query.
	 *
	 * @param int   $user_id The current user ID.
	 * @param array $args    Query args.
	 * @return int
	 */
	public static function get_user_books_for_library_count( $user_id, $args = array() ) {
		global $wpdb;

		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return 0;
		}

		$search = isset( $args['search'] ) ? trim( (string) $args['search'] ) : '';

		$ub = $wpdb->prefix . 'politeia_user_books';
		$b  = $wpdb->prefix . 'politeia_books';
		$ba = $wpdb->prefix . 'politeia_book_authors';
		$a  = $wpdb->prefix . 'politeia_authors';

		$sql = "
			SELECT COUNT(*)
			FROM {$ub} ub
			JOIN {$b} b ON b.id = ub.book_id
			WHERE ub.user_id = %d
			  AND ub.deleted_at IS NULL
			  AND (ub.owning_status IS NULL OR ub.owning_status != 'deleted')
		";

		$params = array( $user_id );

		if ( '' !== $search ) {
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			$sql .= "
			  AND (
					b.title LIKE %s
					OR EXISTS (
						SELECT 1
						FROM {$ba} ba_search
						INNER JOIN {$a} a_search ON a_search.id = ba_search.author_id
						WHERE ba_search.book_id = b.id
						  AND a_search.display_name LIKE %s
					)
			  )
			";
			$params[] = $like;
			$params[] = $like;
		}

		$prepared = $wpdb->prepare( $sql, $params );
		return (int) $wpdb->get_var( $prepared );
	}

	/**
	 * Diagnose canonical identity collisions.
	 */
	public static function diagnose_canonical_identity_collisions() {
		global $wpdb;

		$books_table = $wpdb->prefix . 'politeia_books';
		$pivot_table = $wpdb->prefix . 'politeia_book_authors';

		$sql = "
			SELECT b.id, b.normalized_title, b.year,
				   GROUP_CONCAT(ba.author_id ORDER BY ba.sort_order ASC SEPARATOR ',') AS author_ids
			FROM {$books_table} b
			LEFT JOIN {$pivot_table} ba ON ba.book_id = b.id
			GROUP BY b.id
			ORDER BY b.id ASC
		";
		$rows = $wpdb->get_results( $sql, ARRAY_A );

		$identity_map = array();

		foreach ( (array) $rows as $row ) {
			$normalized_title = isset( $row['normalized_title'] ) ? trim( (string) $row['normalized_title'] ) : '';
			$author_ids       = isset( $row['author_ids'] ) && $row['author_ids'] !== null ? (string) $row['author_ids'] : '';
			$year             = isset( $row['year'] ) && $row['year'] !== null && $row['year'] !== '' ? (string) $row['year'] : '';

			$identity = $normalized_title . '|' . $author_ids;
			if ( '' !== $year ) {
				$identity .= '|' . $year;
			}

			if ( ! isset( $identity_map[ $identity ] ) ) {
				$identity_map[ $identity ] = array();
			}
			$identity_map[ $identity ][] = (int) $row['id'];
		}

		$collision_details = array();
		foreach ( $identity_map as $identity => $ids ) {
			if ( count( $ids ) > 1 ) {
				$collision_details[] = array(
					'identity' => $identity,
					'book_ids' => array_values( $ids ),
				);
			}
		}

		return array(
			'total_books'       => count( $rows ),
			'unique_identities' => count( $identity_map ),
			'collisions'        => count( $collision_details ),
			'collision_details' => $collision_details,
		);
	}
}
