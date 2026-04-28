<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Notes Feed Component (v2)
 * Refactored to comply with the 500-line rule.
 */

global $wpdb, $book, $user_id, $tbl_session_notes, $tbl_sessions;

if ( empty( $tbl_session_notes ) ) {
	$tbl_session_notes = $wpdb->prefix . 'politeia_read_ses_notes';
}
if ( empty( $tbl_sessions ) ) {
	$tbl_sessions = $wpdb->prefix . 'politeia_reading_sessions';
}
$tbl_ub = $wpdb->prefix . 'politeia_user_books';

$notes = array();
if ( ! isset( $ub ) || ! is_object( $ub ) ) {
	$ub = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tbl_ub} WHERE user_id=%d AND book_id=%d LIMIT 1", $user_id, $book->id ) );
}

if ( ! empty( $ub ) && ! empty( $user_id ) ) {
	$notes = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT n.*, s.start_time, s.end_time, s.start_page, s.end_page
			 FROM {$tbl_session_notes} n
			 LEFT JOIN {$tbl_sessions} s ON s.id = n.rs_id AND s.user_id = n.user_id
			 WHERE n.user_book_id = %d AND n.user_id = %d
			 ORDER BY n.created_at DESC LIMIT 50",
			(int) $ub->id,
			(int) $user_id
		)
	);
}

$note_index_map = array();
if ( ! empty( $notes ) ) {
	$ordered_notes = $notes;
	usort( $ordered_notes, function ( $a, $b ) {
		$a_time = ! empty( $a->created_at ) ? strtotime( (string) $a->created_at ) : 0;
		$b_time = ! empty( $b->created_at ) ? strtotime( (string) $b->created_at ) : 0;
		return ( $a_time === $b_time ) ? ( (int) $a->id <=> (int) $b->id ) : ( $a_time <=> $b_time );
	} );

	foreach ( $ordered_notes as $idx => $ordered_note ) {
		$note_index_map[ (int) $ordered_note->id ] = $idx + 1;
	}
}

$other_readers = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT DISTINCT user_id FROM {$tbl_ub} WHERE book_id = %d AND deleted_at IS NULL AND user_id <> %d ORDER BY user_id ASC LIMIT 8",
		(int) $book->id,
		(int) $user_id
	)
);
?>

<section class="prs-notes-feed">
	<div class="prs-notes-feed__app">
		<main class="prs-notes-feed__layout">
			<section class="prs-notes-feed__notes">
				<?php if ( ! empty( $notes ) ) : ?>
					<?php foreach ( $notes as $note ) : ?>
						<?php include POLITEIA_READING_PATH . 'templates/my-book-single-ver-2/notes-feed/note-item.php'; ?>
					<?php endforeach; ?>
				<?php else : ?>
					<p class="prs-note__empty"><?php esc_html_e( 'You have not taken any notes on this book yet', 'politeia-reading' ); ?></p>
				<?php endif; ?>
			</section>

			<?php include POLITEIA_READING_PATH . 'templates/my-book-single-ver-2/notes-feed/readers-sidebar.php'; ?>
		</main>
	</div>
</section>

<?php include POLITEIA_READING_PATH . 'templates/my-book-single-ver-2/notes-feed/modal.php'; ?>
