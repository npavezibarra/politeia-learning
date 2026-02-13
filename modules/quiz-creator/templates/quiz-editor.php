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
    <div class="pqc-header pqc-editor-header">
        <div class="pqc-header-top">
            <h2 contenteditable="true" class="pqc-editable-title" data-field="quiz_title"
                title="<?php _e('Click to edit quiz title', 'politeia-quiz-creator'); ?>">
                <?php echo esc_html($quiz_data['title']); ?></h2>
            <div class="pqc-editor-badge">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                </svg>
                <span><?php _e('Editor Mode', 'politeia-quiz-creator'); ?></span>
            </div>
        </div>
        <div class="pqc-description pqc-editor-meta">
            <span><strong><?php echo count($quiz_data['questions']); ?></strong>
                <?php _e('Questions found', 'politeia-quiz-creator'); ?></span>
        </div>
    </div>

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
                                <?php echo sprintf(__('QUESTION %d', 'politeia-quiz-creator'), $index + 1); ?>
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

                            <div class="pqc-slide-actions-mini">
                                <button type="button" class="pqc-save-quiz-btn pqc-submit-btn-mini">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2">
                                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                        <polyline points="7 3 7 8 15 8"></polyline>
                                    </svg>
                                    <span><?php _e('SAVE', 'politeia-quiz-creator'); ?></span>
                                </button>
                            </div>
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