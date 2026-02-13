<?php
/**
 * Upload Form Template - Wizard Version
 * Multi-slide form for quiz creation
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="pqc-container pqc-wizard-container">
    <div class="pqc-header">
        <h2><?php echo esc_html($atts['title']); ?></h2>
        <div class="pqc-wizard-progress">
            <div class="pqc-progress-step active" data-step="1">
                <span>1</span><label><?php _e('Config', 'politeia-quiz-creator'); ?></label></div>
            <div class="pqc-progress-line"></div>
            <div class="pqc-progress-step" data-step="2">
                <span>2</span><label><?php _e('Questions', 'politeia-quiz-creator'); ?></label></div>
            <div class="pqc-progress-line"></div>
            <div class="pqc-progress-step" data-step="3">
                <span>3</span><label><?php _e('Settings', 'politeia-quiz-creator'); ?></label></div>
        </div>
    </div>

    <form id="pqc-quiz-form" class="pqc-quiz-form">
        <div class="pqc-wizard-viewport">
            <!-- SLIDE 1: BASIC CONFIG -->
            <div class="pqc-wizard-slide active" data-slide="1">
                <div class="pqc-section-header">
                    <h3><?php _e('Step 1: Quiz Goals', 'politeia-quiz-creator'); ?></h3>
                </div>
                <div class="pqc-settings-grid">
                    <div class="pqc-field pqc-field-full">
                        <label for="pqc-quiz-title"><?php _e('Quiz Title', 'politeia-quiz-creator'); ?> <span
                                class="pqc-required">*</span></label>
                        <input type="text" id="pqc-quiz-title" name="quiz_title"
                            placeholder="<?php _e('e.g., Introduction to Ancient Rome', 'politeia-quiz-creator'); ?>"
                            required />
                    </div>
                    <div class="pqc-field pqc-field-full">
                        <label for="pqc-keywords"><?php _e('Specific Subjects', 'politeia-quiz-creator'); ?></label>
                        <input type="text" id="pqc-keywords" name="keywords"
                            placeholder="<?php _e('e.g., early republic, economy, demography', 'politeia-quiz-creator'); ?>" />
                    </div>
                    <div class="pqc-field">
                        <label for="pqc-num-questions"><?php _e('Number of Questions', 'politeia-quiz-creator'); ?>
                            <span class="pqc-required">*</span></label>
                        <input type="number" id="pqc-num-questions" name="num_questions" min="1" max="100" value="10"
                            required />
                    </div>
                    <div class="pqc-field">
                        <label for="pqc-time-limit"><?php _e('Time Limit (min)', 'politeia-quiz-creator'); ?></label>
                        <input type="number" id="pqc-time-limit" name="time_limit" min="0" value="0" />
                    </div>
                    <div class="pqc-field">
                        <label for="pqc-passing-percentage"><?php _e('Pass %', 'politeia-quiz-creator'); ?></label>
                        <input type="number" id="pqc-passing-percentage" name="passing_percentage" min="0" max="100"
                            value="80" />
                    </div>
                </div>
                <div class="pqc-wizard-footer">
                    <button type="button" class="pqc-wizard-next pqc-btn-primary"
                        data-next="2"><?php _e('Next: Questions', 'politeia-quiz-creator'); ?></button>
                </div>
            </div>

            <!-- SLIDE 2: ChatGPT & INPUT -->
            <div class="pqc-wizard-slide" data-slide="2">
                <div class="pqc-section-header">
                    <h3><?php _e('Step 2: Get Questions', 'politeia-quiz-creator'); ?></h3>
                </div>

                <div class="pqc-prompt-action">
                    <button type="button" class="pqc-copy-prompt-btn">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                        </svg>
                        <span class="pqc-btn-text"><?php _e('Copy ChatGPT Prompt', 'politeia-quiz-creator'); ?></span>
                        <span class="pqc-btn-copied" style="display: none;">✓
                            <?php _e('Copied!', 'politeia-quiz-creator'); ?></span>
                    </button>
                    <p class="pqc-prompt-hint">
                        <?php _e('Paste the prompt into ChatGPT and then return with the JSON result.', 'politeia-quiz-creator'); ?>
                    </p>
                </div>

                <div class="pqc-input-choice-grid">
                    <div class="pqc-upload-compact">
                        <label
                            class="pqc-upload-label-text"><?php _e('Option A: Upload File', 'politeia-quiz-creator'); ?></label>
                        <div class="pqc-upload-area-compact">
                            <div class="pqc-upload-icon-small"><svg width="24" height="24" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                    <polyline points="17 8 12 3 7 8"></polyline>
                                    <line x1="12" y1="3" x2="12" y2="15"></line>
                                </svg></div>
                            <div class="pqc-upload-text-compact">
                                <span><?php _e('Drop file here', 'politeia-quiz-creator'); ?></span></div>
                            <input type="file" id="pqc-file-input" name="quiz_file" accept=".json,.csv,.xml,.txt" />
                        </div>
                        <div class="pqc-file-info" style="display: none;">
                            <span class="pqc-file-name"></span>
                            <button type="button" class="pqc-remove-file">×</button>
                        </div>
                    </div>

                    <div class="pqc-paste-area">
                        <label
                            class="pqc-upload-label-text"><?php _e('Option B: Paste JSON', 'politeia-quiz-creator'); ?></label>
                        <textarea id="pqc-json-paste"
                            placeholder='[{"title":"...","question_text":"...","answers":[...]}]'></textarea>
                    </div>
                </div>

                <div class="pqc-wizard-footer">
                    <button type="button" class="pqc-wizard-prev"
                        data-prev="1"><?php _e('Back', 'politeia-quiz-creator'); ?></button>
                    <button type="button" class="pqc-wizard-next pqc-btn-primary"
                        data-next="3"><?php _e('Next: Settings', 'politeia-quiz-creator'); ?></button>
                </div>
            </div>

            <!-- SLIDE 3: ADVANCED SETTINGS -->
            <div class="pqc-wizard-slide" data-slide="3">
                <div class="pqc-section-header">
                    <h3><?php _e('Step 3: Behavior', 'politeia-quiz-creator'); ?></h3>
                </div>
                <div class="pqc-settings-grid">
                    <div class="pqc-field pqc-field-checkbox">
                        <label><input type="checkbox" id="pqc-random-questions"
                                name="random_questions" /><span><?php _e('Randomize Question Order', 'politeia-quiz-creator'); ?></span></label>
                    </div>
                    <div class="pqc-field pqc-field-checkbox">
                        <label><input type="checkbox" id="pqc-random-answers"
                                name="random_answers" /><span><?php _e('Randomize Answer Order', 'politeia-quiz-creator'); ?></span></label>
                    </div>
                    <div class="pqc-field pqc-field-checkbox">
                        <label><input type="checkbox" id="pqc-run-once"
                                name="run_once" /><span><?php _e('Allow Only One Attempt', 'politeia-quiz-creator'); ?></span></label>
                    </div>
                    <div class="pqc-field pqc-field-checkbox">
                        <label><input type="checkbox" id="pqc-force-solve"
                                name="force_solve" /><span><?php _e('Force Answer Before Next', 'politeia-quiz-creator'); ?></span></label>
                    </div>
                    <div class="pqc-field pqc-field-checkbox">
                        <label><input type="checkbox" id="pqc-show-points"
                                name="show_points" /><span><?php _e('Show Points to Students', 'politeia-quiz-creator'); ?></span></label>
                    </div>
                </div>
                <div class="pqc-wizard-footer">
                    <button type="button" class="pqc-wizard-prev"
                        data-prev="2"><?php _e('Back', 'politeia-quiz-creator'); ?></button>
                    <button type="submit" class="pqc-submit-btn pqc-btn-primary">
                        <span class="pqc-btn-text"><?php _e('CREATE QUIZ', 'politeia-quiz-creator'); ?></span>
                        <span class="pqc-btn-loading" style="display: none;"><span class="pqc-spinner"></span>
                            <?php _e('Processing...', 'politeia-quiz-creator'); ?></span>
                    </button>
                </div>
            </div>
        </div>
    </form>

    <div class="pqc-result" style="display: none;"></div>
</div>