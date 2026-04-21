<?php
/**
 * Quiz Editor Template
 * Slide-based editor for frontend users
 */

if (!defined('ABSPATH')) {
    exit;
}

$quiz_data = PQC_Quiz_Creator::get_quiz_data($quiz_id);

if (!$quiz_data) {
    echo '<div class="pqc-container"><p class="pqc-error-msg">' . __('Could not load quiz data. Please make sure the quiz ID is correct.', 'politeia-quiz-creator') . '</p></div>';
    return;
}
?>

<div class="pqc-container pqc-editor-container" data-quiz-id="<?php echo esc_attr($quiz_data['id']); ?>" data-quiz-title="<?php echo esc_attr($quiz_data['title'] ?? ''); ?>" data-quiz-settings="<?php echo esc_attr(wp_json_encode($quiz_data['settings'] ?? [])); ?>">
    <!-- UNIFIED CONTROL ROW: Question Tag + Arrows + Save (Now Static) -->
    <div class="pqc-slide-controls-row">
        <div class="pqc-question-num-tag" id="pqc-editor-counter">
            <?php echo sprintf(__('Question %d/%d', 'politeia-quiz-creator'), 1, count($quiz_data['questions'])); ?>
        </div>

        <div class="pqc-slide-nav-mini">
            <button type="button" class="pqc-nav-btn pqc-prev-slide" disabled>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M15 18l-6-6 6-6" />
                </svg>
            </button>
            <button type="button" class="pqc-nav-btn pqc-next-slide" <?php echo count($quiz_data['questions']) <= 1 ? 'disabled' : ''; ?>>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 18l6-6-6-6" />
                </svg>
            </button>
            <button type="button" class="pqc-nav-btn pqc-add-question-btn" title="<?php echo esc_attr__('Add Question', 'politeia-quiz-creator'); ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 5v14" />
                    <path d="M5 12h14" />
                </svg>
            </button>
            <button type="button" class="pqc-ai-toggle-btn" aria-expanded="false" title="<?php echo esc_attr__('AI Assisted', 'politeia-quiz-creator'); ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 2l1.6 5.2L19 9l-5.4 1.8L12 16l-1.6-5.2L5 9l5.4-1.8L12 2z" />
                    <path d="M19 13l.9 2.8L23 17l-3.1 1.2L19 21l-.9-2.8L15 17l3.1-1.2L19 13z" />
                </svg>
                <span class="pqc-ai-toggle-btn__label">AI</span>
            </button>
        </div>

        <button type="button" class="pqc-delete-quiz-btn" data-quiz-id="<?php echo esc_attr($quiz_data['id']); ?>">
            <span class="dashicons dashicons-trash"></span>
            <?php _e('Delete Quiz', 'politeia-quiz-creator'); ?>
        </button>

    </div>

    <div class="pqc-ai-panel" style="display:none;">
        <div class="pqc-ai-panel__inner">
            <div class="pqc-ai-panel__header">
                <div class="pqc-ai-panel__title"><?php _e('AI Assisted', 'politeia-quiz-creator'); ?></div>
                <div class="pqc-ai-panel__subtitle"><?php _e('Genera preguntas con un LLM y pega el JSON para importarlas al quiz.', 'politeia-quiz-creator'); ?></div>
            </div>

            <div class="pqc-settings-grid pqc-ai-panel__grid">
                <div class="pqc-field">
                    <input type="number" id="pqc-ai-num-questions" min="1" max="100" placeholder="<?php echo esc_attr__('Número de preguntas', 'politeia-quiz-creator'); ?>" />
                </div>
                <div class="pqc-field">
                    <input type="number" id="pqc-ai-answers-per-question" min="2" max="6" placeholder="<?php echo esc_attr__('Respuestas por pregunta', 'politeia-quiz-creator'); ?>" />
                </div>
                <div class="pqc-field pqc-field-full">
                    <input type="text" id="pqc-ai-keywords" placeholder="<?php echo esc_attr__('Keywords (separadas por coma)', 'politeia-quiz-creator'); ?>" />
                </div>
                <div class="pqc-field pqc-field-checkbox">
                    <label>
                        <input type="checkbox" id="pqc-ai-upload-docs" />
                        <span><?php _e('Subiré mi propio material (PDF, texto, etc.) al LLM', 'politeia-quiz-creator'); ?></span>
                    </label>
                </div>
            </div>

            <div class="pqc-prompt-action pqc-ai-panel__prompt">
                <button type="button" class="pqc-copy-prompt-btn pqc-ai-copy-prompt-btn" disabled>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2">
                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                    </svg>
                    <span class="pqc-btn-text"><?php _e('Copiar prompt para ChatGPT/Claude', 'politeia-quiz-creator'); ?></span>
                    <span class="pqc-btn-copied" style="display: none;">✓ <?php _e('Copiado', 'politeia-quiz-creator'); ?></span>
                </button>
                <p class="pqc-prompt-hint">
                    <?php _e('Pega el prompt en tu LLM, genera el JSON y luego pégalo aquí para importarlo.', 'politeia-quiz-creator'); ?>
                </p>
                <button type="button" class="pqc-ai-context-btn"><?php _e('Copiar contexto (lo que ya existe)', 'politeia-quiz-creator'); ?></button>
            </div>

            <div class="pqc-paste-area pqc-ai-panel__paste">
                <textarea id="pqc-ai-json-paste" placeholder="<?php echo esc_attr__('Pega aquí el JSON resultante…', 'politeia-quiz-creator'); ?>"></textarea>
            </div>

            <div class="pqc-field pqc-field-checkbox pqc-ai-panel__replace">
                <label>
                    <input type="checkbox" id="pqc-ai-replace-existing" />
                    <span><?php _e('Reemplazar preguntas existentes (borra lo actual)', 'politeia-quiz-creator'); ?></span>
                </label>
            </div>

            <div class="pqc-ai-panel__actions">
                <button type="button" class="pqc-btn-primary pqc-ai-import-btn" disabled><?php _e('IMPORTAR JSON', 'politeia-quiz-creator'); ?></button>
                <div class="pqc-ai-panel__note"><?php _e('Por defecto, Importar agrega nuevas preguntas al final.', 'politeia-quiz-creator'); ?></div>
            </div>

            <div class="pqc-ai-panel__status" aria-live="polite"></div>
        </div>
    </div>

    <div class="pqc-quiz-settings-panel" style="display:none;">
        <div class="pqc-quiz-settings-panel__inner">
            <div class="pqc-quiz-settings-panel__head">
                <div class="pqc-quiz-settings-panel__title"><?php _e('Quiz Settings', 'politeia-quiz-creator'); ?></div>
                <button type="button" class="pqc-quiz-settings-panel__close" aria-label="<?php echo esc_attr__('Close settings', 'politeia-quiz-creator'); ?>">×</button>
            </div>

            <div class="pqc-quiz-settings-panel__desc">
                <?php _e('Define cómo se rendirá este quiz. Estos ajustes aplican cuando los alumnos tomen la evaluación.', 'politeia-quiz-creator'); ?>
            </div>

            <div class="pqc-quiz-settings-panel__row">
                <div class="pqc-quiz-settings-panel__label"><?php _e('Preguntas por intento', 'politeia-quiz-creator'); ?></div>
                <div class="pqc-quiz-settings-panel__control">
                    <label class="pqc-quiz-settings-panel__radio">
                        <input type="radio" name="pqc_questions_mode" value="all" checked>
                        <span><?php _e('Todas', 'politeia-quiz-creator'); ?></span>
                    </label>
                    <label class="pqc-quiz-settings-panel__radio">
                        <input type="radio" name="pqc_questions_mode" value="random">
                        <span><?php _e('Aleatorias', 'politeia-quiz-creator'); ?></span>
                    </label>
                    <input type="number" class="pqc-quiz-settings-panel__num" id="pqc-questions-per-attempt" min="1" step="1" placeholder="10" disabled>
                    <span class="pqc-quiz-settings-panel__hint" id="pqc-questions-per-attempt-hint"></span>
                </div>
            </div>

            <div class="pqc-quiz-settings-panel__row">
                <div class="pqc-quiz-settings-panel__label"><?php _e('Orden de las preguntas', 'politeia-quiz-creator'); ?></div>
                <div class="pqc-quiz-settings-panel__control">
                    <label class="pqc-quiz-settings-panel__check">
                        <input type="checkbox" id="pqc-respect-question-order-editor" checked>
                        <span><?php _e('Respetar orden (si se desactiva, las preguntas se muestran aleatoriamente)', 'politeia-quiz-creator'); ?></span>
                    </label>
                </div>
            </div>

            <div class="pqc-quiz-settings-panel__actions">
                <button type="button" class="pqc-btn-primary pqc-quiz-settings-save-btn"><?php _e('GUARDAR', 'politeia-quiz-creator'); ?></button>
                <div class="pqc-quiz-settings-panel__status" aria-live="polite"></div>
            </div>
        </div>
    </div>

    <div class="pqc-slider-viewport">
        <div class="pqc-slides-container">
            <?php foreach ($quiz_data['questions'] as $index => $question): ?>
                <div class="pqc-slide <?php echo $index === 0 ? 'active' : ''; ?>" data-index="<?php echo $index; ?>"
                    data-question-id="<?php echo esc_attr($question['id']); ?>"
                    data-pro-id="<?php echo esc_attr($question['pro_id']); ?>">

                    <div class="pqc-slide-inner">

                        <div class="pqc-slide-content-card">
                            <div class="pqc-slide-header">
                                <div class="pqc-slide-header-actions">
                                    <button type="button" class="pqc-preview-question-btn"><?php _e('Preview', 'politeia-quiz-creator'); ?></button>
                                    <button type="button" class="pqc-quiz-settings-btn" title="<?php echo esc_attr__('Settings', 'politeia-quiz-creator'); ?>" aria-label="<?php echo esc_attr__('Settings', 'politeia-quiz-creator'); ?>">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <circle cx="12" cy="12" r="3"></circle>
                                            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V22a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H2a2 2 0 1 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H8a1.65 1.65 0 0 0 1-1.51V2a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V8c0 .66.39 1.26 1 1.51.32.13.68.17 1.02.09H22a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                                        </svg>
                                    </button>
                                    <button type="button" class="pqc-delete-question-btn" title="<?php echo esc_attr__('Delete Question', 'politeia-quiz-creator'); ?>">
                                        <span class="dashicons dashicons-trash"></span>
                                    </button>
                                </div>
                            </div>

                            <div class="pqc-slide-body">
                                <div class="pqc-field pqc-field-full">
                                    <div class="pqc-editable-text-area" contenteditable="true" data-field="question_text" data-placeholder="<?php esc_attr_e('Escribe la pregunta...', 'politeia-quiz-creator'); ?>">
                                        <?php echo $question['question_text']; ?>
                                    </div>
                                </div>

                                <div class="pqc-answers-section">
                                    <div class="pqc-answers-header">
                                        <label><?php _e('Respuestas', 'politeia-quiz-creator'); ?>
                                            <small>(<?php _e('Marca la casilla para las respuestas correctas', 'politeia-quiz-creator'); ?>)</small></label>
                                        <button type="button" class="pqc-editor-add-answer-btn">+ <?php _e('add answer', 'politeia-quiz-creator'); ?></button>
                                    </div>
                                    <div class="pqc-answers-editor-list">
                                        <?php foreach ($question['answers'] as $a_index => $answer): ?>
                                            <div class="pqc-answer-edit-row <?php echo $answer['correct'] ? 'is-correct' : ''; ?>"
                                                data-answer-index="<?php echo $a_index; ?>">
                                                <div class="pqc-answer-check-wrap">
                                                    <input type="checkbox" <?php checked($answer['correct'], true); ?>
                                                        class="pqc-answer-correct-check"
                                                        title="<?php _e('Mark as correct', 'politeia-quiz-creator'); ?>">
                                                </div>
                                                <div class="pqc-answer-text-wrap" contenteditable="true" data-field="answer_text">
                                                    <?php echo esc_html($answer['text']); ?>
                                                </div>
                                                <button type="button" class="pqc-remove-answer-btn" title="<?php _e('Remove answer', 'politeia-quiz-creator'); ?>">
                                                    <span class="dashicons dashicons-no-alt"></span>
                                                </button>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

</div>
