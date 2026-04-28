<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles asset registration and localization for the modular Add Book system.
 */
class Politeia_Reading_Add_Book_Assets {

	public static function register() {
		$base_url = POLITEIA_READING_URL;
		$version  = POLITEIA_READING_VERSION;

		// The order of dependencies is critical.
		$scripts = array(
			'constants' => array( 'jquery' ),
			'state'     => array( 'prs-add-book-constants' ),
			'api'       => array( 'prs-add-book-state' ),
			'ui'        => array( 'prs-add-book-api' ),
			'form'      => array( 'prs-add-book-ui' ),
			'search'    => array( 'prs-add-book-form' ),
			'main'      => array( 'prs-add-book-search' ),
		);

		foreach ( $scripts as $slug => $deps ) {
			$handle = ( 'main' === $slug ) ? 'politeia-add-book' : 'prs-add-book-' . $slug;
			wp_register_script(
				$handle,
				$base_url . "assets/js/add-book/{$slug}.js",
				$deps,
				$version,
				true
			);
		}

		self::localize();
	}

	public static function enqueue() {
		wp_enqueue_script( 'politeia-add-book' );
	}

	private static function localize() {
		wp_localize_script( 'prs-add-book-constants', 'PRS_ADD_BOOK_DATA', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'prs_reading_nonce' ),
		) );

		wp_localize_script( 'prs-add-book-constants', 'PRS_ADD_BOOK_I18N', array(
			'error_fetch' => __( 'Error fetching data', 'politeia-reading' ),
		) );
	}
}
