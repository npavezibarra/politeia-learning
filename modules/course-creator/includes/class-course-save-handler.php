<?php
/**
 * Course Save Handler for Course Creator
 * 
 * Refactored to use Traits for modularity following the 500-line rule.
 * Now 100% Learni-native.
 */

if (!defined('ABSPATH'))
    exit;

// Load Traits
require_once __DIR__ . '/traits/trait-save-utils.php';
require_once __DIR__ . '/traits/trait-save-taxonomy-roles.php';
require_once __DIR__ . '/traits/trait-save-media-profile.php';
require_once __DIR__ . '/traits/trait-save-woo-sync.php';
require_once __DIR__ . '/traits/trait-save-course.php';
require_once __DIR__ . '/traits/trait-save-specialization.php';
require_once __DIR__ . '/traits/trait-save-program.php';
require_once __DIR__ . '/traits/trait-save-escritos.php';
require_once __DIR__ . '/traits/trait-save-profile.php';

class PL_CC_Course_Save_Handler
{
    use PL_CC_Utils_Trait;
    use PL_CC_Taxonomy_Roles_Trait;
    use PL_CC_Media_Profile_Trait;
    use PL_CC_WooCommerce_Trait;
    use PL_CC_Course_Trait;
    use PL_CC_Specialization_Trait;
    use PL_CC_Program_Trait;
    use PL_CC_Escritos_Trait;
    use PL_CC_Profile_Save_Trait;

    const REQUIRED_PRODUCT_CATEGORY_NAME = 'Cursos';
    const REQUIRED_PRODUCT_CATEGORY_SLUG = 'cursos';

    public function __construct()
    {
        // Course Handlers
        add_action('wp_ajax_pcg_save_course', [$this, 'handle_save_course']);
        add_action('wp_ajax_pcg_get_my_courses', [$this, 'handle_get_my_courses']);
        add_action('wp_ajax_pcg_get_published_courses', [$this, 'handle_get_published_courses']);
        add_action('wp_ajax_pcg_get_course_for_edit', [$this, 'handle_get_course_for_edit']);
        add_action('wp_ajax_pcg_delete_course', [$this, 'handle_delete_course']);

        // Specialization (Group) Handlers
        add_action('wp_ajax_pcg_save_specialization', [$this, 'handle_save_specialization']);
        add_action('wp_ajax_pcg_get_my_specializations', [$this, 'handle_get_my_specializations']);
        add_action('wp_ajax_pcg_get_published_specializations', [$this, 'handle_get_published_specializations']);
        add_action('wp_ajax_pcg_get_specialization_for_edit', [$this, 'handle_get_specialization_for_edit']);
        add_action('wp_ajax_pcg_delete_specialization', [$this, 'handle_delete_specialization']);

        // Program Handlers
        add_action('wp_ajax_pcg_save_programa', [$this, 'handle_save_programa']);
        add_action('wp_ajax_pcg_get_my_programas', [$this, 'handle_get_my_programas']);
        add_action('wp_ajax_pcg_get_programa_for_edit', [$this, 'handle_get_programa_for_edit']);
        add_action('wp_ajax_pcg_delete_programa', [$this, 'handle_delete_programa']);

        // Escritos (Articles) Handlers
        add_action('wp_ajax_pcg_save_escrito', [$this, 'handle_save_escrito']);
        add_action('wp_ajax_pcg_get_my_escritos', [$this, 'handle_get_my_escritos']);
        add_action('wp_ajax_pcg_get_escrito_for_edit', [$this, 'handle_get_escrito_for_edit']);
        add_action('wp_ajax_pcg_delete_escrito', [$this, 'handle_delete_escrito']);

        // Media & Profile
        add_action('wp_ajax_pcg_upload_cropped_image', [$this, 'handle_upload_cropped_image']);
        add_action('wp_ajax_pcg_save_profile_avatar', [$this, 'handle_save_profile_avatar']);
        add_action('wp_ajax_pcg_save_profile', [$this, 'handle_save_profile']);

        // Taxonomy & Meta
        add_action('wp_ajax_pcg_get_learning_meta_terms', [$this, 'handle_get_learning_meta_terms']);
        add_action('wp_ajax_pcg_create_learning_tag', [$this, 'handle_create_learning_tag']);

        // Hook for inclusion snapshot approvals (Internal Learni mechanics)
        add_action('pcg_inclusion_snapshot_approved', [$this, 'handle_inclusion_snapshot_approved'], 10, 3);
    }
}
