<?php
/**
 * Plugin Name: Politeia Reading
 * Description: Manage "My Library" and Reading Sessions with custom tables and shortcodes.
 * Version: 0.2.7
 * Author: Politeia
 * Text Domain: politeia-reading
 */

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

// ===== Constants =====
if (!defined('POLITEIA_READING_VERSION')) {
	// ⬆️ Incrementa esta versión cuando cambies estructuras/flujo global del plugin
	define('POLITEIA_READING_VERSION', '0.2.7');
}
if (!defined('POLITEIA_READING_DB_VERSION')) {
	define('POLITEIA_READING_DB_VERSION', '1.14.2');
}
if (!defined('POLITEIA_READING_PATH')) {
	define('POLITEIA_READING_PATH', plugin_dir_path(__FILE__));
}
if (!defined('POLITEIA_READING_URL')) {
	define('POLITEIA_READING_URL', plugin_dir_url(__FILE__));
}

// ===== i18n =====
add_action(
	'plugins_loaded',
	function () {
		load_plugin_textdomain('politeia-reading', false, dirname(plugin_basename(__FILE__)) . '/languages');
	}
);

// ===== Includes núcleo =====
require_once POLITEIA_READING_PATH . 'includes/class-installer.php';
require_once POLITEIA_READING_PATH . 'includes/migrations/class-migrations.php';
require_once POLITEIA_READING_PATH . 'includes/class-upgrader.php';
require_once POLITEIA_READING_PATH . 'includes/class-activator.php';
require_once POLITEIA_READING_PATH . 'includes/class-rest.php';
require_once POLITEIA_READING_PATH . 'includes/class-books.php';
require_once POLITEIA_READING_PATH . 'includes/class-user-books-utils.php';
require_once POLITEIA_READING_PATH . 'includes/class-prs-ajax-add-book.php';
require_once POLITEIA_READING_PATH . 'includes/class-prs-add-book-handler.php';
require_once POLITEIA_READING_PATH . 'includes/class-prs-shortcode-add-book.php';
require_once POLITEIA_READING_PATH . 'includes/class-prs-shortcode-my-books.php';
require_once POLITEIA_READING_PATH . 'includes/class-prs-shortcode-start-reading.php';
require_once POLITEIA_READING_PATH . 'includes/class-prs-book-utils.php';
require_once POLITEIA_READING_PATH . 'includes/class-prs-author-manager.php';
require_once POLITEIA_READING_PATH . 'includes/class-prs-book-repository.php';
require_once POLITEIA_READING_PATH . 'includes/class-prs-ui-renderer.php';
require_once POLITEIA_READING_PATH . 'includes/class-prs-db-migrations.php';
require_once POLITEIA_READING_PATH . 'includes/class-prs-book-single-controller.php';
require_once POLITEIA_READING_PATH . 'includes/add-book/class-prs-add-book-assets.php';
require_once POLITEIA_READING_PATH . 'includes/class-prs-my-book-assets.php';
require_once POLITEIA_READING_PATH . 'includes/class-prs-start-reading-assets.php';

Politeia_Reading_Ajax_Add_Book::init();
Politeia_Reading_Add_Book_Handler::init();
Politeia_Reading_Shortcode_Add_Book::init();
Politeia_Reading_Shortcode_My_Books::init();
Politeia_Reading_Shortcode_Start_Reading::init();
Politeia_Reading_DB_Migrations::init();
require_once POLITEIA_READING_PATH . 'includes/class-user-books-loans.php';
require_once POLITEIA_READING_PATH . 'includes/class-user-books-covers.php';
require_once POLITEIA_READING_PATH . 'includes/class-user-books-meta.php';
require_once POLITEIA_READING_PATH . 'includes/class-user-books.php';
require_once POLITEIA_READING_PATH . 'includes/class-reading-sessions-utils.php';
require_once POLITEIA_READING_PATH . 'includes/class-reading-sessions-stats.php';
require_once POLITEIA_READING_PATH . 'includes/class-reading-sessions-recorder.php';
require_once POLITEIA_READING_PATH . 'includes/class-reading-sessions-render.php';
require_once POLITEIA_READING_PATH . 'includes/class-reading-sessions-handler.php';
require_once POLITEIA_READING_PATH . 'includes/class-reading-sessions.php';
require_once POLITEIA_READING_PATH . 'includes/class-ajax-handler.php';
require_once POLITEIA_READING_PATH . 'includes/class-politeia-loan-manager.php';
require_once POLITEIA_READING_PATH . 'includes/class-prs-ajax-user-books.php';
require_once POLITEIA_READING_PATH . 'includes/helpers.php';
require_once POLITEIA_READING_PATH . 'templates/features/cover-upload/cover-upload.php';
PRS_Cover_Upload_Feature::init();
require_once POLITEIA_READING_PATH . 'includes/class-routes.php';

// ===== Módulos (carga modular) =====
// Módulo: Post Reading (botón Start/Finish para posts regulares + tabla wp_politeia_post_reading)
require_once POLITEIA_READING_PATH . 'modules/post-reading/init.php';

// ===== Activation Hooks =====
register_activation_hook(__FILE__, array('\\Politeia\\Reading\\Activator', 'activate'));

// Asegura la migración del módulo post-reading al activar el plugin
register_activation_hook(
	__FILE__,
	function () {
		if (class_exists('Politeia_Post_Reading_Schema')) {
			Politeia_Post_Reading_Schema::migrate();
		}
	}
);

// ===== Upgrade / Migrations on load =====
// Ejecuta migraciones idempotentes cuando cambies POLITEIA_READING_VERSION (core)
add_action('plugins_loaded', array('\\Politeia\\Reading\\Upgrader', 'maybe_upgrade'));
// Nota: el módulo post-reading ya registra su propio maybe_upgrade en su init.php.
// No lo repetimos aquí para evitar dobles llamadas.

// ===== Flush rewrites (una sola vez post-activación) =====
// Las reglas de reescritura se vacían directamente en el Activator.

// ===== Asset Registration / Enqueue (core existente) =====
add_action(
	'wp_enqueue_scripts',
	function () {

		// Estilos base del plugin
		wp_register_style(
			'politeia-reading',
			POLITEIA_READING_URL . 'assets/css/politeia.css',
			array(),
			POLITEIA_READING_VERSION
		);

		wp_register_style(
			'politeia-my-book',
			POLITEIA_READING_URL . 'assets/css/my-book.css',
			array('politeia-reading'),
			POLITEIA_READING_VERSION
		);

		wp_register_style(
			'prs-cover-modal',
			POLITEIA_READING_URL . 'assets/css/prs-cover-modal.css',
			array(),
			POLITEIA_READING_VERSION
		);

		// Scripts varios del plugin
		// Modular Add Book Scripts
		if ( class_exists( 'Politeia_Reading_Add_Book_Assets' ) ) {
			Politeia_Reading_Add_Book_Assets::register();
		}

		// Modular Start Reading Scripts
		if ( class_exists( 'Politeia_Reading_Start_Reading_Assets' ) ) {
			Politeia_Reading_Start_Reading_Assets::register();
		}

		// Script de la página “Mi Libro” (Modular)
		if ( class_exists( 'Politeia_Reading_My_Book_Assets' ) ) {
			Politeia_Reading_My_Book_Assets::register();
		}

		wp_register_script(
			'prs-cover-modal',
			POLITEIA_READING_URL . 'assets/js/prs-cover-modal.js',
			array(),
			POLITEIA_READING_VERSION,
			true
		);

		// Carga condicional en la vista de un libro individual (manteniendo tu lógica)
		if (get_query_var('prs_book_slug')) {
			wp_enqueue_style('politeia-reading');
			wp_enqueue_style('politeia-my-book');
			wp_enqueue_style('prs-cover-modal');
			wp_enqueue_script('politeia-my-book');
			wp_enqueue_script('prs-cover-modal');
		}

		// Importante: los assets del módulo Post Reading (post-reading.css/js)
		// los encola automáticamente Politeia_Post_Reading_Render solo en single posts.
	}
);

// ===== Admin notices =====
add_action(
	'admin_notices',
	static function () {
		if (!current_user_can('manage_options')) {
			return;
		}

		global $wpdb;
		$schema = defined('DB_NAME') ? DB_NAME : $wpdb->dbname;
		$tables = array(
			$wpdb->prefix . 'politeia_books',
			$wpdb->prefix . 'politeia_user_books',
			$wpdb->prefix . 'politeia_authors',
			$wpdb->prefix . 'politeia_book_authors',
		);

		$missing = array();
		foreach ($tables as $table) {
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s',
					$schema,
					$table
				)
			);

			if (!$exists) {
				$missing[] = $table;
			}
		}

		if (empty($missing)) {
			return;
		}

		$missing_list = implode(', ', array_map('esc_html', $missing));
		$message = sprintf(
			__('Politeia Reading is missing the following database tables: %s. Reactivate the plugin to recreate them.', 'politeia-reading'),
			$missing_list
		);

		echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
	}
);

// ===== Shortcodes =====
require_once POLITEIA_READING_PATH . 'shortcodes/add-book.php';
require_once POLITEIA_READING_PATH . 'shortcodes/start-reading.php';
require_once POLITEIA_READING_PATH . 'shortcodes/my-books.php';
