/**
 * Politeia Quiz Creator - JavaScript
 * Compact unified form with Wizard navigation and Slide Editor
 */

(function ($) {
    'use strict';

    let selectedFile = null;

    $(document).ready(function () {
        initWizardNavigation();
        initUploadArea();
        initFileInput();
        initFormSubmit();
        initFormValidation();
        initCopyPrompt();

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
            title: $('.pqc-editable-title').text().trim(),
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
        $(document).on('change keyup', '#pqc-quiz-title, #pqc-json-paste', function () {
            const hasTitle = $('#pqc-quiz-title').val() ? $('#pqc-quiz-title').val().trim().length > 0 : false;
            const hasInput = selectedFile !== null || ($('#pqc-json-paste').val() ? $('#pqc-json-paste').val().trim().length > 0 : false);
            $('.pqc-submit-btn').prop('disabled', !(hasTitle && hasInput));
        });
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
        const allowedExtensions = ['json', 'csv', 'xml', 'txt'];
        const fileExtension = file.name.split('.').pop().toLowerCase();
        if (!allowedExtensions.includes(fileExtension)) { showError('Invalid file type.'); return; }
        if (file.size > 10 * 1024 * 1024) { showError('File size exceeds 10MB limit.'); return; }
        selectedFile = file;
        $('.pqc-file-name').text(file.name);
        $('.pqc-file-info').show();
        $('.pqc-upload-area-compact').hide();
        $('.pqc-paste-area').css('opacity', '0.5');
    }

    function clearFileSelection() {
        selectedFile = null;
        $('#pqc-file-input').val('');
        $('.pqc-file-info').hide();
        $('.pqc-upload-area-compact').show();
        $('.pqc-paste-area').css('opacity', '1');
    }

    function initCopyPrompt() {
        $(document).on('click', '.pqc-copy-prompt-btn', function () {
            const title = $('#pqc-quiz-title').val() ? $('#pqc-quiz-title').val().trim() : '';
            const numQuestions = $('#pqc-num-questions').val() || 10;
            const keywords = $('#pqc-keywords').val() ? $('#pqc-keywords').val().trim() : '';
            const answersPerQuestion = $('#pqc-answers-per-question').val() || 4;

            if (!title) {
                alert('Please enter a quiz title first');
                $('.pqc-wizard-prev[data-prev="1"]').click();
                $('#pqc-quiz-title').focus();
                return;
            }

            const promptText = buildChatGPTPrompt(title, numQuestions, keywords, answersPerQuestion);
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

    function buildChatGPTPrompt(title, numQuestions, keywords, answersPerQuestion) {
        return `Create ${numQuestions} quiz questions about "${title}" in JSON format:\n\n[\n  {\n    "title": "Question title",\n    "question_text": "Full question text",\n    "answer_type": "single",\n    "points": 5,\n    "answers": [\n      {"text": "Answer 1", "correct": true, "points": 5},\n      {"text": "Answer 2", "correct": false, "points": 0}\n    ]\n  }\n]\n\nRequirements:\n- Return ONLY JSON.\n- Keywords to use: ${keywords}\n- Each question MUST have exactly ${answersPerQuestion} answers (1 correct, the rest incorrect).`;
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
        const settings = {
            title: $('#pqc-quiz-title').val().trim(),
            passing_percentage: 80,
            answers_per_question: $('#pqc-answers-per-question').val(),
            random_questions: 0,
            random_answers: 0,
            run_once: 0,
            force_solve: 0,
            show_points: 0
        };

        const formData = new FormData();
        formData.append('action', 'pqc_upload_quiz');
        formData.append('nonce', pqcData.nonce);

        const pastedJson = $('#pqc-json-paste').val() ? $('#pqc-json-paste').val().trim() : '';
        if (selectedFile) {
            formData.append('quiz_file', selectedFile);
        } else if (pastedJson) {
            formData.append('quiz_json_text', pastedJson);
        } else {
            alert('Please upload a file or paste JSON data.');
            return;
        }

        formData.append('quiz_settings', JSON.stringify(settings));

        const courseId = $('#pqc-course-id').val() || 0;
        formData.append('course_id', courseId);

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
                    showError(response.data.message || 'Error');
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
            <a href="${data.quiz_url}" class="pqc-result-link" target="_blank">View</a>
            <a href="${editUrl}" class="pqc-result-link pqc-link-edit">Edit Slide Editor</a>
        </div>`;
        $result.removeClass('error').addClass('success').html(html).show();
    }

    function showError(message) {
        $('.pqc-result').removeClass('success').addClass('error').html('<strong>✗ ' + message + '</strong>').show();
    }

})(jQuery);
