/**
 * Politeia Quiz Creator - JavaScript
 * Compact unified form with Wizard navigation and Slide Editor
 */

(function ($) {
    'use strict';

    let selectedFile = null;
    let selectedText = null;

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
                const title = $('#pqc-quiz-title').val().trim();
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
        let currentSlide = 0;
        const $viewport = $('.pqc-slides-container');
        const $slides = $('.pqc-slide');
        const totalSlides = $slides.length;

        function updateSlider() {
            const offset = -(currentSlide * 100);
            $viewport.css('transform', `translateX(${offset}%)`);
            $('.pqc-prev-slide').prop('disabled', currentSlide === 0);
            $('.pqc-next-slide').prop('disabled', currentSlide === totalSlides - 1);
        }

        $(document).on('click', '.pqc-next-slide', function () {
            if (currentSlide < totalSlides - 1) {
                currentSlide++;
                updateSlider();
            }
        });

        $(document).on('click', '.pqc-prev-slide', function () {
            if (currentSlide > 0) {
                currentSlide--;
                updateSlider();
            }
        });

        $('.pqc-answer-correct-check').on('change', function () {
            const $row = $(this).closest('.pqc-answer-edit-row');
            if ($(this).is(':checked')) {
                $(this).closest('.pqc-answers-editor-list').find('.pqc-answer-correct-check').not(this).prop('checked', false);
                $(this).closest('.pqc-answers-editor-list').find('.pqc-answer-edit-row').removeClass('is-correct');
                $row.addClass('is-correct');
            } else {
                $row.removeClass('is-correct');
            }
        });

        $(document).on('click', '.pqc-save-quiz-btn', function () {
            saveQuizChanges();
        });
    }

    function saveQuizChanges() {
        const $allButtons = $('.pqc-save-quiz-btn');
        const $msg = $('#pqc-edit-msg');
        const quizId = $('.pqc-editor-container').data('quiz-id');

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
        $('#pqc-quiz-title, #pqc-json-paste').on('change keyup', function () {
            const hasTitle = $('#pqc-quiz-title').val().trim().length > 0;
            const hasInput = selectedFile !== null || $('#pqc-json-paste').val().trim().length > 0;
            $('.pqc-submit-btn').prop('disabled', !(hasTitle && hasInput));
        });
    }

    function initUploadArea() {
        const $uploadArea = $('.pqc-upload-area-compact');
        $uploadArea.on('click', function (e) {
            if (e.target !== this && !$(e.target).closest('.pqc-upload-icon-small, .pqc-upload-text-compact').length) return;
            $('#pqc-file-input').click();
        });
        $uploadArea.on('dragover', function (e) {
            e.preventDefault(); e.stopPropagation(); $(this).addClass('drag-over');
        });
        $uploadArea.on('dragleave', function (e) {
            e.preventDefault(); e.stopPropagation(); $(this).removeClass('drag-over');
        });
        $uploadArea.on('drop', function (e) {
            e.preventDefault(); e.stopPropagation(); $(this).removeClass('drag-over');
            const files = e.originalEvent.dataTransfer.files;
            if (files.length > 0) handleFileSelect(files[0]);
        });
    }

    function initFileInput() {
        $('#pqc-file-input').on('change', function (e) {
            const files = e.target.files;
            if (files.length > 0) handleFileSelect(files[0]);
        });
        $('.pqc-remove-file').on('click', function () {
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
        $('.pqc-paste-area').css('opacity', '0.5'); // Dim paste area
    }

    function clearFileSelection() {
        selectedFile = null;
        $('#pqc-file-input').val('');
        $('.pqc-file-info').hide();
        $('.pqc-upload-area-compact').show();
        $('.pqc-paste-area').css('opacity', '1');
    }

    function initCopyPrompt() {
        $('.pqc-copy-prompt-btn').on('click', function () {
            const title = $('#pqc-quiz-title').val().trim();
            const numQuestions = $('#pqc-num-questions').val();
            const keywords = $('#pqc-keywords').val().trim();
            const answersPerQuestion = $('#pqc-answers-per-question').val() || 4;

            if (!title) { alert('Please enter a quiz title'); return; }
            copyToClipboard(buildChatGPTPrompt(title, numQuestions, keywords, answersPerQuestion));
            const $btn = $(this);
            $btn.find('.pqc-btn-text').hide();
            $btn.find('.pqc-btn-copied').show();
            setTimeout(function () { $btn.find('.pqc-btn-text').show(); $btn.find('.pqc-btn-copied').hide(); }, 2000);
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
        $('#pqc-quiz-form').on('submit', function (e) {
            e.preventDefault();
            uploadQuiz();
        });
    }

    function uploadQuiz() {
        const settings = {
            title: $('#pqc-quiz-title').val().trim(),
            passing_percentage: parseInt($('#pqc-passing-percentage').val()) || 80,
            answers_per_question: $('#pqc-answers-per-question').val(),
            random_questions: $('#pqc-random-questions').is(':checked') ? 1 : 0,
            random_answers: $('#pqc-random-answers').is(':checked') ? 1 : 0,
            run_once: $('#pqc-run-once').is(':checked') ? 1 : 0,
            force_solve: $('#pqc-force-solve').is(':checked') ? 1 : 0,
            show_points: $('#pqc-show-points').is(':checked') ? 1 : 0
        };

        const formData = new FormData();
        formData.append('action', 'pqc_upload_quiz');
        formData.append('nonce', pqcData.nonce);

        // Handle either file or pasted JSON
        const pastedJson = $('#pqc-json-paste').val().trim();
        if (selectedFile) {
            formData.append('quiz_file', selectedFile);
        } else if (pastedJson) {
            formData.append('quiz_json_text', pastedJson);
        } else {
            alert('Please upload a file or paste JSON data.');
            return;
        }

        formData.append('quiz_settings', JSON.stringify(settings));

        const $btn = $('.pqc-submit-btn');
        $btn.prop('disabled', true).find('.pqc-btn-loading').show();
        $btn.find('.pqc-btn-text').hide();

        $.ajax({
            url: pqcData.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false, contentType: false,
            success: function (response) {
                if (response.success) { showSuccess(response.data); clearForm(); }
                else showError(response.data.message || 'Error');
            },
            complete: function () {
                $btn.prop('disabled', false).find('.pqc-btn-loading').hide();
                $btn.find('.pqc-btn-text').show();
            }
        });
    }

    function clearForm() { $('#pqc-quiz-form')[0].reset(); clearFileSelection(); }

    function showSuccess(data) {
        // Hide the form and the header/progress bar to focus on the result
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

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return (bytes / Math.pow(k, i)).toFixed(2) + ' ' + ['Bytes', 'KB', 'MB'][i];
    }

})(jQuery);
