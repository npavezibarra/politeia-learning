/**
 * Politeia Quiz Creator - JavaScript
 * Compact unified form with inline prompt copy and Slide Editor
 */

(function ($) {
    'use strict';

    let selectedFile = null;

    $(document).ready(function () {
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

            // Sync all arrows state in all slides (optional, but good for consistency)
            $('.pqc-prev-slide').prop('disabled', currentSlide === 0);
            $('.pqc-next-slide').prop('disabled', currentSlide === totalSlides - 1);
        }

        // Handle navigation clicks from any slide
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

    /**
     * Save Quiz Changes via AJAX
     */
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

        // Loading state for all save buttons
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
        $('#pqc-quiz-title, #pqc-file-input').on('change keyup', function () {
            const hasTitle = $('#pqc-quiz-title').val().trim().length > 0;
            const hasFile = selectedFile !== null;
            $('.pqc-submit-btn').prop('disabled', !(hasTitle && hasFile));
        });
    }

    function initUploadArea() {
        const $uploadArea = $('.pqc-upload-area-compact');
        $uploadArea.on('click', function (e) {
            if (e.target !== this && !$(e.target).closest('.pqc-upload-icon-small, .pqc-upload-text-compact').length) {
                return;
            }
            $('#pqc-file-input').click();
        });
        $uploadArea.on('dragover', function (e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).addClass('drag-over');
        });
        $uploadArea.on('dragleave', function (e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('drag-over');
        });
        $uploadArea.on('drop', function (e) {
            e.preventDefault(); e.stopPropagation();
            $(this).removeClass('drag-over');
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
        if (!allowedExtensions.includes(fileExtension)) {
            showError('Invalid file type.');
            return;
        }
        if (file.size > 5 * 1024 * 1024) {
            showError('File size exceeds 5MB limit.');
            return;
        }
        selectedFile = file;
        $('.pqc-file-name').text(file.name);
        $('.pqc-file-size').text(formatFileSize(file.size));
        $('.pqc-file-info').show();
        $('.pqc-upload-area-compact').hide();
        $('.pqc-result').hide();
        const hasTitle = $('#pqc-quiz-title').val().trim().length > 0;
        $('.pqc-submit-btn').prop('disabled', !hasTitle);
    }

    function clearFileSelection() {
        selectedFile = null;
        $('#pqc-file-input').val('');
        $('.pqc-file-info').hide();
        $('.pqc-upload-area-compact').show();
        $('.pqc-submit-btn').prop('disabled', true);
    }

    function initCopyPrompt() {
        $('.pqc-copy-prompt-btn').on('click', function () {
            const title = $('#pqc-quiz-title').val().trim();
            const numQuestions = $('#pqc-num-questions').val();
            const keywords = $('#pqc-keywords').val().trim();
            if (!title) { alert('Please enter a quiz title'); return; }
            const prompt = buildChatGPTPrompt(title, numQuestions, keywords);
            copyToClipboard(prompt);
            const $btn = $(this);
            $btn.find('.pqc-btn-text').hide();
            $btn.find('.pqc-btn-copied').show();
            setTimeout(function () {
                $btn.find('.pqc-btn-text').show();
                $btn.find('.pqc-btn-copied').hide();
            }, 2000);
        });
    }

    function buildChatGPTPrompt(title, numQuestions, keywords) {
        let prompt = `Create ${numQuestions} quiz questions about "${title}" in JSON format:\n\n[\n  {\n    "title": "Question title",\n    "question_text": "Full question text",\n    "answer_type": "single",\n    "points": 5,\n    "answers": [\n      {"text": "Answer 1", "correct": true, "points": 5},\n      {"text": "Answer 2", "correct": false, "points": 0}\n    ]\n  }\n]\n\nRequirements:\n- Return ONLY JSON object.`;
        return prompt;
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
            time_limit: (parseInt($('#pqc-time-limit').val()) || 0) * 60,
            passing_percentage: parseInt($('#pqc-passing-percentage').val()) || 80,
            random_questions: $('#pqc-random-questions').is(':checked') ? 1 : 0,
            random_answers: $('#pqc-random-answers').is(':checked') ? 1 : 0,
            run_once: $('#pqc-run-once').is(':checked') ? 1 : 0,
            force_solve: $('#pqc-force-solve').is(':checked') ? 1 : 0,
            show_points: $('#pqc-show-points').is(':checked') ? 1 : 0
        };

        const formData = new FormData();
        formData.append('action', 'pqc_upload_quiz');
        formData.append('nonce', pqcData.nonce);
        formData.append('quiz_file', selectedFile);
        formData.append('quiz_settings', JSON.stringify(settings));

        $('.pqc-btn-text').hide();
        $('.pqc-btn-loading').show();
        $('.pqc-submit-btn').prop('disabled', true);

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
                $('.pqc-btn-text').show();
                $('.pqc-btn-loading').hide();
            }
        });
    }

    function clearForm() { $('#pqc-quiz-form')[0].reset(); clearFileSelection(); }

    function showSuccess(data) {
        const $result = $('.pqc-result');
        let html = `<div class="pqc-result-message"><div class="pqc-success-icon">✓</div><h3>${data.message}</h3>`;
        if (data.quiz_id) {
            html += `<div class="pqc-result-details"><p><strong>Quiz ID:</strong> ${data.quiz_id}</p></div>`;
            const currentUrl = window.location.href.split('?')[0];
            const editUrl = currentUrl + '?edit_quiz=' + data.quiz_id;
            html += `<div class="pqc-result-links">
                <a href="${data.quiz_url}" class="pqc-result-link" target="_blank">View Quiz</a>
                <a href="${editUrl}" class="pqc-result-link pqc-link-edit">Edit Quiz</a>
            </div>`;
        }
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
