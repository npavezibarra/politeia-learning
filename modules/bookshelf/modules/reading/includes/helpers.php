<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Backward compatibility wrappers for the refactored modular classes.
 * Logic has been moved to:
 * - Politeia_Reading_Book_Utils
 * - Politeia_Reading_Book_Repository
 * - Politeia_Reading_Author_Manager
 * - Politeia_Reading_UI_Renderer
 * - Politeia_Reading_DB_Migrations
 */

function prs_current_user_id_or_die() {
	if ( ! is_user_logged_in() ) {
		wp_die( __( 'You must be logged in.', 'politeia-reading' ) );
	}
	return get_current_user_id();
}

// --- ISBN & SLUG HELPERS ---

function prs_books_slugs_table_name() {
	return Politeia_Reading_Book_Utils::get_slugs_table_name();
}

function prs_books_has_isbn_column() {
	return Politeia_Reading_Book_Utils::has_isbn_column();
}

function prs_normalize_isbn( $isbn ) {
	return Politeia_Reading_Book_Utils::normalize_isbn( $isbn );
}

function prs_update_book_isbn_if_empty( $book_id, $isbn ) {
	Politeia_Reading_Book_Utils::update_book_isbn_if_empty( $book_id, $isbn );
}

function prs_get_book_id_by_isbn( $isbn ) {
	return Politeia_Reading_Book_Utils::get_book_id_by_isbn( $isbn );
}

function prs_get_book_isbn( $book_id ) {
	return Politeia_Reading_Book_Utils::get_book_isbn( $book_id );
}

function prs_normalize_title( $title ) {
	return Politeia_Reading_Book_Utils::normalize_title( $title );
}

function prs_books_slugs_table_exists() {
	return Politeia_Reading_Book_Utils::slugs_table_exists();
}

function prs_get_book_id_by_slug( $slug ) {
	return Politeia_Reading_Book_Utils::get_book_id_by_slug( $slug );
}

function prs_get_book_id_by_primary_slug( $slug ) {
	// Simple mapping since implementation was identical to get_book_id_by_slug in original
	return Politeia_Reading_Book_Utils::get_book_id_by_slug( $slug );
}

function prs_get_primary_slug_for_book( $book_id ) {
	return Politeia_Reading_Book_Utils::get_primary_slug_for_book( $book_id );
}

function prs_ensure_primary_book_slug( $book_id, $title = '', $year = null ) {
	return Politeia_Reading_Book_Utils::ensure_primary_book_slug( $book_id, $title, $year );
}

function prs_book_slug_exists( $slug, $exclude_book_id = 0 ) {
	return Politeia_Reading_Book_Utils::book_slug_exists( $slug, $exclude_book_id );
}

function prs_generate_book_slug( $title, $year = null, $exclude_book_id = 0 ) {
	return Politeia_Reading_Book_Utils::generate_book_slug( $title, $year, $exclude_book_id );
}

function prs_set_primary_book_slug( $book_id, $slug ) {
	Politeia_Reading_Book_Utils::set_primary_book_slug( $book_id, $slug );
}

function prs_add_book_slug_alias( $book_id, $slug ) {
	// This was a minor helper, mapping to repository/utils if needed, 
	// but kept here for simple compat if rarely used.
	global $wpdb;
	if ( ! Politeia_Reading_Book_Utils::slugs_table_exists() ) return;
	$table = Politeia_Reading_Book_Utils::get_slugs_table_name();
	$wpdb->insert($table, array('book_id' => $book_id, 'slug' => $slug, 'is_primary' => 0));
}

// --- BOOK & AUTHOR REPOSITORY ---

function prs_get_author_hashes_from_names( $authors ) {
	return Politeia_Reading_Author_Manager::get_author_hashes_from_names( $authors );
}

function prs_get_book_id_by_identity( $title, $authors, $year = null ) {
	return Politeia_Reading_Book_Repository::get_book_id_by_identity( $title, $authors, $year );
}

function prs_find_or_create_book( $title, $author, $year = null, $isbn = '', $attachment_id = null, $all_authors = null, $source = 'candidate' ) {
	return Politeia_Reading_Book_Repository::find_or_create_book( $title, $author, $year, $isbn, $attachment_id, $all_authors, $source );
}

function prs_create_book_candidate( $input, $args = array() ) {
	return Politeia_Reading_Book_Repository::create_book_candidate( $input, $args );
}

function prs_promote_candidate_to_canonical( $candidate_id, $user_id, $year_override = null ) {
	return Politeia_Reading_Book_Repository::promote_candidate_to_canonical( $candidate_id, $user_id, $year_override );
}

function prs_diagnose_canonical_identity_collisions() {
	return Politeia_Reading_Book_Repository::diagnose_canonical_identity_collisions();
}

function prs_library_cache_version_meta_key() {
	return '_prs_library_cache_version';
}

function prs_get_library_cache_version( $user_id ) {
	$user_id = (int) $user_id;
	if ( $user_id <= 0 ) {
		return 1;
	}

	$version = (int) get_user_meta( $user_id, prs_library_cache_version_meta_key(), true );
	return $version > 0 ? $version : 1;
}

function prs_bump_library_cache_version( $user_id ) {
	$user_id = (int) $user_id;
	if ( $user_id <= 0 ) {
		return 0;
	}

	$version = prs_get_library_cache_version( $user_id ) + 1;
	update_user_meta( $user_id, prs_library_cache_version_meta_key(), $version );
	return $version;
}

function prs_invalidate_library_cache_for_user( $user_id ) {
	return prs_bump_library_cache_version( $user_id );
}

function prs_invalidate_library_cache_for_book( $book_id ) {
	global $wpdb;

	$book_id = (int) $book_id;
	if ( $book_id <= 0 ) {
		return 0;
	}

	$table = $wpdb->prefix . 'politeia_user_books';
	$user_ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT DISTINCT user_id FROM {$table} WHERE book_id = %d AND deleted_at IS NULL",
			$book_id
		)
	);

	$count = 0;
	foreach ( (array) $user_ids as $user_id ) {
		if ( prs_bump_library_cache_version( (int) $user_id ) ) {
			$count++;
		}
	}

	return $count;
}

function prs_get_library_cache_key( $user_id, $page = 1, $per_page = 15, $force_recent = false ) {
	$user_id = (int) $user_id;
	$page    = max( 1, (int) $page );
	$per_page = max( 1, (int) $per_page );
	$variant = $force_recent ? 'recent' : 'title';
	$locale  = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();

	return implode(
		':',
		array(
			'prs_library_ctx',
			'user' . $user_id,
			'v' . prs_get_library_cache_version( $user_id ),
			'p' . $page,
			'pp' . $per_page,
			$variant,
			'l' . sanitize_key( (string) $locale ),
		)
	);
}

function prs_normalize_library_search_query( $search ) {
	$search = sanitize_text_field( (string) $search );
	$search = trim( $search );

	if ( '' === $search ) {
		return '';
	}

	return function_exists( 'mb_strtolower' ) ? mb_strtolower( $search, 'UTF-8' ) : strtolower( $search );
}

function prs_get_library_results_cache_key( $user_id, $page = 1, $per_page = 15, $search = '', $order = 'title_asc' ) {
	$user_id = (int) $user_id;
	$page    = max( 1, (int) $page );
	$per_page = max( 1, (int) $per_page );
	$search_hash = md5( prs_normalize_library_search_query( $search ) );
	$order_key = sanitize_key( (string) $order );
	$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();

	return implode(
		':',
		array(
			'prs_library_results',
			'user' . $user_id,
			'v' . prs_get_library_cache_version( $user_id),
			'p' . $page,
			'pp' . $per_page,
			'o' . $order_key,
			'q' . $search_hash,
			'l' . sanitize_key( (string) $locale ),
		)
	);
}

function prs_ensure_user_book( $user_id, $book_id ) {
	return Politeia_Reading_Book_Repository::ensure_user_book( $user_id, $book_id );
}

function prs_get_user_books_for_library( $user_id, $args = array() ) {
	return Politeia_Reading_Book_Repository::get_user_books_for_library( $user_id, $args );
}

function prs_get_user_books_for_library_count( $user_id, $args = array() ) {
	return Politeia_Reading_Book_Repository::get_user_books_for_library_count( $user_id, $args );
}

function prs_sync_book_author_links( $book_id, $authors, $source = 'candidate' ) {
	return Politeia_Reading_Author_Manager::sync_book_author_links( $book_id, $authors, $source );
}

function prs_generate_unique_author_slug( $base_slug, $table, $hash_source = '' ) {
	return Politeia_Reading_Author_Manager::generate_unique_author_slug( $base_slug, $table, $hash_source );
}

// --- UI RENDERERS ---

function prs_render_owning_overlay( $args = array() ) {
	Politeia_Reading_UI_Renderer::render_owning_overlay( $args );
}

function prs_get_owning_labels() {
	return Politeia_Reading_UI_Renderer::get_owning_labels();
}

function prs_render_book_row( $book, $context = array() ) {
	return Politeia_Reading_UI_Renderer::render_book_row( $book, $context );
}

// --- OTHER HELPERS ---

function prs_handle_cover_upload( $field_name = 'prs_cover' ) {
	// Kept here as it's a small file-handling helper
	if ( empty( $_FILES[ $field_name ]['name'] ) ) return null;
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	$file = wp_handle_upload( $_FILES[ $field_name ], array( 'test_form' => false ) );
	if ( isset( $file['error'] ) ) return null;
	$attachment = array('post_mime_type' => $file['type'], 'post_title' => sanitize_file_name( basename( $file['file'] ) ), 'post_content' => '', 'post_status' => 'inherit');
	$attach_id = wp_insert_attachment( $attachment, $file['file'] );
	wp_update_attachment_metadata( $attach_id, wp_generate_attachment_metadata( $attach_id, $file['file'] ) );
	return (int) $attach_id;
}

function prs_get_active_loan_start_date( $user_id, $book_id ) {
	global $wpdb;
	$t = $wpdb->prefix . 'politeia_loans';
	return $wpdb->get_var( $wpdb->prepare( "SELECT start_date FROM {$t} WHERE user_id=%d AND book_id=%d AND end_date IS NULL ORDER BY id DESC LIMIT 1", $user_id, $book_id ) );
}
