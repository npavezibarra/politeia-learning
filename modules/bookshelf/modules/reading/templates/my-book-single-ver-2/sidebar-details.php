<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $data Prepared by controller */
$book = $data['book'];
$ub   = $data['ub'];
?>
<section id="book-details-section" class="prs-sidebar-block">
	<h4 style="margin: 0 0 8px; font-size: 18px; color: #000;">
		<?php esc_html_e( 'Book Details', 'politeia-reading' ); ?>
	</h4>
	<ul class="prs-details">
		<li class="prs-detail-divider" aria-hidden="true"><hr /></li>
		<li id="fld-pages" class="prs-field">
			<strong><?php esc_html_e( 'Pages:', 'politeia-reading' ); ?></strong>
			<span id="pages-view"><?php echo $ub->pages ? (int) $ub->pages : '—'; ?></span>
			<a href="#" id="pages-edit" class="prs-inline-actions"><?php esc_html_e( 'edit', 'politeia-reading' ); ?></a>
			<input type="number" id="pages-input" class="prs-inline-input" min="1" value="<?php echo $ub->pages ? (int) $ub->pages : ''; ?>" style="display:none;width:80px;" />
			<div id="pages-hint" class="prs-help" style="display:none;margin-top:4px;">
				<?php esc_html_e( 'Press Enter to save', 'politeia-reading' ); ?>
			</div>
		</li>
		<li id="fld-isbn" class="prs-field">
			<strong><?php esc_html_e( 'ISBN:', 'politeia-reading' ); ?></strong>
			<span id="isbn-view"><?php echo ! empty( $book->isbn ) ? esc_html( $book->isbn ) : '—'; ?></span>
			<a href="#" id="isbn-edit" class="prs-inline-actions" aria-label="<?php echo esc_attr__( 'Edit ISBN', 'politeia-reading' ); ?>"><?php esc_html_e( 'edit', 'politeia-reading' ); ?></a>
			<input type="text" id="isbn-input" class="prs-inline-input" inputmode="text" value="<?php echo ! empty( $book->isbn ) ? esc_attr( $book->isbn ) : ''; ?>" style="display:none;width:140px;" />
			<div id="isbn-hint" class="prs-help" style="display:none;margin-top:4px;">
				<?php esc_html_e( 'Press Enter to save', 'politeia-reading' ); ?>
			</div>
		</li>
		<li>
			<strong><?php esc_html_e( 'Published Date:', 'politeia-reading' ); ?></strong>
			<?php echo $book->year ? esc_html( (string) $book->year ) : '—'; ?>
		</li>
		<li>
			<label for="prs-type-book" style="font-weight: 600; margin-right: 4px; font-size: 13px;"><?php esc_html_e( 'Format:', 'politeia-reading' ); ?></label>
			<select id="prs-type-book" class="prs-type-book__select">
				<option value="" <?php selected( $ub->type_book, '' ); ?>><?php esc_html_e( 'Not specified', 'politeia-reading' ); ?></option>
				<option value="d" <?php selected( $ub->type_book, 'd' ); ?>><?php esc_html_e( 'Digital', 'politeia-reading' ); ?></option>
				<option value="p" <?php selected( $ub->type_book, 'p' ); ?>><?php esc_html_e( 'Printed', 'politeia-reading' ); ?></option>
			</select>
			<span id="type-book-status" class="prs-help" aria-live="polite"></span>
		</li>
		<li class="prs-detail-divider" aria-hidden="true"><hr /></li>
		<li id="fld-purchase-date" class="prs-field">
			<strong><?php esc_html_e( 'Purchase Date:', 'politeia-reading' ); ?></strong><br />
			<span id="purchase-date-view"><?php echo $ub->purchase_date ? esc_html( $ub->purchase_date ) : '—'; ?></span>
			<a href="#" id="purchase-date-edit" class="prs-inline-actions"><?php esc_html_e( 'edit', 'politeia-reading' ); ?></a>
			<span id="purchase-date-form" style="display:none;" class="prs-inline-actions">
				<input type="date" id="purchase-date-input" value="<?php echo $ub->purchase_date ? esc_attr( $ub->purchase_date ) : ''; ?>" />
				<button type="button" id="purchase-date-save" class="prs-btn"><?php esc_html_e( 'Save', 'politeia-reading' ); ?></button>
				<button type="button" id="purchase-date-cancel" class="prs-btn"><?php esc_html_e( 'Cancel', 'politeia-reading' ); ?></button>
				<span id="purchase-date-status" class="prs-help"></span>
			</span>
		</li>
		<li id="fld-purchase-channel" class="prs-field">
			<strong><?php esc_html_e( 'Purchase Channel:', 'politeia-reading' ); ?></strong><br />
			<span id="purchase-channel-view">
				<?php
				$label = '—';
				if ( $ub->purchase_channel ) {
					$label = ( 'online' === $ub->purchase_channel ) ? __( 'Online', 'politeia-reading' ) : __( 'Store', 'politeia-reading' );
					if ( $ub->purchase_place ) {
						$label .= ' — ' . $ub->purchase_place;
					}
				}
				echo esc_html( $label );
				?>
			</span>
			<a href="#" id="purchase-channel-edit" class="prs-inline-actions"><?php esc_html_e( 'edit', 'politeia-reading' ); ?></a>
			<span id="purchase-channel-form" style="display:none;" class="prs-inline-actions">
				<select id="purchase-channel-select">
					<option value=""><?php esc_html_e( 'Select…', 'politeia-reading' ); ?></option>
					<option value="online" <?php selected( $ub->purchase_channel, 'online' ); ?>><?php esc_html_e( 'Online', 'politeia-reading' ); ?></option>
					<option value="store" <?php selected( $ub->purchase_channel, 'store' ); ?>><?php esc_html_e( 'Store', 'politeia-reading' ); ?></option>
				</select>
				<input type="text" id="purchase-place-input" placeholder="<?php esc_attr_e( 'Which?', 'politeia-reading' ); ?>" value="<?php echo $ub->purchase_place ? esc_attr( $ub->purchase_place ) : ''; ?>" style="display: <?php echo $ub->purchase_channel ? 'inline-block' : 'none'; ?>;" />
				<button type="button" id="purchase-channel-save" class="prs-btn"><?php esc_html_e( 'Save', 'politeia-reading' ); ?></button>
				<button type="button" id="purchase-channel-cancel" class="prs-btn"><?php esc_html_e( 'Cancel', 'politeia-reading' ); ?></button>
				<span id="purchase-channel-status" class="prs-help"></span>
			</span>
		</li>
	</ul>
</section>
