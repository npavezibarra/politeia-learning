<?php
/**
 * Upload Form Template
 * Compact unified form: Settings + ChatGPT Prompt + Upload
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="pqc-container">
    <div class="pqc-header">
        <h2><?php echo esc_html($atts['title']); ?></h2>
        <p class="pqc-description">
            <?php _e('Configure your quiz settings, generate a ChatGPT prompt, and upload the questions file.', 'politeia-quiz-creator'); ?>
        </p>
    </div>

    <form id="pqc-quiz-form" class="pqc-quiz-form">

        <!-- UNIFIED QUIZ FORM -->
        <div class="pqc-section pqc-unified-section">
            <div class="pqc-section-header">
                <h3><?php _e('Quiz Configuration', 'politeia-quiz-creator'); ?></h3>
                <p><?php _e('Fill in the details below to create your quiz', 'politeia-quiz-creator'); ?></p>
            </div>

            <div class="pqc-settings-grid">
                <!-- Quiz Title (also used as Topic) -->
                <div class="pqc-field pqc-field-full">
                    <label for="pqc-quiz-title">
                        <?php _e('Quiz Title', 'politeia-quiz-creator'); ?>
                        <span class="pqc-required">*</span>
                    </label>
                    <input type="text" id="pqc-quiz-title" name="quiz_title"
                        placeholder="<?php _e('e.g., Introduction to Ancient Rome', 'politeia-quiz-creator'); ?>"
                        required />
                </div>

                <!-- Number of Questions -->
                <div class="pqc-field">
                    <label for="pqc-num-questions">
                        <?php _e('Number of Questions', 'politeia-quiz-creator'); ?>
                        <span class="pqc-required">*</span>
                    </label>
                    <input type="number" id="pqc-num-questions" name="num_questions" min="1" max="100" value="10"
                        required />
                </div>

                <!-- Time Limit -->
                <div class="pqc-field">
                    <label for="pqc-time-limit">
                        <?php _e('Time Limit (minutes)', 'politeia-quiz-creator'); ?>
                        <span class="pqc-tooltip"
                            title="<?php _e('Set to 0 for no time limit', 'politeia-quiz-creator'); ?>">?</span>
                    </label>
                    <input type="number" id="pqc-time-limit" name="time_limit" min="0" value="0" placeholder="0" />
                </div>

                <!-- Passing Percentage -->
                <div class="pqc-field">
                    <label for="pqc-passing-percentage">
                        <?php _e('Passing Percentage (%)', 'politeia-quiz-creator'); ?>
                    </label>
                    <input type="number" id="pqc-passing-percentage" name="passing_percentage" min="0" max="100"
                        value="80" />
                </div>

                <!-- Specific Subjects (Keywords) -->
                <div class="pqc-field pqc-field-full">
                    <label for="pqc-keywords">
                        <?php _e('Specific Subjects', 'politeia-quiz-creator'); ?>
                    </label>
                    <input type="text" id="pqc-keywords" name="keywords"
                        placeholder="<?php _e('e.g., early republic, economy, demography', 'politeia-quiz-creator'); ?>" />
                    <span
                        class="pqc-field-hint"><?php _e('Optional: Comma-separated topics to focus the questions', 'politeia-quiz-creator'); ?></span>
                </div>

                <!-- Random Questions -->
                <div class="pqc-field pqc-field-checkbox">
                    <label>
                        <input type="checkbox" id="pqc-random-questions" name="random_questions" value="1" />
                        <span><?php _e('Randomize Question Order', 'politeia-quiz-creator'); ?></span>
                    </label>
                </div>

                <!-- Random Answers -->
                <div class="pqc-field pqc-field-checkbox">
                    <label>
                        <input type="checkbox" id="pqc-random-answers" name="random_answers" value="1" />
                        <span><?php _e('Randomize Answer Order', 'politeia-quiz-creator'); ?></span>
                    </label>
                </div>

                <!-- Run Once -->
                <div class="pqc-field pqc-field-checkbox">
                    <label>
                        <input type="checkbox" id="pqc-run-once" name="run_once" value="1" />
                        <span><?php _e('Allow Only One Attempt', 'politeia-quiz-creator'); ?></span>
                    </label>
                </div>

                <!-- Force Solve -->
                <div class="pqc-field pqc-field-checkbox">
                    <label>
                        <input type="checkbox" id="pqc-force-solve" name="force_solve" value="1" />
                        <span><?php _e('Force Answer Before Next Question', 'politeia-quiz-creator'); ?></span>
                    </label>
                </div>

                <!-- Show Points -->
                <div class="pqc-field pqc-field-checkbox">
                    <label>
                        <input type="checkbox" id="pqc-show-points" name="show_points" value="1" />
                        <span><?php _e('Show Points to Students', 'politeia-quiz-creator'); ?></span>
                    </label>
                </div>
            </div>

            <!-- COPY PROMPT BUTTON -->
            <div class="pqc-prompt-action">
                <button type="button" class="pqc-copy-prompt-btn">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                    </svg>
                    <span class="pqc-btn-text"><?php _e('Copy ChatGPT Prompt', 'politeia-quiz-creator'); ?></span>
                    <span class="pqc-btn-copied" style="display: none;">✓
                        <?php _e('Copied!', 'politeia-quiz-creator'); ?></span>
                </button>
                <p class="pqc-prompt-hint">
                    <?php _e('Copy the prompt, paste it into ChatGPT, then upload the generated JSON file below', 'politeia-quiz-creator'); ?>
                </p>
            </div>

            <!-- FILE UPLOAD -->
            <div class="pqc-upload-compact">
                <label
                    class="pqc-upload-label-text"><?php _e('Upload Questions File', 'politeia-quiz-creator'); ?></label>

                <div class="pqc-upload-area-compact">
                    <div class="pqc-upload-icon-small">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="17 8 12 3 7 8"></polyline>
                            <line x1="12" y1="3" x2="12" y2="15"></line>
                        </svg>
                    </div>
                    <div class="pqc-upload-text-compact">
                        <span
                            class="pqc-upload-label-compact"><?php _e('Click to upload or drag and drop', 'politeia-quiz-creator'); ?></span>
                        <span
                            class="pqc-upload-hint-compact"><?php _e('JSON, CSV, XML, or TXT (max 5MB)', 'politeia-quiz-creator'); ?></span>
                    </div>
                    <input type="file" id="pqc-file-input" name="quiz_file" accept=".json,.csv,.xml,.txt" />
                </div>

                <div class="pqc-file-info" style="display: none;">
                    <div class="pqc-file-details">
                        <span class="pqc-file-name"></span>
                        <span class="pqc-file-size"></span>
                    </div>
                    <button type="button" class="pqc-remove-file">
                        <?php _e('Remove', 'politeia-quiz-creator'); ?>
                    </button>
                </div>
            </div>

            <!-- SUBMIT BUTTON -->
            <div class="pqc-submit-action">
                <button type="submit" class="pqc-submit-btn" disabled>
                    <span class="pqc-btn-text"><?php _e('Create Quiz', 'politeia-quiz-creator'); ?></span>
                    <span class="pqc-btn-loading" style="display: none;">
                        <span class="pqc-spinner"></span>
                        <?php _e('Processing...', 'politeia-quiz-creator'); ?>
                    </span>
                </button>
            </div>
        </div>
    </form>

    <!-- RESULT MESSAGE -->
    <div class="pqc-result" style="display: none;"></div>
</div>