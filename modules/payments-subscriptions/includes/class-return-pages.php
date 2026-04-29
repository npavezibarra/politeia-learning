<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates and maintains default return pages for Mercado Pago hosted checkout.
 *
 * - Success URL: where MP redirects after a successful checkout/authorization.
 * - Cancel URL: where MP redirects if the user cancels the checkout.
 *
 * These are stored in Politeia_PPS_Settings keys: success_url / cancel_url.
 */
class Politeia_PPS_Return_Pages {
	const SUCCESS_SLUG  = 'subscription-success';
	const CANCEL_SLUG   = 'subscription-cancel';
	const SUCCESS_TITLE = 'Suscripción completada';
	const CANCEL_TITLE  = 'Suscripción cancelada';

	public static function init(): void {
		// Only run creation logic in admin to avoid unexpected writes from public requests.
		add_action( 'admin_init', array( __CLASS__, 'ensure' ), 5 );
	}

	public static function ensure(): void {
		if ( ! function_exists( 'wp_insert_post' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$success_id = self::ensure_page(
			self::SUCCESS_SLUG,
			self::SUCCESS_TITLE,
			'[politeia_pps_subscription_success]'
		);
		$cancel_id  = self::ensure_page(
			self::CANCEL_SLUG,
			self::CANCEL_TITLE,
			'[politeia_pps_subscription_cancel]'
		);

		$settings = Politeia_PPS_Settings::get_all();
		$dirty    = false;

		if ( empty( $settings['success_url'] ) && $success_id > 0 ) {
			$settings['success_url'] = get_permalink( $success_id );
			$dirty                  = true;
		}
		if ( empty( $settings['cancel_url'] ) && $cancel_id > 0 ) {
			$settings['cancel_url'] = get_permalink( $cancel_id );
			$dirty                 = true;
		}

		if ( $dirty ) {
			update_option( Politeia_PPS_Settings::OPTION_KEY, $settings );
		}
	}

	private static function ensure_page( string $slug, string $title, string $content ): int {
		$slug = sanitize_title( $slug );
		if ( $slug === '' ) {
			return 0;
		}

		$existing = get_page_by_path( $slug, OBJECT, 'page' );
		if ( $existing instanceof WP_Post ) {
			// If someone changed the content, do not overwrite it.
			return (int) $existing->ID;
		}

		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_name'    => $slug,
				'post_title'   => $title,
				'post_content' => $content,
			),
			true
		);

		if ( is_wp_error( $page_id ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[PPS][DEBUG][return_pages_create_error] ' . $slug . ' ' . $page_id->get_error_message() );
			}
			return 0;
		}

		return (int) $page_id;
	}
}

