<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="prs-note-modal" id="prs-note-modal" aria-hidden="true">
	<div class="prs-note-modal__panel" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Rate emotions', 'politeia-reading' ); ?>">
		<div class="prs-note-modal__rows" id="prs-note-rows"></div>
		<div class="prs-note-modal__actions">
			<button class="prs-note-modal__reset" type="button" id="prs-note-reset"><?php esc_html_e( 'Reset', 'politeia-reading' ); ?></button>
			<button class="prs-note-modal__save is-disabled" type="button" id="prs-note-save" disabled="disabled"><?php esc_html_e( 'Save Emotional Rating', 'politeia-reading' ); ?></button>
		</div>
	</div>
</div>
