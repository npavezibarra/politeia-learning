<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the logic for processing book additions (single and candidates).
 */
class Politeia_Reading_Add_Book_Handler {

	/**
	 * Initialize form submission hooks.
	 */
	public static function init() {
		add_action( 'admin_post_prs_add_book_submit', array( __CLASS__, 'handle_submit' ) );
		add_action( 'admin_post_nopriv_prs_add_book_submit', array( __CLASS__, 'handle_submit' ) );
	}

	/**
	 * Handle the book addition form submission.
	 */
	public static function handle_submit() {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Login required.', 'politeia-reading' ) );
		}
		if ( ! isset( $_POST['prs_nonce'] ) || ! wp_verify_nonce( $_POST['prs_nonce'], 'prs_add_book' ) ) {
			wp_die( esc_html__( 'Invalid nonce.', 'politeia-reading' ) );
		}

		global $wpdb;
		$user_id = get_current_user_id();

		// Sanitization
		$title   = isset( $_POST['prs_title'] ) ? sanitize_text_field( wp_unslash( $_POST['prs_title'] ) ) : '';
		$authors = array();

		if ( isset( $_POST['prs_author'] ) ) {
			$raw_authors = wp_unslash( $_POST['prs_author'] );

			$collect_authors = static function( $value ) use ( &$authors ) {
				if ( null === $value || '' === $value ) {
					return;
				}

				$candidates = explode( ',', (string) $value );

				foreach ( $candidates as $candidate ) {
					$clean_author = sanitize_text_field( $candidate );
					if ( '' === $clean_author ) {
						continue;
					}

					$clean_author = preg_replace( '/\s+/', ' ', $clean_author );
					$clean_author = trim( (string) $clean_author );

					if ( '' !== $clean_author ) {
						$authors[] = $clean_author;
					}
				}
			};

			if ( is_array( $raw_authors ) ) {
				foreach ( $raw_authors as $raw_author ) {
					$collect_authors( $raw_author );
				}
			} else {
				$collect_authors( $raw_authors );
			}
		}

		$primary_author = '';

		if ( ! empty( $authors ) ) {
			$normalized = array();
			$unique     = array();

			foreach ( $authors as $raw_author ) {
				$key = function_exists( 'mb_strtolower' ) ? mb_strtolower( $raw_author, 'UTF-8' ) : strtolower( $raw_author );
				if ( isset( $normalized[ $key ] ) ) {
					continue;
				}
				$normalized[ $key ] = true;
				$unique[]           = $raw_author;
			}

			if ( ! empty( $unique ) ) {
				$primary_author = array_shift( $unique );
				$authors        = array_values( $unique );
			}
		}

		$year = null;
		if ( isset( $_POST['prs_year'] ) && $_POST['prs_year'] !== '' ) {
			$y   = absint( $_POST['prs_year'] );
			$min = 1400;
			$max = (int) date( 'Y' ) + 1;
			if ( $y >= $min && $y <= $max ) {
				$year = $y;
			}
		}

		$pages = null;
		if ( isset( $_POST['prs_pages'] ) && $_POST['prs_pages'] !== '' ) {
			$p = absint( $_POST['prs_pages'] );
			if ( $p > 0 ) {
				$pages = $p;
			}
		}

		$isbn = '';
		if ( isset( $_POST['prs_isbn'] ) && $_POST['prs_isbn'] !== '' ) {
			$raw_isbn = sanitize_text_field( wp_unslash( $_POST['prs_isbn'] ) );
			$raw_isbn = preg_replace( '/[^0-9Xx]/', '', (string) $raw_isbn );
			if ( '' !== $raw_isbn ) {
				$isbn = strtoupper( $raw_isbn );
			}
		}

		if ( '' === $title || '' === $primary_author ) {
			wp_safe_redirect( add_query_arg( 'prs_error', 1, wp_get_referer() ?: home_url() ) );
			exit;
		}

		$all_authors = array_merge( array( $primary_author ), $authors );

		// Handle cover upload
		$attachment_id = function_exists( 'prs_handle_cover_upload' ) ? prs_handle_cover_upload( 'prs_cover' ) : 0;
		$cover_url     = '';
		if ( $attachment_id ) {
			$cover_url = wp_get_attachment_image_url( $attachment_id, 'medium' );
			$cover_url = $cover_url ? esc_url_raw( $cover_url ) : '';
		}
		if ( ! $attachment_id && isset( $_POST['prs_cover_url'] ) && $_POST['prs_cover_url'] !== '' ) {
			$cover_url = esc_url_raw( wp_unslash( $_POST['prs_cover_url'] ) );
		}

		// Check if book exists
		$book_id = 0;
		if ( $isbn ) {
			$book_id = (int) ( function_exists( 'prs_get_book_id_by_isbn' ) ? prs_get_book_id_by_isbn( $isbn ) : 0 );
		}
		$slug = function_exists( 'prs_generate_book_slug' ) ? prs_generate_book_slug( $title, $year ) : '';
		if ( ! $book_id && $slug ) {
			$book_id = (int) ( function_exists( 'prs_get_book_id_by_slug' ) ? prs_get_book_id_by_slug( $slug ) : 0 );
		}
		if ( ! $book_id ) {
			$book_id = (int) ( function_exists( 'prs_get_book_id_by_identity' ) ? prs_get_book_id_by_identity( $title, $all_authors, $year ) : 0 );
		}

		if ( $book_id ) {
			$existing_user_book_id = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}politeia_user_books WHERE user_id = %d AND book_id = %d AND deleted_at IS NULL LIMIT 1",
					$user_id,
					$book_id
				)
			);
			if ( $existing_user_book_id ) {
				$input_isbn      = function_exists( 'prs_normalize_isbn' ) ? prs_normalize_isbn( $isbn ) : $isbn;
				$canonical_isbn  = function_exists( 'prs_get_book_isbn' ) ? prs_get_book_isbn( $book_id ) : '';
				$allow_duplicate = ( '' !== $input_isbn && '' !== $canonical_isbn && $input_isbn !== $canonical_isbn );
				if ( ! $allow_duplicate ) {
					$redirect_url = wp_get_referer() ?: home_url();
					$query_args   = array(
						'prs_error'       => 'duplicate',
						'prs_error_title' => $title,
					);
					wp_safe_redirect( add_query_arg( $query_args, $redirect_url ) );
					exit;
				}
			}

			$user_book_id = function_exists( 'prs_ensure_user_book' ) ? prs_ensure_user_book( $user_id, (int) $book_id ) : 0;
			if ( $user_book_id && null !== $pages ) {
				$wpdb->update(
					$wpdb->prefix . 'politeia_user_books',
					array( 'pages' => $pages ),
					array( 'id' => (int) $user_book_id ),
					array( '%d' ),
					array( '%d' )
				);
			}
			if ( $book_id && $isbn ) {
				if ( function_exists( 'prs_update_book_isbn_if_empty' ) ) {
					prs_update_book_isbn_if_empty( $book_id, $isbn );
				}
			}
			if ( $user_book_id && $cover_url ) {
				$wpdb->update(
					$wpdb->prefix . 'politeia_user_books',
					array( 'cover_reference' => $cover_url ),
					array( 'id' => (int) $user_book_id ),
					array( '%s' ),
					array( '%d' )
				);
			}
			if ( $user_book_id ) {
				$wpdb->update(
					$wpdb->prefix . 'politeia_user_books',
					array( 'updated_at' => current_time( 'mysql' ) ),
					array( 'id' => (int) $user_book_id ),
					array( '%s' ),
					array( '%d' )
				);
			}
		} else {
			// Create candidate
			$candidate_input = array(
				'title'  => $title,
				'author' => $primary_author,
				'year'   => $year,
				'isbn'   => $isbn,
				'image'  => $attachment_id ? (int) $attachment_id : null,
			);
			$candidate_args = array(
				'user_id'      => $user_id,
				'input_type'   => 'single_add',
				'source_note'  => 'single-add',
				'enqueue'      => true,
				'raw_response' => array(
					'cover_attachment_id' => $attachment_id ? (int) $attachment_id : null,
					'cover_url'           => $cover_url,
					'pages'               => $pages,
					'authors'             => $authors,
					'isbn'                => $isbn,
				),
			);

			$candidate_result = function_exists( 'prs_create_book_candidate' ) ? prs_create_book_candidate( $candidate_input, $candidate_args ) : array();

			$confirm_items = array();
			if ( ! empty( $candidate_result['pending'] ) ) {
				$pending_item = $candidate_result['pending'][0];
				$confirm_items[] = array(
					'id'     => isset( $pending_item['id'] ) ? (int) $pending_item['id'] : 0,
					'title'  => $pending_item['title'] ?? $title,
					'author' => $pending_item['author'] ?? $primary_author,
					'year'   => $year,
				);
			} elseif ( ! empty( $candidate_result['in_shelf'] ) ) {
				$confirm_items = array();
			} else {
				wp_safe_redirect( add_query_arg( 'prs_error', 1, wp_get_referer() ?: home_url() ) );
				exit;
			}

			if ( ! empty( $confirm_items ) ) {
				if ( function_exists( 'politeia_chatgpt_safe_require' ) ) {
					politeia_chatgpt_safe_require( 'modules/buttons/class-buttons-confirm-controller.php' );
				}

				if ( class_exists( 'Politeia_Buttons_Confirm_Controller' ) && method_exists( 'Politeia_Buttons_Confirm_Controller', 'confirm_items_direct' ) ) {
					$confirm_result = Politeia_Buttons_Confirm_Controller::confirm_items_direct( $confirm_items );
					if ( empty( $confirm_result['confirmed'] ) ) {
						wp_safe_redirect( add_query_arg( 'prs_error', 1, wp_get_referer() ?: home_url() ) );
						exit;
					}
				} else {
					wp_safe_redirect( add_query_arg( 'prs_error', 1, wp_get_referer() ?: home_url() ) );
					exit;
				}
			}

			$resolved_book_id = (int) $book_id;
			if ( $resolved_book_id <= 0 ) {
				if ( $isbn ) {
					$resolved_book_id = (int) ( function_exists( 'prs_get_book_id_by_isbn' ) ? prs_get_book_id_by_isbn( $isbn ) : 0 );
				}
				if ( $resolved_book_id <= 0 && $slug ) {
					$resolved_book_id = (int) ( function_exists( 'prs_get_book_id_by_slug' ) ? prs_get_book_id_by_slug( $slug ) : 0 );
				}
				if ( $resolved_book_id <= 0 ) {
					$resolved_book_id = (int) ( function_exists( 'prs_get_book_id_by_identity' ) ? prs_get_book_id_by_identity( $title, $all_authors, $year ) : 0 );
				}
			}

			if ( $resolved_book_id > 0 ) {
				$user_book_id = function_exists( 'prs_ensure_user_book' ) ? prs_ensure_user_book( $user_id, (int) $resolved_book_id ) : 0;
				if ( $user_book_id ) {
					$wpdb->update(
						$wpdb->prefix . 'politeia_user_books',
						array( 'updated_at' => current_time( 'mysql' ) ),
						array( 'id' => (int) $user_book_id ),
						array( '%s' ),
						array( '%d' )
					);
				}
			}

			if ( null !== $pages ) {
				$page_slug = $slug ?: ( function_exists( 'prs_generate_book_slug' ) ? prs_generate_book_slug( $title, $year ) : '' );
				if ( $page_slug ) {
					$book_id = (int) ( function_exists( 'prs_get_book_id_by_slug' ) ? prs_get_book_id_by_slug( $page_slug ) : 0 );
				}

				if ( $book_id ) {
					$user_book_id = function_exists( 'prs_ensure_user_book' ) ? prs_ensure_user_book( $user_id, (int) $book_id ) : 0;
					if ( $user_book_id ) {
						$wpdb->update(
							$wpdb->prefix . 'politeia_user_books',
							array( 'pages' => $pages ),
							array( 'id' => (int) $user_book_id ),
							array( '%d' ),
							array( '%d' )
						);
					}
					if ( $user_book_id && $cover_url ) {
						$wpdb->update(
							$wpdb->prefix . 'politeia_user_books',
							array( 'cover_reference' => $cover_url ),
							array( 'id' => (int) $user_book_id ),
							array( '%s' ),
							array( '%d' )
						);
					}
					if ( $user_book_id ) {
						$wpdb->update(
							$wpdb->prefix . 'politeia_user_books',
							array( 'updated_at' => current_time( 'mysql' ) ),
							array( 'id' => (int) $user_book_id ),
							array( '%s' ),
							array( '%d' )
						);
					}
				}
			}
		}

		// Redirect back with success flag
		$redirect_url    = wp_get_referer() ?: home_url();
		$display_authors = array_merge( array( $primary_author ), $authors );
		$query_args      = array(
			'prs_added'        => 1,
			'prs_added_title'  => $title,
			'prs_added_author' => implode( ', ', $display_authors ),
		);

		if ( null !== $year ) {
			$query_args['prs_added_year'] = $year;
		}

		if ( null !== $pages ) {
			$query_args['prs_added_pages'] = $pages;
		}

		if ( $attachment_id ) {
			$query_args['prs_added_cover'] = (int) $attachment_id;
		}
		$book_slug = '';
		if ( $book_id ) {
			$book_slug = function_exists( 'prs_get_primary_slug_for_book' ) ? prs_get_primary_slug_for_book( (int) $book_id ) : '';
			if ( '' === $book_slug && function_exists( 'prs_ensure_primary_book_slug' ) ) {
				$book_slug = prs_ensure_primary_book_slug( (int) $book_id, $title, $year );
			}
		}
		if ( ! $book_slug && $slug ) {
			$book_slug = $slug;
		}
		if ( $book_slug ) {
			$query_args['prs_added_slug'] = $book_slug;
		}

		$url = add_query_arg( $query_args, $redirect_url );
		wp_safe_redirect( $url );
		exit;
	}
}
