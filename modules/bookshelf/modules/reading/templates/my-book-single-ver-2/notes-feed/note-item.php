<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var object $note */
/** @var array $note_index_map */

$emotion_keys     = array( 'joy', 'sorrow', 'fear', 'fascination', 'anger', 'serenity', 'enlightenment' );
$emotion_values   = array();
$total_emotion    = 0;
$note_visibility  = ! empty( $note->visibility ) ? (string) $note->visibility : 'private';
$decoded_emotions = $note->emotions ? json_decode( (string) $note->emotions, true ) : array();
if ( ! is_array( $decoded_emotions ) ) {
	$decoded_emotions = array();
}
foreach ( $emotion_keys as $key ) {
	$value = isset( $decoded_emotions[ $key ] ) ? (int) $decoded_emotions[ $key ] : 0;
	$value = max( 0, min( 5, $value ) );
	$emotion_values[ $key ] = $value;
	$total_emotion += $value;
}

$session_start_page = isset( $note->start_page ) ? (int) $note->start_page : 0;
$session_end_page   = isset( $note->end_page ) ? (int) $note->end_page : 0;
$note_index         = isset( $note_index_map[ (int) $note->id ] ) ? (int) $note_index_map[ (int) $note->id ] : 0;
$session_label      = $note_index
	? sprintf( __( 'Session #%d', 'politeia-reading' ), $note_index )
	: __( 'Session', 'politeia-reading' );
$page_range         = ( $session_start_page || $session_end_page )
	? sprintf( __( 'pages %1$s - %2$s', 'politeia-reading' ), $session_start_page ?: '—', $session_end_page ?: '—' )
	: '';
?>
<article class="prs-note" data-note-id="<?php echo esc_attr( (string) $note->id ); ?>"
	data-rs-id="<?php echo esc_attr( (string) $note->rs_id ); ?>"
	data-note-visibility="<?php echo esc_attr( $note_visibility ); ?>">
	
	<header class="prs-note__header">
		<div class="prs-note__session">
			<?php echo esc_html( $session_label . ( $page_range ? ', ' . $page_range : '' ) ); ?>
		</div>
		<div class="prs-note__meta">
			<time class="prs-note__date">
				<?php
				$note_date = ! empty( $note->created_at ) ? strtotime( $note->created_at ) : 0;
				echo $note_date ? esc_html( date_i18n( 'M j, Y', $note_date ) ) : esc_html__( 'Date', 'politeia-reading' );
				?>
			</time>
			<div class="prs-note__visibility">
				<span class="prs-note__visibility-label"><?php esc_html_e( 'Private', 'politeia-reading' ); ?></span>
				<label class="prs-toggle">
					<input class="prs-note__visibility-toggle" type="checkbox"
						data-note-id="<?php echo esc_attr( (string) $note->id ); ?>"
						aria-label="<?php esc_attr_e( 'Toggle private note', 'politeia-reading' ); ?>"
						<?php checked( 'private', $note_visibility ); ?> />
					<span class="prs-toggle__slider" aria-hidden="true"></span>
				</label>
			</div>
		</div>
	</header>
	
	<div class="prs-note__body">
		<div class="prs-note__content">
			<?php echo wp_kses_post( (string) $note->note ); ?>
		</div>
		<textarea class="prs-note__text" readonly="readonly" hidden="hidden"><?php echo esc_textarea( (string) $note->note ); ?></textarea>
	</div>
	
	<footer class="prs-note__footer">
		<div class="prs-note__composition<?php echo $total_emotion > 0 ? '' : ' is-empty'; ?>"
			aria-label="<?php esc_attr_e( 'Emotional composition', 'politeia-reading' ); ?>">
			<?php if ( $total_emotion > 0 ) : ?>
				<?php foreach ( $emotion_values as $key => $value ) : ?>
					<?php if ( $value > 0 ) : ?>
						<div class="prs-note__composition-seg--<?php echo esc_attr( $key ); ?>"
							style="width: <?php echo esc_attr( number_format( ( $value / $total_emotion ) * 100, 2, '.', '' ) ); ?>%;">
						</div>
					<?php endif; ?>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
		<div class="prs-note__actions">
			<button class="prs-note__edit-button" type="button"><?php esc_html_e( 'Edit', 'politeia-reading' ); ?></button>
			<button class="prs-note__rate-button" type="button"
				data-note-id="<?php echo esc_attr( (string) $note->id ); ?>"
				data-emotions="<?php echo esc_attr( $note->emotions ? (string) $note->emotions : '' ); ?>">
				<?php esc_html_e( 'Rate', 'politeia-reading' ); ?>
			</button>
			<button class="prs-note__delete-button" type="button"
				data-note-id="<?php echo esc_attr( (string) $note->id ); ?>"
				data-rs-id="<?php echo esc_attr( (string) $note->rs_id ); ?>">
				<?php esc_html_e( 'Delete', 'politeia-reading' ); ?>
			</button>
		</div>
	</footer>
</article>
