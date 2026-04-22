<?php
/**
 * Course Creator - General Info Mode
 */
if (!defined('ABSPATH')) exit;
?>

<div id="pcg-mode-curso" class="pcg-mode-content" <?php echo $pcg_active_segment !== 'curso' ? 'style="display:none;"' : ''; ?>>
    <!-- Header: Title -->
    <header class="pcg-form-header pcg-course-editor__header">
        <div class="pcg-title-field pcg-course-editor__title">
            <input type="text" id="pcg-course-title"
                placeholder="<?php _e('Título del curso', 'politeia-learning'); ?>" class="pcg-modern-input">
        </div>
    </header>

    <div class="pcg-course-editor__grid">
        <div class="pcg-course-editor__left">
            <!-- Description / Excerpt / Teachers Tabs -->
            <div class="pcg-tabs-card">
                <div class="pcg-desc-tabs">
                    <button type="button" class="pcg-desc-tab active" data-target="pcg-tab-description">
                        <?php _e('DESCRIPCIÓN', 'politeia-learning'); ?>
                    </button>
                    <button type="button" class="pcg-desc-tab" data-target="pcg-tab-excerpt">
                        <?php _e('EXTRACTO', 'politeia-learning'); ?>
                    </button>
                    <button type="button" class="pcg-desc-tab" data-target="pcg-tab-image">
                        <?php _e('IMAGEN', 'politeia-learning'); ?>
                    </button>
                    <button type="button" class="pcg-desc-tab" data-target="pcg-tab-teachers">
                        <?php _e('PROFESORES', 'politeia-learning'); ?>
                    </button>
                </div>

                <div class="pcg-tabs-card__body">
                    <div id="pcg-tab-description" class="pcg-tab-content active">
                        <textarea id="pcg-course-description"
                            placeholder="<?php _e('Escribe la descripción del curso aquí... (máx. 700 palabras)', 'politeia-learning'); ?>"
                            class="pcg-modern-textarea"></textarea>
                        <span class="pcg-word-count" id="pcg-desc-word-count">0 / 700
                            <?php _e('palabras', 'politeia-learning'); ?></span>
                    </div>

                    <div id="pcg-tab-excerpt" class="pcg-tab-content">
                        <textarea id="pcg-course-excerpt"
                            placeholder="<?php _e('Escribe un resumen breve del curso... (máx. 50 palabras)', 'politeia-learning'); ?>"
                            class="pcg-modern-textarea pcg-excerpt-textarea"></textarea>
                        <span class="pcg-word-count" id="pcg-excerpt-word-count">0 / 50
                            <?php _e('palabras', 'politeia-learning'); ?></span>
                    </div>

                    <div id="pcg-tab-image" class="pcg-tab-content">
                        <div class="pcg-media-grid">
                            <div class="pcg-media-card pcg-media-card--thumbnail">
                                <div class="pcg-media-card__meta">
                                    <span class="pcg-media-card__label"><?php _e('Portada del curso', 'politeia-learning'); ?></span>
                                </div>

                                <div id="pcg-thumbnail-preview" class="pcg-media-card__preview" style="display:none;">
                                    <img src="" alt="">
                                    <button type="button" id="pcg-remove-thumbnail" class="pcg-media-card__remove">
                                        <?php _e('Quitar portada', 'politeia-learning'); ?>
                                    </button>
                                </div>
                                <div class="pcg-media-card__empty" role="button" tabindex="0" data-upload="thumbnail">
                                    <div class="pcg-media-card__empty-inner">
                                        <div class="pcg-media-card__empty-title"><?php _e('Sin imagen', 'politeia-learning'); ?></div>
                                        <div class="pcg-media-card__empty-action">
                                            <svg class="pcg-media-card__empty-icon" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                                <polyline points="17 8 12 3 7 8"></polyline>
                                                <line x1="12" y1="3" x2="12" y2="15"></line>
                                            </svg>
                                            <span><?php _e('Click para subir imagen', 'politeia-learning'); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="pcg-media-card__hint">
                                    <?php _e('proporción 360 x 240', 'politeia-learning'); ?>
                                </div>
                            </div>

                            <div class="pcg-media-card pcg-media-card--cover">
                                <div class="pcg-media-card__meta">
                                    <span class="pcg-media-card__label"><?php _e('Fondo', 'politeia-learning'); ?></span>
                                </div>

                                <div id="pcg-cover-preview" class="pcg-media-card__preview" style="display:none;">
                                    <img src="" alt="">
                                    <button type="button" id="pcg-remove-cover" class="pcg-media-card__remove">
                                        <?php _e('Quitar foto', 'politeia-learning'); ?>
                                    </button>
                                </div>
                                <div class="pcg-media-card__empty" role="button" tabindex="0" data-upload="cover">
                                    <div class="pcg-media-card__empty-inner">
                                        <div class="pcg-media-card__empty-title"><?php _e('Sin imagen', 'politeia-learning'); ?></div>
                                        <div class="pcg-media-card__empty-action">
                                            <svg class="pcg-media-card__empty-icon" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                                <polyline points="17 8 12 3 7 8"></polyline>
                                                <line x1="12" y1="3" x2="12" y2="15"></line>
                                            </svg>
                                            <span><?php _e('Click para subir imagen', 'politeia-learning'); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="pcg-media-card__hint">
                                    <?php _e('proporción 1024 x 768', 'politeia-learning'); ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="pcg-tab-teachers" class="pcg-tab-content">
                        <div class="pcg-teachers-header">
                            <h3><?php _e('PROFESORES & COLABORADORES', 'politeia-learning'); ?></h3>
                            <button type="button" class="pcg-btn-gold pcg-btn-add-teacher" id="pcg-btn-add-teacher"
                                data-target="#pcg-teachers-list">
                                <span class="dashicons dashicons-plus-alt2"></span>
                                <?php _e('Miembro', 'politeia-learning'); ?>
                            </button>
                        </div>

                        <div id="pcg-teachers-list" class="pcg-items-list">
                            <!-- Teacher items will be added here -->
                            <div class="pcg-empty-teachers-state">
                                <p><?php _e('No hay colaboradores asignados.', 'politeia-learning'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php include __DIR__ . '/sidebar.php'; ?>
    </div>
</div>
