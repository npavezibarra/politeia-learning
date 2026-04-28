<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the [politeia_add_book] shortcode logic and UI orchestration.
 */
class Politeia_Reading_Shortcode_Add_Book {

	/**
	 * Initialize the shortcode.
	 */
	public static function init() {
		add_shortcode( 'politeia_add_book', array( __CLASS__, 'render_shortcode' ) );
	}

	/**
	 * Render the shortcode [politeia_add_book].
	 *
	 * @return string The shortcode output.
	 */
	public static function render_shortcode() {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'You must be logged in to add books.', 'politeia-reading' ) . '</p>';
		}

		self::enqueue_assets();

		$context = self::get_render_context();

		ob_start();
		// We'll pass the context to the template.
		include POLITEIA_READING_PATH . 'templates/shortcode-add-book.php';
		return ob_get_clean();
	}

	/**
	 * Enqueue styles and scripts required for adding books.
	 */
	private static function enqueue_assets() {
		wp_enqueue_style( 'politeia-reading' );
		wp_enqueue_script( 'politeia-add-book' );
		wp_enqueue_style(
			'politeia-material-symbols',
			'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&icon_names=play_circle',
			array(),
			null
		);
		wp_localize_script(
			'politeia-add-book',
			'PRS_ADD_BOOK_AUTOCOMPLETE',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'prs_canonical_title_search' ),
			)
		);
	}

	/**
	 * Gather all necessary data for rendering the template.
	 *
	 * @return array The context array.
	 */
	private static function get_render_context() {
		$success                = ! empty( $_GET['prs_added'] ) && '1' === $_GET['prs_added'];
		$success_title          = '';
		$success_author         = '';
		$success_year           = null;
		$success_pages          = null;
		$success_cover_url      = '';
		$success_slug           = '';
		$success_start_url      = '';
		$duplicate_message      = '';
		$multiple_mode_content  = '';
		$multiple_shortcode_tag = 'politeia_chatgpt_input';

		if ( shortcode_exists( $multiple_shortcode_tag ) ) {
			$multiple_mode_content = do_shortcode( '[' . $multiple_shortcode_tag . ']' );
		}

		if ( ! empty( $_GET['prs_error'] ) && $_GET['prs_error'] === 'duplicate' ) {
			$duplicate_message = esc_html__( 'Already in your library', 'politeia-reading' );
		}

		if ( $success ) {
			if ( isset( $_GET['prs_added_title'] ) ) {
				$success_title = sanitize_text_field( wp_unslash( $_GET['prs_added_title'] ) );
			}
			if ( isset( $_GET['prs_added_author'] ) ) {
				$success_author = sanitize_text_field( wp_unslash( $_GET['prs_added_author'] ) );
			}
			if ( isset( $_GET['prs_added_year'] ) && '' !== $_GET['prs_added_year'] ) {
				$year = absint( $_GET['prs_added_year'] );
				if ( $year >= 1400 && $year <= ( (int) date( 'Y' ) + 1 ) ) {
					$success_year = $year;
				}
			}
			if ( isset( $_GET['prs_added_pages'] ) && '' !== $_GET['prs_added_pages'] ) {
				$pages = absint( $_GET['prs_added_pages'] );
				if ( $pages > 0 ) {
					$success_pages = $pages;
				}
			}
			if ( isset( $_GET['prs_added_cover'] ) && '' !== $_GET['prs_added_cover'] ) {
				$cover_id = absint( $_GET['prs_added_cover'] );
				if ( $cover_id ) {
					$cover_url = wp_get_attachment_image_url( $cover_id, 'medium' );
					if ( $cover_url ) {
						$success_cover_url = $cover_url;
					}
				}
			}
			if ( isset( $_GET['prs_added_slug'] ) && '' !== $_GET['prs_added_slug'] ) {
				$success_slug = sanitize_title( wp_unslash( $_GET['prs_added_slug'] ) );
				if ( $success_slug ) {
					$success_start_url = add_query_arg(
						'prs_start_session',
						'1',
						home_url( '/my-books/my-book-' . $success_slug . '/' )
					);
				}
			}
		}

		return array(
			'success'                => $success,
			'success_title'          => $success_title,
			'success_author'         => $success_author,
			'success_year'           => $success_year,
			'success_pages'          => $success_pages,
			'success_cover_url'      => $success_cover_url,
			'success_slug'           => $success_slug,
			'success_start_url'      => $success_start_url,
			'duplicate_message'      => $duplicate_message,
			'multiple_mode_content'  => $multiple_mode_content,
		);
	}
}
