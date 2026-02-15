<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PL_CPT {
    public function __construct() {
        add_action( 'init', [ $this, 'register_cpt' ] );
    }

    public function register_cpt() {
        if ( post_type_exists( 'course_program' ) ) {
            return;
        }

        $labels = [
            'name'                     => __( 'Programa Politeia', 'politeia-learning' ),
            'singular_name'            => __( 'Programa Politeia', 'politeia-learning' ),
            'menu_name'                => __( 'Programa Politeia', 'politeia-learning' ),
            'add_new'                  => __( 'Agregar nuevo', 'politeia-learning' ),
            'add_new_item'             => __( 'Agregar nuevo Programa Politeia', 'politeia-learning' ),
            'edit_item'                => __( 'Editar Programa Politeia', 'politeia-learning' ),
            'new_item'                 => __( 'Nuevo Programa Politeia', 'politeia-learning' ),
            'view_item'                => __( 'Ver Programa Politeia', 'politeia-learning' ),
            'view_items'               => __( 'Ver Programas Politeia', 'politeia-learning' ),
            'search_items'             => __( 'Buscar Programas Politeia', 'politeia-learning' ),
            'not_found'                => __( 'No se encontraron Programas Politeia', 'politeia-learning' ),
            'not_found_in_trash'       => __( 'No hay Programas Politeia en la papelera', 'politeia-learning' ),
            'all_items'                => __( 'Todos los Programas Politeia', 'politeia-learning' ),
            'archives'                 => __( 'Archivo de Programas Politeia', 'politeia-learning' ),
            'attributes'               => __( 'Atributos del Programa Politeia', 'politeia-learning' ),
            'insert_into_item'         => __( 'Insertar en Programa Politeia', 'politeia-learning' ),
            'uploaded_to_this_item'    => __( 'Subido a este Programa Politeia', 'politeia-learning' ),
            'featured_image'           => __( 'Imagen destacada', 'politeia-learning' ),
            'set_featured_image'       => __( 'Establecer imagen destacada', 'politeia-learning' ),
            'remove_featured_image'    => __( 'Eliminar imagen destacada', 'politeia-learning' ),
            'use_featured_image'       => __( 'Usar como imagen destacada', 'politeia-learning' ),
            'filter_items_list'        => __( 'Filtrar lista de Programas Politeia', 'politeia-learning' ),
            'items_list'               => __( 'Lista de Programas Politeia', 'politeia-learning' ),
            'items_list_navigation'    => __( 'Navegación de lista de Programas Politeia', 'politeia-learning' ),
            'name_admin_bar'           => __( 'Programa Politeia', 'politeia-learning' ),
            'item_published'           => __( 'Programa Politeia publicado.', 'politeia-learning' ),
            'item_updated'             => __( 'Programa Politeia actualizado.', 'politeia-learning' ),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => true,
            'show_in_rest'       => true,
            'show_in_menu'       => false,
            'show_in_admin_bar'  => true,
            'supports'           => [ 'title', 'editor', 'thumbnail' ],
            'has_archive'        => false,
            'rewrite'            => [ 'slug' => 'programa-filosofico' ],
        ];

        register_post_type( 'course_program', $args );
    }
}
