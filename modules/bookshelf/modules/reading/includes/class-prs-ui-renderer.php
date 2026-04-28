<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles UI rendering for books and overlays.
 */
class Politeia_Reading_UI_Renderer {

	/**
	 * Get owning labels.
	 */
	public static function get_owning_labels() {
		return array(
			'borrowing'    => __( 'Borrowing to:', 'politeia-reading' ),
			'borrowed'     => __( 'Borrowed from:', 'politeia-reading' ),
			'sold'         => __( 'Sold to:', 'politeia-reading' ),
			'lost'         => __( 'Last borrowed to:', 'politeia-reading' ),
			'sold_on'      => __( 'Sold on:', 'politeia-reading' ),
			'lost_date'    => __( 'Lost:', 'politeia-reading' ),
			'location'     => __( 'Location', 'politeia-reading' ),
			'in_shelf'     => __( 'In Shelf', 'politeia-reading' ),
			'not_in_shelf' => __( 'Not In Shelf', 'politeia-reading' ),
			'unknown'      => __( 'Unknown', 'politeia-reading' ),
		);
	}

	/**
	 * Render owning overlay.
	 */
	public static function render_owning_overlay( $args = array() ) {
		static $rendered = false;

		if ( $rendered ) {
			return;
		}

		$defaults = array(
			'heading' => __( 'Borrowing to:', 'politeia-reading' ),
		);

		$args     = wp_parse_args( $args, $defaults );
		$heading  = is_string( $args['heading'] ) ? $args['heading'] : '';
		$rendered = true;

		?>
		<div id="owning-overlay" class="prs-overlay" style="display:none;">
			<div class="prs-overlay-backdrop"></div>

			<div class="prs-overlay-content">
				<h2 id="owning-overlay-title"><?php echo esc_html( $heading ); ?></h2>

				<input type="text" id="owning-overlay-name" class="prs-contact-input" placeholder="<?php echo esc_attr__( 'Name', 'politeia-reading' ); ?>" required>
				<input type="email" id="owning-overlay-email" class="prs-contact-input" placeholder="<?php echo esc_attr__( 'Email', 'politeia-reading' ); ?>" required>
				<input type="number" id="owning-overlay-amount" class="prs-contact-input" placeholder="<?php echo esc_attr__( 'Amount (e.g. 12000)', 'politeia-reading' ); ?>" step="0.01" style="display:none;">

				<div class="prs-overlay-actions">
					<button type="button" id="owning-overlay-confirm" class="prs-btn"><?php esc_html_e( 'Confirm', 'politeia-reading' ); ?></button>
					<button type="button" id="owning-overlay-cancel" class="prs-btn prs-btn-secondary"><?php esc_html_e( 'Cancel', 'politeia-reading' ); ?></button>
				</div>

				<span id="owning-overlay-status" class="prs-help"></span>
			</div>
		</div>
		<div id="bought-overlay" class="prs-overlay" style="display:none;">
			<div class="prs-overlay-backdrop"></div>
			<div class="prs-overlay-content" style="max-width:360px;">
				<h2><?php esc_html_e( 'Confirm Re-acquisition', 'politeia-reading' ); ?></h2>
				<p><?php esc_html_e( 'You are marking this book as Bought Again. It will return to your shelf and become editable.', 'politeia-reading' ); ?></p>
				<div class="prs-overlay-actions">
					<button type="button" id="bought-overlay-confirm" class="prs-btn"><?php esc_html_e( 'Confirm', 'politeia-reading' ); ?></button>
					<button type="button" id="bought-overlay-cancel" class="prs-btn prs-btn-secondary"><?php esc_html_e( 'Cancel', 'politeia-reading' ); ?></button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render a book row for the library table.
	 */
	public static function render_book_row( $book, $context = array() ) {
		if ( ! $book ) {
			return '';
		}

		$defaults = array(
			'user_id'       => get_current_user_id(),
			'owning_labels' => self::get_owning_labels(),
		);

		$context = wp_parse_args( $context, $defaults );

		$user_id       = isset( $context['user_id'] ) ? (int) $context['user_id'] : get_current_user_id();
		$owning_labels = isset( $context['owning_labels'] ) && is_array( $context['owning_labels'] ) ? $context['owning_labels'] : self::get_owning_labels();

		$labels = wp_parse_args(
			$owning_labels,
			array(
				'borrowing'    => __( 'Borrowing to:', 'politeia-reading' ),
				'borrowed'     => __( 'Borrowed from:', 'politeia-reading' ),
				'sold'         => __( 'Sold to:', 'politeia-reading' ),
				'lost'         => __( 'Last borrowed to:', 'politeia-reading' ),
				'location'     => __( 'Location', 'politeia-reading' ),
				'in_shelf'     => __( 'In Shelf', 'politeia-reading' ),
				'not_in_shelf' => __( 'Not In Shelf', 'politeia-reading' ),
				'unknown'      => __( 'Unknown', 'politeia-reading' ),
			)
		);

		$label_borrowing    = $labels['borrowing'];
		$label_borrowed     = $labels['borrowed'];
		$label_sold         = $labels['sold'];
		$label_lost         = $labels['lost'];
		$label_location     = $labels['location'];
		$label_in_shelf     = $labels['in_shelf'];
		$label_not_in_shelf = $labels['not_in_shelf'];
		$label_unknown      = $labels['unknown'];

		$authors_value = isset( $book->authors ) ? (string) $book->authors : '';
		$slug          = $book->slug ? $book->slug : Politeia_Reading_Book_Utils::generate_book_slug( $book->title, $book->year ?? null );
		$url           = home_url( '/my-books/my-book-' . $slug );

		$year            = $book->year ? (int) $book->year : null;
		$pages           = $book->pages ? (int) $book->pages : null;
		$book_total_page = isset( $book->book_total_pages ) ? (int) $book->book_total_pages : 0;
		$effective_pages = $book_total_page > 0 ? $book_total_page : ( $pages ?? 0 );
		$progress        = 0;

		$owning_status   = isset( $book->owning_status ) ? (string) $book->owning_status : '';
		$row_owning_attr = $owning_status ? $owning_status : 'in_shelf';
		$reading_status  = isset( $book->reading_status ) ? (string) $book->reading_status : '';
		$author_value    = $authors_value;
		$title_value     = isset( $book->title ) ? (string) $book->title : '';

		if ( class_exists( 'Politeia_Reading_Sessions_Stats' ) && $effective_pages > 0 ) {
			$progress = Politeia_Reading_Sessions_Stats::calculate_progress_percent( $user_id, (int) $book->book_id, $effective_pages );
		}
		$progress_base = $progress;
		if ( 'finished' === $reading_status ) {
			$progress = 100;
		}

		$reading_id  = 'reading-status-' . (int) $book->user_book_id;
		$owning_id   = 'owning-status-' . (int) $book->user_book_id;
		$progress_id = 'reading-progress-' . (int) $book->user_book_id;

		$loan_contact_name  = isset( $book->counterparty_name ) ? trim( (string) $book->counterparty_name ) : '';
		$loan_contact_email = isset( $book->counterparty_email ) ? trim( (string) $book->counterparty_email ) : '';
		$is_digital         = ( isset( $book->type_book ) && 'd' === $book->type_book );

		$active_start_local = '';
		if ( ! empty( $book->active_loan_start ) ) {
			$converted = get_date_from_gmt( $book->active_loan_start, 'Y-m-d' );
			if ( $converted ) {
				$active_start_local = $converted;
			}
		}

		$year_text      = $year ? sprintf( __( 'Published: %s', 'politeia-reading' ), $year ) : __( 'Published: —', 'politeia-reading' );
		$pages_value    = $pages ? (int) $pages : '';
		$pages_display  = $pages ? (string) (int) $pages : '';
		$pages_input_id = 'prs-pages-input-' . (int) $book->user_book_id;

		$progress_label = sprintf( __( '%s%% complete', 'politeia-reading' ), (int) $progress );

		$current_select_value = $owning_status ? $owning_status : 'in_shelf';
		$stored_status        = $owning_status ? $owning_status : '';

		$owning_info_lines = array();

		if ( in_array( $owning_status, array( 'borrowed', 'borrowing', 'sold' ), true ) ) {
			$label_map    = array(
				'borrowed'  => $label_borrowed,
				'borrowing' => $label_borrowing,
				'sold'      => $label_sold,
			);
			$info_label   = isset( $label_map[ $owning_status ] ) ? $label_map[ $owning_status ] : '';
			$display_name = $loan_contact_name ? $loan_contact_name : $label_unknown;

			if ( $info_label ) {
				$owning_info_lines[] = '<strong>' . esc_html( $info_label ) . '</strong>';
			}

			$owning_info_lines[] = esc_html( $display_name );

			if ( $active_start_local ) {
				$owning_info_lines[] = '<small>' . esc_html( $active_start_local ) . '</small>';
			}
		} elseif ( 'lost' === $owning_status ) {
			$owning_info_lines[] = sprintf(
				'<strong>%s</strong>: %s',
				esc_html( $label_location ),
				esc_html( $label_not_in_shelf )
			);

			if ( $loan_contact_name ) {
				$owning_info_lines[] = sprintf(
					'<strong>%s</strong> %s',
					esc_html( $label_lost ),
					esc_html( $loan_contact_name )
				);
			}
		} else {
			$owning_info_lines[] = sprintf(
				'<strong>%s</strong>: %s',
				esc_html( $label_location ),
				esc_html( $label_in_shelf )
			);
		}

		$owning_info_html    = implode( '<br>', $owning_info_lines );
		$owning_info_display = $owning_info_html ? wp_kses(
			$owning_info_html,
			array(
				'strong' => array(),
				'br'     => array(),
				'small'  => array(),
			)
		) : '';

		$reading_disabled       = in_array( $owning_status, array( 'borrowing', 'borrowed' ), true );
		$reading_disabled_text  = __( 'Disabled while this book is being borrowed.', 'politeia-reading' );
		$reading_disabled_class = $reading_disabled ? ' is-disabled' : '';
		$reading_disabled_title = $reading_disabled ? ' title="' . esc_attr( $reading_disabled_text ) . '"' : '';

		$user_cover_raw = '';
		if ( isset( $book->cover_reference ) && '' !== $book->cover_reference && null !== $book->cover_reference ) {
			$user_cover_raw = $book->cover_reference;
		} elseif ( isset( $book->cover_attachment_id_user ) ) {
			$user_cover_raw = $book->cover_attachment_id_user;
		}

		$parsed_user_cover = method_exists( 'PRS_Cover_Upload_Feature', 'parse_cover_value' ) ? PRS_Cover_Upload_Feature::parse_cover_value( $user_cover_raw ) : array(
			'attachment_id' => is_numeric( $user_cover_raw ) ? (int) $user_cover_raw : 0,
			'url'           => '',
			'source'        => '',
		);

		$user_cover_id     = isset( $parsed_user_cover['attachment_id'] ) ? (int) $parsed_user_cover['attachment_id'] : 0;
		$user_cover_url    = isset( $parsed_user_cover['url'] ) ? trim( (string) $parsed_user_cover['url'] ) : '';
		$user_cover_url    = $user_cover_url ? esc_url_raw( $user_cover_url ) : '';
		$user_cover_source = isset( $parsed_user_cover['source'] ) ? trim( (string) $parsed_user_cover['source'] ) : '';

		if ( $user_cover_id ) {
			$attachment_source = get_post_meta( $user_cover_id, '_prs_cover_source', true );
			if ( $attachment_source ) {
				$user_cover_source = $attachment_source;
			}
		}

		$book_cover_id     = isset( $book->cover_attachment_id ) ? (int) $book->cover_attachment_id : 0;
		$book_cover_url    = '';
		$book_cover_source = '';

		if ( $book_cover_id ) {
			$book_cover_url    = wp_get_attachment_image_url( $book_cover_id, 'medium' );
			$book_cover_source = get_post_meta( $book_cover_id, '_prs_cover_source', true );
		}

		$has_user_cover = $user_cover_url || $user_cover_id;

		ob_start();
		?>
		<tr
			class="prs-library-row"
			data-user-book-id="<?php echo (int) $book->user_book_id; ?>"
			data-owning-status="<?php echo esc_attr( $row_owning_attr ); ?>"
			data-reading-status="<?php echo esc_attr( $reading_status ); ?>"
			data-progress="<?php echo esc_attr( (int) $progress ); ?>"
			data-progress-base="<?php echo esc_attr( (int) $progress_base ); ?>"
			data-author="<?php echo esc_attr( $author_value ); ?>"
			data-title="<?php echo esc_attr( $title_value ); ?>"
		>
			<td class="prs-library__info">
			<div class="prs-library__cover">
			<?php
			if ( $has_user_cover ) {
				if ( $user_cover_url ) {
					echo '<img class="prs-library__cover-image" src="' . esc_url( $user_cover_url ) . '" alt="' . esc_attr( $book->title ) . '" />';
					if ( $user_cover_source ) {
						echo '<div class="prs-library__cover-attribution"><a href="' . esc_url( $user_cover_source ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'View on Google Books', 'politeia-reading' ) . '</a></div>';
					}
				} else {
					echo wp_get_attachment_image(
						$user_cover_id,
						'medium',
						false,
						array(
							'class' => 'prs-library__cover-image',
							'alt'   => esc_attr( $book->title ),
						)
					);
					if ( $user_cover_source ) {
						echo '<div class="prs-library__cover-attribution"><a href="' . esc_url( $user_cover_source ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'View on Google Books', 'politeia-reading' ) . '</a></div>';
					}
				}
			} elseif ( $book_cover_id ) {
				if ( $book_cover_url ) {
					echo '<img class="prs-library__cover-image" src="' . esc_url( $book_cover_url ) . '" alt="' . esc_attr( $book->title ) . '" />';
					if ( $book_cover_source ) {
						echo '<div class="prs-library__cover-attribution"><a href="' . esc_url( $book_cover_source ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'View on Google Books', 'politeia-reading' ) . '</a></div>';
					}
				} else {
					echo '<div class="prs-library__cover-placeholder" aria-hidden="true"></div>';
				}
			} else {
				echo '<div class="prs-library__cover-placeholder" aria-hidden="true"></div>';
			}
			?>
			</div>
			<div class="prs-library__details">
				<a class="prs-library__title" href="<?php echo esc_url( $url ); ?>">
					<span class="prs-book-title__text"><?php echo esc_html( $book->title ); ?></span>
				</a>
				<div class="prs-library__meta">
					<?php if ( '' !== $author_value ) : ?>
					<span class="prs-library__meta-item prs-library__author"><span class="prs-book-author"><?php echo esc_html( $author_value ); ?></span></span>
					<?php endif; ?>
					<span class="prs-library__meta-item prs-library__year"><?php echo esc_html( $year ? sprintf( __( 'Published: %s', 'politeia-reading' ), $year ) : __( 'Published: —', 'politeia-reading' ) ); ?></span>
					<span class="prs-library__meta-item prs-library__pages" data-pages="<?php echo esc_attr( $pages_value ); ?>">
						<span class="prs-library__pages-display">
							<span class="prs-library__pages-label"><?php esc_html_e( 'Pages:', 'politeia-reading' ); ?></span>
							<span class="prs-library__pages-value"><?php echo esc_html( $pages_display ); ?></span>
							<button type="button" class="prs-library__pages-edit"><?php esc_html_e( 'Edit', 'politeia-reading' ); ?></button>
						</span>
						<input
							type="number"
							min="1"
							step="1"
							inputmode="numeric"
							class="prs-library__pages-input"
							id="<?php echo esc_attr( $pages_input_id ); ?>"
							value="<?php echo esc_attr( $pages_value ); ?>"
							aria-label="<?php esc_attr_e( 'Total pages', 'politeia-reading' ); ?>"
						/>
						<span class="prs-library__pages-error" role="alert" aria-live="polite"></span>
					</span>
				</div>
			</div>
			</td>
			<td class="prs-library__actions">
			<div class="prs-library__controls">
				<div class="prs-library__field">
					<label for="<?php echo esc_attr( $reading_id ); ?>"><?php esc_html_e( 'Reading Status', 'politeia-reading' ); ?></label>
					<select
						id="<?php echo esc_attr( $reading_id ); ?>"
						class="prs-reading-status reading-status-select<?php echo esc_attr( $reading_disabled_class ); ?>"
						data-disabled-text="<?php echo esc_attr( $reading_disabled_text ); ?>"
						aria-disabled="<?php echo $reading_disabled ? 'true' : 'false'; ?>"<?php echo $reading_disabled ? ' disabled="disabled"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo $reading_disabled_title; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					>
					<?php
					$reading = array(
						'not_started' => __( 'Not Started', 'politeia-reading' ),
						'started'     => __( 'Started', 'politeia-reading' ),
						'finished'    => __( 'Finished', 'politeia-reading' ),
					);
					foreach ( $reading as $val => $label ) {
						printf(
							'<option value="%s"%s>%s</option>',
							esc_attr( $val ),
							selected( $book->reading_status, $val, false ),
							esc_html( $label )
						);
					}
					?>
					</select>
				</div>
				<div class="prs-library__field">
					<label for="<?php echo esc_attr( $owning_id ); ?>"><?php esc_html_e( 'Owning Status', 'politeia-reading' ); ?></label>
					<select
						id="<?php echo esc_attr( $owning_id ); ?>"
						class="prs-owning-status owning-status-select<?php echo $is_digital ? ' is-disabled' : ''; ?>"
						data-book-id="<?php echo (int) $book->book_id; ?>"
						data-user-book-id="<?php echo (int) $book->user_book_id; ?>"
						data-current-value="<?php echo esc_attr( $current_select_value ); ?>"
						data-stored-status="<?php echo esc_attr( $stored_status ); ?>"
						data-contact-name="<?php echo esc_attr( $loan_contact_name ); ?>"
						data-contact-email="<?php echo esc_attr( $loan_contact_email ); ?>"
						data-active-start="<?php echo esc_attr( $active_start_local ); ?>"
						<?php echo $is_digital ? 'disabled="disabled" aria-disabled="true"' : ''; ?>
					>
					<option value=""><?php echo esc_html__( '— Select —', 'politeia-reading' ); ?></option>
					<?php
					$owning = array(
						'in_shelf'  => __( 'In Shelf', 'politeia-reading' ),
						'borrowed'  => __( 'Borrowed', 'politeia-reading' ),
						'borrowing' => __( 'Lent Out', 'politeia-reading' ),
						'bought'    => __( 'Bought', 'politeia-reading' ),
						'sold'      => __( 'Sold', 'politeia-reading' ),
						'lost'      => __( 'Lost', 'politeia-reading' ),
					);
					foreach ( $owning as $val => $label ) {
						$selected_attr = selected( $owning_status, $val, false );
						if ( 'in_shelf' === $val && '' === $owning_status ) {
							$selected_attr = ' selected="selected"';
						}
						printf(
							'<option value="%s"%s>%s</option>',
							esc_attr( $val ),
							$selected_attr,
							esc_html( $label )
						);
					}
					?>
					</select>
					<?php
					$show_return_btn  = ! $is_digital && in_array( $owning_status, array( 'borrowed', 'borrowing' ), true );
					$return_btn_style = $show_return_btn ? '' : 'display:none;';
					?>
					<button
						type="button"
						class="prs-btn owning-return-shelf"
						data-book-id="<?php echo (int) $book->book_id; ?>"
						data-user-book-id="<?php echo (int) $book->user_book_id; ?>"
						style="<?php echo esc_attr( $return_btn_style ); ?>"
						<?php echo $is_digital ? 'disabled="disabled" aria-disabled="true"' : ''; ?>
					>
						<?php esc_html_e( 'Mark as returned', 'politeia-reading' ); ?>
					</button>
					<span class="owning-status-info" data-book-id="<?php echo (int) $book->book_id; ?>"><?php echo $owning_info_display; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<?php if ( $is_digital ) : ?>
					<div class="prs-owning-status-note"><?php esc_html_e( 'Owning status is available only for printed copies.', 'politeia-reading' ); ?></div>
					<?php endif; ?>
				</div>
			</div>
			<div class="prs-library__extras">
				<div class="prs-library__progress-field">
					<span id="<?php echo esc_attr( $progress_id ); ?>" class="prs-library__progress-label"><?php esc_html_e( 'Reading Progress', 'politeia-reading' ); ?></span>
					<div class="prs-library__progress">
						<div
							class="prs-library__progress-track"
							role="progressbar"
							aria-valuenow="<?php echo esc_attr( (int) $progress ); ?>"
							aria-valuemin="0"
							aria-valuemax="100"
							aria-valuetext="<?php echo esc_attr( $progress_label ); ?>"
							aria-labelledby="<?php echo esc_attr( $progress_id ); ?>"
						>
							<div class="prs-library__progress-fill" style="width: <?php echo (int) $progress; ?>%;"></div>
						</div>
						<span class="prs-library__progress-value"><?php echo (int) $progress; ?>%</span>
					</div>
				</div>
				<button
					type="button"
					class="prs-library__remove prs-remove-book"
					data-id="<?php echo esc_attr( $book->user_book_id ); ?>"
					data-nonce="<?php echo esc_attr( wp_create_nonce( 'remove_user_book_' . (int) $book->user_book_id ) ); ?>"
					aria-label="<?php esc_attr_e( 'Remove book', 'politeia-reading' ); ?>">
					<?php esc_html_e( 'Remove', 'politeia-reading' ); ?>
				</button>
			</div>
			</td>
		</tr>
		<?php

		return ob_get_clean();
	}
}
