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

<div class="pqc-container pqc-editor-container" data-quiz-id="<?php echo esc_attr($quiz_data['id']); ?>">
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
        </div>

        <button type="button" class="pqc-delete-quiz-btn" data-quiz-id="<?php echo esc_attr($quiz_data['id']); ?>">
            <span class="dashicons dashicons-trash"></span>
            <?php _e('Delete Quiz', 'politeia-quiz-creator'); ?>
        </button>

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
                                <div class="pqc-field pqc-field-full">
                                    <h3 contenteditable="true" class="pqc-editable-question-title" data-field="title" data-placeholder="<?php esc_attr_e('Tema de la Pregunta', 'politeia-quiz-creator'); ?>">
                                        <?php echo esc_html($question['title']); ?>
                                    </h3>
                                </div>
                                <button type="button" class="pqc-delete-question-btn" title="<?php echo esc_attr__('Delete Question', 'politeia-quiz-creator'); ?>">
                                    <span class="dashicons dashicons-trash"></span>
                                </button>
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

    <!-- Hidden status message container -->
    <div id="pqc-edit-msg" class="pqc-status-overlay" style="display: none;"></div>
</div>
