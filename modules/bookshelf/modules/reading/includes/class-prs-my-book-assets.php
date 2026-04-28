<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles asset registration for the modular my-book system (split into safe parts).
 */
class Politeia_Reading_My_Book_Assets {

	public static function register() {
		$base_url = POLITEIA_READING_URL;
		$version  = POLITEIA_READING_VERSION;

		// We load the 11 parts in sequence to reconstruct the original logic perfectly.
		$last_handle = 'jquery';
		for ( $i = 0; $i <= 16; $i++ ) {
			$suffix = sprintf( '%02d', $i );
			$handle = 'prs-my-book-part-' . $suffix;
			wp_register_script(
				$handle,
				$base_url . "assets/js/my-book/my_book-part-{$suffix}.js",
				array( $last_handle ),
				$version,
				true
			);
			$last_handle = $handle;
		}

		wp_register_script(
			'politeia-my-book',
			$base_url . 'assets/js/my-book/my_book-loader.js',
			array( $last_handle ),
			$version,
			true
		);
	}

	public static function enqueue() {
		wp_enqueue_script( 'politeia-my-book' );
	}
}
