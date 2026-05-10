<?php
/**
 * Stats & Coverage logic for Reading Sessions
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Politeia_Reading_Sessions_Stats {

	public static function coverage_stats( $user_id, $book_id, $total_pages ) {
		return self::coverage_stats_from_intervals( self::fetch_intervals( $user_id, $book_id ), $total_pages );
	}

	public static function coverage_stats_from_intervals( array $intervals, $total_pages ) {
		$total_pages = (int) $total_pages;
		if ( $total_pages <= 0 ) {
			return array(
				'covered' => 0,
				'total'   => 0,
				'full'    => false,
			);
		}

		// normalizar y clamp
		$norm = array();
		foreach ( $intervals as $iv ) {
			$a = max( 1, (int) $iv['s'] );
			$b = min( $total_pages, (int) $iv['e'] );
			if ( $b < $a ) {
				continue;
			}
			$norm[] = array( $a, $b );
		}
		if ( ! $norm ) {
			return array(
				'covered' => 0,
				'total'   => $total_pages,
				'full'    => false,
			);
		}

		// unir
		usort(
			$norm,
			function ( $x, $y ) {
				return $x[0] <=> $y[0];
			}
		);
		$merged = array();
		$cur    = $norm[0];
		for ( $i = 1; $i < count( $norm ); $i++ ) {
			$iv = $norm[ $i ];
			if ( $iv[0] <= $cur[1] + 1 ) {
				// solapa o adyacente -> unir
				$cur[1] = max( $cur[1], $iv[1] );
			} else {
				$merged[] = $cur;
				$cur      = $iv;
			}
		}
		$merged[] = $cur;

		// suma de longitudes (inclusivo)
		$covered = 0;
		foreach ( $merged as $m ) {
			$covered += ( $m[1] - $m[0] + 1 );
		}
		$covered = max( 0, min( $covered, $total_pages ) );

		return array(
			'covered' => $covered,
			'total'   => $total_pages,
			'full'    => ( $covered >= $total_pages ),
		);
	}

	public static function fetch_intervals( $user_id, $book_id ) {
		global $wpdb;
		$t  = $wpdb->prefix . 'politeia_reading_sessions';
		$ub = $wpdb->prefix . 'politeia_user_books';

		// Resolve user_book_id (could pass in, but for safety lookup)
		$user_book_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$ub} WHERE user_id=%d AND book_id=%d LIMIT 1",
				$user_id,
				$book_id
			)
		);

		if ( ! $user_book_id ) {
			return array();
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT start_page, end_page FROM {$t}
             WHERE user_id=%d AND user_book_id=%d AND end_time IS NOT NULL AND deleted_at IS NULL",
				$user_id,
				$user_book_id
			),
			ARRAY_A
		);
		$out  = array();
		if ( $rows ) {
			foreach ( $rows as $r ) {
				$s = (int) $r['start_page'];
				$e = (int) $r['end_page'];
				if ( $e < $s ) {
					continue;
				}
				$out[] = array(
					's' => $s,
					'e' => $e,
				);
			}
		}
		return $out;
	}

	public static function calculate_progress_percent( $user_id, $book_id, $total_pages ) {
		$total_pages = (int) $total_pages;
		if ( $total_pages <= 0 ) {
			return 0;
		}

		$coverage = self::coverage_stats( $user_id, $book_id, $total_pages );

		$covered = isset( $coverage['covered'] ) ? (int) $coverage['covered'] : 0;
		$total   = isset( $coverage['total'] ) ? (int) $coverage['total'] : $total_pages;

		if ( $total <= 0 ) {
			return 0;
		}

		$percent = ( $covered / $total ) * 100;
		$percent = round( $percent );

		if ( $percent < 0 ) {
			return 0;
		}

		if ( $percent > 100 ) {
			return 100;
		}

		return (int) $percent;
	}

	public static function calculate_progress_percent_from_intervals( array $intervals, $total_pages ) {
		$coverage = self::coverage_stats_from_intervals( $intervals, $total_pages );

		$covered = isset( $coverage['covered'] ) ? (int) $coverage['covered'] : 0;
		$total   = isset( $coverage['total'] ) ? (int) $coverage['total'] : (int) $total_pages;

		if ( $total <= 0 ) {
			return 0;
		}

		$percent = round( ( $covered / $total ) * 100 );

		if ( $percent < 0 ) {
			return 0;
		}

		if ( $percent > 100 ) {
			return 100;
		}

		return (int) $percent;
	}
}
