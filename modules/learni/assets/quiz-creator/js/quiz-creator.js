/**
 * Politeia Quiz Creator - JavaScript
 * Compact unified form with Wizard navigation and Slide Editor
 */

(function ($) {
    'use strict';

    let selectedFile = null;
    let editorAiPanelInited = false;
    let quizPreviewInited = false;
    let quizSettingsInited = false;

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
        initEditorAiPanel();
        initQuizPreview();
        initQuizSettings();

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
        $(document).on('pqc_save', function (e, opts) {
            if ($('.pqc-editor-container').length) {
                saveQuizChanges(opts || {});
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

        const $counterText = $('#pqc-editor-counter-text');
        if ($counterText.length) {
            const oldText = $counterText.text();
            const newText = oldText.replace(/(\d+)\s*\/\s*(\d+)/, () => `${current + 1} / ${total}`);
            $counterText.text(newText);
        } else {
            const $counter = $('#pqc-editor-counter');
            if ($counter.length) {
                const oldText = $counter.text();
                const newText = oldText.replace(/(\d+)\s*\/\s*(\d+)/, () => `${current + 1} / ${total}`);
                $counter.text(newText);
            }
        }
    }

    // ───────────────────────────────────────────────────────────
    // Mobile: Question Selector (iOS-safe reorder via Up/Down)
    // ───────────────────────────────────────────────────────────

    function pqcIsSmartphoneView() {
        try {
            return window.matchMedia && window.matchMedia('(max-width: 640px)').matches;
        } catch (e) {
            return false;
        }
    }

    function pqcLockScroll(locked) {
        $('body').toggleClass('pqc-scroll-locked', !!locked);
    }

    function pqcGetSlideTitle($slide) {
        const raw = String($slide.find('.pqc-editable-text-area').text() || '').replace(/\s+/g, ' ').trim();
        if (!raw) return '';
        const maxLen = 80;
        return raw.length > maxLen ? (raw.slice(0, maxLen).trim() + '…') : raw;
    }

    function pqcUpdateSlideIndexes() {
        $('.pqc-editor-container .pqc-slide').each(function (idx) {
            $(this).attr('data-index', idx);
        });
    }

    function pqcSetCurrentSlideByQuestionId(questionId) {
        const $container = $('.pqc-editor-container');
        if (!$container.length) return;
        const $slides = $('.pqc-editor-container .pqc-slide');
        const idx = $slides.index($slides.filter(`[data-question-id="${questionId}"]`).first());
        if (idx >= 0) {
            goToEditorSlide(idx);
        }
    }

    function pqcMoveSlide(fromIndex, toIndex) {
        const $slidesContainer = $('.pqc-editor-container .pqc-slides-container');
        if (!$slidesContainer.length) return false;

        const $slides = $slidesContainer.children('.pqc-slide');
        const $from = $slides.eq(fromIndex);
        const $to = $slides.eq(toIndex);
        if (!$from.length || !$to.length) return false;

        if (toIndex < fromIndex) $from.insertBefore($to);
        else $from.insertAfter($to);

        pqcUpdateSlideIndexes();
        return true;
    }

    function pqcRenderQuestionSelectorList() {
        const $root = $('#pqc-question-selector');
        const $list = $('#pqc-question-selector-list');
        const $total = $('#pqc-question-selector-total');
        const $container = $('.pqc-editor-container');
        if (!$root.length || !$list.length || !$container.length) return;

        const $slides = $('.pqc-editor-container .pqc-slide');
        const total = $slides.length;
        const currentIndex = Number($container.data('current-slide') || 0) || 0;

        $total.text(`${total} TOTAL`);
        $list.empty();

        $slides.each(function (idx) {
            const $slide = $(this);
            const questionId = $slide.data('question-id');
            const isCurrent = idx === currentIndex;
            const title = pqcGetSlideTitle($slide) || `Pregunta ${idx + 1}`;

            const $item = $('<div class="pqc-qsel-item"></div>').attr('data-index', idx).attr('data-question-id', questionId);
            if (isCurrent) $item.addClass('is-current');

            const $main = $('<button type="button" class="pqc-qsel-item__main"></button>');
            $main.append(`<span class="pqc-qsel-item__num">${idx + 1}</span>`);
            $main.append($('<span class="pqc-qsel-item__text"></span>').text(title));

            const $actions = $('<div class="pqc-qsel-item__actions"></div>');
            const $up = $('<button type="button" class="pqc-qsel-item__move" data-dir="-1" aria-label="Mover arriba">↑</button>');
            const $down = $('<button type="button" class="pqc-qsel-item__move" data-dir="1" aria-label="Mover abajo">↓</button>');
            $up.prop('disabled', idx === 0);
            $down.prop('disabled', idx === total - 1);
            $actions.append($up, $down);

            $item.append($main, $actions);
            $list.append($item);
        });
    }

    function pqcOpenQuestionSelector() {
        if (!pqcIsSmartphoneView()) return;
        const $root = $('#pqc-question-selector');
        const $toggle = $('#pqc-editor-counter');
        if (!$root.length) return;
        pqcRenderQuestionSelectorList();
        $root.addClass('is-open').attr('aria-hidden', 'false');
        $toggle.attr('aria-expanded', 'true');
        pqcLockScroll(true);
    }

    function pqcCloseQuestionSelector() {
        const $root = $('#pqc-question-selector');
        const $toggle = $('#pqc-editor-counter');
        if (!$root.length) return;
        $root.removeClass('is-open').attr('aria-hidden', 'true');
        $toggle.attr('aria-expanded', 'false');
        pqcLockScroll(false);
    }

    function pqcToggleQuestionSelector() {
        const $root = $('#pqc-question-selector');
        if (!$root.length) return;
        if ($root.hasClass('is-open')) pqcCloseQuestionSelector();
        else pqcOpenQuestionSelector();
    }

    $(document).on('click', '#pqc-editor-counter', function () {
        if (!pqcIsSmartphoneView()) return;
        pqcToggleQuestionSelector();
    });

    $(document).on('click', '#pqc-question-selector [data-action=\"close\"], #pqc-question-selector .pqc-question-selector__close', function () {
        pqcCloseQuestionSelector();
    });

    $(document).on('click', '#pqc-question-selector .pqc-qsel-item__main', function () {
        const idx = Number($(this).closest('.pqc-qsel-item').attr('data-index') || 0) || 0;
        goToEditorSlide(idx);
        pqcCloseQuestionSelector();
    });

    $(document).on('click', '#pqc-question-selector .pqc-qsel-item__move', function (e) {
        e.preventDefault();
        if (!pqcIsSmartphoneView()) return;

        const $container = $('.pqc-editor-container');
        const currentIndex = Number($container.data('current-slide') || 0) || 0;
        const $currentSlide = $('.pqc-editor-container .pqc-slide').eq(currentIndex);
        const currentQuestionId = $currentSlide.data('question-id');

        const $item = $(this).closest('.pqc-qsel-item');
        const fromIndex = Number($item.attr('data-index') || 0) || 0;
        const dir = Number($(this).attr('data-dir') || 0) || 0;
        const toIndex = fromIndex + dir;
        if (toIndex < 0) return;

        const moved = pqcMoveSlide(fromIndex, toIndex);
        if (!moved) return;

        pqcSetCurrentSlideByQuestionId(currentQuestionId);
        pqcRenderQuestionSelectorList();
    });

    $(document).on('click', '#pqc-question-selector .pqc-question-selector__add', function () {
        if (!pqcIsSmartphoneView()) return;
        pqcCloseQuestionSelector();
        $('.pqc-add-question-btn').first().trigger('click');
    });

    $(document).on('keydown', function (e) {
        if (e.key !== 'Escape') return;
        const $root = $('#pqc-question-selector');
        if ($root.length && $root.hasClass('is-open')) {
            pqcCloseQuestionSelector();
        }
    });

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
            <div class="pqc-answer-edit-row" data-answer-index="${nextIndex}" data-image-id="0">
                <div class="pqc-answer-check-wrap">
                    <input type="checkbox" class="pqc-answer-correct-check" title="Mark as correct">
                </div>
                <div class="pqc-answer-thumb">
                    <img src="" alt="" style="display:none;" />
                    <button type="button" class="pqc-answer-image-remove-btn" title="Quitar imagen" style="display:none;">×</button>
                </div>
                <div class="pqc-answer-text-wrap" contenteditable="true" data-field="answer_text" data-placeholder="Nueva respuesta..."></div>
                <button type="button" class="pqc-answer-image-btn" title="Adjuntar imagen">
                    <span class="dashicons dashicons-format-image"></span>
                </button>
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

    $(document).on('click', '.pqc-answer-image-btn', function (e) {
        e.preventDefault();
        const $row = $(this).closest('.pqc-answer-edit-row');
        if (!$row.length) return;

        const toast = (msg, type) => {
            if (typeof window.pcgShowToast === 'function') window.pcgShowToast(msg, type || 'info');
            else alert(msg);
        };

        const canUseCropper =
            typeof window.PL_Cropper !== 'undefined' &&
            window.PL_Cropper &&
            typeof window.PL_Cropper.open === 'function' &&
            typeof window.pcgCreatorData !== 'undefined' &&
            window.pcgCreatorData &&
            window.pcgCreatorData.ajaxUrl &&
            window.pcgCreatorData.nonce;

        if (canUseCropper) {
            const questionId = Number($row.closest('.pqc-slide').data('question-id') || 0) || 0;
            window.PL_Cropper.open({
                title: 'Imagen de respuesta',
                width: 160,
                height: 100,
                outputMaxWidth: 1600,
                quality: 0.92,
                onSave: function (dataUrl) {
                    $.ajax({
                        url: window.pcgCreatorData.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'pcg_upload_cropped_image',
                            nonce: window.pcgCreatorData.nonce,
                            image_data: dataUrl,
                            type: 'quiz_answer',
                            entity_id: questionId
                        },
                        success: function (response) {
                            if (response && response.success && response.data && response.data.id && response.data.url) {
                                $row.attr('data-image-id', String(response.data.id || 0));
                                $row.addClass('has-image');
                                $row.find('.pqc-answer-thumb img').attr('src', response.data.url).show();
                                $row.find('.pqc-answer-image-remove-btn').show();
                            } else {
                                toast((response && response.data && response.data.message) ? response.data.message : 'Error al subir imagen.', 'error');
                            }
                        },
                        error: function () {
                            toast('Error al subir imagen.', 'error');
                        }
                    });
                }
            });
            return;
        }

        if (typeof wp === 'undefined' || !wp.media) {
            toast('Media uploader not available.', 'error');
            return;
        }

        const frame = wp.media({
            title: 'Seleccionar imagen',
            button: { text: 'Usar imagen' },
            multiple: false,
            library: { type: 'image' }
        });

        frame.on('select', function () {
            const selection = frame.state().get('selection');
            const attachment = selection && selection.first ? selection.first().toJSON() : null;
            if (!attachment || !attachment.id) return;

            const thumbUrl =
                (attachment.sizes && attachment.sizes.thumbnail && attachment.sizes.thumbnail.url)
                    ? attachment.sizes.thumbnail.url
                    : (attachment.url || '');

            $row.attr('data-image-id', String(attachment.id || 0));
            $row.addClass('has-image');
            $row.find('.pqc-answer-thumb img').attr('src', thumbUrl).show();
            $row.find('.pqc-answer-image-remove-btn').show();
        });

        frame.open();
    });

    $(document).on('click', '.pqc-answer-image-remove-btn', function (e) {
        e.preventDefault();
        const $row = $(this).closest('.pqc-answer-edit-row');
        if (!$row.length) return;
        $row.attr('data-image-id', '0');
        $row.removeClass('has-image');
        $row.find('.pqc-answer-thumb img').attr('src', '').hide();
        $(this).hide();
    });

    $(document).on('click', '.pqc-question-image-btn', function (e) {
        e.preventDefault();
        const $wrap = $(this).closest('.pqc-question-edit-wrap');
        if (!$wrap.length) return;

        const toast = (msg, type) => {
            if (typeof window.pcgShowToast === 'function') window.pcgShowToast(msg, type || 'info');
            else alert(msg);
        };

        const canUseCropper =
            typeof window.PL_Cropper !== 'undefined' &&
            window.PL_Cropper &&
            typeof window.PL_Cropper.open === 'function' &&
            typeof window.pcgCreatorData !== 'undefined' &&
            window.pcgCreatorData &&
            window.pcgCreatorData.ajaxUrl &&
            window.pcgCreatorData.nonce;

        if (canUseCropper) {
            const questionId = Number($wrap.closest('.pqc-slide').data('question-id') || 0) || 0;
            window.PL_Cropper.open({
                title: 'Imagen de pregunta',
                width: 160,
                height: 100,
                outputMaxWidth: 1600,
                quality: 0.92,
                onSave: function (dataUrl) {
                    $.ajax({
                        url: window.pcgCreatorData.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'pcg_upload_cropped_image',
                            nonce: window.pcgCreatorData.nonce,
                            image_data: dataUrl,
                            type: 'quiz_question',
                            entity_id: questionId
                        },
                        success: function (response) {
                            if (response && response.success && response.data && response.data.id && response.data.url) {
                                $wrap.attr('data-question-image-id', String(response.data.id || 0));
                                $wrap.addClass('has-question-image');
                                $wrap.find('.pqc-question-image-thumb').attr('src', response.data.url).show();
                                $wrap.find('.pqc-question-image-remove-btn').show();
                            } else {
                                toast((response && response.data && response.data.message) ? response.data.message : 'Error al subir imagen.', 'error');
                            }
                        },
                        error: function () {
                            toast('Error al subir imagen.', 'error');
                        }
                    });
                }
            });
            return;
        }

        if (typeof wp === 'undefined' || !wp.media) {
            toast('Media uploader not available.', 'error');
            return;
        }

        const frame = wp.media({
            title: 'Seleccionar imagen',
            button: { text: 'Usar imagen' },
            multiple: false,
            library: { type: 'image' }
        });

        frame.on('select', function () {
            const selection = frame.state().get('selection');
            const attachment = selection && selection.first ? selection.first().toJSON() : null;
            if (!attachment || !attachment.id) return;

            const url = attachment.url || '';
            $wrap.attr('data-question-image-id', String(attachment.id || 0));
            $wrap.addClass('has-question-image');
            $wrap.find('.pqc-question-image-thumb').attr('src', url).show();
            $wrap.find('.pqc-question-image-remove-btn').show();
        });

        frame.open();
    });

    $(document).on('click', '.pqc-question-image-remove-btn', function (e) {
        e.preventDefault();
        const $wrap = $(this).closest('.pqc-question-edit-wrap');
        if (!$wrap.length) return;
        $wrap.attr('data-question-image-id', '0');
        $wrap.removeClass('has-question-image');
        $wrap.find('.pqc-question-image-thumb').attr('src', '').hide();
        $(this).hide();
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

    // Expose for other modules (e.g., course creator dashboard)
    window.pqcSaveQuizChanges = saveQuizChanges;

    function saveQuizChanges(opts) {
        const options = opts && typeof opts === 'object' ? opts : {};
        const deferred =
            options.deferred && typeof options.deferred.resolve === 'function'
                ? options.deferred
                : $.Deferred();
        const $allButtons = $('.pqc-save-quiz-btn');
        const quizId = $('.pqc-editor-container').data('quiz-id');
        if (!quizId) {
            deferred.resolve({ success: true, data: { message: 'No quiz id' } });
            return deferred.promise();
        }

        const deriveTitleFromHtml = (html) => {
            const text = String(html || '')
                .replace(/<[^>]*>/g, ' ')
                .replace(/\s+/g, ' ')
                .trim();
            if (!text) return '';
            const maxLen = 64;
            return text.length > maxLen ? (text.slice(0, maxLen).trim() + '…') : text;
        };

        const quizData = {
            quiz_id: quizId,
            questions: []
        };

        $('.pqc-slide').each(function () {
            const $slide = $(this);
            const questionHtml = $slide.find('.pqc-editable-text-area').html().trim();
            const questionImageId =
                parseInt($slide.find('.pqc-question-edit-wrap').attr('data-question-image-id') || '0', 10) || 0;
            const question = {
                id: $slide.data('question-id'),
                pro_id: $slide.data('pro-id'),
                title: deriveTitleFromHtml(questionHtml),
                question_text: questionHtml,
                image_id: questionImageId,
                answers: []
            };

            $slide.find('.pqc-answer-edit-row').each(function () {
                const $ans = $(this);
                const text = $ans.find('.pqc-answer-text-wrap').text().trim();
                if (!text) {
                    return;
                }
                question.answers.push({
                    text: text,
                    correct: $ans.find('.pqc-answer-correct-check').is(':checked'),
                    points: parseInt($ans.find('.pqc-answer-points-edit').val()) || 0,
                    image_id: parseInt($ans.attr('data-image-id') || '0', 10) || 0
                });
            });

            quizData.questions.push(question);
        });

        $allButtons.prop('disabled', true).addClass('loading');

        $.ajax({
            url: pqcData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pqc_save_quiz_changes',
                nonce: pqcData.nonce,
                quiz_data: JSON.stringify(quizData)
            },
            success: function (response) {
                if (response && response.success) {
                    deferred.resolve(response);
                } else {
                    deferred.reject(response || { success: false, data: 'Save failed' });
                }
            },
            error: function () {
                deferred.reject({ success: false, data: 'Network error occurred.' });
            },
            complete: function () {
                $allButtons.prop('disabled', false).removeClass('loading');
            }
        });

        return deferred.promise();
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
        $('.pqc-wizard-container .pqc-copy-prompt-btn').prop('disabled', !isValid);
        
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
        $(document).on('click', '.pqc-wizard-container .pqc-copy-prompt-btn', function () {
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

    function initEditorAiPanel() {
        if (editorAiPanelInited) return;
        editorAiPanelInited = true;

        const getExistingQuestionTitles = (max = 25) => {
            const titles = [];
            $('.pqc-slide').each(function () {
                const questionText = String($(this).find('.pqc-editable-text-area').text() || '').trim().replace(/\s+/g, ' ');
                if (!questionText) return;
                const t = questionText.length > 70 ? (questionText.slice(0, 70).trim() + '…') : questionText;
                titles.push(t);
            });
            if (titles.length > max) return titles.slice(0, max);
            return titles;
        };

        const buildExistingQuestionsContextJson = () => {
            const quizTitle = String($('.pqc-editor-container').data('quiz-title') || '').trim();
            const total = $('.pqc-slide').length;
            const sample = [];

            $('.pqc-slide').each(function () {
                if (sample.length >= 20) return false;
                const questionText = String($(this).find('.pqc-editable-text-area').text() || '').trim().replace(/\s+/g, ' ');
                const excerpt = questionText.length > 140 ? (questionText.slice(0, 140) + '…') : questionText;
                if (!excerpt) return;
                sample.push({ question_excerpt: excerpt });
            });

            return JSON.stringify(
                {
                    quiz_title: quizTitle,
                    existing_questions_total: total,
                    existing_questions_sample: sample
                },
                null,
                2
            );
        };

        const setStatus = (text, type) => {
            const $status = $('.pqc-ai-panel__status');
            if (!$status.length) return;
            $status.removeClass('is-error is-success');
            if (type === 'error') $status.addClass('is-error');
            if (type === 'success') $status.addClass('is-success');
            $status.text(text || '');
        };

        const validatePrompt = () => {
            const numQ = Number($('#pqc-ai-num-questions').val() || 0);
            const ansPerQ = Number($('#pqc-ai-answers-per-question').val() || 0);
            const keywords = String($('#pqc-ai-keywords').val() || '').trim();
            const ok = numQ > 0 && ansPerQ >= 2 && keywords.length > 0;
            $('.pqc-ai-copy-prompt-btn').prop('disabled', !ok);
            return ok;
        };

        const validateImport = () => {
            const jsonText = String($('#pqc-ai-json-paste').val() || '').trim();
            const ok = jsonText.length > 0;
            $('.pqc-ai-import-btn').prop('disabled', !ok);
            return ok;
        };

        $(document).on('click', '.pqc-ai-toggle-btn', function () {
            const $btn = $(this);
            const $panel = $('.pqc-ai-panel');
            if (!$panel.length) return;
            const isOpen = $panel.is(':visible');
            if (isOpen) {
                $panel.slideUp(180);
                $btn.attr('aria-expanded', 'false');
            } else {
                $panel.slideDown(180);
                $btn.attr('aria-expanded', 'true');
                validatePrompt();
                validateImport();
            }
            setStatus('');
        });

        $(document).on('input change', '#pqc-ai-num-questions, #pqc-ai-answers-per-question, #pqc-ai-keywords, #pqc-ai-upload-docs', function () {
            validatePrompt();
        });

        $(document).on('input paste change', '#pqc-ai-json-paste', function () {
            validateImport();
        });

        $(document).on('click', '.pqc-ai-copy-prompt-btn', function () {
            const quizId = Number($('.pqc-editor-container').data('quiz-id') || 0) || 0;
            if (!quizId) return;

            const promptOk = validatePrompt();
            if (!promptOk) return;

            const title = String($('.pqc-editor-container').data('quiz-title') || '').trim() || 'Quiz';
            const numQuestions = Number($('#pqc-ai-num-questions').val() || 0);
            const keywords = String($('#pqc-ai-keywords').val() || '').trim();
            const answersPerQuestion = Number($('#pqc-ai-answers-per-question').val() || 4);
            const uploadDocs = $('#pqc-ai-upload-docs').is(':checked');

            let promptText = buildChatGPTPrompt(title, String(numQuestions), keywords, answersPerQuestion, uploadDocs);
            const existingTitles = getExistingQuestionTitles(25);
            if (existingTitles.length) {
                promptText += `\n\nExisting questions (do NOT repeat; create different questions):\n- ${existingTitles.join('\n- ')}`;
            }
            copyToClipboard(promptText);

            const $btn = $(this);
            const $text = $btn.find('.pqc-btn-text');
            const $copied = $btn.find('.pqc-btn-copied');

            $text.hide();
            $copied.show();
            setStatus('Prompt copiado. Pégalo en tu LLM y vuelve con el JSON.', 'success');

            setTimeout(function () {
                $text.show();
                $copied.hide();
            }, 2000);
        });

        $(document).on('click', '.pqc-ai-context-btn', function () {
            const quizId = Number($('.pqc-editor-container').data('quiz-id') || 0) || 0;
            if (!quizId) return;
            const contextJson = buildExistingQuestionsContextJson();
            copyToClipboard(contextJson);
            setStatus('Contexto copiado (JSON). Pégalo en tu LLM para que genere preguntas nuevas sin repetir.', 'success');
        });

        $(document).on('click', '.pqc-ai-import-btn', function () {
            const quizId = Number($('.pqc-editor-container').data('quiz-id') || 0) || 0;
            if (!quizId) return;

            const jsonText = String($('#pqc-ai-json-paste').val() || '').trim();
            if (!jsonText) return;

            const shouldReplace = $('#pqc-ai-replace-existing').is(':checked');

            if (!window.pqcData || !pqcData.ajaxUrl || !pqcData.nonce) {
                setStatus('Error: configuración AJAX no disponible (pqcData). Recarga la página e intenta nuevamente.', 'error');
                return;
            }

            // Client-side validation for nicer UX
            try {
                const parsed = JSON.parse(jsonText);
                if (!Array.isArray(parsed) || parsed.length === 0) {
                    setStatus('El JSON debe ser un arreglo de preguntas.', 'error');
                    return;
                }
            } catch (e) {
                setStatus('JSON inválido. Asegúrate de pegar solo JSON.', 'error');
                return;
            }

            if (shouldReplace) {
                if (!confirm('Esto borrará las preguntas actuales del quiz y las reemplazará por el JSON. ¿Continuar?')) {
                    return;
                }
            }

            $('.pqc-ai-import-btn').prop('disabled', true);
            setStatus(shouldReplace ? 'Reemplazando preguntas…' : 'Agregando preguntas…', '');

            $.ajax({
                url: pqcData.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'pqc_import_questions_json',
                    nonce: pqcData.nonce,
                    quiz_id: quizId,
                    quiz_json_text: jsonText,
                    mode: shouldReplace ? 'replace' : 'append'
                },
                success: function (response) {
                    if (response && response.success) {
                        setStatus('Importación lista. Actualizando editor…', 'success');
                        const goToIndex = response && response.data && typeof response.data.go_to_index !== 'undefined'
                            ? Number(response.data.go_to_index || 0)
                            : 0;
                        refreshQuizEditor(quizId, goToIndex);
                        $('#pqc-ai-json-paste').val('');
                        $('#pqc-ai-replace-existing').prop('checked', false);
                        validateImport();
                    } else {
                        const msg = (response && response.data && response.data.message) ? response.data.message : 'No se pudo importar el JSON (respuesta inválida).';
                        setStatus(msg, 'error');
                    }
                },
                error: function (xhr) {
                    const raw = (xhr && xhr.responseText) ? String(xhr.responseText).slice(0, 300) : '';
                    setStatus(raw ? `Error al importar (respuesta): ${raw}` : 'Error de red al importar el JSON.', 'error');
                },
                complete: function () {
                    validateImport();
                }
            });
        });
    }

    function initQuizPreview() {
        if (quizPreviewInited) return;
        quizPreviewInited = true;

        const ensureModal = () => {
            let modal = document.getElementById('pqc-quiz-preview-modal');
            if (modal) return modal;
            modal = document.createElement('div');
            modal.id = 'pqc-quiz-preview-modal';
            modal.className = 'learni-quiz-modal';
            modal.innerHTML =
                '<div class="learni-quiz-modal__backdrop" data-learni-quiz-close="1"></div>' +
                '<div class="learni-quiz-modal__panel" role="dialog" aria-modal="true" aria-label="Quiz">' +
                '<div class="learni-quiz-modal__head">' +
                '<div class="learni-quiz-modal__title" id="pqc-quiz-preview-modal-title">Quiz</div>' +
                '<button type="button" class="learni-quiz-modal__close" data-learni-quiz-close="1" aria-label="Close">×</button>' +
                '</div>' +
                '<div class="learni-quiz-modal__body" id="pqc-quiz-preview-modal-body"></div>' +
                '</div>';
            document.body.appendChild(modal);

            modal.addEventListener('click', function (e) {
                const close = e.target && e.target.getAttribute && e.target.getAttribute('data-learni-quiz-close');
                if (close) hideModal();
            });
            return modal;
        };

        const showModal = (html) => {
            const modal = ensureModal();
            const body = document.getElementById('pqc-quiz-preview-modal-body');
            if (body) body.innerHTML = html || '';
            modal.classList.add('is-open');
            document.body.style.overflow = 'hidden';
        };

        const hideModal = () => {
            const modal = ensureModal();
            modal.classList.remove('is-open');
            document.body.style.overflow = '';
            const body = document.getElementById('pqc-quiz-preview-modal-body');
            if (body) body.innerHTML = '';
        };

        const setTitle = (text) => {
            const node = document.getElementById('pqc-quiz-preview-modal-title');
            if (node) node.textContent = text || 'Quiz';
        };

        const collectQuiz = () => {
            const questions = [];
            $('.pqc-slide').each(function () {
                const prompt = String($(this).find('.pqc-editable-text-area').text() || '').trim().replace(/\s+/g, ' ');
                const qImageUrl = String($(this).find('.pqc-question-image-thumb').attr('src') || '').trim();
                const hasQuestionImage = !!qImageUrl;
                const answers = [];
                $(this).find('.pqc-answer-edit-row').each(function () {
                    const text = String($(this).find('.pqc-answer-text-wrap').text() || '').trim();
                    if (!text) return;
                    const aImageUrl = String($(this).find('.pqc-answer-thumb img').attr('src') || '').trim();
                    const hasAnswerImage = !!aImageUrl;
                    answers.push({
                        text,
                        imageUrl: hasAnswerImage ? aImageUrl : ''
                    });
                });
                if (!prompt) return;
                if (answers.length < 2) return;
                questions.push({
                    prompt,
                    imageUrl: hasQuestionImage ? qImageUrl : '',
                    answers
                });
            });
            return questions;
        };

        const renderIntro = (quizTitle) => {
            return (
                '<div class="learni-quiz-intro">' +
                '<div class="learni-quiz-intro__kicker">Preview</div>' +
                '<div class="learni-quiz-intro__title">' + escapeHtml(quizTitle || 'Quiz') + '</div>' +
                '<div class="learni-quiz-intro__text">Este preview muestra el diseño y la experiencia del quiz. No se guarda progreso ni resultados.</div>' +
                '<div class="learni-quiz-actions">' +
                '<button type="button" class="learni-btn" id="pqc-quiz-preview-begin">Begin</button>' +
                '<button type="button" class="learni-btn secondary" data-learni-quiz-close="1">Cancel</button>' +
                '</div>' +
                '</div>'
            );
        };

        const renderQuestion = (q, index, total, seed, checkedValue) => {
            let answersHtml = '';
            const shuffled = stableShuffleAnswers(q.answers || [], String(seed || '') + ':q:' + String(index));
            const hasImageAnswers = shuffled.some((a) => !!(a && a.imageUrl));
            if (hasImageAnswers) {
                shuffled.forEach((a) => {
                    const checked =
                        checkedValue !== undefined && checkedValue !== null && String(checkedValue) === String(a.value)
                            ? ' checked="checked"'
                            : '';
                    const thumb = a.imageUrl
                        ? '<img src="' + escapeHtml(a.imageUrl) + '" alt="" loading="lazy">'
                        : '<span class="learni-quiz-img-a__thumb-placeholder" aria-hidden="true"></span>';
                    answersHtml +=
                        '<label class="learni-quiz-img-a">' +
                        '<input type="radio" name="q" value="' + escapeHtml(String(a.value)) + '"' + checked + '>' +
                        '<span class="learni-quiz-img-a__inner">' +
                        '<span class="learni-quiz-img-a__thumb">' + thumb + '</span>' +
                        '<span class="learni-quiz-img-a__text">' + escapeHtml(a.text) + '</span>' +
                        '<span class="learni-quiz-img-a__check" aria-hidden="true">✓</span>' +
                        '</span>' +
                        '</label>';
                });
            } else {
                shuffled.forEach((a) => {
                    const checked =
                        checkedValue !== undefined && checkedValue !== null && String(checkedValue) === String(a.value)
                            ? ' checked="checked"'
                            : '';
                    answersHtml +=
                        '<label class="learni-quiz-a">' +
                        '<input type="radio" name="q" value="' + escapeHtml(String(a.value)) + '"' + checked + '>' +
                        '<span class="learni-quiz-a__text">' + escapeHtml(a.text) + '</span>' +
                        '</label>';
                });
            }

            const isLast = index === total - 1;
            return (
                '<form id="pqc-quiz-preview-slide" class="learni-quiz-form">' +
                '<div class="learni-quiz-q">' +
                '<div class="learni-quiz-q__meta">Question ' + (index + 1) + ' of ' + total + '</div>' +
                (q.imageUrl ? '<div class="learni-quiz-q__img"><img src="' + escapeHtml(q.imageUrl) + '" alt="" loading="lazy"></div>' : '') +
                '<div class="learni-quiz-q__text">' + escapeHtml(q.prompt || '') + '</div>' +
                '</div>' +
                (hasImageAnswers ? '<div class="learni-quiz-a-kicker">Selecciona la opción correcta</div>' : '') +
                '<div class="learni-quiz-a-list' + (hasImageAnswers ? ' learni-quiz-a-list--grid' : '') + '">' + answersHtml + '</div>' +
                '<div class="learni-quiz-actions">' +
                (index > 0
                    ? '<button type="button" class="learni-btn secondary" id="pqc-quiz-preview-prev">Back</button>'
                    : '<button type="button" class="learni-btn secondary" data-learni-quiz-close="1">Cancel</button>') +
                '<button type="submit" class="learni-btn" id="pqc-quiz-preview-next">' + (isLast ? 'Submit' : 'Next') + '</button>' +
                '</div>' +
                '</form>'
            );
        };

        const renderDone = () => {
            return (
                '<div class="learni-quiz-results">' +
                '<div class="learni-quiz-results__kicker">Preview</div>' +
                '<div class="learni-quiz-results__text">Preview finalizado. Este modo no calcula resultados.</div>' +
                '<div class="learni-quiz-actions">' +
                '<button type="button" class="learni-btn" data-learni-quiz-close="1">Cerrar</button>' +
                '</div>' +
                '</div>'
            );
        };

        // Minimal escape for injected HTML
        const escapeHtml = (s) => String(s || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\"/g, '&quot;')
            .replace(/'/g, '&#039;');

        const hash32 = (str) => {
            const s = String(str || '');
            let h = 2166136261;
            for (let i = 0; i < s.length; i++) {
                h ^= s.charCodeAt(i);
                h = (h + (h << 1) + (h << 4) + (h << 7) + (h << 8) + (h << 24)) >>> 0;
            }
            return h >>> 0;
        };

        const stableShuffleAnswers = (answers, seed) => {
            const items = (Array.isArray(answers) ? answers : []).map((raw, i) => {
                if (raw && typeof raw === 'object') {
                    return {
                        text: String(raw.text || ''),
                        imageUrl: String(raw.imageUrl || ''),
                        value: String(i),
                    };
                }
                return {
                    text: String(raw || ''),
                    imageUrl: '',
                    value: String(i),
                };
            });
            const s = String(seed || '');
            items.sort((a, b) => {
                const ha = hash32(s + ':' + a.value + ':' + a.text + ':' + a.imageUrl);
                const hb = hash32(s + ':' + b.value + ':' + b.text + ':' + b.imageUrl);
                if (ha === hb) return 0;
                return ha < hb ? -1 : 1;
            });
            return items;
        };

        $(document).on('click', '.pqc-preview-question-btn', function () {
            const quizTitle = String($('.pqc-editor-container').data('quiz-title') || '').trim();
            const questions = collectQuiz();

            const currentIndex = Number($(this).closest('.pqc-slide').data('index') || 0) || 0;
            const startIndex = Math.max(0, Math.min(questions.length - 1, currentIndex));
            const state = {
                index: -1,
                startIndex: startIndex,
                questions: questions,
                title: quizTitle || 'Quiz',
                seed: String(Date.now()),
                answers: {},
            };

            const render = () => {
                if (state.index < 0) {
                    setTitle(state.title);
                    showModal(renderIntro(state.title));
                    return;
                }
                if (state.index >= state.questions.length) {
                    setTitle('Resultados');
                    showModal(renderDone());
                    return;
                }
                setTitle(state.title);
                showModal(renderQuestion(state.questions[state.index], state.index, state.questions.length, state.seed, state.answers[state.index]));
            };

            render();

            // Bind within modal (delegated by document, since body is replaced on each render)
            $(document).off('click.pqcPreview').on('click.pqcPreview', '#pqc-quiz-preview-begin,#pqc-quiz-preview-prev,[data-learni-quiz-close]', function (e) {
                const $t = $(e.target);
                if ($t.is('[data-learni-quiz-close]')) {
                    $(document).off('click.pqcPreview');
                    $(document).off('submit.pqcPreview');
                    hideModal();
                    return;
                }
                if ($t.is('#pqc-quiz-preview-begin')) {
                    state.index = state.startIndex;
                    render();
                    return;
                }
                if ($t.is('#pqc-quiz-preview-prev')) {
                    state.index = Math.max(0, state.index - 1);
                    render();
                    return;
                }
            });

            $(document).off('submit.pqcPreview').on('submit.pqcPreview', '#pqc-quiz-preview-slide', function (e) {
                e.preventDefault();
                const form = e.currentTarget;
                const chosen = form.querySelector('input[name="q"]:checked');
                if (!chosen || !chosen.value) {
                    alert('Please choose an answer.');
                    return;
                }
                state.answers[state.index] = chosen.value;
                if (state.index >= state.questions.length - 1) {
                    state.index = state.questions.length;
                    render();
                    return;
                }
                state.index = Math.min(state.questions.length - 1, state.index + 1);
                render();
            });
        });
    }

    function initQuizSettings() {
        if (quizSettingsInited) return;
        quizSettingsInited = true;

        const setStatus = (text, type) => {
            const $status = $('.pqc-quiz-settings-panel__status');
            if (!$status.length) return;
            $status.removeClass('is-error is-success');
            if (type === 'error') $status.addClass('is-error');
            if (type === 'success') $status.addClass('is-success');
            $status.text(text || '');
        };

        const getTotalQuestions = () => {
            return $('.pqc-slide').length;
        };

        const getSettings = () => {
            const raw = String($('.pqc-editor-container').attr('data-quiz-settings') || '').trim();
            if (!raw) return {};
            try {
                const parsed = JSON.parse(raw);
                return parsed && typeof parsed === 'object' ? parsed : {};
            } catch (e) {
                return {};
            }
        };

	        const applySettingsToUi = (settings) => {
	            const total = getTotalQuestions();
	            const perAttempt = Number(settings.questions_per_attempt || 0) || 0;
	            const subsetRandom = Number(settings.questions_subset_random || 0) ? 1 : 0;
	            const restartCooldownDays = Number(settings.restartCooldownDays || 0) || 0;
	            const orderMode = String(settings.questionOrder || '');
	            const randomQuestions = Number(settings.random_questions || 0) ? 1 : 0;
	            const respectOrder = orderMode ? (orderMode !== 'random') : !randomQuestions;

            $('#pqc-respect-question-order-editor').prop('checked', Boolean(respectOrder));

            if (perAttempt > 0) {
                $('input[name="pqc_questions_mode"][value="random"]').prop('checked', true);
                $('#pqc-questions-per-attempt').prop('disabled', false).val(String(perAttempt));
            } else {
                $('input[name="pqc_questions_mode"][value="all"]').prop('checked', true);
                $('#pqc-questions-per-attempt').prop('disabled', true).val('');
            }

            if (subsetRandom) {
                // UI currently only supports random subset if a number is provided.
            }

	            const $hint = $('#pqc-questions-per-attempt-hint');
	            if ($hint.length) {
	                $hint.text(total ? `(total: ${total})` : '');
	            }

	            const $restart = $('#pqc-restart-cooldown-days');
	            if ($restart.length) {
	                $restart.val(String(Math.max(0, Math.round(restartCooldownDays))));
	            }
	        };

        const openSettings = () => {
            const $panel = $('.pqc-quiz-settings-panel');
            if (!$panel.length) return;
            $('.pqc-ai-panel').hide();
            $('.pqc-slider-viewport').hide();
            $panel.slideDown(160);

            const settings = getSettings();
            applySettingsToUi(settings);

            // Update counter tag to reflect settings mode
            const $counter = $('#pqc-editor-counter');
            if ($counter.length) {
                $counter.attr('data-prev-text', $counter.text());
                $counter.text('SETTINGS');
            }
            $('.pqc-prev-slide, .pqc-next-slide, .pqc-add-question-btn').prop('disabled', true);
            setStatus('');
        };

        const closeSettings = () => {
            const $panel = $('.pqc-quiz-settings-panel');
            if (!$panel.length) return;
            $panel.slideUp(120, function () {
                $('.pqc-slider-viewport').show();
            });

            const $counter = $('#pqc-editor-counter');
            if ($counter.length) {
                const prev = $counter.attr('data-prev-text');
                if (prev) {
                    $counter.text(prev);
                    $counter.removeAttr('data-prev-text');
                }
            }

            // Restore nav state from current slide
            const $container = $('.pqc-editor-container');
            const current = Number($container.data('current-slide') || 0) || 0;
            const total = $('.pqc-slide').length;
            updateNavState(current, total);
            $('.pqc-add-question-btn').prop('disabled', false);
            setStatus('');
        };

        const syncModeUi = () => {
            const mode = String($('input[name="pqc_questions_mode"]:checked').val() || 'all');
            const total = getTotalQuestions();
            const $num = $('#pqc-questions-per-attempt');
            if (mode === 'random') {
                $num.prop('disabled', false);
                const currentVal = Number($num.val() || 0) || 0;
                if (!currentVal) {
                    $num.val(String(Math.min(10, Math.max(1, total || 10))));
                }
            } else {
                $num.prop('disabled', true).val('');
            }
        };

        $(document).on('click', '.pqc-quiz-settings-btn', function () {
            openSettings();
        });

        $(document).on('click', '.pqc-quiz-settings-panel__close', function () {
            closeSettings();
        });

        $(document).on('change', 'input[name="pqc_questions_mode"]', function () {
            syncModeUi();
        });

	        $(document).on('input', '#pqc-questions-per-attempt', function () {
	            const total = getTotalQuestions();
	            let v = Number($(this).val() || 0) || 0;
	            if (v < 1) v = 1;
	            if (total && v > total) v = total;
	            $(this).val(String(v));
	        });

	        $(document).on('input', '#pqc-restart-cooldown-days', function () {
	            let v = Number($(this).val() || 0) || 0;
	            if (v < 0) v = 0;
	            if (v > 3650) v = 3650;
	            $(this).val(String(Math.round(v)));
	        });

        $(document).on('click', '.pqc-quiz-settings-save-btn', function () {
            const quizId = Number($('.pqc-editor-container').data('quiz-id') || 0) || 0;
            if (!quizId) return;

            const mode = String($('input[name="pqc_questions_mode"]:checked').val() || 'all');
            const total = getTotalQuestions();
            const respectOrder = $('#pqc-respect-question-order-editor').is(':checked') ? 1 : 0;

	            let questionsPerAttempt = 0;
	            let subsetRandom = 0;
	            if (mode === 'random') {
	                questionsPerAttempt = Number($('#pqc-questions-per-attempt').val() || 0) || 0;
	                if (questionsPerAttempt < 1) questionsPerAttempt = 1;
	                if (total && questionsPerAttempt > total) questionsPerAttempt = total;
	                subsetRandom = 1;
	            }

	            let restartCooldownDays = Number($('#pqc-restart-cooldown-days').val() || 0) || 0;
	            if (restartCooldownDays < 0) restartCooldownDays = 0;
	            if (restartCooldownDays > 3650) restartCooldownDays = 3650;

	            if (!window.pqcData || !pqcData.ajaxUrl || !pqcData.nonce) {
	                setStatus('Error: configuración AJAX no disponible (pqcData).', 'error');
	                return;
	            }

            setStatus('Guardando…');
            $('.pqc-quiz-settings-save-btn').prop('disabled', true);

            $.ajax({
                url: pqcData.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'pqc_save_quiz_settings',
                    nonce: pqcData.nonce,
                    quiz_id: quizId,
	                    settings: JSON.stringify({
	                        questionOrder: respectOrder ? 'in_order' : 'random',
	                        questions_per_attempt: questionsPerAttempt,
	                        questions_subset_random: subsetRandom,
	                        restartCooldownDays: restartCooldownDays
	                    })
	                },
                success: function (response) {
                    if (response && response.success) {
                        const s = response.data && response.data.settings ? response.data.settings : null;
                        if (s) {
                            $('.pqc-editor-container').attr('data-quiz-settings', JSON.stringify(s));
                        }
                        setStatus('Guardado.', 'success');
                    } else {
                        const msg = (response && response.data && response.data.message) ? response.data.message : 'No se pudo guardar.';
                        setStatus(msg, 'error');
                    }
                },
                error: function (xhr) {
                    const raw = (xhr && xhr.responseText) ? String(xhr.responseText).slice(0, 240) : '';
                    setStatus(raw ? `Error: ${raw}` : 'Error de red.', 'error');
                },
                complete: function () {
                    $('.pqc-quiz-settings-save-btn').prop('disabled', false);
                }
            });
        });
    }

    function buildChatGPTPrompt(title, numQuestions, keywords, answersPerQuestion, uploadDocs) {
        let docContext = uploadDocs ? "\n- BASE THE QUESTIONS ON THE DOCUMENTS I AM UPLOADING TO YOU." : "";
        return `Create ${numQuestions} quiz questions about "${title}" in JSON format:\n\n[\n  {\n    \"question_text\": \"Write the full question here\",\n    \"answers\": [\n      {\"text\": \"Answer 1\", \"correct\": true},\n      {\"text\": \"Answer 2\", \"correct\": false}\n    ]\n  }\n]\n\nRequirements:\n- Return ONLY JSON.${docContext}\n- Keywords to use (as concepts): ${keywords}\n- Apply Spanish orthography rules to names/keywords in the output when relevant (capitalize properly and add accents/diacritics when needed). Example: \"pitagoras\" → \"Pitágoras\".\n- Ensure questions are independent: no question should reveal the answer to another question.\n- Each question MUST have exactly ${answersPerQuestion} answers (1 correct, the rest incorrect).\n- Questions must be specific and answerable with precision.\n- Incorrect answers (distractors) must be plausible and not easy to discard.\n- Avoid obvious patterns (e.g., joke options, extreme wording, \"all of the above\").`;
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
            respect_question_order: $('#pqc-respect-question-order').is(':checked') ? 1 : 0,
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
