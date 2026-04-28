<?php
/**
 * Loan Handlers for User Books
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Politeia_Reading_User_Books_Loans {

	/**
	 * AJAX: guarda contacto + owning_status desde overlay.
	 */
	public static function ajax_save_owning_contact() {
		if ( ! is_user_logged_in() ) {
			Politeia_Reading_User_Books_Utils::json_error( __( 'You must be logged in.', 'politeia-reading' ), 401 );
		}

		if ( ! Politeia_Reading_User_Books_Utils::verify_nonce( 'save_owning_contact', array( 'nonce' ) ) ) {
			Politeia_Reading_User_Books_Utils::json_error( __( 'Security check failed.', 'politeia-reading' ), 403 );
		}

		$user_id      = get_current_user_id();
		$book_id      = isset( $_POST['book_id'] ) ? absint( $_POST['book_id'] ) : 0;
		$user_book_id = isset( $_POST['user_book_id'] ) ? absint( $_POST['user_book_id'] ) : 0;

		if ( ! $book_id || ! $user_book_id ) {
			Politeia_Reading_User_Books_Utils::json_error( __( 'Invalid book.', 'politeia-reading' ), 400 );
		}

		$row = Politeia_Reading_User_Books_Utils::get_user_book_row( $user_book_id, $user_id );
		if ( ! $row || (int) $row->book_id !== $book_id ) {
			Politeia_Reading_User_Books_Utils::json_error( __( 'Book not found in your library.', 'politeia-reading' ), 403 );
		}

		if ( 'd' === (string) $row->type_book ) {
			Politeia_Reading_User_Books_Utils::json_error( __( 'Owning status is available only for printed copies.', 'politeia-reading' ), 400 );
		}

		$status_raw = isset( $_POST['owning_status'] ) ? wp_unslash( $_POST['owning_status'] ) : '';
		$status_key = is_string( $status_raw ) ? sanitize_key( $status_raw ) : '';
		$is_reacquire = ( 'bought' === $status_key );
		$transaction_raw = isset( $_POST['transaction_type'] ) ? wp_unslash( $_POST['transaction_type'] ) : '';
		$transaction_type = $transaction_raw ? sanitize_key( $transaction_raw ) : '';

		$current_state = Politeia_Loan_Manager::normalize_state( $row->owning_status );
		$requested_status = '';
		if ( '' === $status_raw || null === $status_raw || 'in_shelf' === $status_key || $is_reacquire ) {
			$requested_status = '';
		} else {
			$requested_status = $status_key;
		}

		$next_state = $is_reacquire
			? Politeia_Loan_Manager::DEFAULT_STATE
			: Politeia_Loan_Manager::normalize_state( $requested_status );
		$validation = Politeia_Loan_Manager::validate_transition(
			$current_state,
			$next_state,
			array(
				'transaction_type' => $transaction_type,
				'requested_state'  => $status_key,
			)
		);

		if ( is_wp_error( $validation ) ) {
			Politeia_Reading_User_Books_Utils::json_error( $validation->get_error_message(), 400 );
		}

		if ( '' !== $requested_status && ! in_array( $requested_status, Politeia_Reading_User_Books_Utils::allowed_owning_status(), true ) && 'in_shelf' !== $requested_status ) {
			Politeia_Reading_User_Books_Utils::json_error( __( 'Invalid owning status.', 'politeia-reading' ), 400 );
		}

		$name_raw        = isset( $_POST['contact_name'] ) ? wp_unslash( $_POST['contact_name'] ) : '';
		$email_raw       = isset( $_POST['contact_email'] ) ? wp_unslash( $_POST['contact_email'] ) : '';
		$name_sanitized  = sanitize_text_field( $name_raw );
		$name_trimmed    = trim( $name_sanitized );
		$email_sanitized = sanitize_email( $email_raw );
		$email_trimmed   = $email_sanitized ? $email_sanitized : '';

		$amount_raw = isset( $_POST['amount'] ) ? wp_unslash( $_POST['amount'] ) : '';
		$amount_value = null;
		if ( '' !== $amount_raw && null !== $amount_raw ) {
			if ( is_string( $amount_raw ) ) {
				$normalized_amount = str_replace( ',', '.', trim( $amount_raw ) );
			} else {
				$normalized_amount = $amount_raw;
			}

			if ( is_numeric( $normalized_amount ) ) {
				$amount_value = round( (float) $normalized_amount, 2 );
			}
		}

		$requires_contact = in_array( $next_state, array( 'borrowed', 'borrowing', 'sold' ), true );

		if ( $requires_contact && ( '' === $name_trimmed || '' === $email_trimmed ) ) {
			Politeia_Reading_User_Books_Utils::json_error( __( 'Please enter both name and email.', 'politeia-reading' ), 400 );
		}

		$update        = array();
		$now           = current_time( 'mysql', true );
		$safe_name     = '' === $name_trimmed ? null : $name_trimmed;
		$safe_email    = '' === $email_trimmed ? null : $email_trimmed;
		$state_changed = ( $current_state !== $next_state );

		if ( Politeia_Loan_Manager::DEFAULT_STATE === $next_state ) {
			$update['owning_status']      = null;
			$update['counterparty_name']  = null;
			$update['counterparty_email'] = null;
			self::close_open_loan( (int) $row->user_id, (int) $row->book_id, $now );
		} else {
			$update['owning_status']      = $requested_status;
			$update['counterparty_name']  = $safe_name;
			$update['counterparty_email'] = $safe_email;

			if ( in_array( $next_state, array( 'borrowed', 'borrowing' ), true ) ) {
				self::ensure_open_loan(
					(int) $row->user_id,
					(int) $row->book_id,
					array(
						'counterparty_name'  => $safe_name,
						'counterparty_email' => $safe_email,
						'owning_status'      => $next_state,
						'transaction_type'   => $transaction_type,
					),
					$now
				);
			} else {
				self::close_open_loan( (int) $row->user_id, (int) $row->book_id, $now );
			}

			if ( 'lost' === $next_state ) {
				$update['counterparty_name']  = null;
				$update['counterparty_email'] = null;
			}
		}

		Politeia_Reading_User_Books_Utils::update_user_book( (int) $row->id, $update );

		if ( $state_changed ) {
			Politeia_Loan_Manager::record_transition(
				(int) $row->user_id,
				(int) $row->book_id,
				$current_state,
				$next_state,
				array(
					'counterparty_name'  => $update['counterparty_name'] ?? $safe_name,
					'counterparty_email' => $update['counterparty_email'] ?? $safe_email,
					'transaction_type'   => $transaction_type,
					'amount'             => ( 'sold' === $next_state ) ? $amount_value : null,
				)
			);
		}

		Politeia_Reading_User_Books_Utils::json_success(
			array(
				'message'            => __( 'Contact saved', 'politeia-reading' ),
				'owning_status'      => Politeia_Loan_Manager::DEFAULT_STATE === $next_state ? '' : $requested_status,
				'counterparty_name'  => $name_trimmed,
				'counterparty_email' => $email_trimmed,
			)
		);
	}

	/**
	 * AJAX: marca un libro prestado como devuelto.
	 */
	public static function ajax_mark_as_returned() {
		if ( ! is_user_logged_in() ) {
			Politeia_Reading_User_Books_Utils::json_error( __( 'You must be logged in.', 'politeia-reading' ), 401 );
		}

		if ( ! Politeia_Reading_User_Books_Utils::verify_nonce( 'save_owning_contact', array( 'nonce' ) ) ) {
			Politeia_Reading_User_Books_Utils::json_error( __( 'Security check failed.', 'politeia-reading' ), 403 );
		}

		$user_id      = get_current_user_id();
		$book_id      = isset( $_POST['book_id'] ) ? absint( $_POST['book_id'] ) : 0;
		$user_book_id = isset( $_POST['user_book_id'] ) ? absint( $_POST['user_book_id'] ) : 0;

		if ( ! $book_id ) {
			Politeia_Reading_User_Books_Utils::json_error( __( 'Invalid book.', 'politeia-reading' ), 400 );
		}

		if ( $user_book_id ) {
			$row = Politeia_Reading_User_Books_Utils::get_user_book_row( $user_book_id, $user_id );
		} else {
			$row = Politeia_Reading_User_Books_Utils::get_user_book_by_book( $user_id, $book_id );
		}

		if ( ! $row || (int) $row->book_id !== $book_id ) {
			Politeia_Reading_User_Books_Utils::json_error( __( 'Book not found in your library.', 'politeia-reading' ), 403 );
		}

		if ( 'd' === (string) $row->type_book ) {
			Politeia_Reading_User_Books_Utils::json_error( __( 'Owning status is available only for printed copies.', 'politeia-reading' ), 400 );
		}

		$current_state = Politeia_Loan_Manager::normalize_state( $row->owning_status );
		$validation    = Politeia_Loan_Manager::validate_transition( $current_state, Politeia_Loan_Manager::DEFAULT_STATE );
		if ( is_wp_error( $validation ) ) {
			Politeia_Reading_User_Books_Utils::json_error( $validation->get_error_message(), 400 );
		}

		global $wpdb;
		$table = Politeia_Reading_User_Books_Utils::loans_table();
		$loan  = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT counterparty_name, counterparty_email FROM {$table} WHERE user_id=%d AND book_id=%d AND notes LIKE %s ORDER BY id DESC LIMIT 1",
				(int) $row->user_id,
				$book_id,
				'%"state":"borrowing"%'
			)
		);

		$counterparty_name  = $loan && ! empty( $loan->counterparty_name ) ? $loan->counterparty_name : $row->counterparty_name;
		$counterparty_email = $loan && ! empty( $loan->counterparty_email ) ? $loan->counterparty_email : $row->counterparty_email;

		$now_gmt = current_time( 'mysql', true );

		Politeia_Reading_User_Books_Utils::update_user_book(
			(int) $row->id,
			array(
				'owning_status'      => null,
				'counterparty_name'  => null,
				'counterparty_email' => null,
			)
		);

		self::close_open_loan( (int) $row->user_id, (int) $row->book_id, $now_gmt, 'returned' );

		Politeia_Loan_Manager::record_transition(
			(int) $row->user_id,
			(int) $row->book_id,
			$current_state,
			Politeia_Loan_Manager::DEFAULT_STATE,
			array(
				'counterparty_name'  => $counterparty_name ? $counterparty_name : null,
				'counterparty_email' => $counterparty_email ? $counterparty_email : null,
			)
		);

		Politeia_Reading_User_Books_Utils::json_success(
			array(
				'message'       => __( 'Book marked as returned.', 'politeia-reading' ),
				'owning_status' => '',
				'loan_closed'   => get_date_from_gmt( $now_gmt, 'Y-m-d' ),
			)
		);
	}

	/**
	 * Asegura un único loan abierto por (user, book):
	 * - Si existe, actualiza (contacto/updated_at).
	 * - Si no existe y hay contacto, inserta con start_date = $start_gmt.
	 *   (Si NO hay contacto, no crea nada).
	 */
	public static function ensure_open_loan( $user_id, $book_id, $data = array(), $start_gmt = null ) {
		global $wpdb;
		$t   = Politeia_Reading_User_Books_Utils::loans_table();
		$now = current_time( 'mysql', true );

		$state            = isset( $data['owning_status'] ) ? Politeia_Loan_Manager::normalize_state( $data['owning_status'] ) : '';
		$transaction_type = isset( $data['transaction_type'] ) ? sanitize_key( $data['transaction_type'] ) : '';
		unset( $data['owning_status'], $data['transaction_type'] );

		$notes = null;
		if ( $state && Politeia_Loan_Manager::DEFAULT_STATE !== $state ) {
			$payload = array( 'state' => $state );
			if ( $transaction_type ) {
				$payload['transaction_type'] = $transaction_type;
			}
			$notes = wp_json_encode( $payload );
		}

		$open_id = Politeia_Reading_User_Books_Utils::get_active_loan_id( $user_id, $book_id );
		if ( $open_id ) {
			$row = array( 'updated_at' => $now );
			if ( array_key_exists( 'counterparty_name', $data ) ) {
				$row['counterparty_name'] = $data['counterparty_name'];
			}
			if ( array_key_exists( 'counterparty_email', $data ) ) {
				$row['counterparty_email'] = $data['counterparty_email'];
			}
			if ( null !== $notes ) {
				$row['notes'] = $notes;
			}
			$wpdb->update( $t, $row, array( 'id' => $open_id ) );
			return $open_id;
		}

		// Si NO hay contacto, NO insertes un loan vacío
		$has_contact = ! empty( $data['counterparty_name'] ) || ! empty( $data['counterparty_email'] );
		if ( ! $has_contact ) {
			return 0;
		}

		// Insertar nuevo
		$start = $start_gmt ?: $now;
		$wpdb->insert(
			$t,
			array(
				'user_id'            => (int) $user_id,
				'book_id'            => (int) $book_id,
				'counterparty_name'  => $data['counterparty_name'] ?? null,
				'counterparty_email' => $data['counterparty_email'] ?? null,
				'start_date'         => $start,
				'end_date'           => null,
				'notes'              => $notes,
				'created_at'         => $now,
				'updated_at'         => $now,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	/** Cierra cualquier loan abierto del par (user, book). */
	public static function close_open_loan( $user_id, $book_id, $end_gmt, $status = null ) {
		global $wpdb;
		$t   = Politeia_Reading_User_Books_Utils::loans_table();
		$now = current_time( 'mysql', true );
		if ( $status ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$t}
					 SET status=%s, end_date=%s, updated_at=%s
					 WHERE user_id=%d AND book_id=%d AND end_date IS NULL AND deleted_at IS NULL",
					$status,
					$end_gmt,
					$now,
					$user_id,
					$book_id
				)
			);
		} else {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$t}
					 SET end_date=%s, updated_at=%s
					 WHERE user_id=%d AND book_id=%d AND end_date IS NULL AND deleted_at IS NULL",
					$end_gmt,
					$now,
					$user_id,
					$book_id
				)
			);
		}
	}
}
