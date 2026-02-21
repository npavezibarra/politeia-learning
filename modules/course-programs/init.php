<?php
/**
 * Module: Course Programs
 * Description: Manages the high-level "Philosophical Programs" that group LearnDash course groups.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Define module constants
define( 'PL_CP_PATH', plugin_dir_path( __FILE__ ) );
define( 'PL_CP_URL', plugin_dir_url( __FILE__ ) );

/**
 * Autoload classes for this module
 */
spl_autoload_register( function ( $class ) {
    if ( strpos( $class, 'PL_' ) === 0 ) {
        $class_slug = preg_replace( '/^PL_/', '', $class );
        $class_slug = strtolower( str_replace( '_', '-', $class_slug ) );
        $file       = PL_CP_PATH . 'includes/class-pcg-' . $class_slug . '.php';
        if ( file_exists( $file ) ) {
            require_once $file;
        }
    }
});

/**
 * Initialize Module
 */
add_action( 'plugins_loaded', function() {
    // Register CPT
    if ( class_exists( 'PL_CPT' ) ) {
        new PL_CPT();
    }

    // Register ACF fields (if ACF active)
    if ( class_exists( 'ACF' ) && class_exists( 'PL_ACF' ) ) {
        new PL_ACF();
    }

    // Relations and templates
    if ( class_exists( 'PL_Relations' ) ) {
        new PL_Relations();
    }
    
    if ( class_exists( 'PL_Templates' ) ) {
        new PL_Templates();
    }

    // REST endpoints
    if ( class_exists( 'PL_REST' ) ) {
        new PL_REST();
    }

    // Metaboxes
    if ( class_exists( 'PL_Metaboxes' ) ) {
        new PL_Metaboxes();
    }
}, 20 );

add_action( 'plugins_loaded', function() {
    $admin_file = PL_CP_PATH . 'includes/class-pcg-admin.php';
    if ( file_exists( $admin_file ) ) {
        require_once $admin_file;
        if ( class_exists( 'PL_Admin_Menu' ) ) {
            new PL_Admin_Menu();
        }
    }
}, 25 );

/**
 * Enqueue assets for this module
 */
add_action( 'wp_enqueue_scripts', function() {
    wp_enqueue_style( 'pcg-style', PL_CP_URL . 'assets/css/pcg-style.css', [], '1.0' );
    wp_enqueue_script( 'pcg-script', PL_CP_URL . 'assets/js/pcg-script.js', ['jquery'], '1.0', true );
});

/**
 * Template loading filters
 * We need to update PL_Templates to look into the module's template folder
 */
add_filter( 'pcg_course_programs_template_path', function() {
    return PL_CP_PATH . 'templates/';
});
