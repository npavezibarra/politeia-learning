<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Template for the library header (search, count, add book).
 * Expects $context array from Politeia_Reading_Shortcode_My_Books.
 */
?>
<div class="prs-library__header">
	<div class="prs-library__header-inner">
		<div class="prs-library__header-center">
				<input
					type="text"
					id="my-library-search"
					class="prs-library__search"
					placeholder="<?php esc_attr_e( 'Search by Title or Author…', 'politeia-reading' ); ?>"
				/>
			<span
				id="prs-book-count"
				class="prs-book-count"
				data-total="<?php echo esc_attr( $context['total'] ); ?>"
				data-filter-active="0"
			>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: number of books. */
						_n( '%d book', '%d books', $context['total'], 'politeia-reading' ),
						$context['total']
					)
				);
				?>
			</span>
		</div>

		<div class="prs-library__header-actions">
			<button
				type="button"
				class="prs-library__filter-btn button button-secondary"
				aria-haspopup="dialog"
				aria-controls="prs-filter-dashboard"
				aria-expanded="false"
			>
				<span class="screen-reader-text"><?php esc_html_e( 'Filter', 'politeia-reading' ); ?></span>
			</button>
			<?php if ( ! empty( $context['add_book'] ) ) : ?>
				<div class="prs-library__header-add-book">
					<?php echo $context['add_book']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>
