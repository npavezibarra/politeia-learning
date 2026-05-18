<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles asset registration and localization for Cover Upload.
 */
class Politeia_Reading_Cover_Upload_Assets {

	public static function register_and_enqueue() {
		if ( ! get_query_var( 'prs_book_slug' ) ) {
			return;
		}

		$base_url = POLITEIA_READING_URL;
		$css_path = trailingslashit( POLITEIA_READING_PATH ) . 'templates/features/cover-upload/cover-upload.css';
		$version  = file_exists( $css_path ) ? (string) filemtime( $css_path ) : '0.2.0';

		wp_enqueue_style( 'prs-cover-upload', $base_url . 'templates/features/cover-upload/cover-upload.css', array(), $version );

		// Modular JS
		$scripts = array(
			'constants'    => 'assets/js/cover-upload/constants.js',
			'utils'        => 'assets/js/cover-upload/utils.js',
			'api'          => 'assets/js/cover-upload/api.js',
			'ui'           => 'assets/js/cover-upload/ui.js',
			'upload-modal' => 'assets/js/cover-upload/upload-modal.js',
			'search-modal' => 'assets/js/cover-upload/search-modal.js',
			'main'         => 'assets/js/cover-upload/main.js',
		);

		$prev_handle = 'prs-cover-constants';
		foreach ( $scripts as $handle => $path ) {
			$actual_handle = ( 'main' === $handle ) ? 'prs-cover-upload' : 'prs-cover-' . $handle;
			$script_path   = trailingslashit( POLITEIA_READING_PATH ) . ltrim( (string) $path, '/' );
			$script_ver    = file_exists( $script_path ) ? (string) filemtime( $script_path ) : $version;
			wp_enqueue_script(
				$actual_handle,
				$base_url . $path,
				( 'constants' === $handle ) ? array( 'jquery' ) : array( $prev_handle ),
				$script_ver,
				true
			);
			$prev_handle = $actual_handle;
		}

		self::localize( 'prs-cover-constants' );
	}

	private static function localize( $handle ) {
		$ajax_url   = admin_url( 'admin-ajax.php' );
		$save_nonce = wp_create_nonce( 'prs_cover_nonce' );
		$post_id    = get_queried_object_id();

		wp_localize_script( $handle, 'PRS_COVER', array(
			'ajax'       => $ajax_url,
			'saveNonce'  => $save_nonce,
			'postId'     => (int) $post_id,
		));

		wp_localize_script( $handle, 'prs_cover_data', array(
			'ajaxurl' => $ajax_url,
			'nonce'   => $save_nonce,
		));

		wp_localize_script( $handle, 'PRS_COVER_I18N', array(
			'modal_title'               => __( 'Upload Book Cover', 'politeia-reading' ),
			'drop_here_title'           => __( 'Drop JPEG or PNG file here', 'politeia-reading' ),
			'drag_here'                 => __( 'Drag JPEG or PNG here (220x350 Preview)', 'politeia-reading' ),
			'click_upload'              => __( 'or click upload', 'politeia-reading' ),
			'preview_alt'               => __( 'Book Cover Preview', 'politeia-reading' ),
			'status_awaiting'           => __( 'Awaiting file upload.', 'politeia-reading' ),
			'cancel'                    => __( 'Cancel', 'politeia-reading' ),
			'save'                      => __( 'Save', 'politeia-reading' ),
			'error_invalid_type'        => __( 'Error: Only JPEG and PNG images are accepted.', 'politeia-reading' ),
			'status_saving'             => __( 'Saving…', 'politeia-reading' ),
			'status_saved'              => __( 'Saved', 'politeia-reading' ),
			'status_error'              => __( 'Error', 'politeia-reading' ),
			'file_loaded'               => __( 'File loaded: %s', 'politeia-reading' ),
			'choose_image'              => __( 'Choose an image', 'politeia-reading' ),
			'unknown_author'            => __( 'Unknown Author', 'politeia-reading' ),
			'search_title'              => __( 'Select a Cover', 'politeia-reading' ),
			'set_cover'                 => __( 'Set Cover', 'politeia-reading' ),
			'no_covers_found'           => __( 'No covers found. You can upload your own image instead.', 'politeia-reading' ),
			'cover_for_title'           => __( 'Cover for %s', 'politeia-reading' ),
			'view_on_google'            => __( 'View source', 'politeia-reading' ),
			'click_set_cover'           => __( 'Click “Set Cover” to use the selected image.', 'politeia-reading' ),
			'searching_covers'          => __( 'Searching for covers…', 'politeia-reading' ),
			'missing_title'             => __( 'No book title available. Add a title to search or upload a cover manually.', 'politeia-reading' ),
			'single_cover_found'        => __( 'Only one cover found. Click “Set Cover” to confirm.', 'politeia-reading' ),
			'select_cover'              => __( 'Select a cover below and click “Set Cover”.', 'politeia-reading' ),
			'select_cover_language'     => __( 'Select a cover below and click “Set Cover”. Showing %s results when possible.', 'politeia-reading' ),
			'missing_api_key'           => __( 'Cover search API key is missing. Add it in the plugin settings.', 'politeia-reading' ),
			'search_error'              => __( 'There was an error searching for covers. Please try again later.', 'politeia-reading' ),
			'remove_confirm'            => __( 'Remove this book cover?', 'politeia-reading' ),
			'remove_unavailable'        => __( 'Unable to remove the book cover.', 'politeia-reading' ),
			'remove_failed'             => __( 'Could not remove the cover. Please try again.', 'politeia-reading' ),
			'saving_selected'           => __( 'Saving selected cover…', 'politeia-reading' ),
			'save_selected_failed'      => __( 'Could not save the selected cover. Please try again.', 'politeia-reading' ),
			'server_error'              => __( 'Server error. Please try again later.', 'politeia-reading' ),
			'error_auth'                => __( 'You must be logged in.', 'politeia-reading' ),
			'error_bad_nonce'           => __( 'Your session expired. Please refresh and try again.', 'politeia-reading' ),
			'error_invalid_payload'     => __( 'Invalid data received.', 'politeia-reading' ),
			'error_not_found'           => __( 'Record not found.', 'politeia-reading' ),
			'error_db'                  => __( 'Database error. Please try again.', 'politeia-reading' ),
			'error_forbidden'           => __( 'Permission denied.', 'politeia-reading' ),
			'error_decode'              => __( 'Unable to decode the image.', 'politeia-reading' ),
			'error_missing_params'      => __( 'Missing required data.', 'politeia-reading' ),
			'error_bad_url'             => __( 'Invalid URL.', 'politeia-reading' ),
			'error_unsupported_scheme'  => __( 'Unsupported URL scheme.', 'politeia-reading' ),
			'error_invalid_image_host'  => __( 'Invalid image host.', 'politeia-reading' ),
			'error_bad_source_url'      => __( 'Invalid source URL.', 'politeia-reading' ),
			'error_unsupported_source_scheme' => __( 'Invalid source URL scheme.', 'politeia-reading' ),
			'error_invalid_source_host' => __( 'Source host not permitted.', 'politeia-reading' ),
			'error_no_image_data'       => __( 'No image data received.', 'politeia-reading' ),
			'error_invalid_image_payload' => __( 'Invalid image payload.', 'politeia-reading' ),
			'error_upload_dir'          => __( 'Upload directory unavailable.', 'politeia-reading' ),
			'error_write_failed'        => __( 'Failed to write image.', 'politeia-reading' ),
			'error_attachment_failed'   => __( 'Attachment creation failed.', 'politeia-reading' ),
		));

		$inline_config = sprintf(
			"window.PRS_SAVE_URL = %s;\nwindow.PRS_NONCE = %s;\nwindow.PRS_POST_ID = %s;",
			wp_json_encode( $ajax_url ),
			wp_json_encode( $save_nonce ),
			wp_json_encode( (int) $post_id )
		);
		wp_add_inline_script( $handle, $inline_config, 'before' );
	}
}
