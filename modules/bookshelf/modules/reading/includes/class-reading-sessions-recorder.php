<?php
/**
 * Recorder & Cron logic for Reading Sessions
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Politeia_Reading_Sessions_Recorder {

	public const HARD_PROMPT_SECONDS = 4800; // 80 minutes
	public const AUTO_STOP_SECONDS = 6000; // 100 minutes (80 + 20)
	public const ACTIVE_SESSIONS_OPTION = 'politeia_reading_active_recorder_sessions';
	public const ACTIVE_SESSION_TRANSIENT_PREFIX = 'politeia_reading_active_recorder_session_';
	public const CRON_HOOK = 'politeia_reading_recorder_autostop';
	public const CRON_SCHEDULE = 'politeia_reading_15min';

	public static function ajax_heartbeat() {
		if ( ! is_user_logged_in() ) {
			Politeia_Reading_Sessions_Utils::json_error( 'auth', 401 );
		}
		if ( ! Politeia_Reading_Sessions_Utils::verify_nonce( 'prs_reading_nonce', array( 'nonce' ) ) ) {
			Politeia_Reading_Sessions_Utils::json_error( 'bad_nonce', 403 );
		}

		$user_id = get_current_user_id();
		$session_id = isset( $_POST['session_id'] ) ? absint( $_POST['session_id'] ) : 0;
		if ( $session_id <= 0 ) {
			Politeia_Reading_Sessions_Utils::json_error( 'invalid_session', 400 );
		}

		global $wpdb;
		$t = $wpdb->prefix . 'politeia_reading_sessions';

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id,user_id,start_time,deleted_at FROM {$t} WHERE id=%d AND deleted_at IS NULL LIMIT 1",
				$session_id
			)
		);
		if ( ! $row || (int) $row->user_id !== (int) $user_id ) {
			Politeia_Reading_Sessions_Utils::json_error( 'forbidden', 403 );
		}
		if ( empty( $row->start_time ) ) {
			Politeia_Reading_Sessions_Utils::json_error( 'invalid_session', 400 );
		}

		$start_dt = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', (string) $row->start_time, new \DateTimeZone( 'UTC' ) );
		if ( ! $start_dt ) {
			Politeia_Reading_Sessions_Utils::json_error( 'invalid_session', 400 );
		}

		$now_ts = (int) current_time( 'timestamp', true );
		$elapsed = max( 0, $now_ts - $start_dt->getTimestamp() );

		Politeia_Reading_Sessions_Utils::json_success(
			array(
				'elapsed_sec'      => (int) $elapsed,
				'should_prompt_80' => $elapsed >= self::HARD_PROMPT_SECONDS && $elapsed < self::AUTO_STOP_SECONDS ? 1 : 0,
				'must_stop_100'    => $elapsed >= self::AUTO_STOP_SECONDS ? 1 : 0,
				'is_active'        => self::is_active_session( $session_id ) ? 1 : 0,
			)
		);
	}

	public static function ajax_auto_stop() {
		if ( ! is_user_logged_in() ) {
			Politeia_Reading_Sessions_Utils::json_error( 'auth', 401 );
		}
		if ( ! Politeia_Reading_Sessions_Utils::verify_nonce( 'prs_reading_nonce', array( 'nonce' ) ) ) {
			Politeia_Reading_Sessions_Utils::json_error( 'bad_nonce', 403 );
		}

		$user_id = get_current_user_id();
		$session_id = isset( $_POST['session_id'] ) ? absint( $_POST['session_id'] ) : 0;
		if ( $session_id <= 0 ) {
			Politeia_Reading_Sessions_Utils::json_error( 'invalid_session', 400 );
		}

		$result = self::auto_stop_session( $session_id, (int) $user_id, 'ajax' );
		if ( isset( $result['error'] ) ) {
			Politeia_Reading_Sessions_Utils::json_error( (string) $result['error'], isset( $result['code'] ) ? (int) $result['code'] : 400 );
		}

		Politeia_Reading_Sessions_Utils::json_success( $result );
	}

	public static function register_cron_schedule( $schedules ) {
		if ( ! isset( $schedules[ self::CRON_SCHEDULE ] ) ) {
			$schedules[ self::CRON_SCHEDULE ] = array(
				'interval' => 15 * MINUTE_IN_SECONDS,
				'display'  => 'Every 15 minutes (Politeia Reading)',
			);
		}
		return $schedules;
	}

	public static function schedule_autostop_cron() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, self::CRON_SCHEDULE, self::CRON_HOOK );
		}
	}

	public static function cron_autostop() {
		$sessions = get_option( self::ACTIVE_SESSIONS_OPTION, array() );
		if ( ! is_array( $sessions ) || empty( $sessions ) ) {
			return;
		}

		foreach ( $sessions as $sid ) {
			$sid = absint( $sid );
			if ( $sid <= 0 ) {
				continue;
			}

			// Cron runs without a logged-in user context; auto_stop_session will validate ownership via row data.
			self::auto_stop_session( $sid, 0, 'cron' );
		}
	}

	public static function get_active_sessions(): array {
		$sessions = get_option( self::ACTIVE_SESSIONS_OPTION, array() );
		if ( ! is_array( $sessions ) ) {
			return array();
		}
		return array_values( array_unique( array_map( 'absint', $sessions ) ) );
	}

	public static function save_active_sessions( array $sessions ): void {
		$sessions = array_values( array_unique( array_filter( array_map( 'absint', $sessions ) ) ) );
		update_option( self::ACTIVE_SESSIONS_OPTION, $sessions, false );
	}

	public static function register_active_session( int $session_id, int $user_id, int $book_id, int $user_book_id, string $started_at_gmt ): void {
		$list   = self::get_active_sessions();
		$list[] = $session_id;
		self::save_active_sessions( $list );

		$meta = array(
			'session_id'     => $session_id,
			'user_id'        => $user_id,
			'book_id'        => $book_id,
			'user_book_id'   => $user_book_id,
			'started_at_gmt' => $started_at_gmt,
		);
		set_transient( self::ACTIVE_SESSION_TRANSIENT_PREFIX . $session_id, $meta, 3 * HOUR_IN_SECONDS );
	}

	public static function deregister_active_session( int $session_id ): void {
		$list = self::get_active_sessions();
		$list = array_values( array_filter( $list, static fn( $id ) => (int) $id !== (int) $session_id ) );
		self::save_active_sessions( $list );
		delete_transient( self::ACTIVE_SESSION_TRANSIENT_PREFIX . $session_id );
	}

	public static function get_active_session_meta( int $session_id ) {
		$meta = get_transient( self::ACTIVE_SESSION_TRANSIENT_PREFIX . $session_id );
		return is_array( $meta ) ? $meta : null;
	}

	public static function is_active_session( int $session_id ): bool {
		$list = self::get_active_sessions();
		return in_array( (int) $session_id, array_map( 'intval', $list ), true );
	}

	public static function cleanup_active_sessions(): void {
		$list = self::get_active_sessions();
		if ( empty( $list ) ) {
			return;
		}

		global $wpdb;
		$t = $wpdb->prefix . 'politeia_reading_sessions';

		$kept = array();
		foreach ( $list as $sid ) {
			$sid = (int) $sid;
			if ( $sid <= 0 ) {
				continue;
			}
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id,deleted_at FROM {$t} WHERE id=%d LIMIT 1",
					$sid
				)
			);
			if ( ! $row || ! empty( $row->deleted_at ) ) {
				delete_transient( self::ACTIVE_SESSION_TRANSIENT_PREFIX . $sid );
				continue;
			}
			$kept[] = $sid;
		}
		self::save_active_sessions( $kept );
	}

	public static function find_active_session_id( int $user_id, int $book_id ): int {
		$list = self::get_active_sessions();
		if ( empty( $list ) ) {
			return 0;
		}

		foreach ( $list as $sid ) {
			$meta = self::get_active_session_meta( (int) $sid );
			if ( ! $meta ) {
				continue;
			}
			if ( (int) ( $meta['user_id'] ?? 0 ) === $user_id && (int) ( $meta['book_id'] ?? 0 ) === $book_id ) {
				return (int) $sid;
			}
		}
		return 0;
	}

	public static function compute_forced_end_time( string $start_gmt ): array {
		$start_dt = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', (string) $start_gmt, new \DateTimeZone( 'UTC' ) );
		if ( ! $start_dt ) {
			return array( 'forced' => false, 'end_time' => $start_gmt );
		}

		$now_ts   = (int) current_time( 'timestamp', true );
		$start_ts = $start_dt->getTimestamp();
		$elapsed  = max( 0, $now_ts - $start_ts );
		if ( $elapsed < self::AUTO_STOP_SECONDS ) {
			return array( 'forced' => false, 'end_time' => gmdate( 'Y-m-d H:i:s', $now_ts ) );
		}

		return array(
			'forced'   => true,
			'end_time' => gmdate( 'Y-m-d H:i:s', $start_ts + self::AUTO_STOP_SECONDS ),
		);
	}

	public static function auto_stop_session( int $session_id, int $request_user_id, string $trigger ): array {
		global $wpdb;
		$t = $wpdb->prefix . 'politeia_reading_sessions';

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id,user_id,start_time,insert_type,deleted_at FROM {$t} WHERE id=%d LIMIT 1",
				$session_id
			),
			ARRAY_A
		);

		if ( ! $row || ! empty( $row['deleted_at'] ) ) {
			self::deregister_active_session( $session_id );
			return array(
				'session_id' => $session_id,
				'stopped'    => 0,
				'reason'     => 'missing',
			);
		}

		$owner_id = (int) ( $row['user_id'] ?? 0 );
		if ( $request_user_id > 0 && $owner_id !== $request_user_id ) {
			return array( 'error' => 'forbidden', 'code' => 403 );
		}

		// Only auto-stop if it's still considered active in our registry.
		if ( ! self::is_active_session( $session_id ) ) {
			return array(
				'session_id' => $session_id,
				'stopped'    => 0,
				'reason'     => 'not_active',
			);
		}

		$start_time = isset( $row['start_time'] ) ? (string) $row['start_time'] : '';
		if ( ! $start_time ) {
			self::deregister_active_session( $session_id );
			return array( 'error' => 'invalid_session', 'code' => 400 );
		}

		$forced   = self::compute_forced_end_time( $start_time );
		$end_time = $forced['end_time'];

		if ( ! $forced['forced'] ) {
			return array(
				'session_id' => $session_id,
				'stopped'    => 0,
				'reason'     => 'below_limit',
			);
		}

		$insert_type = isset( $row['insert_type'] ) ? (string) $row['insert_type'] : 'recorder';
		if ( $insert_type !== 'recorder' && $insert_type !== 'automatic_stop' ) {
			self::deregister_active_session( $session_id );
			return array(
				'session_id' => $session_id,
				'stopped'    => 0,
				'reason'     => 'not_recorder',
			);
		}

		$update  = array(
			'end_time' => $end_time,
		);
		$formats = array( '%s' );
		if ( Politeia_Reading_Sessions_Utils::table_has_columns( 'politeia_reading_sessions', array( 'insert_type' ) ) ) {
			$update['insert_type'] = 'automatic_stop';
			$formats[]             = '%s';
		}

		$wpdb->update(
			$t,
			$update,
			array( 'id' => $session_id ),
			$formats,
			array( '%d' )
		);

		self::deregister_active_session( $session_id );

		error_log( sprintf( '[PRS_SR] auto_stop session_id=%d user_id=%d trigger=%s', $session_id, $owner_id, $trigger ) );
		do_action(
			'politeia_reading_session_auto_stopped',
			array(
				'session_id' => $session_id,
				'user_id'    => $owner_id,
				'trigger'    => $trigger,
				'end_time'   => $end_time,
			)
		);

		return array(
			'session_id'  => $session_id,
			'stopped'     => 1,
			'end_time'    => $end_time,
			'insert_type' => 'automatic_stop',
		);
	}
}
