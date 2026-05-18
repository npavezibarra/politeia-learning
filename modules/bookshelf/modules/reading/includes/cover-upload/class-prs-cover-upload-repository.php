<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Repository for Cover Upload operations.
 */
class Politeia_Reading_Cover_Upload_Repository {

	/**
	 * Optimize a cover image on disk:
	 * - Convert to JPEG.
	 * - Constrain dimensions.
	 * - Iteratively reduce quality (and downscale if needed) to keep file size under a threshold.
	 *
	 * Returns array{path:string, mime:string, ext:string} on success.
	 */
	private static function optimize_cover_image_file( $path, $target_path, $max_bytes, $max_width, $max_height ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$editor = wp_get_image_editor( $path );
		if ( is_wp_error( $editor ) ) {
			return $editor;
		}

		// Constrain dimensions (keep aspect ratio).
		$size = $editor->get_size();
		$w    = isset( $size['width'] ) ? (int) $size['width'] : 0;
		$h    = isset( $size['height'] ) ? (int) $size['height'] : 0;
		if ( $w > 0 && $h > 0 && ( $w > $max_width || $h > $max_height ) ) {
			$editor->resize( $max_width, $max_height, false );
		}

		// Prefer JPEG for predictable size; covers don't need alpha.
		$quality = 82;
		$min_q   = 55;
		$tries   = 0;

		while ( true ) {
			if ( method_exists( $editor, 'set_quality' ) ) {
				$editor->set_quality( $quality );
			}

			$saved = $editor->save(
				$target_path,
				'image/jpeg'
			);

			if ( is_wp_error( $saved ) ) {
				return $saved;
			}

			clearstatcache( true, $target_path );
			$bytes = file_exists( $target_path ) ? (int) filesize( $target_path ) : 0;
			if ( $bytes > 0 && $bytes <= $max_bytes ) {
				return array(
					'path' => $target_path,
					'mime' => 'image/jpeg',
					'ext'  => 'jpg',
				);
			}

			$tries++;
			if ( $quality > $min_q ) {
				$quality = max( $min_q, $quality - 8 );
				continue;
			}

			// If still too big at low quality, downscale and try again.
			if ( $tries > 12 ) {
				// Give up; keep latest attempt to avoid totally failing uploads.
				return array(
					'path' => $target_path,
					'mime' => 'image/jpeg',
					'ext'  => 'jpg',
				);
			}

			$size = $editor->get_size();
			$w    = isset( $size['width'] ) ? (int) $size['width'] : 0;
			$h    = isset( $size['height'] ) ? (int) $size['height'] : 0;
			if ( $w > 320 && $h > 320 ) {
				$editor->resize( (int) floor( $w * 0.9 ), (int) floor( $h * 0.9 ), false );
			} else {
				return array(
					'path' => $target_path,
					'mime' => 'image/jpeg',
					'ext'  => 'jpg',
				);
			}
		}
	}

	/**
	 * Build a unique key for the cover.
	 */
	public static function build_cover_key( $user_id, $user_book_id ) {
		return 'ub' . (int) $user_book_id . '-u' . (int) $user_id;
	}

	/**
	 * Cleanup old cover attachments for a user book.
	 */
	public static function cleanup_cover_attachments( $user_id, $user_book_id, $current_attachment_id ) {
		$old_attachments = get_posts(
			array(
				'post_type'      => 'attachment',
				'posts_per_page' => -1,
				'meta_query'     => array(
					array(
						'key'   => '_prs_cover_key',
						'value' => self::build_cover_key( $user_id, $user_book_id ),
					),
				),
				'exclude'        => array( $current_attachment_id ),
			)
		);

		foreach ( $old_attachments as $attach ) {
			wp_delete_attachment( $attach->ID, true );
		}
	}

	/**
	 * Create an attachment from binary image data.
	 */
	public static function create_attachment_from_binary( $binary, $extension, $mime_type, $user_id, $post_id = 0, $user_book_id = 0 ) {
		$upload_dir = wp_upload_dir();

		if ( ! empty( $upload_dir['error'] ) ) {
			return new WP_Error( 'upload_dir_error', $upload_dir['error'] );
		}

		$key_fragment = $user_book_id ? self::build_cover_key( $user_id, $user_book_id ) : 'u' . (int) $user_id;
		$base_name    = 'book-cover-' . $key_fragment . '-' . gmdate( 'Ymd-His' );
		$filename     = $base_name . '.' . $extension;
		$path         = trailingslashit( $upload_dir['path'] ) . $filename;

		if ( false === file_put_contents( $path, $binary ) ) {
			return new WP_Error( 'write_fail', __( 'Failed to write image.', 'politeia-reading' ) );
		}

		// Enforce cover constraints to avoid slow LCP: convert + resize + compress (target <= 400KB).
		$max_bytes   = 400 * 1024;
		$max_width   = 900;
		$max_height  = 1350;
		$final_ext   = 'jpg';
		$final_mime  = 'image/jpeg';
		$final_path  = trailingslashit( $upload_dir['path'] ) . $base_name . '.' . $final_ext;

		$optimized = self::optimize_cover_image_file( $path, $final_path, $max_bytes, $max_width, $max_height );
		if ( is_wp_error( $optimized ) ) {
			@unlink( $path );
			return $optimized;
		}

		// If we wrote to a new file, remove the original.
		if ( $final_path !== $path && file_exists( $path ) ) {
			@unlink( $path );
		}

		$path      = $final_path;
		$filename  = $base_name . '.' . $final_ext;
		$mime_type = $final_mime;

		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => $mime_type,
				'post_title'     => sanitize_file_name( preg_replace( '/\.[^.]+$/', '', $filename ) ),
				'post_status'    => 'inherit',
				'post_author'    => $user_id,
			),
			$path,
			$post_id
		);

		if ( ! $attachment_id ) {
			@unlink( $path );
			return new WP_Error( 'attach_fail', __( 'Could not create attachment.', 'politeia-reading' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		$meta = wp_generate_attachment_metadata( $attachment_id, $path );
		if ( $meta ) {
			wp_update_attachment_metadata( $attachment_id, $meta );
		}

		update_post_meta( $attachment_id, '_prs_cover_user_id', $user_id );
		if ( $user_book_id ) {
			update_post_meta( $attachment_id, '_prs_cover_user_book_id', $user_book_id );
			update_post_meta( $attachment_id, '_prs_cover_key', self::build_cover_key( $user_id, $user_book_id ) );
		}

		return array(
			'attachment_id' => (int) $attachment_id,
			'url'           => wp_get_attachment_url( $attachment_id ),
		);
	}

	/**
	 * Save a cover URL to a user book.
	 */
	public static function update_user_book_cover( $user_book_id, $data ) {
		global $wpdb;
		return $wpdb->update(
			$wpdb->prefix . 'politeia_user_books',
			$data,
			array( 'id' => $user_book_id )
		);
	}
}
