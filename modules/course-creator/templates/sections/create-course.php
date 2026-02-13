<?php if (!defined('ABSPATH'))
    exit; ?>

<!-- Intro Section (Initial State) -->
<div id="pcg-creator-intro-section" class="pcg-creator-intro-card">
    <div class="pcg-intro-text">
        <h2><?php _e('Create your course and sell them in our platform', 'politeia-course-group'); ?></h2>
        <p><?php _e('Empieza hoy mismo a compartir tu conocimiento con el mundo.', 'politeia-course-group'); ?></p>
        <button type="button" id="pcg-show-creator-form" class="pcg-btn-intro-create">
            <?php _e('Crea un Curso', 'politeia-course-group'); ?>
        </button>
    </div>
</div>

<!-- Creation Form (Hidden Initially) -->
<div id="pcg-course-form-section" class="pcg-create-course-container" style="display:none;">

    <!-- Back Button and Current Title -->
    <div class="pcg-form-nav">
        <div class="pcg-nav-left">
            <button type="button" id="pcg-btn-back-to-list" class="pcg-btn-back">
                <span class="dashicons dashicons-arrow-left-alt2"></span>
                <?php _e('Back', 'politeia-course-group'); ?>
            </button>
            <span id="pcg-current-course-label" class="pcg-current-course-label"></span>
        </div>
        <div class="pcg-nav-right">
            <button type="button" id="pcg-btn-preview-course" class="pcg-btn-preview"
                title="<?php _e('Vista Previa', 'politeia-course-group'); ?>" style="display:none;">
                <span class="dashicons dashicons-visibility"></span>
            </button>
        </div>
    </div>

    <div class="pcg-form-divider"></div>

    <!-- Top Bar: Toggle and Save -->
    <div class="pcg-toggle-wrapper">
        <div class="pcg-segmented-control">
            <div class="pcg-segment active" data-value="curso"><?php _e('CURSO', 'politeia-course-group'); ?></div>
            <div class="pcg-segment" data-value="lecciones"><?php _e('LECCIONES', 'politeia-course-group'); ?></div>
        </div>
        <div class="pcg-header-actions">
            <button type="button" id="pcg-btn-cancel-edit"
                class="pcg-btn-outline pcg-btn-small"><?php _e('CANCELAR', 'politeia-course-group'); ?></button>
            <button type="button" class="pcg-btn-save"><?php _e('GUARDAR', 'politeia-course-group'); ?></button>
        </div>
    </div>

    <!-- START: CURSO MODE -->
    <div id="pcg-mode-curso" class="pcg-mode-content">
        <!-- Header: Title -->
        <div class="pcg-form-header">
            <div class="pcg-title-field">
                <input type="text" id="pcg-course-title"
                    placeholder="<?php _e('Título del curso', 'politeia-course-group'); ?>" class="pcg-modern-input">
            </div>
        </div>

        <!-- Assets Buttons -->
        <div id="pcg-thumbnail-preview" class="pcg-thumbnail-preview" style="display:none; margin-bottom: 20px;">
            <img src="" style="max-width: 200px; border-radius: 8px; display: block;">
            <button type="button" id="pcg-remove-thumbnail" class="pcg-btn-remove-thumb"
                style="color: #e53e3e; font-size: 12px; border: none; background: none; cursor: pointer; padding: 5px 0; font-weight: 600;">
                <?php _e('Eliminar imagen', 'politeia-course-group'); ?>
            </button>
        </div>
        <div class="pcg-asset-actions">
            <button type="button" class="pcg-btn-outline" id="pcg-upload-thumbnail">
                <span class="dashicons dashicons-upload"></span>
                <?php _e('UPLOAD', 'politeia-course-group'); ?>
            </button>
            <button type="button" class="pcg-btn-outline" id="pcg-select-background">
                <?php _e('SELECT FONDO', 'politeia-course-group'); ?>
            </button>
        </div>

        <!-- Description -->
        <div class="pcg-description-field">
            <label><?php _e('DESCRIPCIÓN', 'politeia-course-group'); ?></label>
            <textarea id="pcg-course-description"
                placeholder="<?php _e('Escribe la descripción del curso aquí...', 'politeia-course-group'); ?>"
                class="pcg-modern-textarea"></textarea>
        </div>

        <!-- Price -->
        <div class="pcg-price-field">
            <div class="pcg-inline-input">
                <label><?php _e('PRECIO', 'politeia-course-group'); ?></label>
                <div class="pcg-price-input-wrapper">
                    <span class="currency">$</span>
                    <input type="text" id="pcg-course-price" placeholder="0.00" class="pcg-modern-input-small">
                </div>
            </div>
            <div id="pcg-price-free-indicator" class="pcg-price-free-indicator" style="display:none;">
                <?php _e('Gratis', 'politeia-course-group'); ?>
            </div>
        </div>
    </div>
    <!-- END: CURSO MODE -->

    <!-- START: LECCIONES MODE -->
    <div id="pcg-mode-lecciones" class="pcg-mode-content" style="display:none;">
        <div class="pcg-lessons-header">
            <h3><?php _e('CONTENIDO DEL CURSO', 'politeia-course-group'); ?></h3>
            <div class="pcg-progression-container">
                <span class="pcg-progression-label"><?php _e('FLUJO LIBRE', 'politeia-course-group'); ?></span>
                <label class="pcg-switch">
                    <input type="checkbox" id="pcg-course-progression">
                    <span class="pcg-slider round"></span>
                </label>
            </div>
            <div class="pcg-add-actions">
                <button type="button" class="pcg-btn-add-circle" id="pcg-btn-add-content">
                    <span class="dashicons dashicons-plus-alt2"></span>
                </button>
                <div class="pcg-add-dropdown" id="pcg-add-dropdown">
                    <button type="button" class="pcg-add-option" data-type="lesson">
                        <span class="dashicons dashicons-media-text"></span>
                        <?php _e('Add Lesson', 'politeia-course-group'); ?>
                    </button>
                    <button type="button" class="pcg-add-option" data-type="section">
                        <span class="dashicons dashicons-menu-alt3"></span>
                        <?php _e('Add Section', 'politeia-course-group'); ?>
                    </button>
                </div>
            </div>
        </div>

        <div id="pcg-lessons-list" class="pcg-lessons-list">
            <!-- Dynamic lessons/sections will appear here -->
            <div class="pcg-empty-lessons-state">
                <p><?php _e('No hay contenido aún. Haz clic en el botón + para añadir una lección o sección.', 'politeia-course-group'); ?>
                </p>
            </div>
        </div>
    </div>
    <!-- END: LECCIONES MODE -->

</div>

<!-- MY COURSES LIST (Visible underneath) -->
<div id="pcg-my-courses-section" class="pcg-my-courses-container">
    <div class="pcg-section-header">
        <h3><?php _e('MIS CURSOS PUBLICADOS', 'politeia-course-group'); ?></h3>
    </div>

    <div id="pcg-my-courses-grid" class="pcg-my-courses-grid">
        <!-- Will be populated via AJAX/PHP -->
        <div class="pcg-loading-placeholder">
            <span class="dashicons dashicons-update spin"></span>
            <p><?php _e('Cargando tus cursos...', 'politeia-course-group'); ?></p>
        </div>
    </div>
</div>