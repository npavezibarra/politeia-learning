<?php
/**
 * User Books AJAX handlers (Core Controller)
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Politeia_Reading_User_Books {

	public static function init() {
		add_action( 'wp_ajax_prs_update_user_book', array( __CLASS__, 'ajax_update_user_book' ) );
		add_action( 'wp_ajax_prs_update_user_book_meta', array( 'Politeia_Reading_User_Books_Meta', 'ajax_update_user_book_meta' ) );
		add_action( 'wp_ajax_prs_update_pages', array( 'Politeia_Reading_User_Books_Meta', 'ajax_update_pages' ) );
		add_action( 'wp_ajax_prs_update_isbn', array( 'Politeia_Reading_User_Books_Meta', 'ajax_update_isbn' ) );
		add_action( 'wp_ajax_save_owning_contact', array( 'Politeia_Reading_User_Books_Loans', 'ajax_save_owning_contact' ) );
		add_action( 'wp_ajax_mark_as_returned', array( 'Politeia_Reading_User_Books_Loans', 'ajax_mark_as_returned' ) );
		add_action( 'wp_ajax_politeia_bookshelf_search_cover', array( 'Politeia_Reading_User_Books_Covers', 'ajax_search_cover' ) );
		add_action( 'wp_ajax_politeia_bookshelf_save_cover', array( 'Politeia_Reading_User_Books_Covers', 'ajax_save_cover' ) );
	}

	/**
	 * AJAX: update simple (reading_status / owning_status derivado)
	 */
	public static function ajax_update_user_book() {
		if ( ! is_user_logged_in() ) {
			Politeia_Reading_User_Books_Utils::json_error( 'auth', 401 );
		}
		if ( ! Politeia_Reading_User_Books_Utils::verify_nonce( 'prs_update_user_book', array( 'prs_update_user_book_nonce', 'nonce' ) ) ) {
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

		$update = array();

		// reading_status (opcional)
		if ( isset( $_POST['reading_status'] ) ) {
			$rs = sanitize_key( wp_unslash( $_POST['reading_status'] ) );
			if ( in_array( $rs, Politeia_Reading_User_Books_Utils::allowed_reading_status(), true ) ) {
				$update['reading_status'] = $rs;
			}
		}

		// owning_status (DERIVADO: vacío => volver a In Shelf)
		if ( array_key_exists( 'owning_status', $_POST ) ) {
			if ( 'd' === (string) $row->type_book ) {
				Politeia_Reading_User_Books_Utils::json_error( __( 'Owning status is available only for printed copies.', 'politeia-reading' ), 400 );
			}

			$raw        = wp_unslash( $_POST['owning_status'] );
			$sanitized  = is_string( $raw ) ? sanitize_key( $raw ) : '';
			$now        = current_time( 'mysql', true );
			$current    = Politeia_Loan_Manager::normalize_state( $row->owning_status );
			$requested  = '';

			if ( $raw === '' || null === $raw || 'in_shelf' === $sanitized ) {
				$requested = '';
			} else {
				$requested = $sanitized;
			}

			$next_state = Politeia_Loan_Manager::normalize_state( $requested );
			$validation = Politeia_Loan_Manager::validate_transition( $current, $next_state );

			if ( is_wp_error( $validation ) ) {
				Politeia_Reading_User_Books_Utils::json_error( $validation->get_error_message(), 400 );
			}

			$state_changed = ( $current !== $next_state );

			if ( Politeia_Loan_Manager::DEFAULT_STATE === $next_state ) {
				$update['owning_status']      = null;
				$update['counterparty_name']  = null;
				$update['counterparty_email'] = null;
				Politeia_Reading_User_Books_Loans::close_open_loan( (int) $row->user_id, (int) $row->book_id, $now );
				if ( $state_changed ) {
					Politeia_Loan_Manager::record_transition(
						(int) $row->user_id,
						(int) $row->book_id,
						$current,
						$next_state,
						array(
							'counterparty_name'  => null,
							'counterparty_email' => null,
						)
					);
				}
			} elseif ( in_array( $requested, Politeia_Reading_User_Books_Utils::allowed_owning_status(), true ) ) {
				$update['owning_status'] = $requested;

				if ( in_array( $requested, array( 'borrowed', 'borrowing' ), true ) ) {
					Politeia_Reading_User_Books_Loans::ensure_open_loan(
						(int) $row->user_id,
						(int) $row->book_id,
						array(
							'owning_status' => $next_state,
						),
						$now
					);
				} else {
					Politeia_Reading_User_Books_Loans::close_open_loan( (int) $row->user_id, (int) $row->book_id, $now );
				}

				if ( 'lost' === $requested ) {
					$update['counterparty_name']  = null;
					$update['counterparty_email'] = null;
				}

				if ( $state_changed ) {
					Politeia_Loan_Manager::record_transition(
						(int) $row->user_id,
						(int) $row->book_id,
						$current,
						$next_state,
						array(
							'counterparty_name'  => $update['counterparty_name'] ?? $row->counterparty_name,
							'counterparty_email' => $update['counterparty_email'] ?? $row->counterparty_email,
						)
					);
				}
			} else {
				Politeia_Reading_User_Books_Utils::json_error( __( 'Invalid owning status.', 'politeia-reading' ), 400 );
			}
		}

		if ( empty( $update ) ) {
			Politeia_Reading_User_Books_Utils::json_error( 'no_fields', 400 );
		}

		$updated = Politeia_Reading_User_Books_Utils::update_user_book( $user_book_id, $update );
		Politeia_Reading_User_Books_Utils::json_success( $updated );
	}
}

Politeia_Reading_User_Books::init();
