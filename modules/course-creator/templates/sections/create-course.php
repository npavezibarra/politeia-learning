<?php
if (!defined('ABSPATH'))
    exit;

$pcg_is_editing_quiz = isset($_GET['edit_quiz']) && !empty($_GET['edit_quiz']);
$pcg_active_segment = $pcg_is_editing_quiz ? 'evaluacion' : 'curso';
?>

<!-- Creation Form (Hidden Initially) -->
<div id="pcg-course-form-section" class="pcg-create-course-container" <?php echo $pcg_is_editing_quiz ? 'style="display:block;"' : 'style="display:none;"'; ?>>
    <?php
    $current_course_id = 0;
    if ($pcg_is_editing_quiz && class_exists('PQC_Quiz_Creator') && method_exists('PQC_Quiz_Creator', 'get_course_id_by_quiz_id')) {
        $current_course_id = (int) PQC_Quiz_Creator::get_course_id_by_quiz_id((int) $_GET['edit_quiz']);
    }
    ?>
    <input type="hidden" id="pcg-current-course-id" value="<?php echo esc_attr($current_course_id); ?>">

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


    <!-- START: CURSO MODE -->
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

		            <!-- Right Column: Price -->
		            <aside class="pcg-course-editor__right">
		                <div class="pcg-sidecard">
		                    <div class="pcg-sidecard__section">
		                        <div id="pcg-course-actions" class="pcg-sidecard__actions">
		                            <button type="button" class="pcg-btn-save pcg-btn-save-course"
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
		                        </div>
		                        <span class="pcg-sidecard__eyebrow"><?php _e('Precio del curso', 'politeia-learning'); ?></span>
		                        <div class="pcg-sidecard__price">
		                            <span class="pcg-sidecard__currency">$</span>
		                            <input type="text" id="pcg-course-price" placeholder="0.00" class="pcg-sidecard__price-input">
		                        </div>
		                        <div id="pcg-price-free-indicator" class="pcg-price-free-indicator" style="display:none;">
		                            <?php _e('Gratis', 'politeia-learning'); ?>
		                        </div>
		                    </div>
		                </div>
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
	            </aside>
	        </div>
	    </div>
    <!-- END: CURSO MODE -->

    <!-- START: LECCIONES MODE -->
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

	            <aside class="pcg-lessons-editor__right">
	                <div class="pcg-sidecard">
	                    <div class="pcg-sidecard__section">
	                        <div class="pcg-sidecard__actions pcg-sidecard__actions-slot"></div>
	                        <span class="pcg-sidecard__eyebrow"><?php _e('Precio del curso', 'politeia-learning'); ?></span>
	                        <div class="pcg-sidecard__price">
	                            <span class="pcg-sidecard__currency">$</span>
	                            <input type="text" id="pcg-course-price-lessons" placeholder="0.00" class="pcg-sidecard__price-input">
	                        </div>
	                        <div id="pcg-price-free-indicator-lessons" class="pcg-price-free-indicator" style="display:none;">
	                            <?php _e('Gratis', 'politeia-learning'); ?>
	                        </div>
	                    </div>
	                </div>
	                <div class="pcg-checklist-slot"></div>
	            </aside>
        </div>
    </div>
    <!-- END: LECCIONES MODE -->

	    <!-- START: EVALUACIÓN MODE -->
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

		            <aside class="pcg-eval-editor__right">
		                <div class="pcg-sidecard">
		                    <div class="pcg-sidecard__section">
		                        <div class="pcg-sidecard__actions pcg-sidecard__actions-slot"></div>
		                        <span class="pcg-sidecard__eyebrow"><?php _e('Precio del curso', 'politeia-learning'); ?></span>
		                        <div class="pcg-sidecard__price">
		                            <span class="pcg-sidecard__currency">$</span>
		                            <input type="text" id="pcg-course-price-eval" placeholder="0.00" class="pcg-sidecard__price-input">
		                        </div>
		                        <div id="pcg-price-free-indicator-eval" class="pcg-price-free-indicator" style="display:none;">
		                            <?php _e('Gratis', 'politeia-learning'); ?>
		                        </div>
		                    </div>
		                </div>
		                <div class="pcg-checklist-slot"></div>
		            </aside>
	        </div>
	    </div>
	    <!-- END: EVALUACIÓN MODE -->

        <!-- START: CERTIFICADO MODE -->
        <div id="pcg-mode-certificado" class="pcg-mode-content" <?php echo $pcg_active_segment !== 'certificado' ? 'style="display:none;"' : ''; ?>>
            <div class="pcg-meta-editor__grid pcg-certificate-editor__grid">
                <div class="pcg-meta-editor__left">
                    <div class="pcg-tabs-card">
                        <div class="pcg-tabs-card__body">
	                            <div class="pcg-certificate-editor">
	                                <div class="pcg-certificate-editor__section">
	                                    <div class="pcg-certificate-fields">
		                                        <div class="pcg-certificate-field">
		                                            <div class="pcg-certificate-field__label"><?php _e('Title of certificate', 'politeia-learning'); ?></div>
	                                            <input type="text" id="pcg-certificate-title" class="pcg-modern-input" placeholder="<?php esc_attr_e('Certificado Finalización', 'politeia-learning'); ?>">
		                                        </div>

                                        <div class="pcg-certificate-field">
                                            <div class="pcg-certificate-field__label"><?php _e('Congratulations paragraph (max 50 words)', 'politeia-learning'); ?></div>
                                            <div class="pcg-certificate-shortcodes">
                                                <?php _e('Shortcodes:', 'politeia-learning'); ?>
                                                <code>[display_full_name]</code>,
                                                <code>[first_name]</code>,
                                                <code>[course_name]</code>,
                                                <code>[date_start]</code>,
                                                <code>[date_end]</code>
                                            </div>
                                            <textarea id="pcg-certificate-congrats" class="pcg-modern-textarea pcg-certificate-textarea"
                                                placeholder="<?php esc_attr_e('Write the certificate paragraph...', 'politeia-learning'); ?>"></textarea>
                                            <span class="pcg-word-count" id="pcg-cert-word-count">0 / 50 <?php _e('words', 'politeia-learning'); ?></span>
                                        </div>

		                                        <div class="pcg-certificate-row">
		                                            <div class="pcg-media-card pcg-media-card--certificate-logo">
		                                                <div class="pcg-media-card__meta">
		                                                    <span class="pcg-media-card__label"><?php _e('Logo (png/jpg)', 'politeia-learning'); ?></span>
		                                                </div>

                                                <div id="pcg-certificate-logo-preview" class="pcg-media-card__preview" style="display:none;">
                                                    <img src="" alt="">
                                                    <button type="button" id="pcg-remove-certificate-logo" class="pcg-media-card__remove">
                                                        <?php _e('Remove', 'politeia-learning'); ?>
                                                    </button>
                                                </div>
                                                <div class="pcg-media-card__empty" role="button" tabindex="0" data-upload="certificate_logo">
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

	                                            </div>

		                                            <div class="pcg-media-card pcg-media-card--certificate-signature">
		                                                <div class="pcg-media-card__meta">
		                                                    <span class="pcg-media-card__label"><?php _e('Signature scan (png/jpg)', 'politeia-learning'); ?></span>
		                                                </div>

                                                <div id="pcg-certificate-signature-preview" class="pcg-media-card__preview" style="display:none;">
                                                    <img src="" alt="">
                                                    <button type="button" id="pcg-remove-certificate-signature" class="pcg-media-card__remove">
                                                        <?php _e('Remove', 'politeia-learning'); ?>
                                                    </button>
                                                </div>
                                                <div class="pcg-media-card__empty" role="button" tabindex="0" data-upload="certificate_signature">
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

	                                                <div class="pcg-certificate-field pcg-certificate-field--compact">
	                                                    <div class="pcg-certificate-field__label"><?php _e('Signature label', 'politeia-learning'); ?></div>
	                                                    <input type="text" id="pcg-cert-signature-label" class="pcg-modern-input"
	                                                        placeholder="<?php esc_attr_e('Signature label...', 'politeia-learning'); ?>">
	                                                </div>
	                                            </div>
		                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <aside class="pcg-meta-editor__right">
                    <div class="pcg-sidecard">
                        <div class="pcg-sidecard__section">
                            <div class="pcg-sidecard__actions pcg-sidecard__actions-slot"></div>
                            <span class="pcg-sidecard__eyebrow"><?php _e('Precio del curso', 'politeia-learning'); ?></span>
                            <div class="pcg-sidecard__price">
                                <span class="pcg-sidecard__currency">$</span>
                                <input type="text" id="pcg-course-price-cert" placeholder="0.00" class="pcg-sidecard__price-input">
                            </div>
                            <div id="pcg-price-free-indicator-cert" class="pcg-price-free-indicator" style="display:none;">
                                <?php _e('Gratis', 'politeia-learning'); ?>
                            </div>
                        </div>
                    </div>
                    <div class="pcg-checklist-slot"></div>
                </aside>
            </div>
        </div>
        <!-- END: CERTIFICADO MODE -->

        <!-- START: META MODE -->
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

	                <aside class="pcg-meta-editor__right">
	                    <div class="pcg-sidecard">
	                        <div class="pcg-sidecard__section">
	                            <div class="pcg-sidecard__actions pcg-sidecard__actions-slot"></div>
	                            <span class="pcg-sidecard__eyebrow"><?php _e('Precio del curso', 'politeia-learning'); ?></span>
	                            <div class="pcg-sidecard__price">
	                                <span class="pcg-sidecard__currency">$</span>
	                                <input type="text" id="pcg-course-price-meta" placeholder="0.00" class="pcg-sidecard__price-input">
	                            </div>
	                            <div id="pcg-price-free-indicator-meta" class="pcg-price-free-indicator" style="display:none;">
	                                <?php _e('Gratis', 'politeia-learning'); ?>
	                            </div>
	                        </div>
	                    </div>
	                    <div class="pcg-checklist-slot"></div>
	                </aside>
            </div>
        </div>
        <!-- END: META MODE -->

</div>

<!-- MY COURSES LIST (Visible underneath) -->
<div id="pcg-my-courses-section" class="pcg-my-courses-container" <?php echo $pcg_is_editing_quiz ? 'style="display:none;"' : ''; ?>>
    <div class="pcg-section-header">
        <?php
        $pcg_my_courses_title = $pcg_my_courses_title ?? __('MIS CURSOS PUBLICADOS', 'politeia-learning');
        $pcg_create_course_button_label = $pcg_create_course_button_label ?? __('Crear un curso', 'politeia-learning');
        $pcg_list_grid_id = $pcg_list_grid_id ?? 'pcg-my-courses-grid';
        ?>
        <h3><?php echo esc_html($pcg_my_courses_title); ?></h3>
        <button type="button" id="pcg-show-creator-form" class="pcg-btn-intro-create">
            <?php echo esc_html($pcg_create_course_button_label); ?>
        </button>
    </div>

    <div id="<?php echo esc_attr($pcg_list_grid_id); ?>" class="pcg-my-courses-grid">
        <!-- Will be populated via AJAX/PHP -->
        <div class="pcg-loading-placeholder">
            <span class="dashicons dashicons-update spin"></span>
            <p><?php _e('Cargando tus cursos...', 'politeia-learning'); ?></p>
        </div>
    </div>
</div>
