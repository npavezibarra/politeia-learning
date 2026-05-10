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

		wp_register_script(
			'politeia-my-book',
			$base_url . 'assets/js/my-book-reconstructed.js',
			array( 'jquery' ),
			$version,
			true
		);
	}

	public static function enqueue() {
		wp_enqueue_script( 'politeia-my-book' );
	}
}
