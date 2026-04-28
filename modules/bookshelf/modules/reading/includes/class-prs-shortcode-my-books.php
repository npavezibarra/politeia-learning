<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the [politeia_my_books] shortcode logic and library orchestration.
 */
class Politeia_Reading_Shortcode_My_Books {

	/**
	 * Initialize the shortcode.
	 */
	public static function init() {
		add_shortcode( 'politeia_my_books', array( __CLASS__, 'render_shortcode' ) );
	}

	/**
	 * Render the shortcode [politeia_my_books].
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string The shortcode output.
	 */
	public static function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'render' => 'full',
			),
			$atts,
			'politeia_my_books'
		);
		$render = strtolower( (string) $atts['render'] );

		if ( ! is_user_logged_in() ) {
			if ( 'header' === $render ) {
				return '';
			}
			return '<p>' . esc_html__( 'You must be logged in to view your library.', 'politeia-reading' ) . '</p>';
		}

		$context = self::get_render_context();

		if ( ! empty( $context['empty'] ) ) {
			if ( 'header' === $render ) {
				return '';
			}
			return '<p>' . esc_html__( 'Your library is empty. Add a book first.', 'politeia-reading' ) . '</p>';
		}

		self::enqueue_assets( $context );

		ob_start();
		
		if ( 'header' === $render || 'full' === $render ) {
			include POLITEIA_READING_PATH . 'templates/library-header.php';
		}

		if ( 'content' === $render || 'full' === $render ) {
			include POLITEIA_READING_PATH . 'templates/library-table.php';
			include POLITEIA_READING_PATH . 'templates/library-filters.php';
		}

		return ob_get_clean();
	}

	/**
	 * Enqueue assets and localize library data.
	 *
	 * @param array $context The render context.
	 */
	private static function enqueue_assets( $context ) {
		wp_enqueue_style( 'politeia-reading' );
		wp_enqueue_script( 'politeia-my-book' );

		$owning_messages = array(
			'missing' => __( 'Please enter both name and email.', 'politeia-reading' ),
			'saving'  => __( 'Saving...', 'politeia-reading' ),
			'error'   => __( 'Error saving contact.', 'politeia-reading' ),
			'alert'   => __( 'Error saving contact.', 'politeia-reading' ),
		);

		$owning_nonce = wp_create_nonce( 'save_owning_contact' );

		wp_localize_script(
			'politeia-my-book',
			'PRS_LIBRARY',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'strings'  => array(
					'book_single'           => __( 'book', 'politeia-reading' ),
					'book_plural'           => __( 'books', 'politeia-reading' ),
					'error_loading_results' => __( 'Error loading results', 'politeia-reading' ),
					'ajax_unavailable'      => __( 'Ajax URL not available for library search.', 'politeia-reading' ),
					'press_enter_to_save'   => __( 'Press Enter to save', 'politeia-reading' ),
					'pages_error'           => __( 'Error saving pages.', 'politeia-reading' ),
					'pages_too_small'       => __( 'Please enter a number greater than zero.', 'politeia-reading' ),
					'pages_saved'           => __( 'Saved!', 'politeia-reading' ),
					'saved_short'           => __( 'Saved.', 'politeia-reading' ),
					'status_saving'         => __( 'Saving...', 'politeia-reading' ),
					'missing_contact'       => __( 'Please enter both name and email.', 'politeia-reading' ),
					'borrower_buying_title'   => __( 'Borrowed person is buying this book:', 'politeia-reading' ),
					'borrower_buying_confirm' => __( 'Confirm that the borrower is purchasing or compensating for the book.', 'politeia-reading' ),
					'error_saving_contact'  => __( 'Error saving contact.', 'politeia-reading' ),
					'error_saving_date'     => __( 'Error saving date.', 'politeia-reading' ),
					'error_saving_channel'  => __( 'Error saving channel.', 'politeia-reading' ),
					'error_saving_rating'   => __( 'Error saving rating.', 'politeia-reading' ),
					'error_saving_format'   => __( 'Error saving format.', 'politeia-reading' ),
					'saved_successfully'    => __( 'Saved successfully.', 'politeia-reading' ),
					'disabled_lost'         => __( 'Disabled while this book is lost.', 'politeia-reading' ),
					'disabled_borrowed'     => __( 'Disabled while this book is being borrowed.', 'politeia-reading' ),
					'filter_all'            => __( 'All', 'politeia-reading' ),
					'selected_count'        => __( '%d selected', 'politeia-reading' ),
					'channel_online'        => __( 'Online', 'politeia-reading' ),
					'channel_store'         => __( 'Store', 'politeia-reading' ),
					'remove_book_confirm'   => __( 'Are you sure you want to remove this book from your library?', 'politeia-reading' ),
					'remove_book_removing'  => __( 'Removing...', 'politeia-reading' ),
					'remove_book_error'     => __( 'Error removing book.', 'politeia-reading' ),
					'images_from_google'    => __( 'Images from Google Books', 'politeia-reading' ),
					'no_covers_found'       => __( 'No covers found.', 'politeia-reading' ),
					'cover_save_failed'     => __( 'Unable to save cover.', 'politeia-reading' ),
					'error_owning_status'   => __( 'Error updating owning status.', 'politeia-reading' ),
				),
				'messages' => array(
					'invalid'   => __( 'Please enter a valid number of pages.', 'politeia-reading' ),
					'too_small' => __( 'Please enter a number greater than zero.', 'politeia-reading' ),
					'error'     => __( 'There was an error saving the number of pages.', 'politeia-reading' ),
				),
				'owning'   => array(
					'nonce'    => $owning_nonce,
					'labels'   => $context['owning_labels'],
					'messages' => $owning_messages,
				),
			)
		);
	}

	/**
	 * Prepare the data context for rendering the library.
	 *
	 * @return array The context array.
	 */
	private static function get_render_context() {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_get_current_user();
			$user_id = get_current_user_id();
		}

		$owning_labels = function_exists( 'prs_get_owning_labels' ) ? prs_get_owning_labels() : array();

		$per_page = (int) apply_filters( 'politeia_my_books_per_page', 15 );
		if ( $per_page < 1 ) {
			$per_page = 15;
		}

		$paged  = isset( $_GET['prs_page'] ) ? max( 1, absint( $_GET['prs_page'] ) ) : 1;
		$offset = ( $paged - 1 ) * $per_page;
		$force_recent = ! empty( $_GET['prs_added'] ) && '1' === (string) $_GET['prs_added'];
		if ( $force_recent ) {
			$paged  = 1;
			$offset = 0;
		}

		global $wpdb;
		$ub = $wpdb->prefix . 'politeia_user_books';
		$b  = $wpdb->prefix . 'politeia_books';

		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				FROM $ub ub
				JOIN $b  b ON b.id = ub.book_id
				WHERE ub.user_id = %d
				  AND ub.deleted_at IS NULL
				  AND (ub.owning_status IS NULL OR ub.owning_status != 'deleted')",
				$user_id
			)
		);

		$context = array(
			'user_id'       => $user_id,
			'owning_labels' => $owning_labels,
			'total'         => $total,
			'per_page'      => $per_page,
			'paged'         => $paged,
			'offset'        => $offset,
			'force_recent'  => $force_recent,
			'empty'         => false,
			'books'         => array(),
			'pagination'    => array(),
			'add_book'      => '',
		);

		if ( $total === 0 ) {
			$context['empty'] = true;
		} else {
			$max_pages = max( 1, (int) ceil( $total / $per_page ) );
			if ( $paged > $max_pages ) {
				$paged  = $max_pages;
				$offset = ( $paged - 1 ) * $per_page;
			}

			if ( function_exists( 'prs_get_user_books_for_library' ) ) {
				$books = prs_get_user_books_for_library(
					$user_id,
					array(
						'per_page' => $per_page,
						'offset'   => $offset,
						'order'    => $force_recent ? 'recent' : 'title_asc',
					)
				);
				$context['books'] = $books;
			}

			$base_url = remove_query_arg(
				array(
					'prs_page',
					'prs_added',
					'prs_added_title',
					'prs_added_author',
					'prs_added_year',
					'prs_added_pages',
					'prs_added_cover',
					'prs_added_slug',
				)
			);
			$pagination_links = paginate_links(
				array(
					'base'      => add_query_arg( 'prs_page', '%#%', $base_url ),
					'format'    => '',
					'current'   => $paged,
					'total'     => $max_pages,
					'mid_size'  => 2,
					'end_size'  => 1,
					'prev_text' => '',
					'next_text' => '',
					'type'      => 'array',
				)
			);
			$context['pagination'] = $pagination_links;

			if ( shortcode_exists( 'politeia_add_book' ) ) {
				$context['add_book'] = do_shortcode( '[politeia_add_book]' );
			}
		}

		return $context;
	}
}
