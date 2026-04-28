<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Template for the [politeia_add_book] shortcode.
 * Expects $context array from Politeia_Reading_Shortcode_Add_Book.
 */

$success                = $context['success'] ?? false;
$success_title          = $context['success_title'] ?? '';
$success_author         = $context['success_author'] ?? '';
$success_year           = $context['success_year'] ?? null;
$success_pages          = $context['success_pages'] ?? null;
$success_cover_url      = $context['success_cover_url'] ?? '';
$success_slug           = $context['success_slug'] ?? '';
$success_start_url      = $context['success_start_url'] ?? '';
$duplicate_message      = $context['duplicate_message'] ?? '';
$multiple_mode_content  = $context['multiple_mode_content'] ?? '';

static $modal_registered = false;

if ( ! $modal_registered ) {
	$modal_registered = true;

	ob_start();
	?>
	<div class="prs-add-book prs-add-book--modal">
		<div id="prs-add-book-modal"
			class="prs-add-book__modal"
			data-success="<?php echo esc_attr( $success ? '1' : '0' ); ?>"
			style="<?php echo esc_attr( $success ? 'display:flex;' : 'display:none;' ); ?>"
			role="dialog"
			aria-modal="true"
			aria-labelledby="<?php echo esc_attr( $success ? 'prs-add-book-success-title' : 'prs-add-book-form-title' ); ?>">
			<div class="prs-add-book__modal-content<?php echo $success ? ' prs-add-book__modal-content--success' : ''; ?>" onclick="event.stopPropagation();">
				<button type="button"
					class="prs-add-book__close"
					aria-label="<?php echo esc_attr__( 'Close dialog', 'politeia-reading' ); ?>"
					onclick="prsAddBookClose(event)">
					&times;
				</button>
				
				<div id="prs-add-book-success" class="prs-add-book__success"<?php echo $success ? '' : ' hidden'; ?>>
					<div class="prs-add-book__success-headline">
						<span class="prs-add-book__success-icon" aria-hidden="true"></span>
						<h2 id="prs-add-book-success-title" class="prs-add-book__success-heading">
							<?php echo esc_html__( 'Book Added Successfully!', 'politeia-reading' ); ?>
						</h2>
					</div>
					<hr class="prs-add-book__success-rule" />
					<?php if ( $success_title ) : ?>
						<div class="prs-add-book__success-title"><?php echo esc_html( $success_title ); ?></div>
					<?php endif; ?>
					<?php if ( $success_author ) : ?>
						<div class="prs-add-book__success-author">
							<?php
							printf(
								/* translators: %s: author name. */
								esc_html__( 'by %s', 'politeia-reading' ),
								esc_html( $success_author )
							);
							?>
						</div>
					<?php endif; ?>
					<?php if ( $success_start_url ) : ?>
						<a class="prs-add-book__success-action" href="<?php echo esc_url( $success_start_url ); ?>">
							<?php esc_html_e( 'START READING', 'politeia-reading' ); ?>
						</a>
					<?php endif; ?>
					<?php if ( null !== $success_year || null !== $success_pages ) : ?>
						<hr class="prs-add-book__success-rule" />
					<?php endif; ?>
				</div>

				<div id="prs-add-book-mode-switch" class="prs-add-book__mode-switch"<?php echo $success ? ' hidden' : ''; ?>>
					<button type="button"
						class="prs-add-book__mode-button is-active"
						data-mode="single"
						aria-pressed="true">
						<?php esc_html_e( 'Single', 'politeia-reading' ); ?>
					</button>
					<span class="prs-add-book__mode-separator" aria-hidden="true">|</span>
					<button type="button"
						class="prs-add-book__mode-button"
						data-mode="multiple"
						aria-pressed="false">
						<?php esc_html_e( 'Multiple', 'politeia-reading' ); ?>
					</button>
				</div>

				<form id="prs-add-book-form"
					class="prs-form"
					method="post"
					enctype="multipart/form-data"
					action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"<?php echo $success ? ' hidden' : ''; ?>>
					<h2 id="prs-add-book-form-title" class="prs-add-book__heading"<?php echo $success ? ' hidden' : ''; ?>>
						<?php echo esc_html__( 'Add to Library', 'politeia-reading' ); ?>
					</h2>
					<?php
					$auto_fill_note = ( 0 === strpos( determine_locale(), 'es' ) )
						? 'Revisa que los datos sean correctos antes de guardar'
						: 'Check that data is correct before saving';
					?>
					<div id="prs-add-book-auto-fill-note" class="prs-add-book__auto-fill-note" hidden>
						<?php echo esc_html( $auto_fill_note ); ?>
					</div>
					<?php wp_nonce_field( 'prs_add_book', 'prs_nonce' ); ?>
					<input type="hidden" name="action" value="prs_add_book_submit" />
					<input type="hidden" id="prs_cover_url" name="prs_cover_url" value="" />

					<table class="prs-form__table">
						<tbody>
							<tr>
								<th scope="row">
									<label class="prs-form__label" for="prs_cover">
										<span class="prs-form__label-text"><?php esc_html_e( 'Cover', 'politeia-reading' ); ?>:</span>
										<span class="prs-form__label-note"><?php esc_html_e( '(jpg/png/webp)', 'politeia-reading' ); ?></span>
									</label>
								</th>
								<td>
									<label class="prs-form__file-control" for="prs_cover">
										<input
											type="file"
											id="prs_cover"
											name="prs_cover"
											accept=".jpg,.jpeg,.png,.webp"
											class="prs-form__file-input"
										/>
										<div
											id="prs_cover_prompt"
											class="prs-form__file-prompt"
											data-default-label="<?php echo esc_attr__( 'Upload Book Cover', 'politeia-reading' ); ?>"
											data-change-label="<?php echo esc_attr__( 'Change Book Cover', 'politeia-reading' ); ?>"
										>
											<span class="prs-form__file-icon" aria-hidden="true"></span>
											<span class="prs-form__file-text"><?php esc_html_e( 'Upload Book Cover', 'politeia-reading' ); ?></span>
											<span class="prs-form__file-subtext"><?php esc_html_e( 'Drag photo here', 'politeia-reading' ); ?></span>
										</div>
										<?php
										$prs_cover_placeholder = POLITEIA_READING_URL . 'assets/svg/upload_file_24dp_1F1F1F_FILL0_wght400_GRAD0_opsz24.svg';
										?>
										<div id="prs_cover_preview" class="prs-form__file-preview" hidden>
											<img src="<?php echo esc_url( $prs_cover_placeholder ); ?>"
												decoding="async"
												alt="<?php echo esc_attr__( 'Selected book cover preview', 'politeia-reading' ); ?>"
												data-placeholder-src="<?php echo esc_attr( $prs_cover_placeholder ); ?>" />
										</div>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row" aria-hidden="true"></th>
								<td>
									<div class="prs-add-book__field prs-add-book__field--title">
										<?php
										$title_placeholder = ( 0 === strpos( determine_locale(), 'es' ) )
											? 'Título libro'
											: 'Book title';
										$title_not_that = ( 0 === strpos( determine_locale(), 'es' ) )
											? 'No es ese'
											: 'Is not that';
										?>
										<input
											type="text"
											id="prs_title"
											name="prs_title"
											autocomplete="off"
											required
											placeholder="<?php echo esc_attr( $title_placeholder ); ?>"
											aria-label="<?php echo esc_attr( $title_placeholder ); ?>"
										/>
										<button type="button"
											id="prs_title_not_that"
											class="prs-add-book__not-that"
											aria-label="<?php echo esc_attr( $title_not_that ); ?>"
											hidden>
											<?php echo esc_html( $title_not_that ); ?>
										</button>
										<div
											id="prs_title_suggestions"
											class="prs-add-book__suggestions"
											role="listbox"
											aria-label="<?php esc_attr_e( 'Book suggestions', 'politeia-reading' ); ?>"
											aria-hidden="true"
										></div>
										<div
											id="prs_add_book_duplicate"
											class="prs-add-book__inline-warning"
											data-default-message="<?php echo esc_attr__( 'Already in your library', 'politeia-reading' ); ?>"
											<?php echo $duplicate_message ? '' : 'hidden'; ?>
										><?php echo esc_html( $duplicate_message ); ?></div>
									</div>
								</td>
							</tr>
							<tr>
								<th scope="row" aria-hidden="true"></th>
								<td>
									<?php $remove_author_label = esc_attr__( 'Remove author', 'politeia-reading' ); ?>
									<div
										id="prs_author_fields"
										class="prs-add-book__authors"
										data-remove-label="<?php echo $remove_author_label; ?>">
										<div
											id="prs_author_list"
											class="prs-add-book__author-list"
											role="list"
											aria-live="polite"
											aria-relevant="additions removals"
										></div>
										<button
											type="button"
											id="prs_author_add"
											class="prs-add-book__author-add"
											aria-label="<?php echo esc_attr__( 'Add author', 'politeia-reading' ); ?>"
											hidden
										><?php echo esc_html__( 'Edit', 'politeia-reading' ); ?></button>
										<div class="prs-add-book__author-input-wrapper">
											<input
												type="text"
												id="prs_author_input"
												name="prs_author[]"
												class="prs-add-book__author-input"
												autocomplete="off"
												required
												aria-describedby="prs_author_hint"
												placeholder="<?php echo esc_attr__( 'Author', 'politeia-reading' ); ?>"
												aria-label="<?php echo esc_attr__( 'Author', 'politeia-reading' ); ?>"
											/>
										</div>
										<div id="prs_author_hidden" class="prs-add-book__author-hidden" aria-hidden="true"></div>
										<p id="prs_author_hint" class="prs-add-book__author-hint">
											<?php echo esc_html__( 'Separate multiple authors with commas.', 'politeia-reading' ); ?>
										</p>
									</div>
								</td>
							</tr>
							<tr>
								<th scope="row" aria-hidden="true"></th>
								<td>
									<div class="prs-add-book__field-inline">
										<div class="prs-add-book__field-group">
											<input type="number"
												id="prs_year"
												name="prs_year"
												min="1400"
												max="<?php echo esc_attr( (int) date( 'Y' ) + 1 ); ?>"
												placeholder="<?php echo esc_attr__( 'Year', 'politeia-reading' ); ?>"
												aria-label="<?php echo esc_attr__( 'Year', 'politeia-reading' ); ?>"
											/>
											<span class="prs-add-book__field-label" data-for="prs_year" hidden>
												<?php esc_html_e( 'Year', 'politeia-reading' ); ?>
											</span>
										</div>
										<button
											type="button"
											id="prs_year_edit"
											class="prs-add-book__field-edit"
											aria-label="<?php echo esc_attr__( 'Edit year', 'politeia-reading' ); ?>"
											hidden
										><?php echo esc_html__( 'Edit', 'politeia-reading' ); ?></button>
									</div>
								</td>
							</tr>
							<tr>
								<th scope="row" aria-hidden="true"></th>
								<td>
									<div class="prs-add-book__field-inline prs-add-book__field-inline--split">
										<div class="prs-add-book__field prs-add-book__field--isbn prs-add-book__field-group">
											<input type="text"
												id="prs_isbn"
												name="prs_isbn"
												inputmode="text"
												autocomplete="off"
												placeholder="<?php echo esc_attr__( 'ISBN', 'politeia-reading' ); ?>"
												aria-label="<?php echo esc_attr__( 'ISBN', 'politeia-reading' ); ?>"
											/>
											<div
												id="prs_isbn_suggestions"
												class="prs-add-book__suggestions"
												role="listbox"
												aria-label="<?php esc_attr_e( 'ISBN suggestions', 'politeia-reading' ); ?>"
												aria-hidden="true"
											></div>
											<span class="prs-add-book__field-label" data-for="prs_isbn" hidden>
												<?php esc_html_e( 'ISBN', 'politeia-reading' ); ?>
											</span>
										</div>
										<div class="prs-add-book__field-group">
											<input type="number"
												id="prs_pages"
												name="prs_pages"
												min="1"
												step="1"
												inputmode="numeric"
												pattern="[0-9]*"
												placeholder="<?php echo esc_attr__( 'Pages', 'politeia-reading' ); ?>"
												aria-label="<?php echo esc_attr__( 'Pages', 'politeia-reading' ); ?>"
											/>
											<span class="prs-add-book__field-label" data-for="prs_pages" hidden>
												<?php esc_html_e( 'Pages', 'politeia-reading' ); ?>
											</span>
										</div>
									</div>
									<button
										type="button"
										id="prs_isbn_edit"
										class="prs-add-book__field-edit"
										aria-label="<?php echo esc_attr__( 'Edit ISBN', 'politeia-reading' ); ?>"
										hidden
									><?php echo esc_html__( 'Edit', 'politeia-reading' ); ?></button>
									<button
										type="button"
										id="prs_pages_edit"
										class="prs-add-book__field-edit"
										aria-label="<?php echo esc_attr__( 'Edit pages', 'politeia-reading' ); ?>"
										hidden
									><?php echo esc_html__( 'Edit', 'politeia-reading' ); ?></button>
								</td>
							</tr>
							<tr class="prs-form__actions">
								<td colspan="2">
									<button class="prs-btn prs-add-book__submit" id="prs-add-book-submit" type="submit">
										<span class="prs-add-book__submit-text"><?php esc_html_e( 'Save to My Library', 'politeia-reading' ); ?></span>
										<span class="prs-add-book__submit-spinner" aria-hidden="true"></span>
									</button>
								</td>
							</tr>
						</tbody>
					</table>
				</form>
				
				<div id="prs-add-book-multiple" class="prs-add-book__multiple" hidden>
					<h2 id="prs-add-book-multiple-title" class="prs-add-book__heading">
						<?php echo esc_html__( 'Add Multiple Books', 'politeia-reading' ); ?>
					</h2>
					<?php if ( $multiple_mode_content ) : ?>
						<?php echo $multiple_mode_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php else : ?>
						<p class="prs-add-book__mode-unavailable">
							<?php echo esc_html__( 'The multiple entry mode is currently unavailable.', 'politeia-reading' ); ?>
						</p>
					<?php endif; ?>
				</div>

				<script>
					function prsAddBookClose(event) {
						if (event) {
							if (typeof event.preventDefault === 'function') {
								event.preventDefault();
							}
							if (typeof event.stopPropagation === 'function') {
								event.stopPropagation();
							}
						}
						var modal = document.getElementById('prs-add-book-modal');
						var form = document.getElementById('prs-add-book-form');
						if (form) {
							form.reset();
							form.dispatchEvent(new Event('reset', { bubbles: true }));
						}
						if (modal) {
							modal.style.display = 'none';
							modal.setAttribute('data-success', '0');
						}
					}
					( function () {
						var fileInput = document.getElementById('prs_cover');
						if (!fileInput) {
							return;
						}

						var trigger = document.getElementById('prs_cover_prompt');
						var triggerText = trigger ? trigger.querySelector('.prs-form__file-text') : null;
						var previewWrapper = document.getElementById('prs_cover_preview');
						var previewImage = previewWrapper ? previewWrapper.querySelector('img') : null;
						var previewPlaceholder = previewImage ? previewImage.getAttribute('data-placeholder-src') : '';
						var defaultLabel = trigger ? trigger.getAttribute('data-default-label') : '';
						var changeLabel = trigger ? trigger.getAttribute('data-change-label') : '';
						var form = fileInput.form;
						var controlWrap = previewWrapper ? previewWrapper.closest('.prs-form__file-control') : null;

						var resetPreview = function () {
							if (previewWrapper) {
								previewWrapper.hidden = !previewPlaceholder;
								previewWrapper.classList.toggle('is-placeholder', !!previewPlaceholder);
							}
							if (previewImage) {
								if (previewPlaceholder) {
									previewImage.src = previewPlaceholder;
								} else {
									previewImage.removeAttribute('src');
								}
							}
							if (triggerText && defaultLabel) {
								triggerText.textContent = defaultLabel;
							}
							if (controlWrap) {
								controlWrap.classList.remove('is-has-preview');
							}
						};

						if (form) {
							form.addEventListener('reset', function () {
								window.setTimeout(resetPreview);
							});
						}

						fileInput.addEventListener('change', function () {
							if (this.files && this.files[0]) {
								var reader = new FileReader();
								reader.onload = function (event) {
									if (previewWrapper && previewImage) {
										previewImage.src = event.target && event.target.result ? event.target.result : '';
										previewWrapper.hidden = false;
										previewWrapper.classList.remove('is-placeholder');
									}
								};
								reader.readAsDataURL(this.files[0]);
								if (triggerText && changeLabel) {
									triggerText.textContent = changeLabel;
								}
								if (controlWrap) {
									controlWrap.classList.add('is-has-preview');
								}
							} else {
								resetPreview();
							}
						});

						resetPreview();
					}() );
				</script>
			</div>
		</div>
	</div>
	<?php
	$modal_markup = ob_get_clean();

	add_action(
		'wp_footer',
		function () use ( $modal_markup ) {
			echo $modal_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	);
}

if ( ! empty( $_GET['prs_error'] ) && $_GET['prs_error'] === '1' ) {
	echo '<div class="prs-notice prs-notice--error">' .
	esc_html__( 'There was a problem adding the book.', 'politeia-reading' ) .
	'</div>';
}
?>
<div class="prs-add-book">
	<button
		type="button"
		class="prs-btn"
		id="prs-add-book-button"
		aria-controls="prs-add-book-modal"
		onclick="document.getElementById('prs-add-book-modal').style.display='flex'">
		<img
			class="prs-add-book-button__icon"
			src="<?php echo esc_url( POLITEIA_READING_URL . 'assets/svg/book_4_24dp_1F1F1F_FILL0_wght400_GRAD0_opsz24.svg' ); ?>"
			alt=""
			aria-hidden="true"
		/>
		<?php echo esc_html__( 'Add Book', 'politeia-reading' ); ?>
	</button>
</div>
