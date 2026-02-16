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
    <div class="pqc-slider-viewport">
        <div class="pqc-slides-container">
            <?php foreach ($quiz_data['questions'] as $index => $question): ?>
                <div class="pqc-slide <?php echo $index === 0 ? 'active' : ''; ?>" data-index="<?php echo $index; ?>"
                    data-question-id="<?php echo esc_attr($question['id']); ?>"
                    data-pro-id="<?php echo esc_attr($question['pro_id']); ?>">

                    <div class="pqc-slide-inner">
                        <!-- UNIFIED CONTROL ROW: Question Tag + Arrows + Save -->
                        <div class="pqc-slide-controls-row">
                            <div class="pqc-question-num-tag">
                                <?php echo sprintf(__('Question %d/%d', 'politeia-quiz-creator'), $index + 1, count($quiz_data['questions'])); ?>
                            </div>

                            <div class="pqc-slide-nav-mini">
                                <button type="button" class="pqc-nav-btn pqc-prev-slide" <?php echo $index === 0 ? 'disabled' : ''; ?>>
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M15 18l-6-6 6-6" />
                                    </svg>
                                </button>
                                <button type="button" class="pqc-nav-btn pqc-next-slide" <?php echo $index === count($quiz_data['questions']) - 1 ? 'disabled' : ''; ?>>
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M9 18l6-6-6-6" />
                                    </svg>
                                </button>
                            </div>

                            <button type="button" class="pqc-delete-quiz-btn"
                                style="margin-left: auto; background: none; border: none; color: #e53e3e; cursor: pointer; display: flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">
                                <span class="dashicons dashicons-trash" style="font-size: 16px; width: 16px; height: 16px;"></span>
                                <?php _e('Delete Quiz', 'politeia-quiz-creator'); ?>
                            </button>

                        </div>

                        <div class="pqc-slide-header">
                            <div class="pqc-field pqc-field-full">
                                <label><?php _e('Question Title', 'politeia-quiz-creator'); ?></label>
                                <h3 contenteditable="true" class="pqc-editable-question-title" data-field="title">
                                    <?php echo esc_html($question['title']); ?>
                                </h3>
                            </div>
                        </div>

                        <div class="pqc-slide-body">
                            <div class="pqc-field pqc-field-full">
                                <label><?php _e('Question Text', 'politeia-quiz-creator'); ?></label>
                                <div class="pqc-editable-text-area" contenteditable="true" data-field="question_text">
                                    <?php echo $question['question_text']; ?>
                                </div>
                            </div>

                            <div class="pqc-answers-section">
                                <label><?php _e('Answers', 'politeia-quiz-creator'); ?>
                                    <small>(<?php _e('Check the box for correct answers', 'politeia-quiz-creator'); ?>)</small></label>
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
                                            <div class="pqc-answer-points-wrap">
                                                <input type="number" value="<?php echo esc_attr($answer['points']); ?>"
                                                    class="pqc-answer-points-edit" min="0"
                                                    title="<?php _e('Points', 'politeia-quiz-creator'); ?>">
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
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
