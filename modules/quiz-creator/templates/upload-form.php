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
        <div class="pqc-header-top"
            style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
            <h2><?php _e('Quiz Creator', 'politeia-quiz-creator'); ?></h2>
            <?php if ($quiz_id): ?>
                <button type="button" class="pqc-delete-quiz-btn" data-quiz-id="<?php echo esc_attr($quiz_id); ?>"
                    style="background: none; border: none; color: #e53e3e; cursor: pointer; display: flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">
                    <span class="dashicons dashicons-trash" style="font-size: 16px; width: 16px; height: 16px;"></span>
                    <?php _e('Delete Existing Quiz', 'politeia-quiz-creator'); ?>
                </button>
            <?php endif; ?>
        </div>
        <p class="pqc-description">
            <?php _e('Set up your quiz parameters and upload your content to generate the evaluation.', 'politeia-quiz-creator'); ?>
        </p>

    </div>

    <form id="pqc-quiz-form" class="pqc-quiz-form">
        <!-- Hidden method tracker -->
        <input type="hidden" id="pqc-creation-method" name="creation_method" value="llm">

        <div class="pqc-wizard-viewport">
            <!-- SLIDE 1: BASIC CONFIG -->
            <div class="pqc-wizard-slide active" data-slide="1">
                <div class="pqc-section-header">
                    <h3><?php _e('Step 1: Quiz Goals', 'politeia-quiz-creator'); ?></h3>
                </div>
                <div class="pqc-settings-grid">
                    <input type="hidden" id="pqc-course-id" name="course_id"
                        value="<?php echo esc_attr($course_id); ?>">
                    <input type="hidden" id="pqc-quiz-title" name="quiz_title"
                        value="<?php echo esc_attr($default_quiz_title); ?>" />
                    <div class="pqc-field">
                        <input type="number" id="pqc-num-questions" name="num_questions" min="1" max="100"
                            placeholder="<?php _e('Number of Questions', 'politeia-quiz-creator'); ?>" required />
                    </div>
                    <div class="pqc-field">
                        <input type="number" id="pqc-answers-per-question" name="answers_per_question" min="2" max="6"
                            placeholder="<?php _e('Answers per Question', 'politeia-quiz-creator'); ?>" />
                    </div>
                </div>
                <div class="pqc-wizard-footer">
                    <button type="button" class="pqc-wizard-next pqc-btn-primary"
                        data-next="2"><?php _e('Next: Method', 'politeia-quiz-creator'); ?></button>
                </div>
            </div>

            <!-- SLIDE 2: METHOD CHOICE -->
            <div class="pqc-wizard-slide" data-slide="2">
                <div class="pqc-section-header">
                    <h3><?php _e('Step 2: Creation Method', 'politeia-quiz-creator'); ?></h3>
                    <p><?php _e('How do you want to create your questions?', 'politeia-quiz-creator'); ?></p>
                </div>

                <div class="pqc-method-choice-grid">
                    <div class="pqc-method-card active" data-method="llm">
                        <div class="pqc-method-icon">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M12 2a10 10 0 1 0 10 10H12V2z"></path>
                                <path d="M12 12L2.5 12"></path>
                                <path d="M12 12l9.17 4.83"></path>
                            </svg>
                        </div>
                        <h4><?php _e('AI Assisted', 'politeia-quiz-creator'); ?></h4>
                        <p><?php _e('Use ChatGPT/LLM to generate questions from keywords or documents.', 'politeia-quiz-creator'); ?>
                        </p>
                    </div>

                    <div class="pqc-method-card" data-method="manual">
                        <div class="pqc-method-icon">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                            </svg>
                        </div>
                        <h4><?php _e('Manual Creation', 'politeia-quiz-creator'); ?></h4>
                        <p><?php _e('Write your own questions and answers one by one.', 'politeia-quiz-creator'); ?></p>
                    </div>
                </div>

                <div class="pqc-wizard-footer">
                    <button type="button" class="pqc-wizard-prev"
                        data-prev="1"><?php _e('Back', 'politeia-quiz-creator'); ?></button>
                    <button type="button" class="pqc-wizard-next pqc-btn-primary"
                        data-next="3"><?php _e('Next: Questions', 'politeia-quiz-creator'); ?></button>
                </div>
            </div>

            <!-- SLIDE 3: QUESTIONS (PATH DEPENDENT) -->
            <div class="pqc-wizard-slide" data-slide="3">

                <!-- LLM PATH VIEW -->
                <div id="pqc-path-llm" class="pqc-method-path">
                    <div class="pqc-section-header">
                        <h3><?php _e('Step 3: Generate with AI', 'politeia-quiz-creator'); ?></h3>
                    </div>

                    <div class="pqc-settings-grid" style="margin-bottom: 20px;">
                        <div class="pqc-field pqc-field-full">
                            <input type="text" id="pqc-keywords" name="keywords"
                                placeholder="<?php _e('Keywords (e.g., economy, demography, science)', 'politeia-quiz-creator'); ?>" />
                        </div>
                        <div class="pqc-field pqc-field-checkbox">
                            <label>
                                <input type="checkbox" id="pqc-upload-docs-llm" name="upload_docs_llm" />
                                <span><?php _e('I will upload my own documents to the LLM (PDF, Text, etc.)', 'politeia-quiz-creator'); ?></span>
                            </label>
                        </div>
                    </div>

                    <div class="pqc-prompt-action">
                        <button type="button" class="pqc-copy-prompt-btn">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                            </svg>
                            <span
                                class="pqc-btn-text"><?php _e('Copy ChatGPT Prompt', 'politeia-quiz-creator'); ?></span>
                            <span class="pqc-btn-copied" style="display: none;">✓
                                <?php _e('Copied!', 'politeia-quiz-creator'); ?></span>
                        </button>
                        <p class="pqc-prompt-hint">
                            <?php _e('Paste the prompt into ChatGPT and then return with the JSON result.', 'politeia-quiz-creator'); ?>
                        </p>
                    </div>

                    <div class="pqc-input-choice-grid">
                        <div class="pqc-upload-compact">
                            <div class="pqc-upload-area-compact">
                                <div class="pqc-upload-icon-small"><svg width="24" height="24" viewBox="0 0 24 24"
                                        fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                        <polyline points="17 8 12 3 7 8"></polyline>
                                        <line x1="12" y1="3" x2="12" y2="15"></line>
                                    </svg></div>
                                <div class="pqc-upload-text-compact">
                                    <span><?php _e('Drop JSON file here', 'politeia-quiz-creator'); ?></span>
                                </div>
                                <input type="file" id="pqc-file-input" name="quiz_file" accept=".json" />
                            </div>
                            <div class="pqc-file-info" style="display: none;">
                                <span class="pqc-file-name"></span>
                                <button type="button" class="pqc-remove-file">×</button>
                            </div>
                        </div>

                        <div class="pqc-paste-area">
                            <textarea id="pqc-json-paste"
                                placeholder="<?php _e('Paste JSON result here...', 'politeia-quiz-creator'); ?>"></textarea>
                        </div>
                    </div>
                </div>

                <!-- MANUAL PATH VIEW -->
                <div id="pqc-path-manual" class="pqc-method-path" style="display: none;">
                    <div class="pqc-section-header pqc-manual-header-row">
                        <div class="pqc-header-title-nav">
                            <h3><?php _e('Step 3: Write Questions', 'politeia-quiz-creator'); ?></h3>
                            <div class="pqc-manual-nav">
                                <button type="button" class="pqc-manual-prev-btn pqc-nav-btn" disabled>
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2.5">
                                        <path d="M15 18l-6-6 6-6" />
                                    </svg>
                                </button>
                                <span class="pqc-manual-counter">1 / 10</span>
                                <button type="button" class="pqc-manual-next-btn pqc-nav-btn">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2.5">
                                        <path d="M9 18l6-6-6-6" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <p><?php _e('Complete all slides with your questions and answers.', 'politeia-quiz-creator'); ?>
                        </p>
                    </div>

                    <div class="pqc-manual-questions-container">
                        <!-- Questions will be dynamically injected here in manual mode -->
                        <div id="pqc-manual-slider-viewport" class="pqc-slider-viewport">
                            <div id="pqc-manual-slides-wrap" class="pqc-slides-container">
                                <!-- Slides injected by JS -->
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pqc-wizard-footer">
                    <button type="button" class="pqc-wizard-prev"
                        data-prev="2"><?php _e('Back', 'politeia-quiz-creator'); ?></button>
                    <button type="button" class="pqc-wizard-next pqc-btn-primary"
                        data-next="4"><?php _e('Next: Behavior', 'politeia-quiz-creator'); ?></button>
                </div>
            </div>

            <!-- SLIDE 4: BEHAVIOR/SETTINGS -->
            <div class="pqc-wizard-slide" data-slide="4">
                <div class="pqc-section-header">
                    <h3><?php _e('Step 4: Behavior', 'politeia-quiz-creator'); ?></h3>
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
                        data-prev="3"><?php _e('Back', 'politeia-quiz-creator'); ?></button>
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
