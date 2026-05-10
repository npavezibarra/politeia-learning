<?php
if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

class PRS_Ajax_User_Books {
        public static function init() {
                add_action( 'wp_ajax_politeia_remove_user_book', array( __CLASS__, 'handle_remove_user_book' ) );
                add_action( 'wp_ajax_prs_get_all_books', array( __CLASS__, 'handle_get_all_books' ) );
                add_action( 'wp_ajax_nopriv_prs_get_all_books', array( __CLASS__, 'handle_get_all_books' ) );
                add_action( 'wp_ajax_prs_get_books_page', array( __CLASS__, 'handle_get_books_page' ) );
                add_action( 'wp_ajax_nopriv_prs_get_books_page', array( __CLASS__, 'handle_get_books_page' ) );
        }

        public static function handle_remove_user_book() {
                if ( ! is_user_logged_in() ) {
                        wp_send_json_error( __( 'Invalid request.', 'politeia-reading' ) );
                }

                global $wpdb;

                $user_id      = get_current_user_id();
                $user_book_id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
                $nonce        = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

                if ( ! $user_book_id || ! wp_verify_nonce( $nonce, 'remove_user_book_' . $user_book_id ) ) {
                        wp_send_json_error( __( 'Invalid request.', 'politeia-reading' ) );
                }

                $table_user_books = $wpdb->prefix . 'politeia_user_books';

                $user_book = $wpdb->get_row(
                        $wpdb->prepare(
                                "SELECT id, user_id, book_id FROM {$table_user_books} WHERE id = %d AND deleted_at IS NULL",
                                $user_book_id
                        )
                );

                if ( ! $user_book ) {
                        wp_send_json_error( __( 'Invalid request.', 'politeia-reading' ) );
                }

                if ( (int) $user_book->user_id !== (int) $user_id ) {
                        wp_send_json_error( __( 'You are not allowed to remove this book.', 'politeia-reading' ) );
                }

                $now = current_time( 'mysql' );

                $updated_book = $wpdb->update(
                        $table_user_books,
                        array(
                                'deleted_at' => $now,
                                'updated_at' => $now,
                        ),
                        array( 'id' => $user_book_id )
                );

                if ( false === $updated_book ) {
                        wp_send_json_error( __( 'Error removing book.', 'politeia-reading' ) );
                }

                $table_sessions = $wpdb->prefix . 'politeia_reading_sessions';
                $table_loans    = $wpdb->prefix . 'politeia_loans';

                $wpdb->update(
                        $table_sessions,
                        array( 'deleted_at' => $now ),
                        array(
                                'user_id' => (int) $user_id,
                                'book_id' => (int) $user_book->book_id,
                        )
                );

                $wpdb->update(
                        $table_loans,
                        array(
                                'deleted_at' => $now,
                                'updated_at' => $now,
                        ),
                        array(
                                'user_id' => (int) $user_id,
                                'book_id' => (int) $user_book->book_id,
                        )
                );

                if ( function_exists( 'prs_invalidate_library_cache_for_user' ) ) {
                        prs_invalidate_library_cache_for_user( $user_id );
                }

                wp_send_json_success(
                        array(
                                'message'    => __( 'Book removed from your library.', 'politeia-reading' ),
                                'deleted_at' => $now,
                        )
                );
        }

        public static function handle_get_all_books() {
                self::render_books_response();
        }

        public static function handle_get_books_page() {
                $per_page = (int) apply_filters( 'politeia_my_books_per_page', 15 );
                if ( $per_page < 1 ) {
                        $per_page = 15;
                }

                $page   = isset( $_GET['page'] ) ? max( 1, absint( $_GET['page'] ) ) : 1;
                $offset = ( $page - 1 ) * $per_page;
                $search = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';

                self::render_books_json_response(
                        array(
                                'per_page' => $per_page,
                                'offset'   => $offset,
                                'page'     => $page,
                                'search'   => $search,
                        )
                );
        }

        private static function render_books_response( $args = array() ) {
                if ( ! is_user_logged_in() ) {
                        wp_die( esc_html__( 'You must be logged in to view your library.', 'politeia-reading' ), '', 403 );
                }

                $user_id = get_current_user_id();
                $books   = prs_get_user_books_for_library( $user_id, $args );
                $labels  = prs_get_owning_labels();

                foreach ( (array) $books as $book ) {
                        echo prs_render_book_row( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                $book,
                                array(
                                        'user_id'       => $user_id,
                                        'owning_labels' => $labels,
                                )
                        );
                }

                wp_die();
        }

        private static function render_books_json_response( $args = array() ) {
                if ( ! is_user_logged_in() ) {
                        wp_send_json_error( array( 'message' => __( 'You must be logged in to view your library.', 'politeia-reading' ) ), 403 );
                }

                $user_id = get_current_user_id();
                $per_page = isset( $args['per_page'] ) ? (int) $args['per_page'] : (int) apply_filters( 'politeia_my_books_per_page', 15 );
                if ( $per_page < 1 ) {
                        $per_page = 15;
                }

                $page   = isset( $args['page'] ) ? max( 1, absint( $args['page'] ) ) : 1;
                $search = isset( $args['search'] ) ? trim( (string) $args['search'] ) : '';
                $order  = 'title_asc';
                $cache_key = function_exists( 'prs_get_library_results_cache_key' )
                        ? prs_get_library_results_cache_key( $user_id, $page, $per_page, $search, $order )
                        : '';

                if ( '' !== $cache_key ) {
                        $cached_response = get_transient( $cache_key );
                        if ( is_array( $cached_response ) ) {
                                wp_send_json_success( $cached_response );
                        }
                }

                $overall_total = prs_get_user_books_for_library_count(
                        $user_id,
                        array(
                        )
                );
                $match_total = $overall_total;
                if ( '' !== $search ) {
                        $match_total = prs_get_user_books_for_library_count(
                                $user_id,
                                array(
                                        'search' => $search,
                                )
                        );
                }

                $max_pages = max( 1, (int) ceil( $match_total / $per_page ) );
                if ( $page > $max_pages ) {
                        $page = $max_pages;
                }
                $offset = ( $page - 1 ) * $per_page;

                $books = prs_get_user_books_for_library(
                        $user_id,
                        array(
                                'per_page' => $per_page,
                                'offset'   => $offset,
                                'search'   => $search,
                                'order'    => $order,
                        )
                );
                $labels = prs_get_owning_labels();

                ob_start();
                foreach ( (array) $books as $book ) {
                        echo prs_render_book_row( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                $book,
                                array(
                                        'user_id'       => $user_id,
                                        'owning_labels' => $labels,
                                )
                        );
                }
                $rows_html = ob_get_clean();

                $pagination_html = '';
                if ( $max_pages > 1 ) {
                        $pagination_html = self::render_pagination_html( $page, $max_pages, $search );
                }

                $response = array(
                        'rows_html'        => $rows_html,
                        'pagination_html'  => $pagination_html,
                        'total'            => $overall_total,
                        'match_total'      => $match_total,
                        'page'             => $page,
                        'per_page'         => $per_page,
                        'search'           => $search,
                );

                if ( '' !== $cache_key ) {
                        set_transient( $cache_key, $response, 10 * MINUTE_IN_SECONDS );
                }

                wp_send_json_success( $response );
        }

        private static function render_pagination_html( $current_page, $max_pages, $search = '' ) {
                $base_url = add_query_arg(
                        array_filter(
                                array(
                                        'action' => 'prs_get_books_page',
                                        'q'      => '' !== $search ? $search : null,
                                ),
                                static function ( $value ) {
                                        return null !== $value && '' !== $value;
                                }
                        ),
                        admin_url( 'admin-ajax.php' )
                );

                $links = paginate_links(
                        array(
                                'base'      => add_query_arg( 'page', '%#%', $base_url ),
                                'format'    => '',
                                'current'   => $current_page,
                                'total'     => $max_pages,
                                'mid_size'  => 2,
                                'end_size'  => 1,
                                'prev_text' => '',
                                'next_text' => '',
                                'type'      => 'array',
                        )
                );

                if ( empty( $links ) ) {
                        return '';
                }

                ob_start();
                ?>
                <nav class="prs-pagination-sheet" aria-label="<?php esc_attr_e( 'Library pagination', 'politeia-reading' ); ?>">
                        <div class="prs-pagination-sheet__inner">
                                <div class="prs-pagination-sheet__numbers">
                                        <?php
                                        foreach ( (array) $links as $link ) {
                                                $label = trim( wp_strip_all_tags( $link ) );
                                                if ( ! is_numeric( $label ) ) {
                                                        continue;
                                                }

                                                $is_current = strpos( $link, 'current' ) !== false;
                                                if ( $is_current ) {
                                                        printf(
                                                                '<span class="prs-pagination-sheet__page is-current">%1$s</span>',
                                                                esc_html( $label )
                                                        );
                                                        continue;
                                                }

                                                if ( preg_match( '/href="([^"]+)"/', $link, $matches ) ) {
                                                        printf(
                                                                '<a class="prs-pagination-sheet__page" href="%1$s" data-page="%2$s">%3$s</a>',
                                                                esc_url( $matches[1] ),
                                                                esc_attr( $label ),
                                                                esc_html( $label )
                                                        );
                                                }
                                        }
                                        ?>
                                </div>
                        </div>
                </nav>
                <?php
                return ob_get_clean();
        }
}

PRS_Ajax_User_Books::init();
