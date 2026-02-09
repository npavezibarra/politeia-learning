<?php
if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class PCG_Programs_Table extends WP_List_Table {
    public function get_columns() {
        return [
            'title'            => __( 'Programa', 'pcg' ),
            'nivel_programa'   => __( 'Nivel', 'pcg' ),
            'precio_programa'  => __( 'Precio', 'pcg' ),
            'related_groups'   => __( 'Grupos Relacionados', 'pcg' ),
        ];
    }

    protected function get_sortable_columns() {
        return [];
    }

    public function get_bulk_actions() {
        return [];
    }

    public function prepare_items() {
        $columns  = $this->get_columns();
        $hidden   = [];
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = [ $columns, $hidden, $sortable ];

        $posts = get_posts(
            [
                'post_type'      => 'course_program',
                'posts_per_page' => -1,
                'post_status'    => 'any',
            ]
        );

        $items = [];

        foreach ( $posts as $post ) {
            $nivel_programa  = function_exists( 'get_field' ) ? get_field( 'nivel_programa', $post->ID ) : '';
            $precio_programa = function_exists( 'get_field' ) ? get_field( 'precio_programa', $post->ID ) : '';
            $related_groups  = function_exists( 'get_field' ) ? get_field( 'related_groups', $post->ID ) : [];

            if ( ! is_array( $related_groups ) ) {
                $related_groups = empty( $related_groups ) ? [] : [ $related_groups ];
            }

            $items[] = [
                'ID'               => $post->ID,
                'title'            => $post->post_title,
                'nivel_programa'   => $nivel_programa,
                'precio_programa'  => $precio_programa,
                'related_groups'   => count( $related_groups ),
            ];
        }

        $this->items = $items;
    }

    protected function column_title( $item ) {
        $edit_link = get_edit_post_link( $item['ID'] );

        if ( ! $edit_link ) {
            return esc_html( $item['title'] );
        }

        return sprintf(
            '<strong><a href="%s">%s</a></strong>',
            esc_url( $edit_link ),
            esc_html( $item['title'] )
        );
    }

    protected function column_default( $item, $column_name ) {
        if ( isset( $item[ $column_name ] ) ) {
            if ( 'related_groups' === $column_name ) {
                return intval( $item[ $column_name ] );
            }

            return esc_html( $item[ $column_name ] );
        }

        return '';
    }
}
