<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<!-- PROGRAMAS FORM (Hidden Initially) -->
<div id="pcg-programa-form-section" class="pcg-create-course-container" style="display:none;">
    <input type="hidden" id="pcg-current-programa-id" value="0">

    <div class="pcg-form-nav">
        <div class="pcg-nav-left">
            <button type="button" id="pcg-btn-back-to-programas" class="pcg-btn-back">
                <span class="dashicons dashicons-arrow-left-alt2"></span>
                <?php _e('Volver', 'politeia-learning'); ?>
            </button>
            <span id="pcg-current-programa-label" class="pcg-current-course-label"></span>
        </div>
        <div class="pcg-nav-right">
            <div class="pcg-segmented-control">
                <div class="pcg-segment pcg-prog-segment active" data-value="programa">
                    <?php _e('PROGRAMA', 'politeia-learning'); ?>
                </div>
                <div class="pcg-segment pcg-prog-segment" data-value="especializaciones">
                    <?php _e('ESPECIALIZACIONES', 'politeia-learning'); ?>
                </div>
                <div class="pcg-segment pcg-prog-segment" data-value="meta">
                    <?php _e('META', 'politeia-learning'); ?>
                </div>
            </div>
            <button type="button" class="pcg-btn-save pcg-btn-save-compact pcg-btn-save-programa"
                title="<?php _e('Guardar', 'politeia-learning'); ?>">
                <span class="dashicons dashicons-saved"></span>
            </button>
        </div>
    </div>

    <!-- START: PROGRAMA MODE -->
    <div id="pcg-prog-mode-programa" class="pcg-mode-content">
        <div class="pcg-form-header">
            <div class="pcg-title-field">
                <input type="text" id="pcg-programa-title"
                    placeholder="<?php _e('Nombre del programa', 'politeia-learning'); ?>" class="pcg-modern-input">
            </div>
        </div>

        <div class="pcg-media-row">
            <div class="pcg-media-left-col">
                <div class="pcg-asset-actions">
                    <button type="button" class="pcg-btn-outline" id="pcg-upload-programa-thumbnail" disabled
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
                            <input type="text" id="pcg-programa-price" placeholder="0.00" class="pcg-modern-input-small">
                        </div>
                    </div>
                    <div id="pcg-programa-price-free-indicator" class="pcg-price-free-indicator" style="display:none;">
                        <?php _e('Gratis', 'politeia-learning'); ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="pcg-description-field">
            <div class="pcg-desc-tabs">
                <button type="button" class="pcg-desc-tab active" data-target="pcg-prog-tab-description">
                    <?php _e('DESCRIPCIÓN', 'politeia-learning'); ?>
                </button>
                <button type="button" class="pcg-desc-tab" data-target="pcg-prog-tab-teachers">
                    <?php _e('PROFESORES', 'politeia-learning'); ?>
                </button>
            </div>

            <div id="pcg-prog-tab-description" class="pcg-tab-content active">
                <textarea id="pcg-programa-description"
                    placeholder="<?php _e('Describe el programa...', 'politeia-learning'); ?>"
                    class="pcg-modern-textarea"></textarea>
            </div>

            <div id="pcg-prog-tab-teachers" class="pcg-tab-content">
                <div class="pcg-teachers-header">
                    <h3><?php _e('PROFESORES & COLABORADORES', 'politeia-learning'); ?></h3>
                    <button type="button" class="pcg-btn-add-circle pcg-btn-add-teacher" data-target="#pcg-program-teachers-list">
                        <span class="dashicons dashicons-plus-alt2"></span>
                    </button>
                </div>

                <div id="pcg-program-teachers-list" class="pcg-items-list pcg-teachers-list">
                    <div class="pcg-empty-teachers-state">
                        <p><?php _e('No hay colaboradores asignados.', 'politeia-learning'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- END: PROGRAMA MODE -->

    <!-- START: ESPECIALIZACIONES MODE -->
    <div id="pcg-prog-mode-especializaciones" class="pcg-mode-content" style="display:none;">
        <div class="pcg-prog-panel">
            <div class="pcg-prog-search">
                <input type="text" id="pcg-prog-spec-search" class="pcg-modern-input"
                    placeholder="<?php esc_attr_e('Buscar especialización...', 'politeia-learning'); ?>" autocomplete="off" />
            </div>

            <div class="pcg-prog-columns">
                <div class="pcg-prog-col">
                    <div class="pcg-prog-col-header">
                        <h4><?php _e('Todas las especializaciones', 'politeia-learning'); ?></h4>
                    </div>
                    <div id="pcg-prog-all-specs" class="pcg-prog-list">
                        <div class="pcg-loading-placeholder">
                            <span class="dashicons dashicons-update spin"></span>
                            <p><?php _e('Cargando...', 'politeia-learning'); ?></p>
                        </div>
                    </div>
                    <div id="pcg-prog-pagination" class="pcg-spec-pagination" style="display:none;">
                        <button type="button" class="pcg-spec-page-btn" id="pcg-prog-page-prev">
                            <?php _e('Anterior', 'politeia-learning'); ?>
                        </button>
                        <span class="pcg-spec-page-info" id="pcg-prog-page-info"></span>
                        <button type="button" class="pcg-spec-page-btn" id="pcg-prog-page-next">
                            <?php _e('Siguiente', 'politeia-learning'); ?>
                        </button>
                    </div>
                </div>

                <div class="pcg-prog-col">
                    <div class="pcg-prog-col-header">
                        <h4><?php _e('Especializaciones agregadas', 'politeia-learning'); ?></h4>
                    </div>
                    <div id="pcg-prog-added-specs" class="pcg-spec-added-courses">
                        <div class="pcg-loading-placeholder">
                            <span class="dashicons dashicons-update spin"></span>
                            <p><?php _e('Cargando...', 'politeia-learning'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- END: ESPECIALIZACIONES MODE -->

    <!-- START: META MODE -->
    <div id="pcg-prog-mode-meta" class="pcg-mode-content" style="display:none;">
        <div class="pcg-meta-card">
            <h3 class="pcg-meta-title"><?php _e('META DEL PROGRAMA', 'politeia-learning'); ?></h3>

            <div class="pcg-meta-section">
                <label><?php _e('CATEGORÍAS', 'politeia-learning'); ?></label>
                <div class="pcg-meta-cat-picker" data-entity="programa">
                    <div class="pcg-meta-cat-level pcg-meta-cat-level--l1" id="pcg-programa-meta-cat-l1" aria-live="polite"></div>
                    <div class="pcg-meta-cat-level pcg-meta-cat-level--l2" id="pcg-programa-meta-cat-l2" aria-live="polite"></div>
                    <div class="pcg-meta-cat-level pcg-meta-cat-level--l3" id="pcg-programa-meta-cat-l3" aria-live="polite"></div>
                </div>
            </div>

            <div class="pcg-meta-section">
                <label for="pcg-programa-meta-tag-input"><?php _e('ETIQUETAS', 'politeia-learning'); ?></label>
                <div class="pcg-meta-tags">
                    <div id="pcg-programa-meta-tag-chips" class="pcg-meta-chips" aria-live="polite"></div>
                    <input type="text" id="pcg-programa-meta-tag-input" class="pcg-modern-input pcg-meta-tag-input"
                        placeholder="<?php esc_attr_e('Escribe para buscar o crear...', 'politeia-learning'); ?>" autocomplete="off" />
                    <div id="pcg-programa-meta-tag-suggestions" class="pcg-meta-suggestions"></div>
                </div>
            </div>
        </div>
    </div>
    <!-- END: META MODE -->
</div>

<!-- MY PROGRAMAS LIST -->
<div id="pcg-my-programas-section" class="pcg-my-courses-container">
    <div class="pcg-section-header">
        <h3><?php _e('MIS PROGRAMAS', 'politeia-learning'); ?></h3>
        <button type="button" id="pcg-show-programa-form" class="pcg-btn-intro-create">
            <?php _e('Crear programa', 'politeia-learning'); ?>
        </button>
    </div>

    <div id="programas-grid" class="pcg-my-courses-grid">
        <div class="pcg-loading-placeholder">
            <span class="dashicons dashicons-update spin"></span>
            <p><?php _e('Cargando tus programas...', 'politeia-learning'); ?></p>
        </div>
    </div>
</div>
