<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Controller for the Single Book view.
 */
class Politeia_Reading_Book_Single_Controller {

	/**
	 * Prepare data and render the single book template.
	 */
	public static function render( $slug ) {
		if ( ! is_user_logged_in() ) {
			return self::render_login_required();
		}

		global $wpdb;
		$user_id = get_current_user_id();

		$book_id = Politeia_Reading_Book_Utils::get_book_id_by_slug( $slug );
		if ( ! $book_id ) {
			return self::render_not_found();
		}

		$book = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT b.*,
						(
							SELECT GROUP_CONCAT(a.display_name ORDER BY ba.sort_order ASC SEPARATOR ', ')
							FROM {$wpdb->prefix}politeia_book_authors ba
							LEFT JOIN {$wpdb->prefix}politeia_authors a ON a.id = ba.author_id
							WHERE ba.book_id = b.id
						) AS authors
				 FROM {$wpdb->prefix}politeia_books b
				 WHERE b.id = %d
				 LIMIT 1",
				$book_id
			)
		);

		if ( ! $book ) {
			return self::render_not_found();
		}

		// Handle primary slug redirect
		$primary_slug = Politeia_Reading_Book_Utils::get_primary_slug_for_book( (int) $book->id );
		if ( $primary_slug && ( $slug !== $primary_slug ) ) {
			wp_safe_redirect( home_url( '/my-books/my-book-' . $primary_slug . '/' ), 301 );
			exit;
		}

		$ub = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}politeia_user_books WHERE user_id = %d AND book_id = %d AND deleted_at IS NULL LIMIT 1",
				$user_id,
				$book->id
			)
		);

		if ( ! $ub ) {
			return self::render_no_access();
		}

		// Prepare data for templates
		$data = self::prepare_template_data( $user_id, $book, $ub );

		// Enqueue assets
		self::enqueue_assets( $data );

		// Render main template
		include POLITEIA_READING_PATH . 'templates/my-book-single-ver-2.php';
	}

	/**
	 * Prepare data for the template.
	 */
	private static function prepare_template_data( $user_id, $book, $ub ) {
		global $wpdb;

		$labels = Politeia_Reading_UI_Renderer::get_owning_labels();

		$owning_message = '';
		$contact_name   = $ub->counterparty_name ? (string) $ub->counterparty_name : '';
		$contact_email  = $ub->counterparty_email ? (string) $ub->counterparty_email : '';

		if ( $contact_name ) {
			switch ( (string) $ub->owning_status ) {
				case 'borrowing':
					$owning_message = sprintf( '%s %s', $labels['borrowing'], $contact_name );
					break;
				case 'borrowed':
					$owning_message = sprintf( '%s %s', $labels['borrowed'], $contact_name );
					break;
				case 'sold':
					$owning_message = sprintf( '%s %s', $labels['sold'], $contact_name );
					break;
				case 'lost':
					$owning_message = sprintf( '%s %s', $labels['lost'], $contact_name );
					break;
			}
		}

		$active_start_gmt = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT start_date FROM {$wpdb->prefix}politeia_loans
				 WHERE user_id = %d AND book_id = %d AND end_date IS NULL AND deleted_at IS NULL
				 ORDER BY id DESC LIMIT 1",
				$user_id,
				$book->id
			)
		);

		$sessions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.*, n.note 
				 FROM {$wpdb->prefix}politeia_reading_sessions s 
				 LEFT JOIN {$wpdb->prefix}politeia_read_ses_notes n ON s.id = n.rs_id AND n.user_id = s.user_id 
				 WHERE s.user_id = %d 
				   AND s.user_book_id = %d 
				   AND s.deleted_at IS NULL 
				 ORDER BY s.start_time DESC",
				$user_id,
				$ub->id
			)
		);

		// Cover logic
		$cover_data = self::prepare_cover_data( $book, $ub );

		// Progress logic
		$total_pages      = ( isset( $ub->pages ) && $ub->pages ) ? (int) $ub->pages : 0;
		$progress_percent = 0;
		$density_sessions = array();

		if ( $total_pages > 0 && ! empty( $sessions ) ) {
			$pages_read_total = 0;
			foreach ( $sessions as $session ) {
				if ( ! isset( $session->start_page, $session->end_page ) ) {
					continue;
				}
				$start = (int) $session->start_page;
				$end   = (int) $session->end_page;
				if ( $end < $start ) {
					continue;
				}
				$end               = min( $total_pages, $end );
				$start             = min( $total_pages, $start );
				$pages_read_total += max( 0, $end - $start );

				$density_sessions[] = array(
					'start_page' => (int) $session->start_page,
					'end_page'   => (int) $session->end_page,
				);
			}
			if ( $pages_read_total > 0 ) {
				$progress_percent = (int) round( ( $pages_read_total / $total_pages ) * 100 );
			}
		}

		return array(
			'user_id'            => $user_id,
			'book'               => $book,
			'ub'                 => $ub,
			'owning_message'     => $owning_message,
			'active_start_local' => $active_start_gmt ? get_date_from_gmt( $active_start_gmt, 'Y-m-d' ) : '',
			'sessions'           => $sessions,
			'density_sessions'   => $density_sessions,
			'progress_percent'   => max( 0, min( 100, $progress_percent ) ),
			'cover'              => $cover_data,
			'total_pages'        => $total_pages,
			'labels'             => $labels,
			'nonces'             => array(
				'owning'         => wp_create_nonce( 'save_owning_contact' ),
				'meta_update'    => wp_create_nonce( 'prs_update_user_book_meta' ),
				'cover_actions'  => wp_create_nonce( 'politeia_bookshelf_cover_actions' ),
				'reading'        => wp_create_nonce( 'prs_reading_nonce' ),
			),
		);
	}

	/**
	 * Prepare cover-related data.
	 */
	private static function prepare_cover_data( $book, $ub ) {
		$force_http = function_exists( 'politeia_bookshelf_force_http_covers' ) ? politeia_bookshelf_force_http_covers() : false;
		$scheme     = $force_http ? 'http' : ( is_ssl() ? 'https' : 'http' );

		$user_url    = '';
		$user_source = '';
		$user_id     = 0;

		// Parse cover_reference values saved by older flows (URL, attachment ID, JSON).
		if ( ! empty( $ub->cover_reference ) ) {
			$parsed_reference = self::parse_cover_reference( $ub->cover_reference );
			$user_url         = ! empty( $parsed_reference['url'] ) ? $parsed_reference['url'] : '';
			$user_source      = ! empty( $parsed_reference['source'] ) ? $parsed_reference['source'] : '';
			$user_id          = ! empty( $parsed_reference['attachment_id'] ) ? (int) $parsed_reference['attachment_id'] : 0;
		}

		// Fallback to explicit columns
		if ( ! $user_url && ! empty( $ub->cover_url ) ) {
			$user_url = $ub->cover_url;
		}
		if ( ! $user_id && ! empty( $ub->cover_attachment_id_user ) ) {
			$user_id = (int) $ub->cover_attachment_id_user;
		}

		$final_url    = $user_url ? set_url_scheme( $user_url, $scheme ) : '';
		$final_source = $user_source;
		$final_id     = $user_id;

		// Resolve attachment URL
		if ( ! $final_url && $final_id > 0 ) {
			$attach_url = wp_get_attachment_url( $final_id );
			if ( $attach_url ) {
				$final_url = set_url_scheme( $attach_url, $scheme );
			}
			if ( ! $final_source ) {
				$meta_source = get_post_meta( $final_id, '_prs_cover_source', true );
				if ( $meta_source ) {
					$final_source = $meta_source;
				}
			}
		}

		// Fallback to Canonical Book Cover
		if ( ! $final_url && ! $final_id ) {
			if ( ! empty( $book->cover_url ) ) {
				$final_url    = set_url_scheme( $book->cover_url, $scheme );
				$final_source = ! empty( $book->cover_source ) ? $book->cover_source : '';
			} elseif ( ! empty( $book->cover_attachment_id ) ) {
				$final_id   = (int) $book->cover_attachment_id;
				$attach_url = wp_get_attachment_url( $final_id );
				if ( $attach_url ) {
					$final_url = set_url_scheme( $attach_url, $scheme );
				}
			}
		}

		if ( $force_http && $final_url ) {
			$final_url = preg_replace( '#^https:#', 'http:', $final_url );
		}

		return array(
			'url'        => $final_url,
			'source'     => $final_source,
			'id'         => $final_id,
			'has_image'  => ( $final_id > 0 || '' !== $final_url ),
			'scheme'     => $scheme,
			'force_http' => $force_http,
		);
	}

	/**
	 * Normalize the different cover_reference formats used across add/upload flows.
	 */
	private static function parse_cover_reference( $raw ) {
		if ( method_exists( 'PRS_Cover_Upload_Feature', 'parse_cover_value' ) ) {
			return PRS_Cover_Upload_Feature::parse_cover_value( $raw );
		}

		$result = array(
			'attachment_id' => 0,
			'url'           => '',
			'source'        => '',
		);

		if ( is_array( $raw ) ) {
			$result['attachment_id'] = ! empty( $raw['attachment_id'] ) ? (int) $raw['attachment_id'] : 0;
			$result['url']           = ! empty( $raw['url'] ) ? esc_url_raw( (string) $raw['url'] ) : '';
			$result['source']        = ! empty( $raw['source'] ) ? sanitize_text_field( (string) $raw['source'] ) : '';
			if ( ! $result['url'] && ! empty( $raw['external_cover'] ) ) {
				$result['url'] = esc_url_raw( (string) $raw['external_cover'] );
			}
			return $result;
		}

		if ( ! is_string( $raw ) && ! is_numeric( $raw ) ) {
			return $result;
		}

		$trimmed = trim( (string) $raw );
		if ( '' === $trimmed ) {
			return $result;
		}

		$maybe_unserialized = maybe_unserialize( $trimmed );
		if ( is_array( $maybe_unserialized ) ) {
			return self::parse_cover_reference( $maybe_unserialized );
		}

		$decoded = json_decode( $trimmed, true );
		if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
			return self::parse_cover_reference( $decoded );
		}

		if ( preg_match( '/^\d+$/', $trimmed ) ) {
			$result['attachment_id'] = (int) $trimmed;
			return $result;
		}

		if ( filter_var( $trimmed, FILTER_VALIDATE_URL ) ) {
			$result['url']    = esc_url_raw( $trimmed );
			$result['source'] = sanitize_text_field( $trimmed );
			return $result;
		}

		$result['source'] = sanitize_text_field( $trimmed );
		return $result;
	}

	/**
	 * Enqueue styles and scripts.
	 */
	private static function enqueue_assets( $data ) {
		wp_enqueue_style( 'politeia-reading' );
		wp_enqueue_style( 'prs-book-single-v2', POLITEIA_READING_URL . 'assets/css/my-book-single-v2.css', array(), POLITEIA_READING_VERSION );
		wp_enqueue_style( 'prs-notes-feed', POLITEIA_READING_URL . 'assets/css/notes-feed.css', array(), POLITEIA_READING_VERSION );
		wp_enqueue_style( 'politeia-material-symbols', 'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200', array(), null );

		wp_enqueue_script( 'prs-book-single-v2', POLITEIA_READING_URL . 'assets/js/my-book-single-v2.js', array( 'jquery' ), POLITEIA_READING_VERSION, true );
		wp_enqueue_script( 'prs-notes-feed', POLITEIA_READING_URL . 'assets/js/notes-feed.js', array( 'jquery' ), POLITEIA_READING_VERSION, true );

		wp_localize_script(
			'prs-book-single-v2',
			'PRS_BOOK',
			array(
				'ajax_url'                => admin_url( 'admin-ajax.php' ),
				'nonce'                   => $data['nonces']['meta_update'],
				'reading_nonce'           => $data['nonces']['reading'],
				'owning_nonce'            => $data['nonces']['owning'],
				'user_book_id'            => (int) $data['ub']->id,
				'book_id'                 => (int) $data['book']->id,
				'owning_status'           => (string) $data['ub']->owning_status,
				'rating'                  => isset( $data['ub']->rating ) ? (int) $data['ub']->rating : 0,
				'type_book'               => (string) $data['ub']->type_book,
				'title'                   => (string) $data['book']->title,
				'authors'                 => (string) $data['book']->authors,
				'cover_url'               => $data['cover']['url'],
				'cover_nonce'             => $data['nonces']['cover_actions'],
				'user_id'                 => (int) $data['user_id'],
				'purchase_channel_labels' => array(
					'online' => __( 'Online', 'politeia-reading' ),
					'store'  => __( 'Store', 'politeia-reading' ),
				),
				'strings'                 => self::get_js_strings(),
			)
		);

		wp_localize_script( 'prs-notes-feed', 'PRS_NOTES_I18N', array(
			'emotion_joy'           => __( 'Joy', 'politeia-reading' ),
			'emotion_sorrow'        => __( 'Sorrow', 'politeia-reading' ),
			'emotion_fear'          => __( 'Fear', 'politeia-reading' ),
			'emotion_fascination'   => __( 'Fascination', 'politeia-reading' ),
			'emotion_anger'         => __( 'Anger', 'politeia-reading' ),
			'emotion_serenity'      => __( 'Serenity', 'politeia-reading' ),
			'emotion_enlightenment' => __( 'Enlightenment', 'politeia-reading' ),
			'logged_impression'     => __( 'Logged Impression', 'politeia-reading' ),
			'save_rating'           => __( 'Save Emotional Rating', 'politeia-reading' ),
		) );
	}

	/**
	 * Get translated strings for JS.
	 */
	private static function get_js_strings() {
		return array(
			'note_required'             => __( 'Please write a note before saving.', 'politeia-reading' ),
			'note_missing_details'      => __( 'Missing session details. Please try again.', 'politeia-reading' ),
			'note_unavailable'          => __( 'Unable to save the note right now. Please refresh the page and try again.', 'politeia-reading' ),
			'note_missing_nonce'        => __( 'Unable to save the note because the session security token is missing. Please refresh the page and try again.', 'politeia-reading' ),
			'note_saved'                => __( '✅ Note saved successfully!', 'politeia-reading' ),
			'note_save_failed_prefix'   => __( '⚠️ Failed to save note: %s', 'politeia-reading' ),
			'note_ajax_failed'          => __( '❌ AJAX request failed — check console.', 'politeia-reading' ),
			'note_missing_session_id'   => __( 'Unable to load this session note because the session identifier is missing.', 'politeia-reading' ),
			'unknown_error'             => __( 'Unknown error', 'politeia-reading' ),
			'press_enter_to_save'       => __( 'Press Enter to save', 'politeia-reading' ),
			'pages_error'               => __( 'Error saving pages.', 'politeia-reading' ),
			'pages_too_small'           => __( 'Please enter a number greater than zero.', 'politeia-reading' ),
			'pages_saved'               => __( 'Saved!', 'politeia-reading' ),
			'isbn_error'                => __( 'Error saving ISBN.', 'politeia-reading' ),
			'isbn_invalid'              => __( 'Invalid ISBN.', 'politeia-reading' ),
			'saved_short'               => __( 'Saved.', 'politeia-reading' ),
			'status_saving'             => __( 'Saving...', 'politeia-reading' ),
			'manual_invalid_pages'      => __( 'Page number cannot be less than the starting page.', 'politeia-reading' ),
			'manual_invalid_datetime'   => __( 'Please enter valid date & time values.', 'politeia-reading' ),
			'manual_invalid_time_range' => __( 'End date/time must be after start date/time.', 'politeia-reading' ),
			'manual_save_failed'        => __( 'Unable to save session. Please try again.', 'politeia-reading' ),
			'missing_contact'           => __( 'Please enter both name and email.', 'politeia-reading' ),
			'borrower_buying_title'     => __( 'Borrowed person is buying this book:', 'politeia-reading' ),
			'borrower_buying_confirm'   => __( 'Confirm that the borrower is purchasing or compensating for the book.', 'politeia-reading' ),
			'error_saving_contact'      => __( 'Error saving contact.', 'politeia-reading' ),
			'error_saving_date'         => __( 'Error saving date.', 'politeia-reading' ),
			'error_saving_channel'      => __( 'Error saving channel.', 'politeia-reading' ),
			'error_saving_rating'       => __( 'Error saving rating.', 'politeia-reading' ),
			'error_saving_format'       => __( 'Error saving format.', 'politeia-reading' ),
			'saved_successfully'        => __( 'Saved successfully.', 'politeia-reading' ),
			'disabled_lost'             => __( 'Disabled while this book is lost.', 'politeia-reading' ),
			'disabled_borrowed'         => __( 'Disabled while this book is being borrowed.', 'politeia-reading' ),
			'filter_all'                => __( 'All', 'politeia-reading' ),
			'selected_count'            => __( '%d selected', 'politeia-reading' ),
			'channel_online'            => __( 'Online', 'politeia-reading' ),
			'channel_store'             => __( 'Store', 'politeia-reading' ),
			'remove_book_confirm'       => __( 'Are you sure you want to remove this book from your library?', 'politeia-reading' ),
			'remove_book_removing'      => __( 'Removing...', 'politeia-reading' ),
			'remove_book_error'         => __( 'Error removing book.', 'politeia-reading' ),
			'images_from_google'        => __( 'Images from external sources', 'politeia-reading' ),
			'no_covers_found'           => __( 'No covers found.', 'politeia-reading' ),
			'cover_save_failed'         => __( 'Unable to save cover.', 'politeia-reading' ),
			'error_owning_status'       => __( 'Error updating owning status.', 'politeia-reading' ),
			'label_borrowing'           => __( 'Borrowing to:', 'politeia-reading' ),
			'label_borrowed'            => __( 'Borrowed from:', 'politeia-reading' ),
			'label_sold'                => __( 'Sold to:', 'politeia-reading' ),
			'label_lost'                => __( 'Last borrowed to:', 'politeia-reading' ),
			'label_sold_on'             => __( 'Sold on:', 'politeia-reading' ),
			'label_lost_date'           => __( 'Lost:', 'politeia-reading' ),
			'label_location'            => __( 'Location', 'politeia-reading' ),
			'label_in_shelf'            => __( 'In Shelf', 'politeia-reading' ),
			'label_not_in_shelf'        => __( 'Not In Shelf', 'politeia-reading' ),
			'label_unknown'             => __( 'Unknown', 'politeia-reading' ),
		);
	}

	private static function render_login_required() {
		prs_template_open();
		echo '<div class="wrap"><p>' . esc_html__( 'You must be logged in.', 'politeia-reading' ) . '</p></div>';
		prs_template_close();
	}

	private static function render_not_found() {
		status_header( 404 );
		prs_template_open();
		echo '<div class="wrap"><h1>' . esc_html__( 'Not found', 'politeia-reading' ) . '</h1></div>';
		prs_template_close();
	}

	private static function render_no_access() {
		status_header( 403 );
		prs_template_open();
		echo '<div class="wrap"><h1>' . esc_html__( 'No access', 'politeia-reading' ) . '</h1><p>' . esc_html__( 'This book is not in your library.', 'politeia-reading' ) . '</p></div>';
		prs_template_close();
	}
}
