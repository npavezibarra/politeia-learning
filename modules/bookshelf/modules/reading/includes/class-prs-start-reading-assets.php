<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles asset registration for the modular start-reading.js.
 */
class Politeia_Reading_Start_Reading_Assets {

	public static function register() {
		$base_url = POLITEIA_READING_URL;
		$version  = POLITEIA_READING_VERSION;

		wp_register_script(
			'politeia-start-reading',
			$base_url . 'assets/js/start-reading/start_reading-bundle.js',
			array( 'jquery' ),
			$version,
			true
		);
	}

	public static function enqueue() {
		wp_enqueue_script( 'politeia-start-reading' );
	}
}
