<?php
/**
 * Core Handler for Reading Sessions (Start/Save logic)
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Politeia_Reading_Sessions_Handler {

	public static function ajax_start() {
		if ( ! is_user_logged_in() ) {
			Politeia_Reading_Sessions_Utils::json_error( 'auth', 401 );
		}
		if ( ! Politeia_Reading_Sessions_Utils::verify_nonce( 'prs_reading_nonce', array( 'nonce' ) ) ) {
			Politeia_Reading_Sessions_Utils::json_error( 'bad_nonce', 403 );
		}

		$user_id = get_current_user_id();
		$book_id = isset( $_POST['book_id'] ) ? absint( $_POST['book_id'] ) : 0;
		if ( ! $book_id ) {
			Politeia_Reading_Sessions_Utils::json_error( 'invalid_book', 400 );
		}

		$ub_row = Politeia_Reading_Sessions_Utils::get_user_book_row( $user_id, $book_id );
		if ( ! $ub_row ) {
			Politeia_Reading_Sessions_Utils::json_error( 'forbidden', 403 );
		}

		// Debe tener pages definidos
		$total_pages = (int) ( $ub_row->pages ?? 0 );
		if ( $total_pages <= 0 ) {
			Politeia_Reading_Sessions_Utils::json_error( 'pages_required', 400 );
		}

		// Bloqueo por estado de posesión
		$owning_status = (string) ( $ub_row->owning_status ?? 'in_shelf' );
		if ( Politeia_Reading_Sessions_Utils::blocked_by_status( $owning_status ) ) {
			Politeia_Reading_Sessions_Utils::json_error( 'not_in_possession', 403 );
		}

		$start_page = isset( $_POST['start_page'] ) ? absint( $_POST['start_page'] ) : 0;
		if ( $start_page < 1 ) {
			Politeia_Reading_Sessions_Utils::json_error( 'invalid_start_page', 400 );
		}

		$chapter = isset( $_POST['chapter_name'] ) ? sanitize_text_field( wp_unslash( $_POST['chapter_name'] ) ) : '';
		if ( strlen( $chapter ) > 255 ) {
			$chapter = substr( $chapter, 0, 255 );
		}

		global $wpdb;
		$t = $wpdb->prefix . 'politeia_reading_sessions';

		Politeia_Reading_Sessions_Recorder::cleanup_active_sessions();

		$existing = Politeia_Reading_Sessions_Recorder::find_active_session_id( $user_id, $book_id );
		if ( $existing > 0 ) {
			$meta       = Politeia_Reading_Sessions_Recorder::get_active_session_meta( $existing );
			$started_at = $meta && ! empty( $meta['started_at_gmt'] ) ? (string) $meta['started_at_gmt'] : '';
			if ( ! $started_at ) {
				$row = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT start_time FROM {$t} WHERE id=%d AND user_id=%d AND deleted_at IS NULL LIMIT 1",
						$existing,
						$user_id
					)
				);
				if ( $row && ! empty( $row->start_time ) ) {
					$started_at = (string) $row->start_time;
				}
			}
			if ( $started_at ) {
				// Refresh transient TTL for the active session.
				set_transient(
					Politeia_Reading_Sessions_Recorder::ACTIVE_SESSION_TRANSIENT_PREFIX . $existing,
					array(
						'session_id'     => (int) $existing,
						'user_id'        => (int) $user_id,
						'book_id'        => (int) $book_id,
						'user_book_id'   => (int) $ub_row->id,
						'started_at_gmt' => (string) $started_at,
					),
					3 * HOUR_IN_SECONDS
				);

				Politeia_Reading_Sessions_Utils::json_success(
					array(
						'session_id' => (int) $existing,
						'started_at' => $started_at,
						'reused'     => 1,
					)
				);
			}
		}

		$now_gmt = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp', true ) ); // GMT
		// Guardamos placeholder (end_time=end_time=start_time) por restricción NOT NULL
		$ins = array(
			'user_id'      => (int) $user_id,
			'user_book_id' => (int) $ub_row->id,
			'start_time'   => $now_gmt,
			'end_time'     => $now_gmt,
			'start_page'   => max( 1, min( $start_page, $total_pages ) ),
			'end_page'     => max( 1, min( $start_page, $total_pages ) ),
			'chapter_name' => $chapter ?: null,
		);

		$formats = array( '%d', '%d', '%s', '%s', '%d', '%d', '%s' );
		if ( Politeia_Reading_Sessions_Utils::table_has_columns( 'politeia_reading_sessions', array( 'insert_type' ) ) ) {
			$ins['insert_type'] = 'recorder';
			$formats[]          = '%s';
		}

		$ok = $wpdb->insert( $t, $ins, $formats );
		if ( ! $ok ) {
			Politeia_Reading_Sessions_Utils::json_error( 'db_insert_failed', 500 );
		}

		$session_id = (int) $wpdb->insert_id;

		Politeia_Reading_Sessions_Recorder::register_active_session(
			$session_id,
			(int) $user_id,
			(int) $book_id,
			(int) $ub_row->id,
			$now_gmt
		);

		Politeia_Reading_Sessions_Utils::json_success(
			array(
				'session_id' => $session_id,
				'started_at' => $now_gmt,
				'reused'     => 0,
			)
		);
	}

	public static function ajax_save() {
		if ( ! is_user_logged_in() ) {
			Politeia_Reading_Sessions_Utils::json_error( 'auth', 401 );
		}
		if ( ! Politeia_Reading_Sessions_Utils::verify_nonce( 'prs_reading_nonce', array( 'nonce' ) ) ) {
			Politeia_Reading_Sessions_Utils::json_error( 'bad_nonce', 403 );
		}

		$data = self::save_session_common( 'recorder', true );
		Politeia_Reading_Sessions_Utils::json_success( $data );
	}

	public static function ajax_add_manual_session() {
		if ( ! is_user_logged_in() ) {
			Politeia_Reading_Sessions_Utils::json_error( 'auth', 401 );
		}
		if ( ! Politeia_Reading_Sessions_Utils::verify_nonce( 'prs_reading_nonce', array( 'nonce' ) ) ) {
			Politeia_Reading_Sessions_Utils::json_error( 'bad_nonce', 403 );
		}

		$data = self::save_session_common( 'manual', false );
		Politeia_Reading_Sessions_Utils::json_success( $data );
	}

	/**
	 * Shared implementation for saving a session.
	 */
	public static function save_session_common( $insert_type, $allow_update_existing ) {
		$user_id = get_current_user_id();
		$book_id = isset( $_POST['book_id'] ) ? absint( $_POST['book_id'] ) : 0;
		if ( ! $book_id ) {
			Politeia_Reading_Sessions_Utils::json_error( 'invalid_book', 400 );
		}

		$ub_row = Politeia_Reading_Sessions_Utils::get_user_book_row( $user_id, $book_id );
		if ( ! $ub_row ) {
			Politeia_Reading_Sessions_Utils::json_error( 'forbidden', 403 );
		}

		$total_pages = (int) ( $ub_row->pages ?? 0 );
		if ( $total_pages <= 0 ) {
			Politeia_Reading_Sessions_Utils::json_error( 'pages_required', 400 );
		}

		$owning_status = (string) ( $ub_row->owning_status ?? 'in_shelf' );
		if ( Politeia_Reading_Sessions_Utils::blocked_by_status( $owning_status ) ) {
			Politeia_Reading_Sessions_Utils::json_error( 'not_in_possession', 403 );
		}

		$start_page = isset( $_POST['start_page'] ) ? absint( $_POST['start_page'] ) : 0;
		$end_page   = isset( $_POST['end_page'] ) ? absint( $_POST['end_page'] ) : 0;

		// clamp a [1..pages]
		$start_page = max( 1, min( $start_page, $total_pages ) );
		$end_page   = max( 1, min( $end_page, $total_pages ) );

		if ( $start_page < 1 || $end_page < 1 || $end_page < $start_page ) {
			Politeia_Reading_Sessions_Utils::json_error( 'invalid_pages', 400 );
		}

		$chapter = isset( $_POST['chapter_name'] ) ? sanitize_text_field( wp_unslash( $_POST['chapter_name'] ) ) : '';
		if ( strlen( $chapter ) > 255 ) {
			$chapter = substr( $chapter, 0, 255 );
		}

		$duration_sec = isset( $_POST['duration_sec'] ) ? absint( $_POST['duration_sec'] ) : 0;

		global $wpdb;
		$t = $wpdb->prefix . 'politeia_reading_sessions';

		$start_gmt = '';
		$end_gmt   = '';
		if ( $insert_type === 'manual' ) {
			$raw_start = isset( $_POST['start_datetime'] ) ? sanitize_text_field( wp_unslash( $_POST['start_datetime'] ) ) : '';
			$raw_end   = isset( $_POST['end_datetime'] ) ? sanitize_text_field( wp_unslash( $_POST['end_datetime'] ) ) : '';
			$raw_start = trim( (string) $raw_start );
			$raw_end   = trim( (string) $raw_end );
			if ( $raw_start === '' || $raw_end === '' ) {
				Politeia_Reading_Sessions_Utils::json_error( 'invalid_datetime', 400 );
			}

			$tz          = wp_timezone();
			$start_local = \DateTimeImmutable::createFromFormat( 'Y-m-d\\TH:i', $raw_start, $tz );
			if ( ! $start_local ) {
				$start_local = \DateTimeImmutable::createFromFormat( 'Y-m-d\\TH:i:s', $raw_start, $tz );
			}
			$end_local = \DateTimeImmutable::createFromFormat( 'Y-m-d\\TH:i', $raw_end, $tz );
			if ( ! $end_local ) {
				$end_local = \DateTimeImmutable::createFromFormat( 'Y-m-d\\TH:i:s', $raw_end, $tz );
			}
			if ( ! $start_local || ! $end_local ) {
				Politeia_Reading_Sessions_Utils::json_error( 'invalid_datetime', 400 );
			}

			$start_utc = $start_local->setTimezone( new \DateTimeZone( 'UTC' ) );
			$end_utc   = $end_local->setTimezone( new \DateTimeZone( 'UTC' ) );

			if ( $end_utc->getTimestamp() < $start_utc->getTimestamp() ) {
				Politeia_Reading_Sessions_Utils::json_error( 'invalid_time_range', 400 );
			}

			$start_gmt = $start_utc->format( 'Y-m-d H:i:s' );
			$end_gmt   = $end_utc->format( 'Y-m-d H:i:s' );
		} else {
			$end_gmt  = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp', true ) ); // end_time GMT
			$end_dt   = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $end_gmt, new \DateTimeZone( 'UTC' ) );
			$end_ts   = $end_dt ? $end_dt->getTimestamp() : time();
			$start_ts = $duration_sec > 0 ? max( 0, $end_ts - $duration_sec ) : $end_ts;
			$start_gmt = gmdate( 'Y-m-d H:i:s', $start_ts );
		}

		$session_id = 0;
		if ( $allow_update_existing ) {
			$session_id = isset( $_POST['session_id'] ) ? absint( $_POST['session_id'] ) : 0;
		}
		if ( $allow_update_existing && $session_id > 0 ) {
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id,user_id,user_book_id,start_time,end_time,insert_type FROM {$t} WHERE id=%d AND deleted_at IS NULL LIMIT 1",
					$session_id
				)
			);
			if ( $row && (int) $row->user_id === $user_id && (int) $row->user_book_id === (int) $ub_row->id ) {
				$is_active         = Politeia_Reading_Sessions_Recorder::is_active_session( $session_id );
				$is_automatic_stop = isset( $row->insert_type ) && (string) $row->insert_type === 'automatic_stop';
				$is_placeholder    = ! empty( $row->start_time ) && ! empty( $row->end_time ) && (string) $row->start_time === (string) $row->end_time;

				// When updating an existing recorder session, treat DB start_time as authoritative.
				if ( $insert_type === 'recorder' && ! empty( $row->start_time ) ) {
					$start_gmt = (string) $row->start_time;
				}

				$forced_type = null;
				if ( $insert_type === 'recorder' && ( $is_active || $is_placeholder ) && ! $is_automatic_stop && ! empty( $row->start_time ) ) {
					$forced = Politeia_Reading_Sessions_Recorder::compute_forced_end_time( (string) $row->start_time );
					if ( $forced['forced'] ) {
						$end_gmt     = $forced['end_time'];
						$forced_type = 'automatic_stop';
					}
				}

				$update_data    = array(
					'start_time'   => $start_gmt,
					'end_time'     => $end_gmt,
					'start_page'   => $start_page,
					'end_page'     => $end_page,
					'chapter_name' => $chapter ?: null,
				);
				$update_formats = array( '%s', '%s', '%d', '%d', '%s' );
				if ( $forced_type && Politeia_Reading_Sessions_Utils::table_has_columns( 'politeia_reading_sessions', array( 'insert_type' ) ) ) {
					$update_data['insert_type'] = $forced_type;
					$update_formats[]           = '%s';
				}

				$wpdb->update(
					$t,
					$update_data,
					array( 'id' => $session_id ),
					$update_formats,
					array( '%d' )
				);
				if ( $wpdb->last_error ) {
					Politeia_Reading_Sessions_Utils::json_error( 'db_update_failed', 500 );
				}

				// Closing a recorder session ends its active lifecycle (safe even if already deregistered).
				if ( $insert_type === 'recorder' ) {
					Politeia_Reading_Sessions_Recorder::deregister_active_session( $session_id );
				}
			} else {
				$session_id = 0; // caer a inserción
			}
		}
		if ( $session_id === 0 ) {
			$insert  = array(
				'user_id'      => $user_id,
				'user_book_id' => (int) $ub_row->id,
				'start_time'   => $start_gmt,
				'end_time'     => $end_gmt,
				'start_page'   => $start_page,
				'end_page'     => $end_page,
				'chapter_name' => $chapter ?: null,
			);
			$formats = array( '%d', '%d', '%s', '%s', '%d', '%d', '%s' );
			if ( Politeia_Reading_Sessions_Utils::table_has_columns( 'politeia_reading_sessions', array( 'insert_type' ) ) ) {
				$insert_type_value = $insert_type;
				if ( $insert_type === 'recorder' ) {
					// Clamp derived durations to avoid indefinite sessions.
					$start_dt = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', (string) $start_gmt, new \DateTimeZone( 'UTC' ) );
					$end_dt   = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', (string) $end_gmt, new \DateTimeZone( 'UTC' ) );
					if ( $start_dt && $end_dt ) {
						$elapsed = max( 0, $end_dt->getTimestamp() - $start_dt->getTimestamp() );
						if ( $elapsed >= Politeia_Reading_Sessions_Recorder::AUTO_STOP_SECONDS ) {
							$insert_type_value  = 'automatic_stop';
							$end_gmt            = gmdate( 'Y-m-d H:i:s', $start_dt->getTimestamp() + Politeia_Reading_Sessions_Recorder::AUTO_STOP_SECONDS );
							$insert['end_time'] = $end_gmt;
						}
					}
				}
				$insert['insert_type'] = $insert_type_value;
				$formats[]             = '%s';
			}

			$ok = $wpdb->insert( $t, $insert, $formats );
			if ( ! $ok ) {
				Politeia_Reading_Sessions_Utils::json_error( 'db_insert_failed', 500 );
			}
			$session_id = (int) $wpdb->insert_id;
		}

		// 1) Auto-pasar a STARTED si estaba NOT_STARTED
		if ( (string) $ub_row->reading_status === 'not_started' ) {
			Politeia_Reading_Sessions_Utils::update_user_book_fields(
				(int) $ub_row->id,
				array(
					'reading_status' => 'started',
				)
			);
			// refresca $ub_row para decisiones siguientes
			$ub_row = Politeia_Reading_Sessions_Utils::get_user_book_row( $user_id, $book_id );
		}

		// 2) Calcular cobertura y auto-finished
		$coverage = Politeia_Reading_Sessions_Stats::coverage_stats( $user_id, $book_id, $total_pages );
		$has_full = $coverage['full'] ?? false;

		if ( $has_full ) {
			// si no es finished o es finished auto, lo ponemos finished auto
			$do_finish = false;
			$update    = array( 'reading_status' => 'finished' );
			if ( Politeia_Reading_Sessions_Utils::table_has_columns( 'politeia_user_books', array( 'finish_mode', 'finished_at' ) ) ) {
				$update['finish_mode'] = 'auto';
				$update['finished_at'] = $end_gmt;
			}
			if ( (string) $ub_row->reading_status !== 'finished' ) {
				$do_finish = true;
			} else {
				// está finished: solo lo tocamos si es auto o nulo
				if ( property_exists( $ub_row, 'finish_mode' ) ) {
					$fm = (string) ( $ub_row->finish_mode ?? '' );
					if ( $fm === '' || $fm === 'auto' ) {
						$do_finish = true;
					}
				} else {
					// si no existe la col, asumimos que podemos setear finished
					$do_finish = true;
				}
			}
			if ( $do_finish ) {
				Politeia_Reading_Sessions_Utils::update_user_book_fields( (int) $ub_row->id, $update );
			}
		} else {
			// si estaba finished auto, revertir a started
			$was_finished_auto = false;
			if ( (string) $ub_row->reading_status === 'finished' ) {
				if ( property_exists( $ub_row, 'finish_mode' ) ) {
					$was_finished_auto = ( (string) $ub_row->finish_mode === 'auto' );
				} else {
					// sin columna, no sabríamos: no tocamos
					$was_finished_auto = false;
				}
			}
			if ( $was_finished_auto ) {
				$update = array( 'reading_status' => 'started' );
				// limpiar finished_at/finish_mode si existen
				if ( Politeia_Reading_Sessions_Utils::table_has_columns( 'politeia_user_books', array( 'finish_mode', 'finished_at' ) ) ) {
					$update['finish_mode'] = null;
					$update['finished_at'] = null;
				}
				Politeia_Reading_Sessions_Utils::update_user_book_fields( (int) $ub_row->id, $update );
			}
		}

		self::mark_planned_session_accomplished( $user_id, $book_id, $start_gmt );

		return array(
			'session_id' => (int) $session_id,
			'start_time' => $start_gmt,
			'end_time'   => $end_gmt,
			'coverage'   => $coverage, // { covered, total, full }
		);
	}

	public static function mark_planned_session_accomplished( $user_id, $book_id, $start_gmt ) {
		if ( empty( $start_gmt ) ) {
			return;
		}

		$dt = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', (string) $start_gmt, new \DateTimeZone( 'UTC' ) );
		if ( ! $dt ) {
			return;
		}

		$day_key = $dt->setTimezone( wp_timezone() )->format( 'Y-m-d' );

		global $wpdb;
		$plans_table       = $wpdb->prefix . 'politeia_plans';
		$goals_table       = $wpdb->prefix . 'politeia_plan_goals';
		$finish_book_table = $wpdb->prefix . 'politeia_plan_finish_book';
		$user_books_table  = $wpdb->prefix . 'politeia_user_books';
		$sessions_table    = $wpdb->prefix . 'politeia_planned_sessions';

		// Find plans via legacy goals OR new finish_book table (via user_books link)
		$plan_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.id
				FROM {$plans_table} p
				LEFT JOIN {$goals_table} g ON g.plan_id = p.id
				LEFT JOIN {$finish_book_table} pfb ON pfb.plan_id = p.id
				LEFT JOIN {$user_books_table} ub ON ub.id = pfb.user_book_id
				WHERE p.user_id = %d 
				  AND (g.book_id = %d OR ub.book_id = %d)",
				$user_id,
				$book_id,
				$book_id
			)
		);

		if ( empty( $plan_ids ) ) {
			return;
		}

		$placeholders = implode( ',', array_fill( 0, count( $plan_ids ), '%d' ) );
		$params       = array_merge( $plan_ids, array( $day_key ) );

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$sessions_table}
				SET status = 'accomplished'
				WHERE plan_id IN ({$placeholders})
				AND DATE(planned_start_datetime) = %s
				AND status = 'planned'",
				...$params
			)
		);

		// Invalidate cache for affected plans
		if ( class_exists( '\Politeia\ReadingPlanner\PlanSessionDeriver' ) ) {
			foreach ( $plan_ids as $pid ) {
				\Politeia\ReadingPlanner\PlanSessionDeriver::invalidate_plan_cache( (int) $pid );
			}
		}
	}
}
