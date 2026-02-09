<?php
class PCG_REST {
    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        register_rest_route( 'pcg/v1', '/programs', [
            'methods' => 'GET',
            'callback' => [ $this, 'get_programs' ],
        ]);
    }

    public function get_programs() {
        $programs = get_posts(['post_type' => 'course_program', 'numberposts' => -1]);
        $data = [];

        foreach ($programs as $program) {
            $groups = get_field('related_groups', $program->ID);
            $data[] = [
                'id' => $program->ID,
                'title' => get_the_title($program),
                'content' => apply_filters('the_content', $program->post_content),
                'price' => get_field('program_price', $program->ID),
                'groups' => $groups,
            ];
        }

        return rest_ensure_response($data);
    }
}
