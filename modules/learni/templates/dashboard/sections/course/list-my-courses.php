<?php
/**
 * Course Creator - Initial Dashboard List
 */
if (!defined('ABSPATH')) exit;

$pcg_my_courses_title = $pcg_my_courses_title ?? __('MIS CURSOS PUBLICADOS', 'politeia-learning');
$pcg_create_course_button_label = $pcg_create_course_button_label ?? __('Crear un curso', 'politeia-learning');
$pcg_list_grid_id = $pcg_list_grid_id ?? 'pcg-my-courses-grid';
?>

<div id="pcg-my-courses-section" class="pcg-my-courses-container" <?php echo $pcg_is_editing_quiz ? 'style="display:none;"' : ''; ?>>
    <div class="pcg-section-header">
        <h3><?php echo esc_html($pcg_my_courses_title); ?></h3>
        <button type="button" id="pcg-show-creator-form" class="pcg-btn-intro-create">
            <?php echo esc_html($pcg_create_course_button_label); ?>
        </button>
    </div>

    <div id="<?php echo esc_attr($pcg_list_grid_id); ?>" class="pcg-my-courses-grid">
        <!-- Will be populated via AJAX -->
        <div class="pcg-loading-placeholder">
            <span class="dashicons dashicons-update spin"></span>
            <p><?php _e('Cargando tus cursos...', 'politeia-learning'); ?></p>
        </div>
    </div>
</div>
