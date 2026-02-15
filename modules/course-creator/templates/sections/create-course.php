<?php
if (!defined('ABSPATH'))
    exit;

$pcg_is_editing_quiz = isset($_GET['edit_quiz']) && !empty($_GET['edit_quiz']);
$pcg_active_segment = $pcg_is_editing_quiz ? 'evaluacion' : 'curso';
?>

<!-- Intro Section (Initial State) -->
<div id="pcg-creator-intro-section" class="pcg-creator-intro-card" <?php echo $pcg_is_editing_quiz ? 'style="display:none;"' : ''; ?>>
    <div class="pcg-intro-text">
        <h2><?php _e('Create your course and sell them in our platform', 'politeia-course-group'); ?></h2>
        <p><?php _e('Empieza hoy mismo a compartir tu conocimiento con el mundo.', 'politeia-course-group'); ?></p>
        <button type="button" id="pcg-show-creator-form" class="pcg-btn-intro-create">
            <?php _e('Crea un Curso', 'politeia-course-group'); ?>
        </button>
    </div>
</div>

<!-- Creation Form (Hidden Initially) -->
<div id="pcg-course-form-section" class="pcg-create-course-container" <?php echo $pcg_is_editing_quiz ? 'style="display:block;"' : 'style="display:none;"'; ?>>
    <?php
    $current_course_id = 0;
    if ($pcg_is_editing_quiz && function_exists('learndash_get_course_id')) {
        $current_course_id = learndash_get_course_id(intval($_GET['edit_quiz']));
    }
    ?>
    <input type="hidden" id="pcg-current-course-id" value="<?php echo esc_attr($current_course_id); ?>">

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
            <div class="pcg-segment <?php echo $pcg_active_segment === 'curso' ? 'active' : ''; ?>" data-value="curso">
                <?php _e('CURSO', 'politeia-course-group'); ?>
            </div>
            <div class="pcg-segment <?php echo $pcg_active_segment === 'lecciones' ? 'active' : ''; ?>"
                data-value="lecciones"><?php _e('LECCIONES', 'politeia-course-group'); ?></div>
            <div class="pcg-segment <?php echo $pcg_active_segment === 'evaluacion' ? 'active' : ''; ?>"
                data-value="evaluacion"><?php _e('EVALUACIÓN', 'politeia-course-group'); ?></div>
        </div>
        <div class="pcg-header-actions">
            <button type="button" id="pcg-btn-cancel-edit"
                class="pcg-btn-outline pcg-btn-small"><?php _e('CANCELAR', 'politeia-course-group'); ?></button>
            <button type="button" class="pcg-btn-save"><?php _e('GUARDAR', 'politeia-course-group'); ?></button>
        </div>
    </div>

    <!-- START: CURSO MODE -->
    <div id="pcg-mode-curso" class="pcg-mode-content" <?php echo $pcg_active_segment !== 'curso' ? 'style="display:none;"' : ''; ?>>
        <!-- Header: Title -->
        <div class="pcg-form-header">
            <div class="pcg-title-field">
                <input type="text" id="pcg-course-title"
                    placeholder="<?php _e('Título del curso', 'politeia-course-group'); ?>" class="pcg-modern-input">
            </div>
        </div>

        <div class="pcg-media-row">
            <!-- Thumbnail Preview -->
            <div id="pcg-thumbnail-preview" class="pcg-thumbnail-preview" style="display:none;">
                <p style="font-size: 12px; color: #666; margin-bottom: 5px;">Course Cover Preview:</p>
                <img src="" style="max-width: 200px; border-radius: 8px; display: block;">
                <button type="button" id="pcg-remove-thumbnail" class="pcg-btn-remove-thumb"
                    style="color: #e53e3e; font-size: 12px; border: none; background: none; cursor: pointer; padding: 5px 0; font-weight: 600;">
                    <?php _e('Remove Cover', 'politeia-course-group'); ?>
                </button>
            </div>

            <!-- Cover Photo Preview -->
            <div id="pcg-cover-preview" class="pcg-thumbnail-preview" style="display:none;">
                <p style="font-size: 12px; color: #666; margin-bottom: 5px;">Cover Photo Preview:</p>
                <img src="" style="max-width: 200px; height: 100px; object-fit: cover; border-radius: 8px; display: block;">
                <button type="button" id="pcg-remove-cover" class="pcg-btn-remove-thumb"
                    style="color: #e53e3e; font-size: 12px; border: none; background: none; cursor: pointer; padding: 5px 0; font-weight: 600;">
                    <?php _e('Remove Photo', 'politeia-course-group'); ?>
                </button>
            </div>

            <!-- Upload Buttons -->
            <div class="pcg-asset-actions">
                <button type="button" class="pcg-btn-outline" id="pcg-upload-thumbnail">
                    <span class="dashicons dashicons-upload"></span>
                    <?php _e('Course Cover', 'politeia-course-group'); ?>
                </button>
                <button type="button" class="pcg-btn-outline" id="pcg-select-background">
                    <?php _e('Cover Photo', 'politeia-course-group'); ?>
                </button>
            </div>
        </div>

        <!-- Description / Excerpt Tabs -->
        <div class="pcg-description-field">
            <div class="pcg-desc-tabs">
                <button type="button" class="pcg-desc-tab active" data-target="pcg-tab-description">
                    <?php _e('DESCRIPCIÓN', 'politeia-course-group'); ?>
                </button>
                <button type="button" class="pcg-desc-tab" data-target="pcg-tab-excerpt">
                    <?php _e('EXTRACTO', 'politeia-course-group'); ?>
                </button>
            </div>

            <div id="pcg-tab-description" class="pcg-tab-content active">
                <textarea id="pcg-course-description"
                    placeholder="<?php _e('Escribe la descripción del curso aquí... (máx. 700 palabras)', 'politeia-course-group'); ?>"
                    class="pcg-modern-textarea"></textarea>
                <span class="pcg-word-count" id="pcg-desc-word-count">0 / 700
                    <?php _e('palabras', 'politeia-course-group'); ?></span>
            </div>

            <div id="pcg-tab-excerpt" class="pcg-tab-content">
                <textarea id="pcg-course-excerpt"
                    placeholder="<?php _e('Escribe un resumen breve del curso... (máx. 50 palabras)', 'politeia-course-group'); ?>"
                    class="pcg-modern-textarea pcg-excerpt-textarea"></textarea>
                <span class="pcg-word-count" id="pcg-excerpt-word-count">0 / 50
                    <?php _e('palabras', 'politeia-course-group'); ?></span>
            </div>
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
    <div id="pcg-mode-lecciones" class="pcg-mode-content" <?php echo $pcg_active_segment !== 'lecciones' ? 'style="display:none;"' : ''; ?>>
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

    <!-- START: EVALUACIÓN MODE -->
    <div id="pcg-mode-evaluacion" class="pcg-mode-content" <?php echo $pcg_active_segment !== 'evaluacion' ? 'style="display:none;"' : ''; ?>>
        <div id="pcg-quiz-not-created-msg" class="pcg-empty-state-msg"
            style="display:none; padding: 40px; text-align: center; background: #f8fafc; border-radius: 10px; border: 1px dashed #cbd5e0;">
            <p style="font-weight: 600; color: #4a5568; margin: 0;">
                <?php _e('Before creating a quiz you must create a course first', 'politeia-course-group'); ?>
            </p>
        </div>
        <div id="pcg-quiz-creator-container">
            <?php echo do_shortcode('[politeia_quiz_creator]'); ?>
        </div>
    </div>
    <!-- END: EVALUACIÓN MODE -->

</div>

<!-- MY COURSES LIST (Visible underneath) -->
<div id="pcg-my-courses-section" class="pcg-my-courses-container" <?php echo $pcg_is_editing_quiz ? 'style="display:none;"' : ''; ?>>
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