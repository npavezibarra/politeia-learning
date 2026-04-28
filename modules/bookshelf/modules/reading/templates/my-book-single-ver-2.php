<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Single Book Template (ver-2)
 * Modularized version.
 * @var array $data Prepared by Politeia_Reading_Book_Single_Controller
 */

// If $data is not set, we assume this was called directly (legacy or error)
if ( ! isset( $data ) ) {
	// Fallback to controller if possible, or exit
	$slug = get_query_var( 'prs_book_slug' );
	if ( $slug && class_exists( 'Politeia_Reading_Book_Single_Controller' ) ) {
		Politeia_Reading_Book_Single_Controller::render( $slug );
		return;
	}
	exit;
}

prs_template_open();
?>

<div class="prs-page-wrap prs-page-wrap--ver2">
	<div class="page">
		<aside class="sidebar">
			<section id="prs-cover-progress" class="prs-sidebar-block">
				<?php include __DIR__ . '/my-book-single-ver-2/sidebar-cover.php'; ?>
			</section>

			<?php include __DIR__ . '/my-book-single-ver-2/sidebar-details.php'; ?>
		</aside>

		<section class="content">
			<?php include __DIR__ . '/my-book-single-ver-2/content-header.php'; ?>

			<div id="prs-book-content" class="prs-content-card">
				<div class="tabs" role="tablist" aria-label="<?php esc_attr_e( 'Book sections', 'politeia-reading' ); ?>">
					<button class="tab active" type="button" data-tab="reading-sessions" role="tab" aria-selected="true"><?php esc_html_e( 'Reading Sessions', 'politeia-reading' ); ?></button>
					<button class="tab" type="button" data-tab="book-stats" role="tab" aria-selected="false"><?php esc_html_e( 'Book Stats', 'politeia-reading' ); ?></button>
					<button class="tab" type="button" data-tab="notes-feed" role="tab" aria-selected="false"><?php esc_html_e( 'Notes Feed', 'politeia-reading' ); ?></button>
				</div>

				<div class="prs-tab-content is-active" data-tab="reading-sessions">
					<?php include __DIR__ . '/my-book-single-ver-2/reading-sessions.php'; ?>
				</div>

				<div class="prs-tab-content" data-tab="book-stats">
					<?php include __DIR__ . '/my-book-single-ver-2/book-stats.php'; ?>
				</div>

				<div class="prs-tab-content" data-tab="notes-feed">
					<?php include __DIR__ . '/my-book-single-ver-2/notes-feed.php'; ?>
				</div>
			</div>
		</section>
	</div>
</div>

<?php
// Note: CSS and JS are now enqueued via the Controller using external files.
prs_template_close();
?>
