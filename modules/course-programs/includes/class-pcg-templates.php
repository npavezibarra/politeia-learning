<?php
class PCG_Templates
{
    public function __construct()
    {
        add_filter('template_include', [$this, 'load_custom_templates']);
    }

    public function load_custom_templates($template)
    {
        if (is_singular('course_program')) {
            $custom = PCG_CP_PATH . 'templates/single-course_program.php';
            if (file_exists($custom))
                return $custom;
        }
        if (is_post_type_archive('course_program')) {
            $archive = PCG_CP_PATH . 'templates/archive-course_program.php';
            if (file_exists($archive))
                return $archive;
        }
        return $template;
    }
}
