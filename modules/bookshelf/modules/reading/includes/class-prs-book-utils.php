<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Utility functions for Book metadata and identification.
 */
class Politeia_Reading_Book_Utils {

	/**
	 * Check if the books table has an ISBN column.
	 */
	public static function has_isbn_column() {
		static $has_column = null;
		if ( null !== $has_column ) {
			return $has_column;
		}

		global $wpdb;
		$table      = $wpdb->prefix . 'politeia_books';
		$has_column = (bool) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s LIMIT 1',
				$table,
				'isbn'
			)
		);
		return $has_column;
	}

	/**
	 * Normalize ISBN string.
	 */
	public static function normalize_isbn( $isbn ) {
		if ( null === $isbn ) {
			return '';
		}
		$isbn = preg_replace( '/[^0-9Xx]/', '', (string) $isbn );
		return $isbn ? strtoupper( $isbn ) : '';
	}

	/**
	 * Update book ISBN if it's empty.
	 */
	public static function update_book_isbn_if_empty( $book_id, $isbn ) {
		global $wpdb;

		$book_id = (int) $book_id;
		if ( $book_id <= 0 || ! self::has_isbn_column() ) {
			return;
		}

		$isbn = self::normalize_isbn( $isbn );
		if ( '' === $isbn ) {
			return;
		}

		$table = $wpdb->prefix . 'politeia_books';
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET isbn = %s WHERE id = %d AND (isbn IS NULL OR isbn = '')",
				$isbn,
				$book_id
			)
		);
	}

	/**
	 * Get book ID by ISBN.
	 */
	public static function get_book_id_by_isbn( $isbn ) {
		if ( ! self::has_isbn_column() ) {
			return 0;
		}

		$isbn = self::normalize_isbn( $isbn );
		if ( '' === $isbn ) {
			return 0;
		}

		global $wpdb;
		$table   = $wpdb->prefix . 'politeia_books';
		$book_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE isbn = %s LIMIT 1",
				$isbn
			)
		);
		return $book_id ? (int) $book_id : 0;
	}

	/**
	 * Get book ISBN by ID.
	 */
	public static function get_book_isbn( $book_id ) {
		if ( ! self::has_isbn_column() ) {
			return '';
		}

		$book_id = (int) $book_id;
		if ( $book_id <= 0 ) {
			return '';
		}

		global $wpdb;
		$table = $wpdb->prefix . 'politeia_books';
		$isbn  = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT isbn FROM {$table} WHERE id = %d LIMIT 1",
				$book_id
			)
		);
		return self::normalize_isbn( $isbn );
	}

	/**
	 * Normalize title string for identity checks.
	 */
	public static function normalize_title( $title ) {
		if ( function_exists( 'politeia__normalize_text' ) ) {
			return politeia__normalize_text( $title );
		}

		$normalized_title = (string) $title;
		$normalized_title = wp_strip_all_tags( $normalized_title );
		$normalized_title = html_entity_decode( $normalized_title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
		$normalized_title = trim( $normalized_title );
		$normalized_title = remove_accents( $normalized_title );
		if ( function_exists( 'mb_strtolower' ) ) {
			$normalized_title = mb_strtolower( $normalized_title, 'UTF-8' );
		} else {
			$normalized_title = strtolower( $normalized_title );
		}
		$normalized_title = preg_replace( '/[^a-z0-9\s\-\_\'\":]+/u', ' ', $normalized_title );
		$normalized_title = preg_replace( '/\s+/u', ' ', $normalized_title );
		return trim( $normalized_title );
	}

	/**
	 * Get book ID by slug.
	 */
	public static function get_book_id_by_slug( $slug ) {
		global $wpdb;

		$slug = is_string( $slug ) ? trim( $slug ) : '';
		if ( '' === $slug ) {
			return 0;
		}

		if ( self::slugs_table_exists() ) {
			$table   = self::get_slugs_table_name();
			$book_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT book_id FROM {$table} WHERE slug = %s LIMIT 1",
					$slug
				)
			);
			if ( $book_id ) {
				return (int) $book_id;
			}
		}

		$books_table = $wpdb->prefix . 'politeia_books';
		$book_id     = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$books_table} WHERE slug = %s LIMIT 1",
				$slug
			)
		);
		return $book_id ? (int) $book_id : 0;
	}

	/**
	 * Get slugs table name.
	 */
	public static function get_slugs_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'politeia_book_slugs';
	}

	/**
	 * Check if slugs table exists.
	 */
	public static function slugs_table_exists() {
		static $exists = null;
		if ( null !== $exists ) {
			return $exists;
		}

		global $wpdb;
		$table  = self::get_slugs_table_name();
		$exists = (bool) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s LIMIT 1',
				$table
			)
		);
		return $exists;
	}

	/**
	 * Check if a book slug exists.
	 */
	public static function book_slug_exists( $slug, $exclude_book_id = 0 ) {
		global $wpdb;

		$slug = is_string( $slug ) ? trim( $slug ) : '';
		if ( '' === $slug ) {
			return false;
		}

		$exclude_book_id = (int) $exclude_book_id;

		if ( self::slugs_table_exists() ) {
			$table  = self::get_slugs_table_name();
			$query  = "SELECT book_id FROM {$table} WHERE slug = %s";
			$params = array( $slug );
			if ( $exclude_book_id > 0 ) {
				$query   .= ' AND book_id <> %d';
				$params[] = $exclude_book_id;
			}
			$query   .= ' LIMIT 1';
			$book_id = $wpdb->get_var( $wpdb->prepare( $query, $params ) );
			if ( $book_id ) {
				return true;
			}
		}

		$books_table = $wpdb->prefix . 'politeia_books';
		$query       = "SELECT id FROM {$books_table} WHERE slug = %s";
		$params      = array( $slug );
		if ( $exclude_book_id > 0 ) {
			$query   .= ' AND id <> %d';
			$params[] = $exclude_book_id;
		}
		$query   .= ' LIMIT 1';
		$book_id = $wpdb->get_var( $wpdb->prepare( $query, $params ) );
		return ! empty( $book_id );
	}

	/**
	 * Generate a book slug.
	 */
	public static function generate_book_slug( $title, $year = null, $exclude_book_id = 0 ) {
		$base = sanitize_title( (string) $title );
		if ( '' === $base ) {
			$fallback_id = (int) $exclude_book_id;
			return $fallback_id > 0 ? 'book-' . $fallback_id : '';
		}

		if ( ! self::book_slug_exists( $base, $exclude_book_id ) ) {
			return $base;
		}

		if ( $year ) {
			$with_year = $base . '-' . (int) $year;
			if ( ! self::book_slug_exists( $with_year, $exclude_book_id ) ) {
				return $with_year;
			}
		}

		return '';
	}

	/**
	 * Set primary book slug.
	 */
	public static function set_primary_book_slug( $book_id, $slug ) {
		global $wpdb;

		$book_id = (int) $book_id;
		$slug    = is_string( $slug ) ? trim( $slug ) : '';
		if ( $book_id <= 0 || '' === $slug || ! self::slugs_table_exists() ) {
			return;
		}

		$table = self::get_slugs_table_name();

		$wpdb->update(
			$table,
			array( 'is_primary' => 0 ),
			array( 'book_id' => $book_id ),
			array( '%d' ),
			array( '%d' )
		);

		$existing_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE slug = %s LIMIT 1",
				$slug
			)
		);

		if ( $existing_id ) {
			$wpdb->update(
				$table,
				array(
					'book_id'    => $book_id,
					'is_primary' => 1,
					'updated_at' => current_time( 'mysql' ),
				),
				array( 'id' => (int) $existing_id ),
				array( '%d', '%d', '%s' ),
				array( '%d' )
			);
			return;
		}

		$wpdb->insert(
			$table,
			array(
				'book_id'    => $book_id,
				'slug'       => $slug,
				'is_primary' => 1,
				'created_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%d', '%s', '%s' )
		);
	}

	/**
	 * Get primary slug for book.
	 */
	public static function get_primary_slug_for_book( $book_id ) {
		global $wpdb;

		$book_id = (int) $book_id;
		if ( $book_id <= 0 ) {
			return '';
		}

		if ( self::slugs_table_exists() ) {
			$table = self::get_slugs_table_name();
			$slug  = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT slug FROM {$table} WHERE book_id = %d AND is_primary = 1 LIMIT 1",
					$book_id
				)
			);
			if ( is_string( $slug ) && '' !== trim( $slug ) ) {
				return $slug;
			}
		}

		$books_table = $wpdb->prefix . 'politeia_books';
		$slug        = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT slug FROM {$books_table} WHERE id = %d LIMIT 1",
				$book_id
			)
		);
		return is_string( $slug ) ? $slug : '';
	}

	/**
	 * Ensure primary book slug exists.
	 */
	public static function ensure_primary_book_slug( $book_id, $title = '', $year = null ) {
		global $wpdb;

		$book_id = (int) $book_id;
		if ( $book_id <= 0 ) {
			return '';
		}

		$primary_slug = self::get_primary_slug_for_book( $book_id );
		if ( '' !== $primary_slug ) {
			return $primary_slug;
		}

		$title = trim( wp_strip_all_tags( (string) $title ) );
		$year  = $year ? (int) $year : null;

		$candidates = array();
		if ( '' !== $title ) {
			$base = sanitize_title( $title );
			if ( '' !== $base ) {
				$candidates[] = $base;
				if ( $year ) {
					$candidates[] = $base . '-' . $year;
				}
				$candidates[] = $base . '-' . $book_id;
			}
		}
		$candidates[] = 'book-' . $book_id;

		$books_table = $wpdb->prefix . 'politeia_books';

		foreach ( array_unique( array_filter( $candidates ) ) as $candidate ) {
			if ( self::book_slug_exists( $candidate, $book_id ) ) {
				continue;
			}

			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$books_table} SET slug = %s, updated_at = CURRENT_TIMESTAMP WHERE id = %d AND (slug IS NULL OR slug = '')",
					$candidate,
					$book_id
				)
			);

			if ( self::slugs_table_exists() ) {
				self::set_primary_book_slug( $book_id, $candidate );
			}

			return $candidate;
		}

		return '';
	}
}
