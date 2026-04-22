<?php
/**
 * Course Creator Navigation Header
 */
if (!defined('ABSPATH')) exit;
?>

<!-- Back Button and Current Title -->
<div class="pcg-form-nav">
    <div class="pcg-nav-left">
        <button type="button" id="pcg-btn-back-to-list" class="pcg-btn-back">
            <span class="dashicons dashicons-arrow-left-alt2"></span>
            <?php _e('Volver', 'politeia-learning'); ?>
        </button>
        <span id="pcg-current-course-label" class="pcg-current-course-label"></span>
    </div>
    <div class="pcg-nav-right">
        <div class="pcg-segmented-control">
            <div class="pcg-segment <?php echo $pcg_active_segment === 'curso' ? 'active' : ''; ?>"
                data-value="curso">
                <?php _e('CURSO', 'politeia-learning'); ?>
            </div>
            <div class="pcg-segment <?php echo $pcg_active_segment === 'lecciones' ? 'active' : ''; ?>"
                data-value="lecciones"><?php _e('LECCIONES', 'politeia-learning'); ?></div>
            <div class="pcg-segment <?php echo $pcg_active_segment === 'evaluacion' ? 'active' : ''; ?>"
                data-value="evaluacion"><?php _e('EVALUACIÓN', 'politeia-learning'); ?></div>
            <div class="pcg-segment <?php echo $pcg_active_segment === 'certificado' ? 'active' : ''; ?>"
                data-value="certificado"><?php _e('CERTIFICADO', 'politeia-learning'); ?></div>
            <div class="pcg-segment <?php echo $pcg_active_segment === 'meta' ? 'active' : ''; ?>"
                data-value="meta"><?php _e('META', 'politeia-learning'); ?></div>
        </div>
    </div>
</div>
