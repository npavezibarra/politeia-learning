<?php
/**
 * Module: Course Programs
 * Description: Manages the high-level "Philosophical Programs" that group LearnDash course groups.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Define module constants
define( 'PCG_CP_PATH', plugin_dir_path( __FILE__ ) );
define( 'PCG_CP_URL', plugin_dir_url( __FILE__ ) );

/**
 * Autoload classes for this module
 */
spl_autoload_register( function ( $class ) {
    if ( strpos( $class, 'PCG_' ) === 0 ) {
        $file = PCG_CP_PATH . 'includes/class-' . strtolower( str_replace( '_', '-', $class ) ) . '.php';
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
    if ( class_exists( 'PCG_CPT' ) ) {
        new PCG_CPT();
    }

    // Register ACF fields (if ACF active)
    if ( class_exists( 'ACF' ) && class_exists( 'PCG_ACF' ) ) {
        new PCG_ACF();
    }

    // Relations and templates
    if ( class_exists( 'PCG_Relations' ) ) {
        new PCG_Relations();
    }
    
    if ( class_exists( 'PCG_Templates' ) ) {
        new PCG_Templates();
    }

    // REST endpoints
    if ( class_exists( 'PCG_REST' ) ) {
        new PCG_REST();
    }

    // Metaboxes
    if ( class_exists( 'PCG_Metaboxes' ) ) {
        new PCG_Metaboxes();
    }
}, 20 );

add_action( 'plugins_loaded', function() {
    $admin_file = PCG_CP_PATH . 'includes/class-pcg-admin.php';
    if ( file_exists( $admin_file ) ) {
        require_once $admin_file;
        if ( class_exists( 'PCG_Admin_Menu' ) ) {
            new PCG_Admin_Menu();
        }
    }
}, 25 );

/**
 * Enqueue assets for this module
 */
add_action( 'wp_enqueue_scripts', function() {
    wp_enqueue_style( 'pcg-style', PCG_CP_URL . 'assets/css/pcg-style.css', [], '1.0' );
    wp_enqueue_script( 'pcg-script', PCG_CP_URL . 'assets/js/pcg-script.js', ['jquery'], '1.0', true );
});

/**
 * Template loading filters
 * We need to update PCG_Templates to look into the module's template folder
 */
add_filter( 'pcg_course_programs_template_path', function() {
    return PCG_CP_PATH . 'templates/';
});
