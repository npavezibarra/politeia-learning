<?php
namespace Politeia\ReadingPlanner;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Init {
	public static function register() {
		foreach ( glob( __DIR__ . '/*.php' ) as $file ) {
			$base = basename( $file );
			if ( $base !== 'init.php' && $base !== 'bootstrap.php' && $base !== 'bookshelf-init.php' ) {
				require_once $file;
			}
		}

		foreach ( glob( __DIR__ . '/includes/*.php' ) as $file ) {
			require_once $file;
		}
	}
}
