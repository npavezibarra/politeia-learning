<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $sessions;

$book_title = '';
if ( isset( $data['book']->title ) ) {
	$book_title = (string) $data['book']->title;
}

$total_seconds     = 0;
$total_pages_read  = 0;
$valid_sessions    = 0;
$first_session_ts  = 0;
$last_session_ts   = 0;
$minutes_by_month  = array(); // yyyy-mm => minutes

if ( ! empty( $sessions ) && is_array( $sessions ) ) {
	foreach ( $sessions as $session ) {
		$start_ts = ! empty( $session->start_time ) ? strtotime( (string) $session->start_time ) : 0;
		$end_ts   = ! empty( $session->end_time ) ? strtotime( (string) $session->end_time ) : 0;

		if ( $start_ts > 0 && ( 0 === $first_session_ts || $start_ts < $first_session_ts ) ) {
			$first_session_ts = $start_ts;
		}
		if ( $start_ts > 0 && $start_ts > $last_session_ts ) {
			$last_session_ts = $start_ts;
		}

		$duration = 0;
		if ( $start_ts && $end_ts && $end_ts >= $start_ts ) {
			$duration = (int) ( $end_ts - $start_ts );
		}

		$pages = 0;
		$start_page = isset( $session->start_page ) ? (int) $session->start_page : null;
		$end_page   = isset( $session->end_page ) ? (int) $session->end_page : null;
		if ( null !== $start_page && null !== $end_page && $end_page >= $start_page ) {
			$pages = (int) ( $end_page - $start_page );
		}

		if ( $duration > 0 || $pages > 0 ) {
			$valid_sessions++;
		}

		$total_seconds    += max( 0, $duration );
		$total_pages_read += max( 0, $pages );

		if ( $start_ts ) {
			$month_key = wp_date( 'Y-m', $start_ts );
			$minutes_by_month[ $month_key ] = ( $minutes_by_month[ $month_key ] ?? 0 ) + (int) floor( max( 0, $duration ) / 60 );
		}
	}
}

$avg_minutes = 0;
if ( $valid_sessions > 0 ) {
	$avg_minutes = (int) round( ( $total_seconds / 60 ) / $valid_sessions );
}

$total_hours = 0.0;
if ( $total_seconds > 0 ) {
	$total_hours = (float) ( $total_seconds / 3600 );
}

$pages_per_hour = 0;
if ( $total_hours > 0.0 ) {
	$pages_per_hour = (int) round( $total_pages_read / $total_hours );
}

// Build month range: from first session month to current month (cap to last 18 months).
$months = array();
if ( $first_session_ts ) {
	$start = new DateTimeImmutable( wp_date( 'Y-m-01 00:00:00', $first_session_ts ) );
	$end   = new DateTimeImmutable( wp_date( 'Y-m-01 00:00:00', time() ) );
	$diff  = (int) $start->diff( $end )->format( '%m' ) + ( 12 * (int) $start->diff( $end )->format( '%y' ) );
	$count = max( 0, $diff ) + 1;

	if ( $count > 18 ) {
		$start = $end->modify( '-17 months' );
		$count = 18;
	}

	for ( $i = 0; $i < $count; $i++ ) {
		$month_date = $start->modify( sprintf( '+%d months', $i ) );
		$month_key  = $month_date->format( 'Y-m' );
		$minutes    = (int) ( $minutes_by_month[ $month_key ] ?? 0 );

		$months[] = array(
			'key'     => $month_key,
			'label'   => wp_date( 'M', $month_date->getTimestamp() ),
			'minutes' => $minutes,
		);
	}
}

$max_minutes = 0;
foreach ( $months as $m ) {
	if ( $m['minutes'] > $max_minutes ) {
		$max_minutes = $m['minutes'];
	}
}

$min_month_key = '';
$max_month_key = '';
if ( ! empty( $months ) ) {
	$min_month_key = (string) $months[0]['key'];
	$max_month_key = (string) $months[ count( $months ) - 1 ]['key'];
}
?>

<section class="prs-dash-stats">
	<div class="prs-dash-stats__heading">
		<h2 class="prs-dash-stats__title"><?php esc_html_e( 'Book Stats', 'politeia-reading' ); ?></h2>
		<p class="prs-dash-stats__subtitle"><?php echo esc_html( sprintf( __( 'Métricas de lectura para “%s”.', 'politeia-reading' ), $book_title ) ); ?></p>
	</div>

	<div class="prs-dash-stats__kpis">
		<div class="prs-dash-kpi">
			<div class="prs-dash-kpi__value"><?php echo esc_html( $avg_minutes ? ( $avg_minutes . 'm' ) : '—' ); ?></div>
			<div class="prs-dash-kpi__rule" aria-hidden="true"></div>
			<div class="prs-dash-kpi__label"><?php esc_html_e( 'Tiempo promedio de sesión', 'politeia-reading' ); ?></div>
		</div>
		<div class="prs-dash-kpi">
			<div class="prs-dash-kpi__value">
				<?php
				if ( $total_hours > 0 ) {
					echo esc_html( number_format_i18n( $total_hours, $total_hours < 10 ? 1 : 0 ) . 'h' );
				} else {
					echo '—';
				}
				?>
			</div>
			<div class="prs-dash-kpi__rule" aria-hidden="true"></div>
			<div class="prs-dash-kpi__label"><?php esc_html_e( 'Tiempo total de lectura', 'politeia-reading' ); ?></div>
		</div>
		<div class="prs-dash-kpi">
			<div class="prs-dash-kpi__value"><?php echo esc_html( $pages_per_hour ? (string) $pages_per_hour : '—' ); ?></div>
			<div class="prs-dash-kpi__rule" aria-hidden="true"></div>
			<div class="prs-dash-kpi__label"><?php esc_html_e( 'Páginas por hora', 'politeia-reading' ); ?></div>
		</div>
		<div class="prs-dash-kpi">
			<div class="prs-dash-kpi__value"><?php echo esc_html( $total_pages_read ? (string) $total_pages_read : '—' ); ?></div>
			<div class="prs-dash-kpi__rule" aria-hidden="true"></div>
			<div class="prs-dash-kpi__label"><?php esc_html_e( 'Total de páginas leídas', 'politeia-reading' ); ?></div>
		</div>
	</div>

	<div class="prs-dash-activity">
		<div class="prs-dash-activity__header">
			<span class="prs-dash-activity__label"><?php esc_html_e( 'Actividad (por mes)', 'politeia-reading' ); ?></span>
			<div class="prs-dash-activity__controls">
				<?php if ( $min_month_key && $max_month_key ) : ?>
					<label class="prs-dash-activity__control">
						<span class="prs-dash-activity__control-label"><?php esc_html_e( 'Desde', 'politeia-reading' ); ?></span>
						<input class="prs-dash-activity__month" type="month" id="prs-activity-from" value="<?php echo esc_attr( $min_month_key ); ?>" min="<?php echo esc_attr( $min_month_key ); ?>" max="<?php echo esc_attr( $max_month_key ); ?>">
					</label>
					<label class="prs-dash-activity__control">
						<span class="prs-dash-activity__control-label"><?php esc_html_e( 'Hasta', 'politeia-reading' ); ?></span>
						<input class="prs-dash-activity__month" type="month" id="prs-activity-to" value="<?php echo esc_attr( $max_month_key ); ?>" min="<?php echo esc_attr( $min_month_key ); ?>" max="<?php echo esc_attr( $max_month_key ); ?>">
					</label>
				<?php endif; ?>
				<span class="prs-dash-activity__range" id="prs-activity-range-label">
					<?php
					if ( $min_month_key && $max_month_key ) {
						$start_label = wp_date( 'M Y', strtotime( $min_month_key . '-01' ) );
						$end_label   = wp_date( 'M Y', strtotime( $max_month_key . '-01' ) );
						echo esc_html( sprintf( '%s – %s', $start_label, $end_label ) );
					}
					?>
				</span>
			</div>
		</div>

		<?php if ( empty( $months ) ) : ?>
			<p class="prs-dash-activity__empty"><?php esc_html_e( 'Aún no hay sesiones registradas para este libro.', 'politeia-reading' ); ?></p>
		<?php else : ?>
			<div class="prs-dash-bars" role="img"
				aria-label="<?php esc_attr_e( 'Monthly reading activity', 'politeia-reading' ); ?>"
				data-months="<?php echo esc_attr( wp_json_encode( $months ) ); ?>">
				<?php foreach ( $months as $m ) :
					$height = 14;
					if ( $max_minutes > 0 ) {
						$height = (int) round( 12 + ( ( $m['minutes'] / $max_minutes ) * 88 ) );
					}
					$tooltip = $m['minutes'] ? sprintf( __( '%d min', 'politeia-reading' ), (int) $m['minutes'] ) : __( '0 min', 'politeia-reading' );
					?>
					<div class="prs-dash-bars__col">
						<div class="prs-dash-bars__bar-wrap">
							<div class="prs-dash-bars__bar<?php echo ( $m['minutes'] === $max_minutes && $max_minutes > 0 ) ? ' is-peak' : ''; ?>"
								style="<?php echo esc_attr( 'height:' . $height . '%' ); ?>"
								data-tooltip="<?php echo esc_attr( $tooltip ); ?>"></div>
						</div>
						<div class="prs-dash-bars__tick<?php echo ( $m['minutes'] === $max_minutes && $max_minutes > 0 ) ? ' is-peak' : ''; ?>">
							<?php echo esc_html( $m['label'] ); ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
