/**
 * Politeia Quiz Creator - JavaScript
 * Compact unified form with Wizard navigation and Slide Editor
 */

(function ($) {
    'use strict';

    let selectedFile = null;

    $(document).ready(function () {
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

    /**
     * Wizard Navigation logic
     */
    function initWizardNavigation() {
        // NEXT button
        $(document).on('click', '.pqc-wizard-next', function () {
            const nextSlide = $(this).data('next');
            const currentSlide = nextSlide - 1;

            // Simple validation before going to next slide
            if (currentSlide === 1) {
                const title = $('#pqc-quiz-title').val() ? $('#pqc-quiz-title').val().trim() : '';
                const num = $('#pqc-num-questions').val();
                if (!title) { alert('Please enter a Quiz Title.'); return; }
                if (!num || num < 1) { alert('Please enter number of questions.'); return; }
            }

            if (nextSlide === 3) {
                const method = $('#pqc-creation-method').val();
                if (method === 'manual') {
                    renderManualQuestions();
                }
            }

            goToSlide(nextSlide);
        });

        // PREV button
        $(document).on('click', '.pqc-wizard-prev', function () {
            const prevSlide = $(this).data('prev');
            goToSlide(prevSlide);
        });

        function goToSlide(slideNum) {
            $('.pqc-wizard-slide').removeClass('active');
            $(`.pqc-wizard-slide[data-slide="${slideNum}"]`).addClass('active');

            // Update Progress dots
            $('.pqc-progress-step').removeClass('active');
            $(`.pqc-progress-step[data-step="${slideNum}"]`).addClass('active');
        }
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

            // Toggle path display
            $('.pqc-method-path').hide();
            $(`#pqc-path-${method}`).show();

            // Set first path-specific step validation if needed
            validateStep3();
        });
    }

    /**
     * Manual Mode UI Generation
     */
    function renderManualQuestions() {
        const numQuestions = parseInt($('#pqc-num-questions').val(), 10);
        if (!numQuestions || numQuestions < 1) {
            alert('Please enter number of questions.');
            return;
        }

        // Keep the input empty if the user wants, but default behavior still assumes 4 answers.
        const answersPerQuestion = parseInt($('#pqc-answers-per-question').val(), 10) || 4;
        const $slidesWrap = $('#pqc-manual-slides-wrap');

        $slidesWrap.empty();

        for (let i = 0; i < numQuestions; i++) {
            let html = `<div class="pqc-slide pqc-manual-slide ${i === 0 ? 'active' : ''}" data-manual-index="${i}">`;

            // Question fields
            html += `<div class="pqc-manual-field">
                        <label>Question ${i + 1} Title</label>
                        <input type="text" class="pqc-manual-q-title" placeholder="Internal name (e.g. Question 1)" value="Question ${i + 1}" required />
                    </div>
                    <div class="pqc-manual-field">
                        <label>Question Text</label>
                        <input type="text" class="pqc-manual-q-text" placeholder="Write the actual question here..." required />
                    </div>`;

            // Answers list
            html += `<div class="pqc-manual-field">
                        <label>Answers (Check the correct one)</label>
                        <div class="pqc-manual-answers-list">`;

            for (let j = 0; j < answersPerQuestion; j++) {
                html += `<div class="pqc-manual-answer-row">
                            <input type="radio" name="manual_correct_${i}" class="pqc-manual-correct-radio" ${j === 0 ? 'checked' : ''} />
                            <input type="text" class="pqc-manual-answer-text" placeholder="Answer ${j + 1}" required />
                        </div>`;
            }

            html += `</div></div></div>`;
            $slidesWrap.append(html);
        }

        updateManualCounter(0, numQuestions);
        validateStep3();
    }

    function initManualModeControls() {
        $(document).on('click', '.pqc-manual-next-btn', function () {
            moveManualSlide(1);
        });

        $(document).on('click', '.pqc-manual-prev-btn', function () {
            moveManualSlide(-1);
        });

        $(document).on('change', '.pqc-manual-correct-radio', function () {
            $(this).closest('.pqc-manual-answers-list').find('.pqc-manual-answer-row').removeClass('correct');
            if ($(this).is(':checked')) {
                $(this).closest('.pqc-manual-answer-row').addClass('correct');
            }
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
    }

    $(document).on('click', '.pqc-save-quiz-btn', function () {
        saveQuizChanges();
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
        $(document).on('change keyup', '#pqc-quiz-title, #pqc-json-paste, .pqc-manual-q-title, .pqc-manual-q-text, .pqc-manual-answer-text', function () {
            validateStep3();
        });
    }

    function validateStep3() {
        const method = $('#pqc-creation-method').val();
        const hasTitle = $('#pqc-quiz-title').val() ? $('#pqc-quiz-title').val().trim().length > 0 : false;

        let hasContent = false;
        if (method === 'llm') {
            hasContent = selectedFile !== null || ($('#pqc-json-paste').val() ? $('#pqc-json-paste').val().trim().length > 0 : false);
        } else {
            // Check if ALL manual questions have title and text
            let allFilled = true;
            $('.pqc-manual-slide').each(function () {
                const qTitle = $(this).find('.pqc-manual-q-title').val() ? $(this).find('.pqc-manual-q-title').val().trim() : '';
                const qText = $(this).find('.pqc-manual-q-text').val() ? $(this).find('.pqc-manual-q-text').val().trim() : '';

                if (!qTitle || !qText) {
                    allFilled = false;
                    return false; // break
                }

                // Also check if answer texts are filled
                $(this).find('.pqc-manual-answer-text').each(function () {
                    if (!$(this).val() || $(this).val().trim().length === 0) {
                        allFilled = false;
                        return false;
                    }
                });

                if (!allFilled) return false;
            });
            hasContent = allFilled;
        }

        const canProceed = hasTitle && hasContent;
        $('.pqc-submit-btn').prop('disabled', !canProceed);
        $('.pqc-wizard-next[data-next="4"]').prop('disabled', !canProceed);
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
            const title = $('#pqc-quiz-title').val() ? $('#pqc-quiz-title').val().trim() : '';
            const numQuestions = $('#pqc-num-questions').val();
            const keywords = $('#pqc-keywords').val() ? $('#pqc-keywords').val().trim() : '';
            const answersPerQuestion = parseInt($('#pqc-answers-per-question').val(), 10) || 4;
            const uploadDocs = $('#pqc-upload-docs-llm').is(':checked');

            if (!title) {
                alert((pqcData && pqcData.strings && pqcData.strings.enterTitleFirst) ? pqcData.strings.enterTitleFirst : 'Please enter a quiz title first');
                $('.pqc-wizard-prev[data-prev="1"]').click();
                $('#pqc-quiz-title').focus();
                return;
            }

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
