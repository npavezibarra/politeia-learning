<?php
/**
 * Course Creator - Meta/Taxonomy Mode
 */
if (!defined('ABSPATH')) exit;
?>

<div id="pcg-mode-meta" class="pcg-mode-content" <?php echo $pcg_active_segment !== 'meta' ? 'style="display:none;"' : ''; ?>>
    <div class="pcg-meta-editor__grid">
        <div class="pcg-meta-editor__left">
            <div class="pcg-meta-card">
                <div class="pcg-meta-section">
                    <label><?php _e('CATEGORÍAS', 'politeia-learning'); ?></label>
                    <div class="pcg-meta-cat-picker" data-entity="course">
                        <div class="pcg-meta-cat-level pcg-meta-cat-level--l1" id="pcg-course-meta-cat-l1" aria-live="polite"></div>
                        <div class="pcg-meta-cat-level pcg-meta-cat-level--l2" id="pcg-course-meta-cat-l2" aria-live="polite"></div>
                        <div class="pcg-meta-cat-level pcg-meta-cat-level--l3" id="pcg-course-meta-cat-l3" aria-live="polite"></div>
                    </div>
                </div>

                <div class="pcg-meta-section">
                    <label for="pcg-course-meta-tag-input"><?php _e('ETIQUETAS', 'politeia-learning'); ?></label>
                    <div class="pcg-meta-tags">
                        <div id="pcg-course-meta-tag-chips" class="pcg-meta-chips" aria-live="polite"></div>
                        <input type="text" id="pcg-course-meta-tag-input" class="pcg-modern-input pcg-meta-tag-input"
                            placeholder="<?php esc_attr_e('Escribe para buscar o crear...', 'politeia-learning'); ?>" autocomplete="off" />
                        <div id="pcg-course-meta-tag-suggestions" class="pcg-meta-suggestions"></div>
                    </div>
                </div>
            </div>
        </div>

        <?php 
        $sidebar_id_suffix = '-meta';
        $sidebar_actions_slot = true;
        $sidebar_checklist_slot = true;
        include __DIR__ . '/sidebar.php'; 
        ?>
    </div>
</div>
