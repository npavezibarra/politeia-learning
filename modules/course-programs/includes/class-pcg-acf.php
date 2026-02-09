<?php
class PCG_ACF {
    public function __construct() {
        add_action('acf/init', [$this, 'register_fields']);
    }

    public function register_fields() {
        acf_add_local_field_group([
            'key' => 'group_pcg_programa_politeia_details',
            'title' => 'Detalles del Programa Politeia',
            'fields' => [
                [
                    'key' => 'field_precio_programa',
                    'label' => 'Precio',
                    'name' => 'precio_programa',
                    'type' => 'number',
                    'show_in_rest' => 1,
                ],
                [
                    'key' => 'field_related_groups',
                    'label' => 'Grupos de Cursos',
                    'name' => 'related_groups',
                    'type' => 'relationship',
                    'post_type' => [ 'groups' ],
                    'filters' => [ 'search' ],
                    'return_format' => 'id',
                    'show_in_rest' => 1,
                ],
            ],
            'location' => [[
                ['param' => 'post_type', 'operator' => '==', 'value' => 'course_program'],
            ]],
            'position' => 'side',
            'show_in_rest' => 1,
        ]);
    }
}
