<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Template for the [politeia_start_reading] shortcode.
 * Expects $context array from Politeia_Reading_Shortcode_Start_Reading.
 */
$book_id = $context['book_id'];
$user_id = $context['user_id'];
$book_title = $context['book_title'];
$book_author = $context['book_author'];
$last_end_page = $context['last_end_page'];
?>
<div class="prs-sr" data-book-id="<?php echo (int) $book_id; ?>"
	data-prs-sr="<?php echo esc_attr( wp_json_encode( $context ) ); ?>">
	
	<div class="prs-sr-header">
		<div class="prs-sr-kicker"><?php esc_html_e( 'Session recorder', 'politeia-reading' ); ?></div>
		<?php if ( $book_title || $book_author ) : ?>
			<div class="prs-sr-meta">
				<?php if ( $book_title ) : ?>
					<span class="prs-sr-meta-title"><?php echo esc_html( $book_title ); ?></span>
				<?php endif; ?>
				<?php if ( $book_author ) : ?>
					<span class="prs-sr-meta-author">
						<?php echo esc_html( sprintf( __( 'by %s', 'politeia-reading' ), $book_author ) ); ?>
					</span>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>

	<?php if ( $last_end_page ) : ?>
		<div class="prs-sr-last" data-role="sr-last">
			<?php esc_html_e( 'Last session page', 'politeia-reading' ); ?>:
			<strong><?php echo (int) $last_end_page; ?></strong>
		</div>
	<?php endif; ?>

	<!-- Success Flash block -->
	<div id="prs-sr-flash" class="prs-sr-flash-block" style="display:none;" role="status" aria-live="polite"
		data-session-id="" data-book-id="<?php echo esc_attr( $book_id ); ?>"
		data-user-id="<?php echo esc_attr( $user_id ); ?>">
		<div class="prs-sr-flash-inner">
			<div id="prs-sr-summary" style="font-family: 'Poppins', sans-serif;">
				<span class="prs-sr-flash-icon prs-sr-flash-icon--check" aria-hidden="true"></span>
				<h2><?php esc_html_e( 'Great job!', 'politeia-reading' ); ?></h2>
				<h3>
					<?php
					printf(
						/* translators: 1: pages read, 2: time spent. */
						wp_kses_post( __( 'You read %1$s in %2$s.', 'politeia-reading' ) ),
						'<span id="prs-sr-flash-pages">—</span>',
						'<span id="prs-sr-flash-time">—</span>'
					);
					?>
				</h3>
				<div class="prs-sr-flash-sub">
					<?php esc_html_e( 'See you soon to keep reading this book.', 'politeia-reading' ); ?>
				</div>
				<button type="button" id="prs-add-note-btn" class="prs-btn prs-add-note-btn"
					aria-controls="prs-note-panel" aria-expanded="false">
					<span class="prs-add-note-text"><?php esc_html_e( 'Add Note', 'politeia-reading' ); ?></span>
				</button>
			</div>

			<div id="prs-note-panel" class="prs-note-panel" style="display:none;">
				<div class="note-editor-panel" role="group"
					aria-label="<?php esc_attr_e( 'Session note editor', 'politeia-reading' ); ?>">
					<div class="note-toolbar" role="toolbar"
						aria-label="<?php esc_attr_e( 'Formatting options', 'politeia-reading' ); ?>">
						<button type="button" class="tool-button bold" data-command="bold"
							title="<?php esc_attr_e( 'Bold', 'politeia-reading' ); ?>">B</button>
						<button type="button" class="tool-button italic" data-command="italic"
							title="<?php esc_attr_e( 'Italic', 'politeia-reading' ); ?>">I</button>
						<button type="button" class="tool-button" data-command="bullet"
							title="<?php esc_attr_e( 'Bullet list', 'politeia-reading' ); ?>">•</button>
					</div>
					<?php $note_placeholder = esc_attr__( 'Write your thoughts about this session…', 'politeia-reading' ); ?>
					<div id="prs-note-editor" class="note-textarea editor-area" contenteditable="true" role="textbox"
						aria-multiline="true" spellcheck="true" data-placeholder="<?php echo $note_placeholder; ?>"
						placeholder="<?php echo $note_placeholder; ?>"></div>
					<div class="note-limit-warning" role="status" aria-live="polite"
						style="display:none; font-size:12px; color:#b91c1c; text-align:center;">
						<?php esc_html_e( 'You have reached the 3000 character limit.', 'politeia-reading' ); ?>
					</div>
				</div>
				<div class="note-actions">
					<button type="button" id="prs-save-note-btn" class="prs-btn">
						<span class="prs-btn-text"><?php esc_html_e( 'Save Note', 'politeia-reading' ); ?></span>
					</button>
					<button type="button" id="prs-cancel-note-btn" class="prs-btn prs-btn-secondary">
						<?php esc_html_e( 'Cancel', 'politeia-reading' ); ?>
					</button>
				</div>
			</div>
		</div>
	</div>

	<!-- Form Wrapper -->
	<div id="prs-sr-formwrap" class="prs-sr-form">
		<div id="prs-sr-row-start" class="prs-sr-field" data-role="sr-field">
			<input type="number" min="1" id="prs-sr-start-page" class="prs-sr-input" placeholder="1" />
			<span id="prs-sr-start-page-view" class="prs-sr-view"></span>
			<label for="prs-sr-start-page"
				class="prs-sr-label"><?php esc_html_e( 'Start page', 'politeia-reading' ); ?>*</label>
		</div>

		<div id="prs-sr-row-chapter" class="prs-sr-field" data-role="sr-field">
			<input type="text" id="prs-sr-chapter" class="prs-sr-input"
				placeholder="<?php esc_attr_e( 'Chapter', 'politeia-reading' ); ?>" />
			<span id="prs-sr-chapter-view" class="prs-sr-view"></span>
			<label for="prs-sr-chapter" class="prs-sr-label"><?php esc_html_e( 'Chapter', 'politeia-reading' ); ?></label>
		</div>

		<div id="prs-sr-row-timer" class="prs-sr-timer-row">
			<div class="prs-sr-clock" aria-hidden="true" style="display:none;">
				<canvas class="prs-sr-stardust" aria-hidden="true"></canvas>
				<div class="prs-sr-clock-inner"></div>
				<svg viewBox="0 0 200 200">
					<defs>
						<linearGradient id="prs-sr-gold-<?php echo (int) $book_id; ?>" x1="0" y1="0" x2="200" y2="200"
							gradientUnits="userSpaceOnUse">
							<stop offset="0%" stop-color="#8A6B1E" />
							<stop offset="50%" stop-color="#C79F32" />
							<stop offset="100%" stop-color="#E9D18A" />
						</linearGradient>
					</defs>
					<path id="prs-sr-progress" d="" fill="url(#prs-sr-gold-<?php echo (int) $book_id; ?>)" />
					<circle cx="100" cy="5" r="1.2" fill="#8B7355" opacity="0.4" />
					<circle cx="195" cy="100" r="1.2" fill="#8B7355" opacity="0.4" />
					<circle cx="100" cy="195" r="1.2" fill="#8B7355" opacity="0.4" />
					<circle cx="5" cy="100" r="1.2" fill="#8B7355" opacity="0.4" />
				</svg>
				<div class="prs-sr-clock-center"></div>
			</div>
		</div>

		<div id="prs-sr-row-needs-pages" style="display:none;">
			<div class="prs-sr-row-needs-pages">
				<svg class="prs-warning-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
					stroke="#EAB308" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
					<line x1="12" y1="9" x2="12" y2="13"></line>
					<line x1="12" y1="17" x2="12.01" y2="17"></line>
				</svg>
				<span><?php esc_html_e( 'To start a session, set the total Pages for this book in the info panel.', 'politeia-reading' ); ?></span>
			</div>
		</div>

		<div id="prs-sr-row-actions" class="prs-sr-actions">
			<a href="#" role="button" id="prs-sr-start" class="prs-sr-btn prs-sr-btn--start" aria-disabled="true" aria-label="<?php esc_attr_e( 'Start Reading', 'politeia-reading' ); ?>">
				<span class="material-symbols-outlined prs-sr-start-icon" aria-hidden="true">play_arrow</span>
			</a>
			<a href="#" role="button" id="prs-sr-stop" class="prs-sr-btn prs-sr-btn--stop" style="display:none;"
				aria-disabled="false">
				<span class="prs-sr-btn-icon">&#9632;</span>
				<span class="prs-sr-btn-label"><?php esc_html_e( 'Stop Reading', 'politeia-reading' ); ?></span>
			</a>
		</div>

		<div id="prs-sr-row-end" class="prs-sr-field" style="display:none;">
			<input type="number" min="1" id="prs-sr-end-page" class="prs-sr-input" placeholder="000" />
			<label for="prs-sr-end-page"
				class="prs-sr-label"><?php esc_html_e( 'End Page', 'politeia-reading' ); ?>*</label>
			<div id="prs-sr-end-error" class="prs-sr-end-error">
				<?php esc_html_e( 'Page number cannot be less than the starting page.', 'politeia-reading' ); ?>
			</div>
		</div>

		<div id="prs-sr-row-save" class="prs-sr-actions" style="display:none;">
			<a href="#" role="button" id="prs-sr-save" class="prs-sr-btn prs-sr-btn--save" aria-disabled="true">
				<span class="prs-sr-btn-icon">&#10003;</span>
				<span class="prs-sr-btn-label"><?php esc_html_e( 'Save Session', 'politeia-reading' ); ?></span>
			</a>
		</div>
	</div>

	<div id="prs-sr-limit-overlay" class="prs-sr-limit-overlay" role="dialog" aria-modal="true"
		aria-label="<?php esc_attr_e( 'Session limit reached', 'politeia-reading' ); ?>">
		<div class="prs-sr-limit-card">
			<div class="prs-sr-limit-title"><?php esc_html_e( 'Session limit', 'politeia-reading' ); ?></div>
			<p class="prs-sr-limit-message" id="prs-sr-limit-message">
				<?php esc_html_e( 'This session has reached the maximum length. Confirm to continue or it will stop automatically in 20 minutes.', 'politeia-reading' ); ?>
			</p>
			<div class="prs-sr-limit-actions">
				<button type="button" id="prs-sr-limit-stop" class="prs-sr-limit-btn prs-sr-limit-btn--secondary">
					<?php esc_html_e( 'Stop now', 'politeia-reading' ); ?>
				</button>
				<button type="button" id="prs-sr-limit-continue" class="prs-sr-limit-btn prs-sr-limit-btn--primary">
					<?php esc_html_e( 'Continue', 'politeia-reading' ); ?>
				</button>
			</div>
		</div>
	</div>
</div>
