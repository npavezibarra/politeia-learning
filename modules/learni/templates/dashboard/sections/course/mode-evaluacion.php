<?php
/**
 * Course Creator - Evaluation/Quiz Mode
 */
if (!defined('ABSPATH')) exit;
?>

<div id="pcg-mode-evaluacion" class="pcg-mode-content" <?php echo $pcg_active_segment !== 'evaluacion' ? 'style="display:none;"' : ''; ?>>
    <div class="pcg-eval-editor__grid">
        <div class="pcg-eval-editor__left">
            <div id="pcg-quiz-not-created-msg" class="pcg-empty-state-msg">
                <p><?php _e('Antes de crear una evaluación, primero debes crear un curso.', 'politeia-learning'); ?></p>
            </div>
            <div id="pcg-quiz-creator-container">
                <?php if ($pcg_is_editing_quiz): ?>
                    <?php echo do_shortcode('[politeia_quiz_creator]'); ?>
                <?php else: ?>
                    <div class="pqc-container pqc-loading-state">
                        <p class="pqc-loading-state__text"><?php _e('Cargando evaluación…', 'politeia-learning'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php 
        $sidebar_id_suffix = '-eval';
        $sidebar_actions_slot = true;
        $sidebar_checklist_slot = true;
        include __DIR__ . '/sidebar.php'; 
        ?>
    </div>
</div>
