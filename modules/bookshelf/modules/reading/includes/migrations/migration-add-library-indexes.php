<?php
namespace Politeia\Reading\Migrations;

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

AddLibraryIndexes::run();

class AddLibraryIndexes {
        public static function run(): void {
                global $wpdb;

                $books_table   = $wpdb->prefix . 'politeia_books';
                $user_books    = $wpdb->prefix . 'politeia_user_books';
                $sessions_table = $wpdb->prefix . 'politeia_reading_sessions';

                self::maybe_add_index( $books_table, 'idx_title_id', array( 'title', 'id' ) );
                self::maybe_drop_index( $books_table, 'idx_title' );

                self::maybe_add_index( $user_books, 'idx_user_deleted_book', array( 'user_id', 'deleted_at', 'book_id' ) );
                self::maybe_drop_index( $user_books, 'idx_user' );
                self::maybe_drop_index( $user_books, 'idx_book' );

                self::maybe_add_index( $sessions_table, 'idx_user_book_deleted_end', array( 'user_book_id', 'deleted_at', 'end_time' ) );
                self::maybe_drop_index( $sessions_table, 'user_book_id' );

                if ( defined( 'POLITEIA_READING_DB_VERSION' ) ) {
                        update_option( 'politeia_reading_db_version', POLITEIA_READING_DB_VERSION );
                }
        }

        private static function index_exists( string $table, string $index_name ): bool {
                global $wpdb;

                return (bool) $wpdb->get_var(
                        $wpdb->prepare(
                                'SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND INDEX_NAME = %s LIMIT 1',
                                $table,
                                $index_name
                        )
                );
        }

        private static function maybe_add_index( string $table, string $name, array $columns ): void {
                if ( self::index_exists( $table, $name ) ) {
                        return;
                }

                global $wpdb;

                $columns_sql = implode( ',', array_map( static fn( $column ) => "`{$column}`", $columns ) );
                $wpdb->query( "ALTER TABLE {$table} ADD INDEX `{$name}` ({$columns_sql})" );
        }

        private static function maybe_drop_index( string $table, string $name ): void {
                if ( 'PRIMARY' === $name || ! self::index_exists( $table, $name ) ) {
                        return;
                }

                global $wpdb;

                $wpdb->query( "ALTER TABLE {$table} DROP INDEX `{$name}`" );
        }
}
