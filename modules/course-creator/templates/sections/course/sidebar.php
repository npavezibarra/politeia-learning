<?php
/**
 * Course Creator Sidebar (Actions, Price, Checklist)
 */
if (!defined('ABSPATH')) exit;

// Some sections use slots for sidebar actions, others use the default
$sidebar_id_suffix = $sidebar_id_suffix ?? ''; // e.g. '-lessons', '-eval', etc.
?>

<aside class="pcg-course-editor__right <?php echo !empty($sidebar_extra_class) ? esc_attr($sidebar_extra_class) : ''; ?>">
    <div class="pcg-sidecard">
        <div class="pcg-sidecard__section">
            <div id="pcg-course-actions<?php echo $sidebar_id_suffix; ?>" class="pcg-sidecard__actions <?php echo !empty($sidebar_actions_slot) ? 'pcg-sidecard__actions-slot' : ''; ?>">
                <?php if (empty($sidebar_actions_slot)): ?>
                    <button type="button" id="pcg-btn-save-course" class="pcg-btn-save pcg-btn-save-course"
	                        title="<?php _e('Guardar', 'politeia-learning'); ?>">
                        <span class="dashicons dashicons-saved"></span>
                        <span class="pcg-sidecard__btn-text"><?php _e('Guardar cambios', 'politeia-learning'); ?></span>
                    </button>
                    <div class="pcg-sidecard__secondary-row">
                        <button type="button" id="pcg-btn-preview-course" class="pcg-btn-preview pcg-btn-preview-icon"
                            title="<?php _e('Vista Previa', 'politeia-learning'); ?>">
                            <span class="dashicons dashicons-visibility"></span>
                            <span class="pcg-sidecard__btn-text"><?php _e('Vista previa', 'politeia-learning'); ?></span>
                        </button>
                        <button type="button" id="pcg-btn-toggle-publish-course" class="pcg-btn-publish-course" data-status="publish">
                            <?php _e('PUBLISH', 'politeia-learning'); ?>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
            <span class="pcg-sidecard__eyebrow"><?php _e('Precio del curso', 'politeia-learning'); ?></span>
            <div class="pcg-sidecard__price">
                <span class="pcg-sidecard__currency">$</span>
                <input type="text" id="pcg-course-price<?php echo $sidebar_id_suffix; ?>" placeholder="0.00" class="pcg-sidecard__price-input">
            </div>
            <div id="pcg-price-free-indicator<?php echo $sidebar_id_suffix; ?>" class="pcg-price-free-indicator" style="display:none;">
                <?php _e('Gratis', 'politeia-learning'); ?>
            </div>
        </div>
    </div>

    <?php if (empty($sidebar_checklist_slot)): ?>
        <div id="pcg-course-checklist" class="pcg-checklist-card">
            <h5 class="pcg-checklist-title"><?php _e('Checklist: items to check', 'politeia-learning'); ?></h5>
            <ul class="pcg-checklist-list">
                <li class="pcg-checklist-item" data-check="title">
                    <span class="pcg-checklist-dot" aria-hidden="true"></span>
                    <span class="pcg-checklist-label"><?php _e('Title', 'politeia-learning'); ?></span>
                </li>
                <li class="pcg-checklist-item" data-check="price">
                    <span class="pcg-checklist-dot" aria-hidden="true"></span>
                    <span class="pcg-checklist-label"><?php _e('Price', 'politeia-learning'); ?></span>
                </li>
                <li class="pcg-checklist-item" data-check="description">
                    <span class="pcg-checklist-dot" aria-hidden="true"></span>
                    <span class="pcg-checklist-label"><?php _e('Description', 'politeia-learning'); ?></span>
                </li>
                <li class="pcg-checklist-item" data-check="thumbnail">
                    <span class="pcg-checklist-dot" aria-hidden="true"></span>
                    <span class="pcg-checklist-label"><?php _e('Front Image', 'politeia-learning'); ?></span>
                </li>
                <li class="pcg-checklist-item" data-check="cover">
                    <span class="pcg-checklist-dot" aria-hidden="true"></span>
                    <span class="pcg-checklist-label"><?php _e('Top Banner', 'politeia-learning'); ?></span>
                </li>
                <li class="pcg-checklist-item" data-check="excerpt">
                    <span class="pcg-checklist-dot" aria-hidden="true"></span>
                    <span class="pcg-checklist-label"><?php _e('Excerpt', 'politeia-learning'); ?></span>
                </li>
                <li class="pcg-checklist-item" data-check="teachers">
                    <span class="pcg-checklist-dot" aria-hidden="true"></span>
                    <span class="pcg-checklist-label"><?php _e('Instructors', 'politeia-learning'); ?></span>
                </li>
                <li class="pcg-checklist-item" data-check="lessons">
                    <span class="pcg-checklist-dot" aria-hidden="true"></span>
                    <span class="pcg-checklist-label"><?php _e('Lessons', 'politeia-learning'); ?></span>
                    <span class="pcg-checklist-meta" id="pcg-check-lessons-count"></span>
                </li>
                <li class="pcg-checklist-item" data-check="evaluation">
                    <span class="pcg-checklist-dot" aria-hidden="true"></span>
                    <span class="pcg-checklist-label"><?php _e('Evaluación', 'politeia-learning'); ?></span>
                    <span class="pcg-checklist-meta" id="pcg-check-eval-count"></span>
                </li>
            </ul>
        </div>
    <?php else: ?>
        <div class="pcg-checklist-slot"></div>
    <?php endif; ?>
</aside>
