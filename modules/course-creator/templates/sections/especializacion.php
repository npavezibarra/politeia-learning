<?php
/**
 * Especialización: create/manage LearnDash Groups for the current user.
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<!-- SPECIALIZATION FORM (Hidden Initially) -->
<div id="pcg-specialization-form-section" class="pcg-create-course-container" style="display:none;">
    <input type="hidden" id="pcg-current-group-id" value="0">

    <div class="pcg-form-nav">
        <div class="pcg-nav-left">
            <button type="button" id="pcg-btn-back-to-specializations" class="pcg-btn-back">
                <span class="dashicons dashicons-arrow-left-alt2"></span>
                <?php _e('Volver', 'politeia-learning'); ?>
            </button>
            <span id="pcg-current-specialization-label" class="pcg-current-course-label"></span>
        </div>
        <div class="pcg-nav-right">
            <div class="pcg-segmented-control">
                <div class="pcg-segment pcg-spec-segment active" data-value="especializacion">
                    <?php _e('ESPECIALIZACIÓN', 'politeia-learning'); ?>
                </div>
                <div class="pcg-segment pcg-spec-segment" data-value="cursos">
                    <?php _e('CURSOS', 'politeia-learning'); ?>
                </div>
            </div>
            <button type="button" class="pcg-btn-save pcg-btn-save-compact pcg-btn-save-specialization"
                title="<?php _e('Guardar', 'politeia-learning'); ?>">
                <span class="dashicons dashicons-saved"></span>
            </button>
        </div>
    </div>

    <!-- START: ESPECIALIZACIÓN MODE -->
    <div id="pcg-spec-mode-especializacion" class="pcg-mode-content">
        <div class="pcg-form-header">
            <div class="pcg-title-field">
                <input type="text" id="pcg-group-title"
                    placeholder="<?php _e('Nombre de la especialización', 'politeia-learning'); ?>" class="pcg-modern-input">
            </div>
        </div>

        <div class="pcg-media-row">
            <div class="pcg-media-left-col">
                <div class="pcg-asset-actions">
                    <button type="button" class="pcg-btn-outline" id="pcg-upload-group-thumbnail" disabled
                        title="<?php esc_attr_e('Próximamente', 'politeia-learning'); ?>">
                        <span class="dashicons dashicons-upload"></span>
                        <?php _e('Portada', 'politeia-learning'); ?>
                    </button>
                </div>
            </div>

            <div class="pcg-price-right-col">
                <div class="pcg-price-field">
                    <div class="pcg-inline-input">
                        <label><?php _e('PRECIO', 'politeia-learning'); ?></label>
                        <div class="pcg-price-input-wrapper">
                            <span class="currency">$</span>
                            <input type="text" id="pcg-group-price" placeholder="0.00" class="pcg-modern-input-small">
                        </div>
                    </div>
                    <div id="pcg-group-price-free-indicator" class="pcg-price-free-indicator" style="display:none;">
                        <?php _e('Gratis', 'politeia-learning'); ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="pcg-description-field">
            <div class="pcg-desc-tabs">
                <button type="button" class="pcg-desc-tab active" data-target="pcg-spec-tab-description">
                    <?php _e('DESCRIPCIÓN', 'politeia-learning'); ?>
                </button>
                <button type="button" class="pcg-desc-tab" data-target="pcg-spec-tab-teachers">
                    <?php _e('PROFESORES', 'politeia-learning'); ?>
                </button>
            </div>

            <div id="pcg-spec-tab-description" class="pcg-tab-content active">
                <textarea id="pcg-group-description"
                    placeholder="<?php _e('Describe la especialización...', 'politeia-learning'); ?>"
                    class="pcg-modern-textarea"></textarea>
            </div>

            <div id="pcg-spec-tab-teachers" class="pcg-tab-content">
                <div class="pcg-teachers-header">
                    <h3><?php _e('PROFESORES & COLABORADORES', 'politeia-learning'); ?></h3>
                    <button type="button" class="pcg-btn-add-circle pcg-btn-add-teacher" data-target="#pcg-group-teachers-list">
                        <span class="dashicons dashicons-plus-alt2"></span>
                    </button>
                </div>

                <div id="pcg-group-teachers-list" class="pcg-items-list pcg-teachers-list">
                    <div class="pcg-empty-teachers-state">
                        <p><?php _e('No hay colaboradores asignados.', 'politeia-learning'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- END: ESPECIALIZACIÓN MODE -->

    <!-- START: CURSOS MODE -->
    <div id="pcg-spec-mode-cursos" class="pcg-mode-content" style="display:none;">
        <div class="pcg-spec-courses-panel">
            <div class="pcg-spec-course-search">
                <input type="text" id="pcg-spec-course-search" class="pcg-modern-input"
                    placeholder="<?php esc_attr_e('Buscar curso...', 'politeia-learning'); ?>" autocomplete="off" />
            </div>

            <div class="pcg-spec-courses-columns">
                <div class="pcg-spec-col pcg-spec-col--all">
                    <div class="pcg-spec-col-header">
                        <h4><?php _e('Todos los cursos', 'politeia-learning'); ?></h4>
                    </div>
                    <div id="pcg-spec-all-courses" class="pcg-spec-all-courses">
                        <div class="pcg-loading-placeholder">
                            <span class="dashicons dashicons-update spin"></span>
                            <p><?php _e('Cargando tus cursos...', 'politeia-learning'); ?></p>
                        </div>
                    </div>
                    <div id="pcg-spec-courses-pagination" class="pcg-spec-pagination" style="display:none;">
                        <button type="button" class="pcg-spec-page-btn" id="pcg-spec-page-prev">
                            <?php _e('Anterior', 'politeia-learning'); ?>
                        </button>
                        <span class="pcg-spec-page-info" id="pcg-spec-page-info"></span>
                        <button type="button" class="pcg-spec-page-btn" id="pcg-spec-page-next">
                            <?php _e('Siguiente', 'politeia-learning'); ?>
                        </button>
                    </div>
                </div>

                <div class="pcg-spec-col pcg-spec-col--added">
                    <div class="pcg-spec-col-header">
                        <div class="pcg-spec-added-header-row">
                            <h4><?php _e('Cursos agregados', 'politeia-learning'); ?></h4>
                            <div class="pcg-spec-order-toggle">
                                <span class="pcg-spec-order-label"><?php _e('Orden requerido', 'politeia-learning'); ?></span>
                                <label class="pcg-switch">
                                    <input type="checkbox" id="pcg-spec-order-required">
                                    <span class="pcg-slider round"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div id="pcg-spec-added-courses" class="pcg-spec-added-courses">
                        <div class="pcg-loading-placeholder">
                            <span class="dashicons dashicons-update spin"></span>
                            <p><?php _e('Cargando tus cursos...', 'politeia-learning'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- END: CURSOS MODE -->

</div>

<!-- MY SPECIALIZATIONS LIST -->
<div id="pcg-my-specializations-section" class="pcg-my-courses-container">
    <div class="pcg-section-header">
        <h3><?php _e('MIS ESPECIALIZACIONES', 'politeia-learning'); ?></h3>
        <button type="button" id="pcg-show-specialization-form" class="pcg-btn-intro-create">
            <?php _e('Crear especialización', 'politeia-learning'); ?>
        </button>
    </div>

    <div id="specialization-grid" class="pcg-my-courses-grid">
        <div class="pcg-loading-placeholder">
            <span class="dashicons dashicons-update spin"></span>
            <p><?php _e('Cargando tus especializaciones...', 'politeia-learning'); ?></p>
        </div>
    </div>
</div>
