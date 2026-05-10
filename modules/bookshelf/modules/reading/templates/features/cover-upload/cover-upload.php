<?php
/**
 * Feature: Upload Book Cover
 * Orchestrator class.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PRS_Cover_Upload_Feature {

	private static $bootstrapped = false;

	public static function init() {
		if ( self::$bootstrapped ) {
			return;
		}
		self::$bootstrapped = true;

		// Include modular classes
		$base_path = dirname( dirname( dirname( __DIR__ ) ) ) . '/includes/cover-upload/';
		require_once $base_path . 'class-prs-cover-upload-repository.php';
		require_once $base_path . 'class-prs-cover-upload-assets.php';
		require_once $base_path . 'class-prs-cover-upload-ajax.php';

		add_action( 'wp_enqueue_scripts', array( 'Politeia_Reading_Cover_Upload_Assets', 'register_and_enqueue' ) );
		add_shortcode( 'prs_cover_button', array( __CLASS__, 'shortcode_button' ) );
		
		// AJAX hooks
		add_action( 'wp_ajax_prs_save_cover_url', array( 'Politeia_Reading_Cover_Upload_AJAX', 'save_cover_url' ) );
		add_action( 'wp_ajax_prs_cover_upload_file', array( 'Politeia_Reading_Cover_Upload_AJAX', 'upload_cover_file' ) );
		add_action( 'wp_ajax_prs_save_cropped_cover', array( 'Politeia_Reading_Cover_Upload_AJAX', 'save_cropped_cover' ) );
		add_action( 'wp_ajax_prs_cover_search_google', array( 'Politeia_Reading_Cover_Upload_AJAX', 'search_google' ) );
		add_action( 'wp_ajax_prs_remove_cover', array( 'Politeia_Reading_Cover_Upload_AJAX', 'remove_cover' ) );

		if ( ! has_action( 'wp_footer', array( __CLASS__, 'render_modal_template' ) ) ) {
			add_action( 'wp_footer', array( __CLASS__, 'render_modal_template' ) );
		}
	}

	public static function render_modal_template() {
		if ( ! get_query_var( 'prs_book_slug' ) ) {
			return;
		}
		$template = trailingslashit( POLITEIA_READING_PATH ) . 'templates/partials/prs-cover-modal.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
	}

	public static function shortcode_button( $atts ) {
		$atts = shortcode_atts( array( 'show_search' => true ), $atts, 'prs_cover_button' );
		$show_search = filter_var( $atts['show_search'], FILTER_VALIDATE_BOOLEAN );

		$upload_label   = __( 'Upload Book Cover', 'politeia-reading' );
		$search_label   = __( 'Search Cover', 'politeia-reading' );
		$remove_label   = __( 'Remove book cover', 'politeia-reading' );
		$remove_confirm = __( 'Are you sure you want to remove this book cover?', 'politeia-reading' );

		ob_start();
		?>
		<div class="prs-cover-actions" 
			 data-search-label="<?php echo esc_attr( $search_label ); ?>" 
			 data-remove-label="<?php echo esc_attr( $remove_label ); ?>" 
			 data-remove-confirm="<?php echo esc_attr( $remove_confirm ); ?>">
			<button type="button" id="prs-cover-open" class="prs-btn prs-cover-btn prs-cover-upload-button"><?php echo esc_html( $upload_label ); ?></button>
			<?php /* DISABLED: external cover search
			if ( $show_search ) : ?>
				<button type="button" id="prs-cover-search" class="prs-btn prs-cover-btn prs-cover-search-button"><?php echo esc_html( $search_label ); ?></button>
			<?php endif; */ ?>
			<a href="#" id="prs-cover-remove" class="prs-cover-remove"><?php echo esc_html( $remove_label ); ?></a>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function parse_cover_value( $raw ) {
		$result = array(
			'attachment_id' => 0,
			'url'           => '',
			'source'        => '',
		);

		if ( is_array( $raw ) ) {
			if ( isset( $raw['attachment_id'] ) ) {
				$result['attachment_id'] = (int) $raw['attachment_id'];
			}
			if ( isset( $raw['url'] ) ) {
				$result['url'] = esc_url_raw( (string) $raw['url'] );
			}
			if ( isset( $raw['source'] ) ) {
				$result['source'] = sanitize_text_field( (string) $raw['source'] );
			}
			if ( ! $result['source'] && isset( $raw['external_cover'] ) ) {
				$result['source'] = sanitize_text_field( (string) $raw['external_cover'] );
				if ( ! $result['url'] ) {
					$result['url'] = $result['source'];
				}
			}
			return $result;
		}

		if ( is_numeric( $raw ) ) {
			$result['attachment_id'] = (int) $raw;
			return $result;
		}

		if ( ! is_string( $raw ) ) {
			return $result;
		}

		$trimmed = trim( $raw );
		if ( '' === $trimmed ) {
			return $result;
		}

		$maybe_unserialized = maybe_unserialize( $trimmed );
		if ( is_array( $maybe_unserialized ) ) {
			return self::parse_cover_value( $maybe_unserialized );
		}

		$decoded = json_decode( $trimmed, true );
		if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
			return self::parse_cover_value( $decoded );
		}

		if ( preg_match( '/^\d+$/', $trimmed ) ) {
			$result['attachment_id'] = (int) $trimmed;
			return $result;
		}

		if ( filter_var( $trimmed, FILTER_VALIDATE_URL ) ) {
			$result['url']    = esc_url_raw( $trimmed );
			$result['source'] = sanitize_text_field( $trimmed );
			return $result;
		}

		$result['source'] = sanitize_text_field( $trimmed );
		return $result;
	}
}
