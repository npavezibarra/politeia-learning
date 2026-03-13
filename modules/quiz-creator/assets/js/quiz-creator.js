/**
 * Politeia Quiz Creator - JavaScript
 * Compact unified form with Wizard navigation and Slide Editor
 */

(function ($) {
    'use strict';

    let selectedFile = null;

    $(document).ready(function () {
        syncWizardUiInputs();
        initWizardNavigation();
        initMethodSwitch();
        initUploadArea();
        initFileInput();
        initFormSubmit();
        initFormValidation();
        initCopyPrompt();
        initManualModeControls();

        // Initialize Editor if on editor page
        if ($('.pqc-editor-container').length) {
            initQuizEditor();
        }

        // External refresh trigger
        $(document).on('pqc_refresh', function (e, data) {
            if (data && data.courseId) {
                refreshQuizModule(data.courseId);
            }
        });

        // External save trigger
        $(document).on('pqc_save', function () {
            if ($('.pqc-editor-container').length) {
                saveQuizChanges();
            }
        });
    });

    function pqcGoToSlide(slideNum) {
        const n = Number(slideNum) || 0;
        if (!n) return;
        $('.pqc-wizard-slide').removeClass('active');
        $(`.pqc-wizard-slide[data-slide="${n}"]`).addClass('active');

        $('.pqc-progress-step').removeClass('active');
        $(`.pqc-progress-step[data-step="${n}"]`).addClass('active');
    }

    /**
     * Wizard Navigation logic
     */
    function initWizardNavigation() {
        // NEXT button
        $(document).on('click', '.pqc-wizard-next', function () {
            const nextSlide = $(this).data('next');
            pqcGoToSlide(nextSlide);
        });

        // PREV button
        $(document).on('click', '.pqc-wizard-prev', function () {
            const current = Number($('.pqc-wizard-slide.active').data('slide') || 0);
            const method = $('#pqc-creation-method').val();
            const prevSlide = Number($(this).data('prev') || 0);

            if (current === 4) {
                // Route back to the correct step.
                pqcGoToSlide(method === 'manual' ? 3 : 5);
                return;
            }

            pqcGoToSlide(prevSlide);
        });
    }

    /**
     * Creation Method Switch (AI vs Manual)
     */
    function initMethodSwitch() {
        $(document).on('click', '.pqc-method-card', function () {
            const method = $(this).data('method');
            $('.pqc-method-card').removeClass('active');
            $(this).addClass('active');
            $('#pqc-creation-method').val(method);

            // Set path-specific step validation if needed
            validateStep3();

            // UX: treat cards like buttons (advance from method step)
            const activeSlide = Number($('.pqc-wizard-slide.active').data('slide') || 0);
            if (activeSlide === 1) {
                if (method === 'manual') {
                    renderManualQuestions();
                    pqcGoToSlide(3);
                } else {
                    pqcGoToSlide(2);
                }
            }
        });

        // Keyboard support
        $(document).on('keydown', '.pqc-method-card', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                $(this).trigger('click');
            }
        });
    }

    /**
     * Manual Mode UI Generation
     */
    function renderManualQuestions() {
        const $slidesWrap = $('#pqc-manual-slides-wrap');
        if (!$slidesWrap.length) {
            return;
        }

        // If already rendered, do nothing.
        if ($slidesWrap.find('.pqc-manual-slide').length) {
            validateStep3();
            return;
        }

        const answersPerQuestion = 4;

        $slidesWrap.empty();

        $slidesWrap.append(buildManualSlideHtml(0, answersPerQuestion));
        $slidesWrap.find('.pqc-manual-slide').first().addClass('active');
        updateManualCounter(0, 1);
        updateManualRemoveAnswerState($slidesWrap.find('.pqc-manual-slide').first());
        validateStep3();
    }

    function syncWizardUiInputs() {
        // Sync UI inputs to hidden fields (if present).
        const syncToHidden = (uiSel, hiddenSel) => {
            const $ui = $(uiSel);
            const $hidden = $(hiddenSel);
            if (!$ui.length || !$hidden.length) return;
            if ($ui.val() === '' || $ui.val() == null) {
                $ui.val($hidden.val());
            }
            $(document).on('input change', uiSel, function () {
                $hidden.val($(this).val());
            });
        };

        syncToHidden('#pqc-num-questions-ui', '#pqc-num-questions');
        syncToHidden('#pqc-answers-per-question-ui', '#pqc-answers-per-question');
    }
    function initManualModeControls() {
        ensureManualAddButton();

        $(document).on('click', '.pqc-manual-next-btn', function () {
            moveManualSlide(1);
        });

        $(document).on('click', '.pqc-manual-prev-btn', function () {
            moveManualSlide(-1);
        });

        $(document).on('click', '.pqc-manual-add-btn', function () {
            const $slidesWrap = $('#pqc-manual-slides-wrap');
            if (!$slidesWrap.length) return;
            const currentIndex = Number($('.pqc-manual-slide.active').data('manual-index') || 0);
            addManualSlideAfter(currentIndex);
        });

        $(document).on('click', '.pqc-manual-add-answer-btn', function () {
            const $slide = $(this).closest('.pqc-manual-slide');
            if (!$slide.length) return;
            appendManualAnswerRow($slide);
            updateManualRemoveAnswerState($slide);
            validateStep3();
        });

        $(document).on('click', '.pqc-manual-remove-answer-btn', function () {
            const $slide = $(this).closest('.pqc-manual-slide');
            const $row = $(this).closest('.pqc-manual-answer-row');
            if (!$slide.length || !$row.length) return;

            const $rows = $slide.find('.pqc-manual-answer-row');
            if ($rows.length <= 2) return;

            const wasChecked = $row.find('.pqc-manual-correct-radio').is(':checked');
            $row.remove();

            // Ensure one correct answer remains checked.
            if (wasChecked) {
                const $first = $slide.find('.pqc-manual-answer-row').first();
                if ($first.length) {
                    $first.find('.pqc-manual-correct-radio').prop('checked', true);
                    $first.addClass('correct');
                }
            }

            renumberManualAnswers($slide);
            updateManualRemoveAnswerState($slide);
            validateStep3();
        });

        $(document).on('change', '.pqc-manual-correct-radio', function () {
            $(this).closest('.pqc-manual-answers-list').find('.pqc-manual-answer-row').removeClass('correct');
            if ($(this).is(':checked')) {
                $(this).closest('.pqc-manual-answer-row').addClass('correct');
            }
        });
    }

    function ensureManualAddButton() {
        if ($('.pqc-manual-add-btn').length) return;

        const $nav = $('.pqc-manual-nav').first();
        if (!$nav.length) return;

        const s = (window.pqcData && window.pqcData.strings) ? window.pqcData.strings : {};
        const addQuestionText = s.addQuestion || 'Add question';

        const btnHtml = `
            <button type="button" class="pqc-manual-add-btn" title="${addQuestionText}">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 5v14"></path>
                    <path d="M5 12h14"></path>
                </svg>
                <span>${addQuestionText}</span>
            </button>
        `;

        // Ensure layout wrapper exists (older cached templates)
        if (!$nav.closest('.pqc-manual-actions').length) {
            $nav.wrap('<div class="pqc-manual-actions"></div>');
        }
        $nav.closest('.pqc-manual-actions').prepend(btnHtml);
    }

    function addManualSlideAfter(afterIndex) {
        const $slidesWrap = $('#pqc-manual-slides-wrap');
        if (!$slidesWrap.length) return;

        const answersPerQuestion = 4;
        const $slides = $slidesWrap.find('.pqc-manual-slide');
        const total = $slides.length;

        let insertPos = Number(afterIndex) + 1;
        if (isNaN(insertPos) || insertPos < 0) insertPos = 0;
        if (insertPos > total) insertPos = total;

        const newIndex = insertPos;
        const html = buildManualSlideHtml(newIndex, answersPerQuestion);

        if (insertPos >= total) {
            $slidesWrap.append(html);
        } else {
            $slides.eq(insertPos).before(html);
        }

        renumberManualSlides();

        $slidesWrap.find('.pqc-manual-slide').removeClass('active');
        $slidesWrap.find('.pqc-manual-slide').eq(newIndex).addClass('active');
        updateManualCounter(newIndex, $slidesWrap.find('.pqc-manual-slide').length);
        updateManualRemoveAnswerState($slidesWrap.find('.pqc-manual-slide').eq(newIndex));
        validateStep3();
    }

    function buildManualSlideHtml(index, answersPerQuestion) {
        const i = Number(index) || 0;
        const n = Number(answersPerQuestion) || 4;

        const s = (window.pqcData && window.pqcData.strings) ? window.pqcData.strings : {};
        const labelQuestionTitle = s.questionTitle || 'Question Title';
        const labelQuestionText = s.questionText || 'Question Text';
        const labelAnswers = s.answers || 'Answers';
        const labelCorrectHint = s.checkCorrect || 'Check the box for correct answers';
        const placeholderInternalName = s.internalNamePlaceholder || 'Internal name (e.g. Question 1)';
        const placeholderQuestion = s.questionPlaceholder || 'Write the actual question here...';
        const labelAnswer = s.answerLabel || 'Answer';
        const addAnswerText = s.addAnswer || 'Add Answer';

        let html = `<div class="pqc-slide pqc-manual-slide" data-manual-index="${i}">`;
        html += `<div class="pqc-manual-field">
                    <input type="text" class="pqc-manual-q-title" placeholder="Tema de la Pregunta" value="" />
                </div>
                <div class="pqc-manual-field">
                    <input type="text" class="pqc-manual-q-text" placeholder="Escribe la pregunta..." required />
                </div>`;

        html += `<div class="pqc-manual-field">
                    <div class="pqc-manual-answers-header">
                        <label>${labelAnswers} (${labelCorrectHint})</label>
                        <button type="button" class="pqc-manual-add-answer-btn">+ ${addAnswerText}</button>
                    </div>
                    <div class="pqc-manual-answers-list">`;

        for (let j = 0; j < n; j++) {
            html += `<div class="pqc-manual-answer-row ${j === 0 ? 'correct' : ''}">
                        <input type="radio" name="manual_correct_${i}" class="pqc-manual-correct-radio" ${j === 0 ? 'checked' : ''} />
                        <input type="text" class="pqc-manual-answer-text" placeholder="${labelAnswer} ${j + 1}" required />
                        <button type="button" class="pqc-manual-remove-answer-btn" aria-label="${(s.removeAnswer || 'Remove answer')}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <polyline points="3 6 5 6 21 6"></polyline>
                                <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path>
                                <path d="M10 11v6"></path>
                                <path d="M14 11v6"></path>
                                <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"></path>
                            </svg>
                        </button>
                    </div>`;
        }

        html += `</div></div></div>`;
        return html;
    }

    function appendManualAnswerRow($slide) {
        const $list = $slide.find('.pqc-manual-answers-list').first();
        if (!$list.length) return;

        const s = (window.pqcData && window.pqcData.strings) ? window.pqcData.strings : {};
        const labelAnswer = s.answerLabel || 'Answer';
        const removeAnswerText = s.removeAnswer || 'Remove answer';

        const answerNumber = $list.find('.pqc-manual-answer-row').length + 1;
        const questionIndex = Number($slide.data('manual-index') || 0);

        const rowHtml = `
            <div class="pqc-manual-answer-row">
                <input type="radio" name="manual_correct_${questionIndex}" class="pqc-manual-correct-radio" />
                <input type="text" class="pqc-manual-answer-text" placeholder="${labelAnswer} ${answerNumber}" required />
                <button type="button" class="pqc-manual-remove-answer-btn" aria-label="${removeAnswerText}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path>
                        <path d="M10 11v6"></path>
                        <path d="M14 11v6"></path>
                        <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"></path>
                    </svg>
                </button>
            </div>
        `;
        $list.append(rowHtml);
    }

    function renumberManualAnswers($slide) {
        const $rows = $slide.find('.pqc-manual-answer-row');
        const s = (window.pqcData && window.pqcData.strings) ? window.pqcData.strings : {};
        const labelAnswer = s.answerLabel || 'Answer';

        $rows.each(function (idx) {
            const $row = $(this);
            const $input = $row.find('.pqc-manual-answer-text').first();
            if ($input.length) {
                $input.attr('placeholder', `${labelAnswer} ${idx + 1}`);
            }
        });
    }

    function updateManualRemoveAnswerState($slide) {
        const $rows = $slide.find('.pqc-manual-answer-row');
        const disable = $rows.length <= 2;
        $rows.find('.pqc-manual-remove-answer-btn').prop('disabled', disable);
    }

    function renumberManualSlides() {
        const $slides = $('.pqc-manual-slide');
        const s = (window.pqcData && window.pqcData.strings) ? window.pqcData.strings : {};
        const labelQuestionTitle = s.questionTitle || 'Question Title';

        $slides.each(function (idx) {
            const $slide = $(this);
            $slide.attr('data-manual-index', idx);

            const $fields = $slide.find('.pqc-manual-field');

            // Keep radio group unique per question by index
            $slide.find('.pqc-manual-correct-radio').attr('name', `manual_correct_${idx}`);

            // Renumber answer placeholders per slide.
            renumberManualAnswers($slide);
            updateManualRemoveAnswerState($slide);
        });
    }

    function moveManualSlide(delta) {
        const $slides = $('.pqc-manual-slide');
        const numSlides = $slides.length;
        let currentIndex = $('.pqc-manual-slide.active').data('manual-index');
        let newIndex = currentIndex + delta;

        if (newIndex >= 0 && newIndex < numSlides) {
            $slides.removeClass('active');
            $slides.eq(newIndex).addClass('active');
            updateManualCounter(newIndex, numSlides);
        }
    }

    function updateManualCounter(index, total) {
        $('.pqc-manual-counter').text(`${index + 1} / ${total}`);
        $('.pqc-manual-prev-btn').prop('disabled', index === 0);
        $('.pqc-manual-next-btn').prop('disabled', index === total - 1);
    }

    /**
     * Initialize Quiz Editor (Slider + Inline Editing)
     */
    function initQuizEditor() {
        // Reset slider on init
        const $viewport = $('.pqc-slides-container');
        if ($viewport.length) {
            $viewport.css('transform', 'translateX(0)');
            $('.pqc-editor-container').data('current-slide', 0);
            updateNavState(0, $('.pqc-slide').length);
        }
    }

    function goToEditorSlide(index) {
        const $container = $('.pqc-editor-container');
        const $viewport = $('.pqc-slides-container');
        const $slides = $('.pqc-slide');
        const totalSlides = $slides.length;
        if (!$container.length || !$viewport.length || !totalSlides) return;

        let targetIndex = parseInt(index, 10);
        if (isNaN(targetIndex)) targetIndex = 0;
        if (targetIndex < 0) targetIndex = 0;
        if (targetIndex > totalSlides - 1) targetIndex = totalSlides - 1;

        $container.data('current-slide', targetIndex);
        const offset = -(targetIndex * 100);
        $viewport.css('transform', `translateX(${offset}%)`);
        updateNavState(targetIndex, totalSlides);
    }

    // Global Slider Navigation
    $(document).on('click', '.pqc-next-slide', function () {
        const $container = $('.pqc-editor-container');
        const $viewport = $('.pqc-slides-container');
        const $slides = $('.pqc-slide');
        const totalSlides = $slides.length;
        if (!totalSlides) return;

        let currentSlide = $container.data('current-slide') || 0;

        if (currentSlide < totalSlides - 1) {
            currentSlide++;
            $container.data('current-slide', currentSlide);
            const offset = -(currentSlide * 100);
            $viewport.css('transform', `translateX(${offset}%)`);
            updateNavState(currentSlide, totalSlides);
        }
    });

    $(document).on('click', '.pqc-prev-slide', function () {
        const $container = $('.pqc-editor-container');
        const $viewport = $('.pqc-slides-container');
        const $slides = $('.pqc-slide');
        const totalSlides = $slides.length;
        if (!totalSlides) return;

        let currentSlide = $container.data('current-slide') || 0;

        if (currentSlide > 0) {
            currentSlide--;
            $container.data('current-slide', currentSlide);
            const offset = -(currentSlide * 100);
            $viewport.css('transform', `translateX(${offset}%)`);
            updateNavState(currentSlide, totalSlides);
        }
    });

    function updateNavState(current, total) {
        $('.pqc-prev-slide').prop('disabled', current === 0);
        $('.pqc-next-slide').prop('disabled', current === total - 1);

        const $counter = $('#pqc-editor-counter');
        if ($counter.length) {
            const oldText = $counter.text();
            // Match "Pregunta 1/10" or "Question 1/10" or any similar pattern with numbers
            const newText = oldText.replace(/(\d+)\s*\/\s*(\d+)/, (match, p1, p2) => {
                return `${current + 1} / ${total}`;
            });
            $counter.text(newText);
        }
    }

    $(document).on('click', '.pqc-save-quiz-btn', function () {
        saveQuizChanges();
    });

    $(document).on('click', '.pqc-add-question-btn', function () {
        const $container = $('.pqc-editor-container');
        const quizId = $container.data('quiz-id');
        if (!quizId) return;

        const currentSlide = $container.data('current-slide') || 0;
        const goToIndexAfterAdd = currentSlide + 1; // inserted right after current

        const $btn = $(this);
        if ($btn.prop('disabled')) return;
        $btn.prop('disabled', true).addClass('is-loading');

        $.ajax({
            url: pqcData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pqc_add_question',
                nonce: pqcData.nonce,
                quiz_id: quizId,
                insert_after: currentSlide
            },
            success: function (response) {
                if (response && response.success) {
                    refreshQuizEditor(quizId, goToIndexAfterAdd);
                } else {
                    alert((response && response.data) ? response.data : 'Could not add question.');
                    $btn.prop('disabled', false).removeClass('is-loading');
                }
            },
            error: function () {
                alert('Network error occurred.');
                $btn.prop('disabled', false).removeClass('is-loading');
            }
        });
    });

    $(document).on('click', '.pqc-delete-question-btn', function () {
        const $container = $('.pqc-editor-container');
        const quizId = $container.data('quiz-id');
        const $slide = $(this).closest('.pqc-slide');
        const questionId = $slide.data('question-id');
        const currentSlideIndex = $container.data('current-slide') || 0;

        if (!quizId || !questionId) return;

        const s = (window.pqcData && window.pqcData.strings) ? window.pqcData.strings : {};
        const confirmMsg = s.confirmDeleteQuestion || 'Are you sure you want to delete this question?';

        if (!confirm(confirmMsg)) return;

        const $btn = $(this);
        $btn.prop('disabled', true).addClass('is-loading');

        $.ajax({
            url: pqcData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pqc_delete_question',
                nonce: pqcData.nonce,
                quiz_id: quizId,
                question_id: questionId
            },
            success: function (response) {
                if (response && response.success) {
                    // If we deleted the last slide, we should go to the previous one
                    const totalSlides = $('.pqc-slide').length;
                    let newIndex = currentSlideIndex;
                    if (currentSlideIndex >= totalSlides - 1 && currentSlideIndex > 0) {
                        newIndex = currentSlideIndex - 1;
                    }
                    refreshQuizEditor(quizId, newIndex);
                } else {
                    alert((response && response.data) ? response.data : 'Could not delete question.');
                    $btn.prop('disabled', false).removeClass('is-loading');
                }
            },
            error: function () {
                alert('Network error occurred.');
                $btn.prop('disabled', false).removeClass('is-loading');
            }
        });
    });

    $(document).on('click', '.pqc-delete-quiz-btn', function () {
        if (!confirm('Are you sure you want to delete this quiz? This action cannot be undone.')) return;

        const quizId = $(this).data('quiz-id') || $('.pqc-editor-container').data('quiz-id');
        if (!quizId) return;

        const $btn = $(this);
        const originalHtml = $btn.html();

        $btn.prop('disabled', true).html('Deleting...');

        $.ajax({
            url: pqcData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pqc_delete_quiz',
                nonce: pqcData.nonce,
                quiz_id: quizId
            },
            success: function (response) {
                if (response.success) {
                    if ($('#pcg-current-course-id').length) {
                        const courseId = $('#pcg-current-course-id').val();
                        refreshQuizModule(courseId);
                    } else {
                        location.reload();
                    }
                } else {
                    alert(response.data || 'Delete failed');
                    $btn.prop('disabled', false).html(originalHtml);
                }
            },
            error: function () {
                alert('Network error occurred.');
                $btn.prop('disabled', false).html(originalHtml);
            }
        });
    });

    // Delegated Change Handler for checkboxes
    $(document).on('change', '.pqc-answer-correct-check', function () {
        const $row = $(this).closest('.pqc-answer-edit-row');
        if ($(this).is(':checked')) {
            $(this).closest('.pqc-answers-editor-list').find('.pqc-answer-correct-check').not(this).prop('checked', false);
            $(this).closest('.pqc-answers-editor-list').find('.pqc-answer-edit-row').removeClass('is-correct');
            $row.addClass('is-correct');
        } else {
            $row.removeClass('is-correct');
        }
    });

    $(document).on('click', '.pqc-editor-add-answer-btn', function () {
        const $list = $(this).closest('.pqc-answers-section').find('.pqc-answers-editor-list');
        if (!$list.length) return;

        const nextIndex = $list.find('.pqc-answer-edit-row').length;
        const newRow = `
            <div class="pqc-answer-edit-row" data-answer-index="${nextIndex}">
                <div class="pqc-answer-check-wrap">
                    <input type="checkbox" class="pqc-answer-correct-check" title="Mark as correct">
                </div>
                <div class="pqc-answer-text-wrap" contenteditable="true" data-field="answer_text" data-placeholder="Nueva respuesta..."></div>
                <button type="button" class="pqc-remove-answer-btn" title="Remove answer">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
            </div>
        `;

        $list.append(newRow);
        // Focus the new answer
        $list.find('.pqc-answer-edit-row').last().find('.pqc-answer-text-wrap').focus();
    });

    $(document).on('click', '.pqc-remove-answer-btn', function () {
        const $list = $(this).closest('.pqc-answers-editor-list');
        const $row = $(this).closest('.pqc-answer-edit-row');
        
        // Don't allow removing if it's the only answer left (optional but good for UX)
        if ($list.find('.pqc-answer-edit-row').length <= 1) {
            alert('A question must have at least one answer.');
            return;
        }

        if (confirm('Are you sure you want to remove this answer?')) {
            $row.fadeOut(200, function() {
                $(this).remove();
                // Re-index remaining rows
                $list.find('.pqc-answer-edit-row').each(function(index) {
                    $(this).attr('data-answer-index', index);
                });
            });
        }
    });

    function refreshQuizModule(courseId) {
        const $container = $('#pcg-quiz-creator-container');
        if (!$container.length) return;
        $container.css('opacity', '0.5');

        $.ajax({
            url: pqcData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pqc_get_quiz_module',
                course_id: courseId
            },
            success: function (response) {
                if (response.success) {
                    $container.html(response.data.html);
                    if ($('.pqc-editor-container').length) {
                        initQuizEditor();
                    }
                }
            },
            complete: function () {
                $container.css('opacity', '1');
            }
        });
    }

    function refreshQuizEditor(quizId, goToIndex = 0) {
        const $moduleContainer = $('#pcg-quiz-creator-container');
        const $editorContainer = $('.pqc-editor-container');
        const $target = $moduleContainer.length ? $moduleContainer : $editorContainer;
        if (!$target.length) return;

        $target.css('opacity', '0.5');

        $.ajax({
            url: pqcData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pqc_get_quiz_editor',
                quiz_id: quizId
            },
            success: function (response) {
                if (response && response.success) {
                    if ($moduleContainer.length) {
                        $target.html(response.data.html);
                    } else {
                        $target.replaceWith(response.data.html);
                    }
                    initQuizEditor();
                    goToEditorSlide(goToIndex);
                }
            },
            complete: function () {
                $target.css('opacity', '1');
            }
        });
    }

    function saveQuizChanges() {
        const $allButtons = $('.pqc-save-quiz-btn');
        const $msg = $('#pqc-edit-msg');
        const quizId = $('.pqc-editor-container').data('quiz-id');
        if (!quizId) return;

        const quizData = {
            quiz_id: quizId,
            questions: []
        };

        $('.pqc-slide').each(function () {
            const $slide = $(this);
            const question = {
                id: $slide.data('question-id'),
                pro_id: $slide.data('pro-id'),
                title: $slide.find('.pqc-editable-question-title').text().trim(),
                question_text: $slide.find('.pqc-editable-text-area').html().trim(),
                answers: []
            };

            $slide.find('.pqc-answer-edit-row').each(function () {
                const $ans = $(this);
                question.answers.push({
                    text: $ans.find('.pqc-answer-text-wrap').text().trim(),
                    correct: $ans.find('.pqc-answer-correct-check').is(':checked'),
                    points: parseInt($ans.find('.pqc-answer-points-edit').val()) || 0
                });
            });

            quizData.questions.push(question);
        });

        $allButtons.prop('disabled', true).addClass('loading').find('span').text('Saving...');
        $msg.hide();

        $.ajax({
            url: pqcData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pqc_save_quiz_changes',
                nonce: pqcData.nonce,
                quiz_data: JSON.stringify(quizData)
            },
            success: function (response) {
                if (response.success) {
                    $msg.removeClass('error').addClass('success').text(response.data.message || 'Saved successfully!').fadeIn();
                    setTimeout(() => $msg.fadeOut(), 3000);
                } else {
                    $msg.removeClass('success').addClass('error').text(response.data || 'Save failed').fadeIn();
                }
            },
            error: function () {
                $msg.removeClass('success').addClass('error').text('Network error occurred.').fadeIn();
            },
            complete: function () {
                $allButtons.prop('disabled', false).removeClass('loading').find('span').text('SAVE');
            }
        });
    }

    function initFormValidation() {
        $(document).on('input change', '#pqc-quiz-title, #pqc-json-paste, .pqc-manual-q-title, .pqc-manual-q-text, .pqc-manual-answer-text, #pqc-num-questions-ui, #pqc-answers-per-question-ui, #pqc-keywords', function () {
            validateStep3();
            validatePromptButton();
        });
        
        // Run once on init
        validatePromptButton();
    }

    function validatePromptButton() {
        const numQ = $('#pqc-num-questions-ui').val();
        const ansPerQ = $('#pqc-answers-per-question-ui').val();
        const keywords = $('#pqc-keywords').val() ? $('#pqc-keywords').val().trim() : '';

        const isValid = numQ > 0 && ansPerQ > 0 && keywords.length > 0;
        $('.pqc-copy-prompt-btn').prop('disabled', !isValid);
        
        // Also enable the "Next" button on slide 2 if these are valid
        $('.pqc-wizard-next[data-next="5"]').prop('disabled', !isValid);
    }

    function validateStep3() {
        const method = $('#pqc-creation-method').val();
        const hasTitle = $('#pqc-quiz-title').val() ? $('#pqc-quiz-title').val().trim().length > 0 : false;

        // Slide 2 (Prompt Config)
        validatePromptButton();

        // Slide 5 (Paste Result - only if in LLM mode)
        let hasContent = false;
        if (method === 'llm') {
            hasContent = selectedFile !== null || ($('#pqc-json-paste').val() ? $('#pqc-json-paste').val().trim().length > 0 : false);
            $('.pqc-wizard-next[data-next="4"]').prop('disabled', !hasContent);
        } else {
            const $manualSlides = $('.pqc-manual-slide');
            if (!$manualSlides.length) {
                hasContent = false;
                $('.pqc-submit-btn').prop('disabled', !hasTitle);
                $('.pqc-wizard-next[data-next="4"]').prop('disabled', true);
                return;
            }

            // Check if ALL manual questions have title and text
            let allFilled = true;
            $manualSlides.each(function () {
                const qText = $(this).find('.pqc-manual-q-text').val() ? $(this).find('.pqc-manual-q-text').val().trim() : '';

                if (!qText) {
                    allFilled = false;
                    return false; // break
                }

                const $answers = $(this).find('.pqc-manual-answer-text');
                if ($answers.length < 2) {
                    allFilled = false;
                    return false;
                }

                // Also check if answer texts are filled
                $answers.each(function () {
                    if (!$(this).val() || $(this).val().trim().length === 0) {
                        allFilled = false;
                        return false;
                    }
                });

                if (!allFilled) return false;
            });
            hasContent = allFilled;
            $('.pqc-wizard-next[data-next="4"]').prop('disabled', !hasContent);
        }

        const canProceed = hasTitle && hasContent;
        $('.pqc-submit-btn').prop('disabled', !canProceed);
    }

    function initUploadArea() {
        $(document).on('click', '.pqc-upload-area-compact', function (e) {
            if (e.target !== this && !$(e.target).closest('.pqc-upload-icon-small, .pqc-upload-text-compact').length) return;
            $('#pqc-file-input').click();
        });

        $(document).on('dragover', '.pqc-upload-area-compact', function (e) {
            e.preventDefault(); e.stopPropagation(); $(this).addClass('drag-over');
        });

        $(document).on('dragleave', '.pqc-upload-area-compact', function (e) {
            e.preventDefault(); e.stopPropagation(); $(this).removeClass('drag-over');
        });

        $(document).on('drop', '.pqc-upload-area-compact', function (e) {
            e.preventDefault(); e.stopPropagation(); $(this).removeClass('drag-over');
            const files = e.originalEvent.dataTransfer.files;
            if (files.length > 0) handleFileSelect(files[0]);
        });
    }

    function initFileInput() {
        $(document).on('change', '#pqc-file-input', function (e) {
            const files = e.target.files;
            if (files.length > 0) handleFileSelect(files[0]);
        });

        $(document).on('click', '.pqc-remove-file', function () {
            clearFileSelection();
        });
    }

    function handleFileSelect(file) {
        const allowedExtensions = ['json'];
        const fileExtension = file.name.split('.').pop().toLowerCase();
        if (!allowedExtensions.includes(fileExtension)) { showError((pqcData && pqcData.strings && pqcData.strings.invalidFileJsonOnly) ? pqcData.strings.invalidFileJsonOnly : 'Invalid file type. Please use JSON.'); return; }
        if (file.size > 10 * 1024 * 1024) { showError((pqcData && pqcData.strings && pqcData.strings.fileTooLarge) ? pqcData.strings.fileTooLarge : 'File size exceeds 10MB limit.'); return; }
        selectedFile = file;
        $('.pqc-file-name').text(file.name);
        $('.pqc-file-info').show();
        $('.pqc-upload-area-compact').hide();
        $('.pqc-paste-area').css('opacity', '0.5');
        validateStep3();
    }

    function clearFileSelection() {
        selectedFile = null;
        $('#pqc-file-input').val('');
        $('.pqc-file-info').hide();
        $('.pqc-upload-area-compact').show();
        $('.pqc-paste-area').css('opacity', '1');
        validateStep3();
    }

    function initCopyPrompt() {
        $(document).on('click', '.pqc-copy-prompt-btn', function () {
            const title = ($('#pqc-quiz-title').val() ? $('#pqc-quiz-title').val().trim() : '') || 'Quiz';
            const numQuestions = $('#pqc-num-questions').val();
            const keywords = $('#pqc-keywords').val() ? $('#pqc-keywords').val().trim() : '';
            const answersPerQuestion = parseInt($('#pqc-answers-per-question').val(), 10) || 4;
            const uploadDocs = $('#pqc-upload-docs-llm').is(':checked');

            const promptText = buildChatGPTPrompt(title, numQuestions, keywords, answersPerQuestion, uploadDocs);
            copyToClipboard(promptText);

            const $btn = $(this);
            const $text = $btn.find('.pqc-btn-text');
            const $copied = $btn.find('.pqc-btn-copied');

            $text.hide();
            $copied.show();

            setTimeout(function () {
                $text.show();
                $copied.hide();
            }, 2000);
        });
    }

    function buildChatGPTPrompt(title, numQuestions, keywords, answersPerQuestion, uploadDocs) {
        let docContext = uploadDocs ? "\n- BASE THE QUESTIONS ON THE DOCUMENTS I AM UPLOADING TO YOU." : "";
        return `Create ${numQuestions} quiz questions about "${title}" in JSON format:\n\n[\n  {\n    "title": "Question title",\n    "question_text": "Full question text",\n    "answer_type": "single",\n    "points": 5,\n    "answers": [\n      {"text": "Answer 1", "correct": true, "points": 5},\n      {"text": "Answer 2", "correct": false, "points": 0}\n    ]\n  }\n]\n\nRequirements:\n- Return ONLY JSON.${docContext}\n- Keywords to use: ${keywords}\n- Each question MUST have exactly ${answersPerQuestion} answers (1 correct, the rest incorrect).`;
    }

    function copyToClipboard(text) {
        const $temp = $('<textarea>');
        $('body').append($temp);
        $temp.val(text).select();
        document.execCommand('copy');
        $temp.remove();
    }

    function initFormSubmit() {
        $(document).on('submit', '#pqc-quiz-form', function (e) {
            e.preventDefault();
            uploadQuiz();
        });
    }

    function uploadQuiz() {
        const method = $('#pqc-creation-method').val();
        const settings = {
            title: $('#pqc-quiz-title').val().trim(),
            passing_percentage: 80,
            answers_per_question: parseInt($('#pqc-answers-per-question').val(), 10) || 4,
            random_questions: $('#pqc-random-questions').is(':checked') ? 1 : 0,
            random_answers: $('#pqc-random-answers').is(':checked') ? 1 : 0,
            run_once: $('#pqc-run-once').is(':checked') ? 1 : 0,
            force_solve: $('#pqc-force-solve').is(':checked') ? 1 : 0,
            show_points: $('#pqc-show-points').is(':checked') ? 1 : 0
        };

        const formData = new FormData();
        formData.append('action', 'pqc_upload_quiz');
        formData.append('nonce', pqcData.nonce);
        formData.append('quiz_settings', JSON.stringify(settings));
        formData.append('course_id', $('#pqc-course-id').val() || 0);

        if (method === 'llm') {
            const pastedJson = $('#pqc-json-paste').val() ? $('#pqc-json-paste').val().trim() : '';
            if (selectedFile) {
                formData.append('quiz_file', selectedFile);
            } else if (pastedJson) {
                formData.append('quiz_json_text', pastedJson);
            } else {
                alert('Please upload a file or paste JSON data.');
                return;
            }
        } else {
            // Pack manual questions into JSON
            const manualQuestions = [];
            $('.pqc-manual-slide').each(function () {
                const $slide = $(this);
                const question = {
                    title: $slide.find('.pqc-manual-q-title').val() || `Question ${$slide.data('manual-index') + 1}`,
                    question_text: $slide.find('.pqc-manual-q-text').val() || '',
                    answer_type: 'single',
                    points: 5,
                    answers: []
                };

                $slide.find('.pqc-manual-answer-row').each(function () {
                    const $row = $(this);
                    question.answers.push({
                        text: $row.find('.pqc-manual-answer-text').val() || '',
                        correct: $row.find('.pqc-manual-correct-radio').is(':checked'),
                        points: $row.find('.pqc-manual-correct-radio').is(':checked') ? 5 : 0
                    });
                });
                manualQuestions.push(question);
            });
            formData.append('quiz_json_text', JSON.stringify(manualQuestions));
        }

        const $btn = $('.pqc-submit-btn');
        $btn.prop('disabled', true).find('.pqc-btn-loading').show();
        $btn.find('.pqc-btn-text').hide();

        $.ajax({
            url: pqcData.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false, contentType: false,
            success: function (response) {
                if (response.success) {
                    showSuccess(response.data);
                } else {
                    showError(response.data.message || ((pqcData && pqcData.strings && pqcData.strings.genericError) ? pqcData.strings.genericError : 'Error'));
                }
            },
            complete: function () {
                $btn.prop('disabled', false).find('.pqc-btn-loading').hide();
                $btn.find('.pqc-btn-text').show();
            }
        });
    }

    function showSuccess(data) {
        if ($('#pcg-current-course-id').length) {
            const courseId = $('#pcg-current-course-id').val();
            refreshQuizModule(courseId);
            return;
        }

        $('.pqc-quiz-form').hide();
        $('.pqc-header').hide();

        const $result = $('.pqc-result');
        const currentUrl = window.location.href.split('?')[0];
        const editUrl = currentUrl + '?edit_quiz=' + data.quiz_id;
        let html = `<div class="pqc-result-message"><div class="pqc-success-icon">✓</div><h3>${data.message}</h3>`;
        html += `<div class="pqc-result-links">
            <a href="${data.quiz_url}" class="pqc-result-link" target="_blank">${(pqcData && pqcData.strings && pqcData.strings.view) ? pqcData.strings.view : 'View'}</a>
            <a href="${editUrl}" class="pqc-result-link pqc-link-edit">${(pqcData && pqcData.strings && pqcData.strings.editSlideEditor) ? pqcData.strings.editSlideEditor : 'Edit Slide Editor'}</a>
        </div>`;
        $result.removeClass('error').addClass('success').html(html).show();
    }

    function showError(message) {
        $('.pqc-result').removeClass('success').addClass('error').html('<strong>✗ ' + message + '</strong>').show();
    }

})(jQuery);
