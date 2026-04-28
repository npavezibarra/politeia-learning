<?php
/**
 * Metadata Handlers for User Books
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Politeia_Reading_User_Books_Meta {

	/**
	 * AJAX: update meta granular (pages, purchase_*, contact, reading_status, rating)
	 */
	public static function ajax_update_user_book_meta() {
		if ( ! is_user_logged_in() ) {
			Politeia_Reading_User_Books_Utils::json_error( 'auth', 401 );
		}

		// Acepta cualquiera de los dos nonces
		if ( ! Politeia_Reading_User_Books_Utils::verify_nonce_multi(
			array(
				array(
					'action' => 'prs_update_user_book_meta',
					'keys'   => array( 'nonce' ),
				),
				array(
					'action' => 'prs_update_user_book',
					'keys'   => array( 'prs_update_user_book_nonce' ),
				),
			)
		) ) {
			Politeia_Reading_User_Books_Utils::json_error( 'bad_nonce', 403 );
		}

		$user_id      = get_current_user_id();
		$user_book_id = isset( $_POST['user_book_id'] ) ? absint( $_POST['user_book_id'] ) : 0;
		if ( ! $user_book_id ) {
			Politeia_Reading_User_Books_Utils::json_error( 'invalid_id', 400 );
		}

		$row = Politeia_Reading_User_Books_Utils::get_user_book_row( $user_book_id, $user_id );
		if ( ! $row ) {
			Politeia_Reading_User_Books_Utils::json_error( 'forbidden', 403 );
		}
		if ( empty( $row->book_id ) ) {
			Politeia_Reading_User_Books_Utils::json_error( 'missing_book_id', 500 );
		}

		$update = array();

		// ====== METADATOS ======
		if ( array_key_exists( 'pages', $_POST ) ) {
			$p               = absint( $_POST['pages'] );
			$update['pages'] = $p > 0 ? $p : null;
		}
		if ( array_key_exists( 'purchase_date', $_POST ) ) {
			$d                       = sanitize_text_field( wp_unslash( $_POST['purchase_date'] ) );
			$update['purchase_date'] = ( $d && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ) ? $d : null;
		}
		if ( array_key_exists( 'purchase_channel', $_POST ) ) {
			$pc                         = sanitize_key( $_POST['purchase_channel'] );
			$update['purchase_channel'] = in_array( $pc, array( 'online', 'store' ), true ) ? $pc : null;
		}
		if ( array_key_exists( 'purchase_place', $_POST ) ) {
			$update['purchase_place'] = sanitize_text_field( wp_unslash( $_POST['purchase_place'] ) );
		}
		if ( array_key_exists( 'type_book', $_POST ) ) {
			$raw = wp_unslash( $_POST['type_book'] );
			$tb  = sanitize_key( $raw );

			if ( in_array( $tb, array( 'p', 'd' ), true ) ) {
				$update['type_book'] = $tb;
			} elseif ( '' === $raw || null === $raw ) {
				$update['type_book'] = null;
			}
		}
		if ( array_key_exists( 'reading_status', $_POST ) ) {
			$rs = sanitize_key( wp_unslash( $_POST['reading_status'] ) );
			if ( in_array( $rs, Politeia_Reading_User_Books_Utils::allowed_reading_status(), true ) ) {
				$update['reading_status'] = $rs;
			}
		}

		// ====== RATING ======
		if ( array_key_exists( 'rating', $_POST ) ) {
			$r = is_numeric( $_POST['rating'] ) ? (int) $_POST['rating'] : null;
			if ( is_int( $r ) ) {
				if ( $r < 0 ) {
					$r = 0;
				}
				if ( $r > 5 ) {
					$r = 5;
				}
				$update['rating'] = $r;
			} else {
				$update['rating'] = null; // permitir limpiar
			}
		}

		// ====== CONTACTO ======
		$cp_name_raw  = array_key_exists( 'counterparty_name', $_POST ) ? wp_unslash( $_POST['counterparty_name'] ) : null;
		$cp_email_raw = array_key_exists( 'counterparty_email', $_POST ) ? wp_unslash( $_POST['counterparty_email'] ) : null;
		$cp_name      = isset( $cp_name_raw ) ? sanitize_text_field( $cp_name_raw ) : null;
		$cp_email     = isset( $cp_email_raw ) ? sanitize_email( $cp_email_raw ) : null;

		$both_empty           = ( '' === trim( (string) $cp_name ) ) && ( '' === trim( (string) $cp_email ) );
		$requires_contact_now = in_array( $row->owning_status, array( 'borrowed', 'borrowing', 'sold' ), true );

		if ( ( $both_empty ) && ( $requires_contact_now )
			&& ( array_key_exists( 'counterparty_name', $_POST ) || array_key_exists( 'counterparty_email', $_POST ) ) ) {
			Politeia_Reading_User_Books_Utils::json_error( 'contact_required', 400 );
		}

		if ( array_key_exists( 'counterparty_name', $_POST ) ) {
			$update['counterparty_name'] = $cp_name;
		}
		if ( array_key_exists( 'counterparty_email', $_POST ) ) {
			$update['counterparty_email'] = ( $cp_email && is_email( $cp_email ) ) ? $cp_email : null;
		}

		// ====== FECHA EFECTIVA (UTC) ======
		$effective_at = null;
		if ( ! empty( $_POST['owning_effective_date'] ) ) {
			$raw = sanitize_text_field( wp_unslash( $_POST['owning_effective_date'] ) );
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ) {
				$effective_at = $raw . ' ' . gmdate( 'H:i:s' );
			}
		}
		if ( ! $effective_at ) {
			$effective_at = current_time( 'mysql', true );
		}

		// ====== OWNING STATUS (DERIVADO) ======
		if ( array_key_exists( 'owning_status', $_POST ) ) {
			if ( 'd' === (string) $row->type_book ) {
				Politeia_Reading_User_Books_Utils::json_error( __( 'Owning status is available only for printed copies.', 'politeia-reading' ), 400 );
			}

			$raw             = wp_unslash( $_POST['owning_status'] );
			$sanitized_state = is_string( $raw ) ? sanitize_key( $raw ) : '';
			$current_state   = Politeia_Loan_Manager::normalize_state( $row->owning_status );
			$requested_state = '';

			if ( '' === $raw || null === $raw || 'in_shelf' === $sanitized_state ) {
				$requested_state = '';
			} else {
				$requested_state = $sanitized_state;
			}

			$next_state = Politeia_Loan_Manager::normalize_state( $requested_state );
			if ( '' !== $requested_state && ! in_array( $requested_state, Politeia_Reading_User_Books_Utils::allowed_owning_status(), true ) && 'in_shelf' !== $requested_state ) {
				Politeia_Reading_User_Books_Utils::json_error( __( 'Invalid owning status.', 'politeia-reading' ), 400 );
			}

			$validation = Politeia_Loan_Manager::validate_transition( $current_state, $next_state );
			if ( is_wp_error( $validation ) ) {
				Politeia_Reading_User_Books_Utils::json_error( $validation->get_error_message(), 400 );
			}

			$state_changed = ( $current_state !== $next_state );

			if ( Politeia_Loan_Manager::DEFAULT_STATE === $next_state ) {
				$update['owning_status']      = null;
				$update['counterparty_name']  = null;
				$update['counterparty_email'] = null;
				Politeia_Reading_User_Books_Loans::close_open_loan( (int) $row->user_id, (int) $row->book_id, $effective_at );

				if ( $state_changed ) {
					Politeia_Loan_Manager::record_transition(
						(int) $row->user_id,
						(int) $row->book_id,
						$current_state,
						$next_state,
						array(
							'counterparty_name'  => null,
							'counterparty_email' => null,
						)
					);
				}
			} else {
				$update['owning_status'] = $requested_state;

				if ( in_array( $next_state, array( 'borrowed', 'borrowing' ), true ) ) {
					Politeia_Reading_User_Books_Loans::ensure_open_loan(
						(int) $row->user_id,
						(int) $row->book_id,
						array(
							'counterparty_name'  => $cp_name,
							'counterparty_email' => ( $cp_email && is_email( $cp_email ) ) ? $cp_email : null,
							'owning_status'      => $next_state,
						),
						$effective_at
					);
				} else {
					Politeia_Reading_User_Books_Loans::close_open_loan( (int) $row->user_id, (int) $row->book_id, $effective_at );
				}

				if ( 'lost' === $next_state ) {
					$update['counterparty_name']  = null;
					$update['counterparty_email'] = null;
				}

				if ( $state_changed ) {
					Politeia_Loan_Manager::record_transition(
						(int) $row->user_id,
						(int) $row->book_id,
						$current_state,
						$next_state,
						array(
							'counterparty_name'  => $update['counterparty_name'] ?? ( $cp_name ?: null ),
							'counterparty_email' => $update['counterparty_email'] ?? ( ( $cp_email && is_email( $cp_email ) ) ? $cp_email : null ),
						)
					);
				}
			}
		} else {
			// No cambió owning_status: si llega contacto y el estado actual requiere,
			// actualiza el loan abierto (no crear uno nuevo si no corresponde)
			if ( ( $cp_name || $cp_email ) && in_array( $row->owning_status, array( 'borrowed', 'borrowing' ), true ) ) {
				Politeia_Reading_User_Books_Loans::ensure_open_loan(
					(int) $row->user_id,
					(int) $row->book_id,
					array(
						'counterparty_name'  => $cp_name,
						'counterparty_email' => ( $cp_email && is_email( $cp_email ) ) ? $cp_email : null,
						'owning_status'      => Politeia_Loan_Manager::normalize_state( $row->owning_status ),
					),
					$effective_at
				);
			}
		}

		if ( empty( $update ) ) {
			Politeia_Reading_User_Books_Utils::json_error( 'no_fields', 400 );
		}

		$updated = Politeia_Reading_User_Books_Utils::update_user_book( $user_book_id, $update );
		Politeia_Reading_User_Books_Utils::json_success( $updated );
	}

	/**
	 * AJAX: inline update for pages field.
	 */
	public static function ajax_update_pages() {
		if ( ! is_user_logged_in() ) {
			Politeia_Reading_User_Books_Utils::json_error( 'auth', 401 );
		}

		if ( ! Politeia_Reading_User_Books_Utils::verify_nonce_multi(
			array(
				array(
					'action' => 'prs_update_user_book_meta',
					'keys'   => array( 'nonce' ),
				),
				array(
					'action' => 'prs_update_user_book',
					'keys'   => array( 'prs_update_user_book_nonce' ),
				),
			)
		) ) {
			Politeia_Reading_User_Books_Utils::json_error( 'bad_nonce', 403 );
		}

		$user_book_id = isset( $_POST['book_id'] ) ? absint( $_POST['book_id'] ) : 0;
		$pages        = isset( $_POST['pages'] ) ? absint( $_POST['pages'] ) : 0;

		if ( ! $user_book_id || $pages < 1 ) {
			Politeia_Reading_User_Books_Utils::json_error( __( 'Invalid data', 'politeia-reading' ), 400 );
		}

		$row = Politeia_Reading_User_Books_Utils::get_user_book_row( $user_book_id, get_current_user_id() );
		if ( ! $row ) {
			Politeia_Reading_User_Books_Utils::json_error( 'forbidden', 403 );
		}

		Politeia_Reading_User_Books_Utils::update_user_book(
			$user_book_id,
			array(
				'pages' => $pages,
			)
		);

		Politeia_Reading_User_Books_Utils::json_success(
			array(
				'pages' => $pages,
			)
		);
	}

	/**
	 * AJAX: inline update for ISBN field.
	 */
	public static function ajax_update_isbn() {
		if ( ! is_user_logged_in() ) {
			Politeia_Reading_User_Books_Utils::json_error( 'auth', 401 );
		}

		if ( ! Politeia_Reading_User_Books_Utils::verify_nonce_multi(
			array(
				array(
					'action' => 'prs_update_user_book_meta',
					'keys'   => array( 'nonce' ),
				),
				array(
					'action' => 'prs_update_user_book',
					'keys'   => array( 'prs_update_user_book_nonce' ),
				),
			)
		) ) {
			Politeia_Reading_User_Books_Utils::json_error( __( 'Error saving ISBN.', 'politeia-reading' ), 403 );
		}

		$user_book_id = isset( $_POST['user_book_id'] ) ? absint( $_POST['user_book_id'] ) : 0;
		$raw_isbn     = isset( $_POST['isbn'] ) ? sanitize_text_field( wp_unslash( $_POST['isbn'] ) ) : '';

		if ( ! $user_book_id ) {
			Politeia_Reading_User_Books_Utils::json_error( __( 'Error saving ISBN.', 'politeia-reading' ), 400 );
		}

		$row = Politeia_Reading_User_Books_Utils::get_user_book_row( $user_book_id, get_current_user_id() );
		if ( ! $row ) {
			Politeia_Reading_User_Books_Utils::json_error( 'forbidden', 403 );
		}

		if ( ! function_exists( 'prs_books_has_isbn_column' ) || ! prs_books_has_isbn_column() ) {
			Politeia_Reading_User_Books_Utils::json_error( __( 'Error saving ISBN.', 'politeia-reading' ), 400 );
		}

		$normalized = prs_normalize_isbn( $raw_isbn );
		if ( '' !== $raw_isbn && '' === $normalized ) {
			Politeia_Reading_User_Books_Utils::json_error( __( 'Invalid ISBN.', 'politeia-reading' ), 400 );
		}
		if ( '' !== $normalized && ! in_array( strlen( $normalized ), array( 10, 13 ), true ) ) {
			Politeia_Reading_User_Books_Utils::json_error( __( 'Invalid ISBN.', 'politeia-reading' ), 400 );
		}

		global $wpdb;
		$books_table = $wpdb->prefix . 'politeia_books';

		if ( '' === $normalized ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$books_table} SET isbn = NULL WHERE id = %d",
					(int) $row->book_id
				)
			);
		} else {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$books_table} SET isbn = %s WHERE id = %d",
					$normalized,
					(int) $row->book_id
				)
			);
		}

		Politeia_Reading_User_Books_Utils::json_success(
			array(
				'isbn' => $normalized,
			)
		);
	}
}
