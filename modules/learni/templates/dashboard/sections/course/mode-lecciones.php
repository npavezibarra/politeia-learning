<?php
/**
 * Course Creator - Lessons/Curriculum Mode
 */
if (!defined('ABSPATH')) exit;
?>

<div id="pcg-mode-lecciones" class="pcg-mode-content" <?php echo $pcg_active_segment !== 'lecciones' ? 'style="display:none;"' : ''; ?>>
    <div class="pcg-lessons-editor__grid">
        <div class="pcg-lessons-editor__left">
            <div class="pcg-lessons-header">
                <h3><?php _e('Lecciones del curso', 'politeia-learning'); ?></h3>
                <div class="pcg-progression-container">
                    <span class="pcg-progression-label"><?php _e('FLUJO LIBRE', 'politeia-learning'); ?></span>
                    <label class="pcg-switch">
                        <input type="checkbox" id="pcg-course-progression">
                        <span class="pcg-slider round"></span>
                    </label>
                </div>
                <div class="pcg-add-actions">
                    <?php $pcg_add_button_text = __( 'Añadir', 'politeia-learning' ); ?>
                    <button type="button" class="pcg-btn-add-circle" id="pcg-btn-add-content"
                        aria-label="<?php echo esc_attr($pcg_add_button_text); ?>">
                        <?php echo esc_html($pcg_add_button_text); ?>
                    </button>
                    <div class="pcg-add-dropdown" id="pcg-add-dropdown">
                        <button type="button" class="pcg-add-option" data-type="lesson">
                            <span class="dashicons dashicons-media-text"></span>
                            <?php _e('Agregar lección', 'politeia-learning'); ?>
                        </button>
                        <button type="button" class="pcg-add-option" data-type="section">
                            <span class="dashicons dashicons-menu-alt3"></span>
                            <?php _e('Agregar sección', 'politeia-learning'); ?>
                        </button>
                    </div>
                </div>
            </div>

            <div id="pcg-lessons-list" class="pcg-lessons-list">
                <!-- Dynamic lessons/sections will appear here -->
                <div class="pcg-empty-lessons-state">
                    <p><?php _e('No hay contenido aún. Haz clic en el botón + para añadir una lección o sección.', 'politeia-learning'); ?>
                    </p>
                </div>
            </div>
        </div>

        <?php 
        $sidebar_id_suffix = '-lessons';
        $sidebar_actions_slot = true;
        $sidebar_checklist_slot = true;
        include __DIR__ . '/sidebar.php'; 
        ?>
    </div>
</div>
