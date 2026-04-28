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

		$last_handle = 'jquery';
		for ( $i = 0; $i <= 2; $i++ ) {
			$suffix = sprintf( '%02d', $i );
			$handle = 'prs-start-reading-part-' . $suffix;
			wp_register_script(
				$handle,
				$base_url . "assets/js/start-reading/start_reading-part-{$suffix}.js",
				array( $last_handle ),
				$version,
				true
			);
			$last_handle = $handle;
		}

		wp_register_script(
			'politeia-start-reading',
			$base_url . 'assets/js/start-reading/start_reading-loader.js',
			array( $last_handle ),
			$version,
			true
		);
	}

	public static function enqueue() {
		wp_enqueue_script( 'politeia-start-reading' );
	}
}
