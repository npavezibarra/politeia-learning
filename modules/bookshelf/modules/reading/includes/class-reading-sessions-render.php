<?php
/**
 * Render logic for Reading Sessions
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Politeia_Reading_Sessions_Render {

	public static function ajax_render_sessions() {
		if ( ! is_user_logged_in() ) {
			Politeia_Reading_Sessions_Utils::json_error( 'auth', 401 );
		}
		if ( ! Politeia_Reading_Sessions_Utils::verify_nonce( 'prs_sessions_nonce', array( 'nonce' ) ) ) {
			Politeia_Reading_Sessions_Utils::json_error( 'bad_nonce', 403 );
		}

		$user_id  = get_current_user_id();
		$book_id  = isset( $_POST['book_id'] ) ? absint( $_POST['book_id'] ) : 0;
		$paged    = isset( $_POST['paged'] ) ? max( 1, absint( $_POST['paged'] ) ) : 1;
		$per_page = (int) apply_filters( 'politeia_reading_sessions_per_page', 15 );

		// --- NEW: Read sorting parameters ---
		$orderby = isset( $_POST['orderby'] ) ? sanitize_key( $_POST['orderby'] ) : 'start_time';
		$order   = isset( $_POST['order'] ) ? sanitize_key( $_POST['order'] ) : 'desc';

		if ( ! $book_id ) {
			Politeia_Reading_Sessions_Utils::json_error( 'invalid_book', 400 );
		}

		// Pass sorting parameters to the get_sessions_page function
		$data = self::get_sessions_page( $user_id, $book_id, $per_page, $paged, $orderby, $order, true );

		// --- NEW: Helper function to generate header attributes ---
		$sort_attrs = function ( $key ) use ( $orderby, $order ) {
			$class = 'prs-sortable';
			if ( $key === $orderby ) {
				$class .= ' ' . ( $order === 'asc' ? 'asc' : 'desc' );
			}
			return 'class="' . esc_attr( $class ) . '" data-sort="' . esc_attr( $key ) . '"';
		};

		// --- UPDATED: HTML table with sortable headers ---
		$html  = '<table class="prs-table"><thead><tr>';
		$html .= '<th ' . $sort_attrs( 'start_time' ) . '>' . esc_html__( 'Start', 'politeia-reading' ) . '</th>';
		$html .= '<th>' . esc_html__( 'End', 'politeia-reading' ) . '</th>';
		$html .= '<th ' . $sort_attrs( 'duration' ) . '>' . esc_html__( 'Duration', 'politeia-reading' ) . '</th>';
		$html .= '<th>' . esc_html__( 'Start Pg', 'politeia-reading' ) . '</th>';
		$html .= '<th>' . esc_html__( 'End Pg', 'politeia-reading' ) . '</th>';
		$html .= '<th ' . $sort_attrs( 'pages' ) . '>' . esc_html__( 'Pages', 'politeia-reading' ) . '</th>';
		$html .= '<th>' . esc_html__( 'Chapter', 'politeia-reading' ) . '</th>';
		$html .= '</tr></thead><tbody>';

		$total_seconds    = 0;
		$total_pages_read = 0;

		foreach ( (array) $data['rows'] as $s ) {
			$start_local = $s->start_time ? get_date_from_gmt( $s->start_time, 'Y-m-d H:i' ) : '—';
			$end_local   = $s->end_time ? get_date_from_gmt( $s->end_time, 'Y-m-d H:i' ) : '—';

			$sec = 0;
			if ( $s->start_time && $s->end_time ) {
				$sec = max( 0, strtotime( $s->end_time . ' +0 seconds' ) - strtotime( $s->start_time . ' +0 seconds' ) );
			}
			$p_start    = (int) $s->start_page;
			$p_end      = (int) $s->end_page;
			$pages_read = ( $p_end >= $p_start ) ? ( $p_end - $p_start + 1 ) : 0;

			$total_seconds    += $sec;
			$total_pages_read += $pages_read;

			$html .= '<tr>';
			$html .= '<td>' . esc_html( $start_local ) . '</td>';
			$html .= '<td>' . esc_html( $end_local ) . '</td>';
			$html .= '<td>' . esc_html( self::hms( $sec ) ) . '</td>';
			$html .= '<td>' . (int) $p_start . '</td>';
			$html .= '<td>' . (int) $p_end . '</td>';
			$html .= '<td>' . (int) $pages_read . '</td>';
			$html .= '<td>' . ( $s->chapter_name ? esc_html( $s->chapter_name ) : '—' ) . '</td>';
			$html .= '</tr>';
		}

		if ( empty( $data['rows'] ) ) {
			$html .= '<tr><td colspan="7">' . esc_html__( 'No sessions yet.', 'politeia-reading' ) . '</td></tr>';
		}

		$html .= '</tbody><tfoot><tr>';
		$html .= '<th colspan="2" style="text-align:right">' . esc_html__( 'Totals (this page):', 'politeia-reading' ) . '</th>';
		$html .= '<th>' . esc_html( self::hms( $total_seconds ) ) . '</th>';
		$html .= '<th></th><th></th>';
		$html .= '<th>' . (int) $total_pages_read . '</th>';
		$html .= '<th></th>';
		$html .= '</tr></tfoot></table>';

		// Paginación con enlaces AJAX (data-page)
		if ( (int) $data['max_pages'] > 1 ) {
			$html .= '<nav class="prs-pagination" aria-label="' . esc_attr__( 'Sessions pagination', 'politeia-reading' ) . '"><ul class="page-numbers">';
			for ( $i = 1; $i <= (int) $data['max_pages']; $i++ ) {
				if ( $i === (int) $data['paged'] ) {
					$html .= '<li><span class="page-numbers current">' . $i . '</span></li>';
				} else {
					$html .= '<li><a href="#" class="page-numbers prs-sess-link" data-page="' . $i . '">' . $i . '</a></li>';
				}
			}
			$html .= '</ul></nav>';
		}

		Politeia_Reading_Sessions_Utils::json_success(
			array(
				'html'      => $html,
				'paged'     => (int) $data['paged'],
				'max_pages' => (int) $data['max_pages'],
			)
		);
	}

	public static function get_sessions_page( $user_id, $book_id, $per_page = 15, $paged = 1, $orderby = 'start_time', $order = 'desc', $only_finished = true ) {
		global $wpdb;
		$t = $wpdb->prefix . 'politeia_reading_sessions';

		$user_id  = (int) $user_id;
		$book_id  = (int) $book_id;
		$per_page = max( 1, (int) $per_page );
		$paged    = max( 1, (int) $paged );
		$offset   = ( $paged - 1 ) * $per_page;

		$ub           = $wpdb->prefix . 'politeia_user_books';
		$user_book_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$ub} WHERE user_id=%d AND book_id=%d LIMIT 1",
				$user_id,
				$book_id
			)
		);

		if ( ! $user_book_id ) {
			return array(
				'rows'      => array(),
				'total'     => 0,
				'max_pages' => 0,
				'paged'     => $paged,
				'per_page'  => $per_page,
			);
		}

		$where = 'WHERE user_id=%d AND user_book_id=%d AND deleted_at IS NULL';
		$args  = array( $user_id, $user_book_id );

		if ( $only_finished ) {
			$where .= ' AND end_time IS NOT NULL';
		}

		// Total para paginación
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$t} {$where}",
				...$args
			)
		);

		if ( $total === 0 ) {
			return array(
				'rows'      => array(),
				'total'     => 0,
				'max_pages' => 0,
				'paged'     => $paged,
				'per_page'  => $per_page,
			);
		}

		$max_pages = (int) ceil( $total / $per_page );
		if ( $paged > $max_pages ) {
			$paged  = $max_pages;
			$offset = ( $paged - 1 ) * $per_page;
		}

		// --- NEW: Dynamic and safe ORDER BY clause ---
		$order_clause = '';
		$order_dir    = strtolower( $order ) === 'asc' ? 'ASC' : 'DESC'; // Sanitize direction

		switch ( $orderby ) {
			case 'duration':
				$order_clause = "ORDER BY (UNIX_TIMESTAMP(end_time) - UNIX_TIMESTAMP(start_time)) {$order_dir}, id DESC";
				break;
			case 'pages':
				$order_clause = "ORDER BY (end_page - start_page + 1) {$order_dir}, id DESC";
				break;
			case 'start_time':
			default:
				$order_clause = "ORDER BY start_time {$order_dir}, id DESC";
				break;
		}

		// Traer la página actual
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, start_time, end_time, start_page, end_page, chapter_name
             FROM {$t}
             {$where}
             {$order_clause}
             LIMIT %d OFFSET %d",
				...array_merge( $args, array( $per_page, $offset ) )
			)
		);

		return array(
			'rows'      => $rows ?: array(),
			'total'     => $total,
			'max_pages' => $max_pages,
			'paged'     => $paged,
			'per_page'  => $per_page,
		);
	}

	public static function hms( $sec ) {
		$sec = max( 0, (int) $sec );
		$h   = floor( $sec / 3600 );
		$m   = floor( ( $sec % 3600 ) / 60 );
		$s   = $sec % 60;
		return sprintf( '%02d:%02d:%02d', $h, $m, $s );
	}
}
