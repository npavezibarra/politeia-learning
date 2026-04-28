<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $data Prepared by controller */
$book = $data['book'];
$ub   = $data['ub'];

$reading_disabled = in_array( $ub->owning_status, array( 'borrowing', 'borrowed' ), true );
$reading_disabled_text = __( 'Disabled while this book is being borrowed.', 'politeia-reading' );
$reading_disabled_title = $reading_disabled ? ' title="' . esc_attr( $reading_disabled_text ) . '"' : '';
?>
<div id="prs-book-header" class="prs-content-card">
	<div class="header">
		<div id="book-identity">
			<div id="book-title-row">
				<h1><?php echo esc_html( $book->title ); ?></h1>
				<span role="button" tabindex="0" id="prs-session-recorder-open" class="prs-session-recorder-trigger material-symbols-outlined" aria-label="<?php esc_attr_e( 'Open session recorder', 'politeia-reading' ); ?>" aria-controls="prs-session-modal" aria-expanded="false">play_circle</span>
			</div>
			<p id="book-author">
				<?php echo ! empty( $book->authors ) ? esc_html( $book->authors ) : esc_html__( 'Unknown Author', 'politeia-reading' ); ?>
			</p>
			<div id="fld-user-rating" class="prs-field">
				<div class="prs-stars" id="prs-user-rating" role="radiogroup" aria-label="<?php esc_attr_e( 'Your rating', 'politeia-reading' ); ?>">
					<?php
					$current_rating = isset( $ub->rating ) ? (int) $ub->rating : 0;
					for ( $i = 1; $i <= 5; $i++ ) :
						?>
						<button type="button" class="prs-star<?php echo ( $i <= $current_rating ) ? ' is-active' : ''; ?>" data-value="<?php echo $i; ?>" role="radio" aria-checked="<?php echo ( $i === $current_rating ) ? 'true' : 'false'; ?>">★</button>
					<?php endfor; ?>
					<span id="rating-status" class="prs-help" aria-live="polite"></span>
				</div>
			</div>
			<div id="fld-reading-status" class="prs-field">
				<div class="prs-reading-status-row">
					<label for="reading-status-select"><?php esc_html_e( 'Reading Status', 'politeia-reading' ); ?></label>
					<select id="reading-status-select" class="reading-status-select<?php echo $reading_disabled ? ' is-disabled' : ''; ?>" data-disabled-text="<?php echo esc_attr( $reading_disabled_text ); ?>" aria-disabled="<?php echo $reading_disabled ? 'true' : 'false'; ?>" <?php disabled( $reading_disabled ); ?> <?php echo $reading_disabled_title; ?>>
						<option value="not_started" <?php selected( $ub->reading_status, 'not_started' ); ?>><?php esc_html_e( 'Not Started', 'politeia-reading' ); ?></option>
						<option value="started" <?php selected( $ub->reading_status, 'started' ); ?>><?php esc_html_e( 'Started', 'politeia-reading' ); ?></option>
						<option value="finished" <?php selected( $ub->reading_status, 'finished' ); ?>><?php esc_html_e( 'Finished', 'politeia-reading' ); ?></option>
					</select>
					<span id="reading-status-status" class="prs-help" aria-live="polite"></span>
				</div>
			</div>
		</div>
		<div id="owning-status-summary">
			<?php
			$is_digital      = ( 'd' === $ub->type_book );
			$show_return_btn = in_array( $ub->owning_status, array( 'borrowed', 'borrowing' ), true );
			?>
			<div class="prs-field prs-status-field" id="fld-owning-status" data-contact-name="<?php echo esc_attr( $ub->counterparty_name ); ?>" data-contact-email="<?php echo esc_attr( $ub->counterparty_email ); ?>" data-active-start="<?php echo esc_attr( $data['active_start_local'] ); ?>">
				<label class="label" for="owning-status-select"><?php esc_html_e( 'Owning Status', 'politeia-reading' ); ?></label>
				<select id="owning-status-select" <?php disabled( $is_digital ); ?> aria-disabled="<?php echo $is_digital ? 'true' : 'false'; ?>">
					<option value="" <?php selected( empty( $ub->owning_status ) ); ?>><?php esc_html_e( '— Select —', 'politeia-reading' ); ?></option>
					<option value="borrowed" <?php selected( $ub->owning_status, 'borrowed' ); ?>><?php esc_html_e( 'Borrowed', 'politeia-reading' ); ?></option>
					<option value="borrowing" <?php selected( $ub->owning_status, 'borrowing' ); ?>><?php esc_html_e( 'Lent Out', 'politeia-reading' ); ?></option>
					<option value="bought" <?php selected( $ub->owning_status, 'bought' ); ?>><?php esc_html_e( 'Bought', 'politeia-reading' ); ?></option>
					<option value="sold" <?php selected( $ub->owning_status, 'sold' ); ?>><?php esc_html_e( 'Sold', 'politeia-reading' ); ?></option>
					<option value="lost" <?php selected( $ub->owning_status, 'lost' ); ?>><?php esc_html_e( 'Lost', 'politeia-reading' ); ?></option>
				</select>

				<button type="button" id="owning-return-shelf" class="prs-btn owning-return-shelf" data-book-id="<?php echo (int) $book->id; ?>" data-user-book-id="<?php echo (int) $ub->id; ?>" style="<?php echo $show_return_btn ? '' : 'display:none;'; ?>" <?php disabled( $is_digital ); ?>>
					<?php esc_html_e( 'Mark as returned', 'politeia-reading' ); ?>
				</button>

				<span id="owning-status-status" class="prs-help owning-status-info" data-book-id="<?php echo (int) $book->id; ?>"><?php echo esc_html( $data['owning_message'] ); ?></span>
				<p id="owning-status-note" class="prs-help prs-owning-status-note" style="<?php echo $is_digital ? '' : 'display:none;'; ?>">
					<?php esc_html_e( 'Owning status is available only for printed copies.', 'politeia-reading' ); ?>
				</p>
				<p class="prs-location" id="derived-location">
					<strong><?php esc_html_e( 'Location', 'politeia-reading' ); ?>:</strong>
					<span id="derived-location-text"><?php echo empty( $ub->owning_status ) ? esc_html__( 'In Shelf', 'politeia-reading' ) : esc_html__( 'Not In Shelf', 'politeia-reading' ); ?></span>
				</p>
			</div>
		</div>
	</div>
</div>
