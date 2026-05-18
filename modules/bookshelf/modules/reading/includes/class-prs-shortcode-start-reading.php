<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the [politeia_start_reading] shortcode logic and session recorder data.
 */
class Politeia_Reading_Shortcode_Start_Reading {

	/**
	 * Initialize the shortcode.
	 */
	public static function init() {
		add_shortcode( 'politeia_start_reading', array( __CLASS__, 'render_shortcode' ) );
	}

	/**
	 * Render the shortcode [politeia_start_reading].
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string The shortcode output.
	 */
	public static function render_shortcode( $atts ) {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'You must be logged in.', 'politeia-reading' ) . '</p>';
		}

		$atts = shortcode_atts(
			array(
				'book_id' => 0,
				'plan_id' => 0,
			),
			$atts,
			'politeia_start_reading'
		);

		$book_id = absint( $atts['book_id'] );
		$plan_id = absint( $atts['plan_id'] );
		if ( ! $book_id ) {
			return '';
		}

		$context = self::get_render_context( $book_id, $plan_id );
		self::enqueue_assets( $context );

		ob_start();
		include POLITEIA_READING_PATH . 'templates/shortcode-start-reading.php';
		return ob_get_clean();
	}

	/**
	 * Enqueue assets and localize recorder data.
	 *
	 * @param array $context The render context.
	 */
	private static function enqueue_assets( $context ) {
		wp_enqueue_style( 'politeia-reading' );
		$css_path = trailingslashit( POLITEIA_READING_PATH ) . 'assets/css/start-reading.css';
		$css_ver  = file_exists( $css_path ) ? (string) filemtime( $css_path ) : '1.0.0';
		wp_enqueue_style( 'politeia-start-reading', POLITEIA_READING_URL . 'assets/css/start-reading.css', array(), $css_ver );
		wp_enqueue_script( 'politeia-start-reading' );

		wp_localize_script(
			'politeia-start-reading',
			'PRS_SR',
			array(
				'ajax_url'           => admin_url( 'admin-ajax.php' ),
				'nonce'              => wp_create_nonce( 'prs_reading_nonce' ),
				'user_id'            => (int) $context['user_id'],
				'book_id'            => (int) $context['book_id'],
				'last_end_page'      => is_null( $context['last_end_page'] ) ? '' : (int) $context['last_end_page'],
				'default_start_page' => $context['default_start_page'],
				'owning_status'      => (string) $context['owning_status'],
				'total_pages'        => (int) $context['total_pages'],
				'can_start'          => $context['can_start'] ? 1 : 0,
				'actions'            => array(
					'start'     => 'prs_start_reading',
					'save'      => 'prs_save_reading',
					'heartbeat' => 'prs_sr_heartbeat',
					'auto_stop' => 'prs_sr_auto_stop',
				),
				'strings'            => array(
					'tooltip_pages_required'  => __( 'Set total Pages for this book before starting a session.', 'politeia-reading' ),
					'tooltip_not_owned'       => __( 'You cannot start a session: the book is not in your possession (Borrowed, Lost or Sold).', 'politeia-reading' ),
					'alert_pages_required'    => __( 'You must set total Pages to start a session.', 'politeia-reading' ),
					'alert_end_page_required' => __( 'Please enter an ending page before saving.', 'politeia-reading' ),
					'alert_session_expired'   => __( 'Session expired. Please refresh the page and try again.', 'politeia-reading' ),
					'alert_start_network'     => __( 'Network error while starting the session.', 'politeia-reading' ),
					'alert_save_failed'       => __( 'Could not save the session.', 'politeia-reading' ),
					'alert_save_network'      => __( 'Network error while saving the session.', 'politeia-reading' ),
					'pages_single'            => __( '1 page', 'politeia-reading' ),
					'pages_multiple'          => __( '%d pages', 'politeia-reading' ),
					'minutes_under_one'       => __( 'less than a minute', 'politeia-reading' ),
					'minutes_single'          => __( '1 minute', 'politeia-reading' ),
					'minutes_multiple'        => __( '%d minutes', 'politeia-reading' ),
					'limit_message'           => __( 'This session has reached the maximum length. Confirm to continue or it will stop automatically in 20 minutes.', 'politeia-reading' ),
					'limit_continue'          => __( 'Continue', 'politeia-reading' ),
					'limit_stop_now'          => __( 'Stop now', 'politeia-reading' ),
					'auto_stopped'            => __( 'This session was stopped automatically because it exceeded the maximum length.', 'politeia-reading' ),
					'auto_stop_failed'        => __( 'Network error while stopping the session automatically.', 'politeia-reading' ),
				),
			)
		);
	}

	/**
	 * Prepare the data context for rendering the recorder.
	 *
	 * @param int $book_id Book ID.
	 * @param int $plan_id Plan ID (optional).
	 * @return array The context array.
	 */
	private static function get_render_context( $book_id, $plan_id ) {
		global $wpdb;
		$user_id = get_current_user_id();

		$tbl_rs           = $wpdb->prefix . 'politeia_reading_sessions';
		$tbl_ub           = $wpdb->prefix . 'politeia_user_books';
		$tbl_books        = $wpdb->prefix . 'politeia_books';
		$tbl_authors      = $wpdb->prefix . 'politeia_authors';
		$tbl_book_authors = $wpdb->prefix . 'politeia_book_authors';
		$tbl_plans        = $wpdb->prefix . 'politeia_plans';
		$tbl_plan_sessions = $wpdb->prefix . 'politeia_planned_sessions';

		$book_title = $wpdb->get_var( $wpdb->prepare( "SELECT title FROM {$tbl_books} WHERE id = %d LIMIT 1", $book_id ) );
		$book_title = $book_title ? (string) $book_title : '';

		$book_author = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT GROUP_CONCAT(a.display_name ORDER BY ba.sort_order ASC SEPARATOR ', ')
				FROM {$tbl_book_authors} ba
				LEFT JOIN {$tbl_authors} a ON a.id = ba.author_id
				WHERE ba.book_id = %d",
				$book_id
			)
		);
		$book_author = $book_author ? (string) $book_author : __( 'Unknown author', 'politeia-reading' );

		$row_ub = $wpdb->get_row( $wpdb->prepare( "SELECT id, owning_status, pages FROM {$tbl_ub} WHERE user_id=%d AND book_id=%d AND deleted_at IS NULL LIMIT 1", $user_id, $book_id ) );
		$owning_status = $row_ub && $row_ub->owning_status ? (string) $row_ub->owning_status : 'in_shelf';
		$total_pages   = $row_ub && $row_ub->pages ? (int) $row_ub->pages : 0;
		$user_book_id  = $row_ub ? (int) $row_ub->id : 0;

		$last_end_page = 0;
		if ( $user_book_id > 0 ) {
			$last_end_page = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT end_page FROM {$tbl_rs}
					 WHERE user_id = %d AND user_book_id = %d AND end_time IS NOT NULL AND deleted_at IS NULL
					 ORDER BY end_time DESC LIMIT 1",
					$user_id,
					$user_book_id
				)
			);
		}

		$default_start_page = 0;
		if ( $plan_id > 0 ) {
			$plan_owner = (int) $wpdb->get_var( $wpdb->prepare( "SELECT user_id FROM {$tbl_plans} WHERE id = %d LIMIT 1", $plan_id ) );
			if ( $plan_owner === (int) $user_id ) {
				$default_start_page = (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT planned_start_page FROM {$tbl_plan_sessions}
						WHERE plan_id = %d AND planned_start_page IS NOT NULL
						ORDER BY planned_start_datetime ASC LIMIT 1",
						$plan_id
					)
				);
			}
		}

		$can_start = ! in_array( $owning_status, array( 'borrowed', 'lost', 'sold' ), true );

		$check_icon_url = defined( 'POLITEIA_READING_URL' )
			? esc_url( POLITEIA_READING_URL . 'assets/svg/check_circle_24dp_1F1F1F_FILL0_wght400_GRAD0_opsz24.svg' )
			: '';

		return array(
			'user_id'            => $user_id,
			'book_id'            => $book_id,
			'book_title'         => $book_title,
			'book_author'        => $book_author,
			'last_end_page'      => $last_end_page,
			'default_start_page' => $default_start_page,
			'owning_status'      => $owning_status,
			'total_pages'        => $total_pages,
			'can_start'          => $can_start,
			'check_icon_url'     => $check_icon_url,
		);
	}
}
