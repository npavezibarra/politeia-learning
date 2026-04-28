<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Repository for Cover Upload operations.
 */
class Politeia_Reading_Cover_Upload_Repository {

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
		$filename     = 'book-cover-' . $key_fragment . '-' . gmdate( 'Ymd-His' ) . '.' . $extension;
		$path         = trailingslashit( $upload_dir['path'] ) . $filename;

		if ( false === file_put_contents( $path, $binary ) ) {
			return new WP_Error( 'write_fail', __( 'Failed to write image.', 'politeia-reading' ) );
		}

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
