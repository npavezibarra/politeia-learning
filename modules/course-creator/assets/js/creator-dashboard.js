/**
 * Course Creator Dashboard JS
 */
jQuery(document).ready(function ($) {
    console.log('Politeia Course Creator Dashboard Initialized');

    let currentCourseId = 0;
    let thumbnailId = 0;
    let coverPhotoId = 0; // Added for cover photo
    let currentCoursePermalink = '';

    const $list = $('#pcg-lessons-list');
    const $courseLabel = $('#pcg-current-course-label');
    const $previewBtn = $('#pcg-btn-preview-course');

    // ── Tab Switcher ──
    $(document).on('click', '.pcg-desc-tab', function () {
        var target = $(this).data('target');
        $('.pcg-desc-tab').removeClass('active');
        $(this).addClass('active');
        $('.pcg-tab-content').removeClass('active');
        $('#' + target).addClass('active');
    });

    // ── Word Counter ──
    function countWords(text) {
        text = text.trim();
        if (!text) return 0;
        return text.split(/\s+/).length;
    }

    function updateWordCount(textareaId, counterId, maxWords) {
        var text = $(textareaId).val();
        var count = countWords(text);
        var $counter = $(counterId);
        $counter.text(count + ' / ' + maxWords + ' palabras');
        if (count > maxWords) {
            $counter.addClass('over-limit');
        } else {
            $counter.removeClass('over-limit');
        }
    }

    $(document).on('input', '#pcg-course-description', function () {
        updateWordCount('#pcg-course-description', '#pcg-desc-word-count', 700);
    });

    $(document).on('input', '#pcg-course-excerpt', function () {
        updateWordCount('#pcg-course-excerpt', '#pcg-excerpt-word-count', 50);
    });

    function resetForm() {
        currentCourseId = 0;
        $('#pcg-current-course-id').val(0);
        thumbnailId = 0;
        coverPhotoId = 0; // Reset cover photo ID
        $('#pcg-course-title').val('');
        $('#pcg-course-description').val('');
        $('#pcg-course-excerpt').val('');
        updateWordCount('#pcg-course-description', '#pcg-desc-word-count', 700);
        updateWordCount('#pcg-course-excerpt', '#pcg-excerpt-word-count', 50);
        $('#pcg-course-price').val('');
        $('#pcg-thumbnail-preview').hide().find('img').attr('src', '');
        $('#pcg-cover-preview').hide().find('img').attr('src', ''); // Reset cover preview
        $('#pcg-upload-thumbnail').show();
        $('#pcg-select-background').show();

        $list.empty();
        $('#pcg-course-progression').prop('checked', false);
        $('.pcg-empty-lessons-state').show();
        $courseLabel.text('').hide();
        currentCoursePermalink = '';
        $previewBtn.hide();
        $('#pcg-price-free-indicator').hide();

        // Reset Tabs to "CURSO"
        $('.pcg-segment').removeClass('active');
        $('.pcg-segment[data-value="curso"]').addClass('active');
        $('.pcg-mode-content').hide();
        $('#pcg-mode-curso').show();
    }

    // Show form when "CREATE COURSE" button is clicked
    $('#pcg-show-creator-form').on('click', function () {
        $('#pcg-creator-intro-section').fadeOut(300, function () {
            resetForm();
            $('#pcg-course-form-section').fadeIn(400);
            $('#pcg-my-courses-section').hide();
        });
    });

    // Back to list / Cancel Edit
    $('#pcg-btn-back-to-list, #pcg-btn-cancel-edit').on('click', function () {
        $('#pcg-course-form-section').fadeOut(300, function () {
            $('#pcg-creator-intro-section').fadeIn();
            $('#pcg-my-courses-section').fadeIn();
            resetForm();
        });
    });

    // Update label as user types
    $('#pcg-course-title').on('input', function () {
        const title = $(this).val();
        if (title) {
            $courseLabel.text(title).show();
        } else {
            $courseLabel.hide();
        }
    });

    // Show/hide "Gratis" indicator based on price
    $('#pcg-course-price').on('input change', function () {
        const price = parseFloat($(this).val()) || 0;
        const $freeIndicator = $('#pcg-price-free-indicator');

        if (price === 0) {
            $freeIndicator.fadeIn(200);
        } else {
            $freeIndicator.fadeOut(200);
        }
    });

    // Preview Button click
    $previewBtn.on('click', function () {
        if (currentCoursePermalink) {
            window.open(currentCoursePermalink, '_blank');
        }
    });

    // Toggle between Curso, Lecciones and Evaluaciones
    $(document).on('click', '.pcg-segment', function () {
        $('.pcg-segment').removeClass('active');
        $(this).addClass('active');

        const mode = $(this).data('value');
        $('.pcg-mode-content').hide();

        if (mode === 'curso') {
            $('#pcg-mode-curso').fadeIn(300);
        } else if (mode === 'lecciones') {
            $('#pcg-mode-lecciones').fadeIn(300);
            initSortable();
        } else if (mode === 'evaluacion') {
            const courseId = typeof currentCourseId !== 'undefined' ? currentCourseId : 0;
            if (courseId === 0) {
                $('#pcg-quiz-not-created-msg').show();
                $('#pcg-quiz-creator-container').hide();
            } else {
                $('#pcg-quiz-not-created-msg').hide();
                $('#pcg-quiz-creator-container').show();
            }
            $('#pcg-mode-evaluacion').fadeIn(300);
        }
    });

    function initSortable() {
        if ($.fn.sortable) {
            $('#pcg-lessons-list').sortable({
                axis: 'y',
                containment: 'parent',
                placeholder: 'pcg-sortable-placeholder',
                forcePlaceholderSize: true,
                cancel: 'input, button, .pcg-item-btn-remove',
                opacity: 0.8,
                tolerance: 'pointer',
                refreshPositions: true,
                start: function (e, ui) {
                    ui.placeholder.height(ui.item.outerHeight());
                }
            });
        }
    }

    // Show/Hide Add Dropdown
    $('#pcg-btn-add-content').on('click', function (e) {
        e.stopPropagation();
        $('#pcg-add-dropdown').fadeToggle(200);
    });

    $(document).on('click', function () {
        $('#pcg-add-dropdown').fadeOut(200);
    });

    // Add Lesson or Section
    $('.pcg-add-option').on('click', function () {
        const type = $(this).data('type');
        addContentItem(type);
        $('#pcg-add-dropdown').fadeOut(200);
    });

    function addContentItem(type, data = {}) {
        $('.pcg-empty-lessons-state').hide();

        const iconClass = type === 'section' ? 'dashicons-menu' : 'dashicons-media-text';
        const typeLabel = type === 'section' ? 'Nueva Sección' : 'Nueva Lección';
        const itemClass = type === 'section' ? 'item-section' : 'item-lesson';

        const title = typeof data === 'string' ? data : (data.title || '');
        const videoUrl = data.video_url || '';
        const availableDate = data.available_date || '';

        let expandHtml = '';
        let detailsHtml = '';

        if (type === 'lesson') {
            expandHtml = `
                <div class="pcg-item-expand" title="Expand Details">
                    <span class="dashicons dashicons-arrow-right-alt2"></span>
                </div>
            `;
            detailsHtml = `
                <div class="pcg-item-details" style="display:none;">
                    <div class="pcg-detail-row">
                        <div class="pcg-detail-field">
                            <label>YouTube URL</label>
                            <input type="text" class="pcg-lesson-video-url" value="${videoUrl}" placeholder="https://youtube.com/watch?v=...">
                        </div>
                        <div class="pcg-detail-field">
                            <label>Disponible en</label>
                            <input type="date" class="pcg-lesson-available-date" value="${availableDate}">
                        </div>
                    </div>
                    <div class="pcg-detail-actions">
                        <button type="button" class="pcg-btn-add-text">Add Text</button>
                    </div>
                </div>
            `;
        }

        const itemHtml = `
            <div class="pcg-content-item ${itemClass}" data-type="${type}">
                <div class="pcg-item-header">
                    ${expandHtml}
                    <div class="pcg-item-icon">
                        <span class="dashicons ${iconClass}"></span>
                    </div>
                    <div class="pcg-item-input-wrapper">
                        <input type="text" class="pcg-item-input" value="${title}" placeholder="${typeLabel}...">
                    </div>
                    <div class="pcg-item-actions">
                        <button type="button" class="pcg-item-btn-remove" title="Remove">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                        <div class="pcg-item-drag-handle">
                            <span class="dashicons dashicons-menu"></span>
                        </div>
                    </div>
                </div>
                ${detailsHtml}
            </div>
        `;

        const $newItem = $(itemHtml);
        $('#pcg-lessons-list').append($newItem);
        if (!title) $newItem.find('.pcg-item-input').focus();
        initSortable();
    }

    // Toggle Details
    $(document).on('click', '.pcg-item-expand', function (e) {
        e.stopPropagation();
        const $item = $(this).closest('.pcg-content-item');
        const $details = $item.find('.pcg-item-details');
        const $icon = $(this).find('.dashicons');

        $details.slideToggle(300);
        $icon.toggleClass('expanded');
    });

    // Remove item
    $(document).on('click', '.pcg-item-btn-remove', function () {
        $(this).closest('.pcg-content-item').fadeOut(300, function () {
            $(this).remove();
            if ($('#pcg-lessons-list').children('.pcg-content-item').length === 0) {
                $('.pcg-empty-lessons-state').fadeIn(300);
            }
        });
    });

    // Media Uploader: Thumbnail (Course Cover)
    $('#pcg-upload-thumbnail').on('click', function (e) {
        e.preventDefault();
        PCG_Cropper.open({
            title: 'Course Cover',
            width: 360,
            height: 238,
            onSave: function (dataUrl) {
                saveCroppedImage(dataUrl, 'thumbnail');
            }
        });
    });

    // Media Uploader: Cover Photo (Background)
    $('#pcg-select-background').on('click', function (e) {
        e.preventDefault();
        PCG_Cropper.open({
            title: 'Cover Photo',
            width: 1024,
            height: 768,
            onSave: function (dataUrl) {
                saveCroppedImage(dataUrl, 'cover');
            }
        });
    });

    function saveCroppedImage(dataUrl, type) {
        $.ajax({
            url: pcgCreatorData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pcg_upload_cropped_image',
                nonce: pcgCreatorData.nonce,
                image_data: dataUrl,
                type: type
            },
            success: function (response) {
                if (response.success) {
                    const attachment = response.data;
                    if (type === 'thumbnail') {
                        thumbnailId = attachment.id;
                        $('#pcg-thumbnail-preview img').attr('src', attachment.url);
                        $('#pcg-thumbnail-preview').fadeIn();
                        $('#pcg-upload-thumbnail').hide();
                    } else {
                        coverPhotoId = attachment.id;
                        $('#pcg-cover-preview img').attr('src', attachment.url);
                        $('#pcg-cover-preview').fadeIn();
                        $('#pcg-select-background').hide();
                    }
                } else {
                    alert('Error: ' + response.data.message);
                }
            },
            error: function () {
                alert('Ocurrió un error al subir la imagen.');
            }
        });
    }

    $('#pcg-remove-thumbnail').on('click', function () {
        thumbnailId = 0;
        $('#pcg-thumbnail-preview').fadeOut();
        $('#pcg-upload-thumbnail').fadeIn();
    });

    $('#pcg-remove-cover').on('click', function () {
        coverPhotoId = 0;
        $('#pcg-cover-preview').fadeOut();
        $('#pcg-select-background').fadeIn();
    });

    // Handle Enter key on inputs to "save" (blur)
    $(document).on('keypress', '.pcg-item-input', function (e) {
        if (e.which === 13) {
            $(this).blur();
        }
    });

    // Save Course Logic
    $('.pcg-btn-save').on('click', function () {
        const $btn = $(this);
        const originalText = $btn.text();

        const courseData = {
            id: currentCourseId,
            title: $('#pcg-course-title').val(),
            description: $('#pcg-course-description').val(),
            excerpt: $('#pcg-course-excerpt').val(),
            price: $('#pcg-course-price').val(),
            thumbnail_id: thumbnailId,
            cover_photo_id: coverPhotoId, // New field for cover photo
            progression: $('#pcg-course-progression').is(':checked') ? 'on' : '',
            content: []
        };

        $('#pcg-lessons-list .pcg-content-item').each(function () {
            courseData.content.push({
                type: $(this).data('type'),
                title: $(this).find('.pcg-item-input').val(),
                video_url: $(this).find('.pcg-lesson-video-url').val() || '',
                available_date: $(this).find('.pcg-lesson-available-date').val() || ''
            });
        });

        if (!courseData.title) {
            alert('Por favor, ingresa un título para el curso.');
            return;
        }

        $btn.text('GUARDANDO...').prop('disabled', true);

        $.ajax({
            url: pcgCreatorData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pcg_save_course',
                nonce: pcgCreatorData.nonce,
                course_data: courseData
            },
            success: function (response) {
                if (response.success) {
                    currentCourseId = response.data.course_id;
                    $('#pcg-current-course-id').val(currentCourseId);
                    $btn.text('¡GUARDADO!').addClass('success');
                    loadMyCourses();
                    setTimeout(() => {
                        $btn.text(originalText).prop('disabled', false).removeClass('success');
                    }, 2000);

                    // Added: Update permalink and show preview button
                    if (response.data.permalink) {
                        currentCoursePermalink = response.data.permalink;
                        $previewBtn.fadeIn();
                    }

                } else {
                    alert('Error: ' + response.data.message);
                    $btn.text(originalText).prop('disabled', false);
                }
            },
            error: function () {
                alert('Ocurrió un error al guardar el curso.');
                $btn.text(originalText).prop('disabled', false);
            }
        });
    });



    // Load My Courses
    function loadMyCourses() {
        $.ajax({
            url: pcgCreatorData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pcg_get_my_courses',
                nonce: pcgCreatorData.nonce
            },
            success: function (response) {
                if (response.success) {
                    renderCourses(response.data);
                }
            }
        });
    }

    function renderCourses(courses) {
        const $grid = $('#pcg-my-courses-grid');
        $grid.empty();

        if (courses.length === 0) {
            $grid.append('<p class="pcg-empty-msg">No has publicado cursos aún.</p>');
            return;
        }

        courses.forEach(course => {
            const cardHtml = `
                <div class="pcg-course-card" data-id="${course.id}">
                    <div class="pcg-course-thumb">
                        <img src="${course.thumbnail_url}" alt="${course.title}">
                        <div class="pcg-course-badges">
                            <span class="pcg-badge pcg-badge-count">${course.lesson_count} Lecciones</span>
                        </div>
                    </div>
                    <div class="pcg-course-content">
                        <h4>${course.title}</h4>
                        <div class="pcg-course-meta">
                            <span class="pcg-course-price">${course.price}</span>
                            <div class="pcg-course-actions">
                                <button class="pcg-btn-icon pcg-btn-edit-course" title="Editar">
                                    <span class="dashicons dashicons-edit"></span>
                                </button>
                                <button class="pcg-btn-icon pcg-btn-delete-course" title="Eliminar">
                                    <span class="dashicons dashicons-trash"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            $grid.append(cardHtml);
        });
    }

    // Edit Course
    $(document).on('click', '.pcg-btn-edit-course', function () {
        const courseId = $(this).closest('.pcg-course-card').data('id');
        $.ajax({
            url: pcgCreatorData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pcg_get_course_for_edit',
                nonce: pcgCreatorData.nonce,
                course_id: courseId
            },
            success: function (response) {
                if (response.success) {
                    const data = response.data;
                    currentCourseId = data.id;
                    $('#pcg-current-course-id').val(currentCourseId);
                    $('#pcg-course-title').val(data.title);
                    $('#pcg-course-description').val(data.description);
                    $('#pcg-course-excerpt').val(data.excerpt || '');
                    updateWordCount('#pcg-course-description', '#pcg-desc-word-count', 700);
                    updateWordCount('#pcg-course-excerpt', '#pcg-excerpt-word-count', 50);
                    $('#pcg-course-price').val(data.price);
                    thumbnailId = data.thumbnail_id;
                    if (data.thumbnail_url) {
                        $('#pcg-thumbnail-preview img').attr('src', data.thumbnail_url);
                        $('#pcg-thumbnail-preview').show();
                        $('#pcg-upload-thumbnail').hide();
                    } else {
                        $('#pcg-thumbnail-preview').hide();
                        $('#pcg-upload-thumbnail').show();
                    }

                    coverPhotoId = data.cover_photo_id;
                    if (data.cover_photo_url) {
                        $('#pcg-cover-preview img').attr('src', data.cover_photo_url);
                        $('#pcg-cover-preview').show();
                        $('#pcg-select-background').hide();
                    } else {
                        $('#pcg-cover-preview').hide();
                        $('#pcg-select-background').show();
                    }

                    if (data.permalink) {
                        currentCoursePermalink = data.permalink;
                        $previewBtn.show();
                    } else {
                        $previewBtn.hide();
                    }

                    $courseLabel.text(data.title).show();

                    $('#pcg-course-progression').prop('checked', data.progression === 'on');

                    $('#pcg-lessons-list').empty();
                    if (data.content.length > 0) {
                        $('.pcg-empty-lessons-state').hide();
                        data.content.forEach(item => {
                            addContentItem(item.type, item);
                        });
                    } else {
                        $('.pcg-empty-lessons-state').show();
                    }

                    // Reset Tabs to "CURSO"
                    $('.pcg-segment').removeClass('active');
                    $('.pcg-segment[data-value="curso"]').addClass('active');
                    $('.pcg-mode-content').hide();
                    $('#pcg-mode-curso').show();

                    // Show form
                    $('#pcg-creator-intro-section').hide();
                    $('#pcg-my-courses-section').hide();
                    $('#pcg-course-form-section').fadeIn(400);
                    $('html, body').animate({
                        scrollTop: $("#pcg-course-form-section").offset().top - 100
                    }, 500);
                }
            }
        });
    });

    // Delete Course
    $(document).on('click', '.pcg-btn-delete-course', function () {
        const $card = $(this).closest('.pcg-course-card');
        const courseId = $card.data('id');
        if (!confirm('¿Estás seguro de que deseas eliminar este curso? Esta acción no se puede deshacer.')) return;
        $.ajax({
            url: pcgCreatorData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pcg_delete_course',
                nonce: pcgCreatorData.nonce,
                course_id: courseId
            },
            success: function (response) {
                if (response.success) {
                    // If we are currently editing THIS course, reset form
                    if (currentCourseId === courseId) {
                        currentCourseId = 0;
                        $('#pcg-current-course-id').val(0);
                        $('#pcg-course-form-section').hide();
                        $('#pcg-creator-intro-section').fadeIn();
                        $('#pcg-my-courses-section').fadeIn();
                    }

                    $card.fadeOut(400, function () {
                        $(this).remove();
                        if ($('#pcg-my-courses-grid').children().length === 0) loadMyCourses();
                    });
                }
            }
        });
    });

    loadMyCourses();
});
