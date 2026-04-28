<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $data Prepared by controller */
$book      = $data['book'];
$cover     = $data['cover'];
$has_image = $cover['has_image'];
?>
<section id="book-cover-section">
	<div class="prs-cover-wrap">
		<div id="prs-cover-frame" class="cover-frame <?php echo $has_image ? 'has-image' : ''; ?>"
			data-cover-state="<?php echo $has_image ? 'image' : 'empty'; ?>"
			data-placeholder-title="<?php echo esc_attr__( 'Untitled Book', 'politeia-reading' ); ?>"
			data-placeholder-author="<?php echo esc_attr__( 'Unknown Author', 'politeia-reading' ); ?>"
			data-placeholder-label="<?php echo esc_attr__( 'Default book cover', 'politeia-reading' ); ?>"
			data-search-label="<?php echo esc_attr__( 'Search Cover', 'politeia-reading' ); ?>"
			data-remove-label="<?php echo esc_attr__( 'Remove book cover', 'politeia-reading' ); ?>"
			data-remove-confirm="<?php echo esc_attr__( 'Are you sure you want to remove this book cover?', 'politeia-reading' ); ?>">
			<figure class="prs-book-cover" id="prs-book-cover-figure">
				<?php if ( $has_image ) : ?>
					<?php
					$cover_img_url = $cover['url'];
					$cover_alt     = ! empty( $book->title ) ? $book->title : __( 'Book cover', 'politeia-reading' );
					if ( $cover['id'] ) {
						$meta_alt = get_post_meta( $cover['id'], '_wp_attachment_image_alt', true );
						if ( $meta_alt ) {
							$cover_alt = $meta_alt;
						}
					}
					printf(
						'<img src="%1$s" class="prs-cover-img" id="prs-cover-img" alt="%2$s" />',
						esc_url( $cover_img_url ),
						esc_attr( $cover_alt )
					);
					?>
				<?php else : ?>
					<div id="prs-cover-placeholder" class="prs-cover-placeholder" role="img" aria-label="<?php esc_attr_e( 'Default book cover', 'politeia-reading' ); ?>">
						<h3 id="prs-book-title-placeholder" class="prs-cover-title"><?php echo esc_html__( 'Untitled Book', 'politeia-reading' ); ?></h3>
						<span id="prs-book-author-placeholder" class="prs-cover-author"><?php echo esc_html__( 'Unknown Author', 'politeia-reading' ); ?></span>
						<?php echo do_shortcode( '[prs_cover_button]' ); ?>
					</div>
				<?php endif; ?>
			</figure>
			<?php if ( $has_image ) : ?>
				<div class="prs-cover-overlay">
					<?php echo do_shortcode( '[prs_cover_button show_search="true"]' ); ?>
				</div>
			<?php endif; ?>
		</div>
		<?php /* DISABLED: Google Books/Open Library search
		<figcaption id="prs-cover-attribution-wrap" class="prs-book-cover__caption <?php echo $cover['source'] ? '' : 'is-hidden'; ?>" aria-hidden="<?php echo $cover['source'] ? 'false' : 'true'; ?>">
			<a id="prs-cover-attribution" class="prs-book-cover__link <?php echo $cover['source'] ? '' : 'is-hidden'; ?>" <?php echo $cover['source'] ? 'href="' . esc_url( $cover['source'] ) . '"' : ''; ?> target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'View on Google Books', 'politeia-reading' ); ?>
			</a>
		</figcaption>
		*/ ?>
	</div>
	<div id="prs-book-identity-slot" class="prs-book-identity-slot"></div>
</section>

<section id="progress-section">
	<div class="progress">
		<div class="progress-bar">
			<?php if ( $data['total_pages'] > 0 && ! empty( $data['density_sessions'] ) ) : ?>
				<canvas id="prs-reading-density-canvas"
					data-total-pages="<?php echo esc_attr( $data['total_pages'] ); ?>"
					data-sessions='<?php echo esc_attr( wp_json_encode( $data['density_sessions'] ) ); ?>'></canvas>
			<?php endif; ?>
		</div>
		<small class="prs-progress-text"><?php echo esc_html( sprintf( __( '%d%% completed', 'politeia-reading' ), (int) $data['progress_percent'] ) ); ?></small>
	</div>
</section>
