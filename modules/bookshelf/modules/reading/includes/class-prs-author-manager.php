<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles Author synchronization and relationships.
 */
class Politeia_Reading_Author_Manager {

	/**
	 * Get author hashes from a list of names.
	 */
	public static function get_author_hashes_from_names( $authors ) {
		if ( null === $authors ) {
			return array();
		}

		if ( is_string( $authors ) ) {
			$parts   = preg_split( '/[;,\|]+/', $authors );
			$authors = is_array( $parts ) ? $parts : array( $authors );
		} elseif ( $authors instanceof \Traversable ) {
			$authors = iterator_to_array( $authors, false );
		} elseif ( ! is_array( $authors ) ) {
			$authors = array( $authors );
		}

		$hashes = array();
		foreach ( $authors as $raw_author ) {
			$name = trim( wp_strip_all_tags( (string) $raw_author ) );
			if ( '' === $name ) {
				continue;
			}
			$normalized  = function_exists( 'politeia__normalize_text' ) ? politeia__normalize_text( $name ) : strtolower( $name );
			$normalized  = ( '' !== trim( (string) $normalized ) ) ? $normalized : null;
			$hash_source = $normalized ?: strtolower( $name );
			if ( '' === $hash_source ) {
				continue;
			}
			$hashes[] = hash( 'sha256', $hash_source );
		}

		return array_values( array_unique( $hashes ) );
	}

	/**
	 * Generate a unique slug for an author entry.
	 */
	public static function generate_unique_author_slug( $base_slug, $table, $hash_source = '' ) {
		global $wpdb;

		$slug = $base_slug;
		if ( '' === $slug ) {
			$slug      = 'author-' . substr( $hash_source ?: hash( 'sha256', microtime( true ) ), 0, 8 );
			$base_slug = $slug;
		}

		$candidate = $slug;
		$suffix    = 2;

		while ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE slug = %s LIMIT 1", $candidate ) ) ) {
			$candidate = $base_slug . '-' . $suffix;

			if ( strlen( $candidate ) > 191 ) {
				$candidate = substr( $base_slug, 0, max( 1, 191 - strlen( (string) $suffix ) - 1 ) ) . '-' . $suffix;
			}

			$suffix++;

			if ( $suffix > 20 ) {
				$fallback  = $hash_source ?: hash( 'crc32', $base_slug . microtime( true ) );
				$candidate = substr( $base_slug, 0, 180 ) . '-' . substr( $fallback, 0, 8 );
				break;
			}
		}

		return $candidate;
	}

	/**
	 * Synchronize book-author links.
	 */
	public static function sync_book_author_links( $book_id, $authors, $source = 'candidate' ) {
		global $wpdb;

		$book_id = (int) $book_id;
		if ( $book_id <= 0 ) {
			return array();
		}

		if ( 'confirmed' !== $source ) {
			return array();
		}

		if ( null === $authors ) {
			$authors = array();
		} elseif ( is_string( $authors ) ) {
			$parts   = preg_split( '/[;,\|]+/', $authors );
			$authors = is_array( $parts ) ? $parts : array( $authors );
		} elseif ( $authors instanceof \Traversable ) {
			$authors = iterator_to_array( $authors, false );
		} elseif ( ! is_array( $authors ) ) {
			$authors = array( $authors );
		}

		$canonical = array();
		$position  = 0;

		foreach ( $authors as $raw_author ) {
			$name = trim( wp_strip_all_tags( (string) $raw_author ) );
			if ( '' === $name ) {
				continue;
			}

			$normalized = function_exists( 'politeia__normalize_text' ) ? politeia__normalize_text( $name ) : strtolower( $name );
			$normalized = ( '' !== trim( (string) $normalized ) ) ? $normalized : null;

			$hash_source = $normalized ?: strtolower( $name );
			if ( '' === $hash_source ) {
				continue;
			}

			$hash = hash( 'sha256', $hash_source );

			if ( isset( $canonical[ $hash ] ) ) {
				continue;
			}

			$canonical[ $hash ] = array(
				'name'       => $name,
				'normalized' => $normalized,
				'hash'       => $hash,
				'position'   => $position,
			);
			$position++;
		}

		$book_author_table = $wpdb->prefix . 'politeia_book_authors';
		if ( empty( $canonical ) ) {
			$wpdb->delete( $book_author_table, array( 'book_id' => $book_id ) );
			return array();
		}

		uasort(
			$canonical,
			static function ( $a, $b ) {
				return $a['position'] <=> $b['position'];
			}
		);

		$authors_table = $wpdb->prefix . 'politeia_authors';
		$hashes        = array_keys( $canonical );
		$existing      = array();

		if ( ! empty( $hashes ) ) {
			$placeholders = implode( ', ', array_fill( 0, count( $hashes ), '%s' ) );
			$sql          = "SELECT id, name_hash FROM {$authors_table} WHERE name_hash IN ({$placeholders})";
			$rows         = $wpdb->get_results( $wpdb->prepare( $sql, $hashes ) );

			foreach ( $rows as $row ) {
				$existing[ $row->name_hash ] = (int) $row->id;
			}
		}

		$now = current_time( 'mysql', true );

		foreach ( $canonical as $hash => &$author ) {
			if ( isset( $existing[ $hash ] ) ) {
				$author['id'] = $existing[ $hash ];
				continue;
			}

			$slug_base = sanitize_title( $author['name'] );
			$slug      = self::generate_unique_author_slug( $slug_base, $authors_table, $hash );

			$wpdb->insert(
				$authors_table,
				array(
					'display_name'    => $author['name'],
					'normalized_name' => $author['normalized'],
					'name_hash'       => $author['hash'],
					'slug'            => $slug,
					'created_at'      => $now,
					'updated_at'      => $now,
				),
				array( '%s', '%s', '%s', '%s', '%s', '%s' )
			);

			if ( $wpdb->last_error ) {
				continue;
			}

			$author['id'] = (int) $wpdb->insert_id;
		}
		unset( $author );

		$author_ids = array();
		foreach ( $canonical as $author ) {
			if ( empty( $author['id'] ) ) {
				continue;
			}
			$author_ids[] = (int) $author['id'];
		}

		$existing_links = $wpdb->get_results(
			$wpdb->prepare( "SELECT id, author_id FROM {$book_author_table} WHERE book_id = %d", $book_id )
		);
		$existing_map   = array();

		foreach ( $existing_links as $link ) {
			$existing_map[ (int) $link->author_id ] = (int) $link->id;
		}

		$position = 0;
		foreach ( $canonical as $author ) {
			if ( empty( $author['id'] ) ) {
				continue;
			}

			$author_id = (int) $author['id'];

			if ( isset( $existing_map[ $author_id ] ) ) {
				$wpdb->update(
					$book_author_table,
					array(
						'sort_order' => $position,
						'updated_at' => $now,
					),
					array( 'id' => $existing_map[ $author_id ] ),
					array( '%d', '%s' ),
					array( '%d' )
				);

				unset( $existing_map[ $author_id ] );
			} else {
				$wpdb->insert(
					$book_author_table,
					array(
						'book_id'    => $book_id,
						'author_id'  => $author_id,
						'sort_order' => $position,
						'created_at' => $now,
						'updated_at' => $now,
					),
					array( '%d', '%d', '%d', '%s', '%s' )
				);
			}

			$position++;
		}

		if ( ! empty( $existing_map ) ) {
			$ids_to_delete = array_values( $existing_map );
			$placeholders  = implode( ', ', array_fill( 0, count( $ids_to_delete ), '%d' ) );
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$book_author_table} WHERE id IN ({$placeholders})",
					$ids_to_delete
				)
			);
		}

		return $author_ids;
	}
}
