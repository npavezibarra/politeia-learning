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
    const $teachersList = $('#pcg-teachers-list');
    const $courseLabel = $('#pcg-current-course-label');
    const $previewBtn = $('#pcg-btn-preview-course');
    let editCourseRequest = null;

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

        $teachersList.empty();
        $('.pcg-empty-teachers-state').show();

        // Reset Tabs to "CURSO"
        $('.pcg-segment').removeClass('active');
        $('.pcg-segment[data-value="curso"]').addClass('active');
        $('.pcg-mode-content').hide();
        $('#pcg-mode-curso').show();

        // Reset Desc Tabs
        $('.pcg-desc-tab').removeClass('active');
        $('.pcg-desc-tab[data-target="pcg-tab-description"]').addClass('active');
        $('.pcg-tab-content').removeClass('active');
        $('#pcg-tab-description').addClass('active');
    }

    // Show form when "CREATE COURSE" button is clicked
    $('#pcg-show-creator-form').on('click', function () {
        $('#pcg-my-courses-section').fadeOut(300, function () {
            resetForm();
            // Automatically add current user as main author
            addTeacherItem({
                user_id: pcgCreatorData.currentUserId,
                user_name: pcgCreatorData.currentUserName,
                avatar: pcgCreatorData.currentUserAvatar,
                is_main_author: true,
                role_slug: 'Autor principal',
                profit_percentage: 100
            });
            $('#pcg-course-form-section').fadeIn(400);
        });
    });

    // Back to list / Cancel Edit
    $('#pcg-btn-back-to-list, #pcg-btn-cancel-edit').on('click', function () {
        $('#pcg-course-form-section').fadeOut(300, function () {
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
                // Dynamically refresh quiz module for current course
                $(document).trigger('pqc_refresh', { courseId: courseId });
            }
            $('#pcg-mode-evaluacion').fadeIn(300);
        }
    });

    // ── Teacher Management Logic ──
    let teacherSearchTimeout = null;

    function normalizePercentInt(value) {
        const n = Math.round(parseFloat(String(value).replace(',', '.')) || 0);
        if (Number.isNaN(n)) return 0;
        return Math.min(100, Math.max(0, n));
    }

    function normalizeTeacherIdentity(rawName = '', rawEmail = '') {
        let name = (rawName || '').trim();
        let email = (rawEmail || '').trim();

        const match = name.match(/^(.*)\s+\(([^()]+@[^()]+)\)$/);
        if (match) {
            name = match[1].trim();
            if (!email) {
                email = match[2].trim();
            }
        }

        return { name, email };
    }

    function addTeacherItem(data = {}) {
        $('.pcg-empty-teachers-state').hide();

        const userId = data.user_id || '';
        const identity = normalizeTeacherIdentity(data.user_name || '', data.user_email || data.email || '');
        const userName = identity.name;
        const userEmail = identity.email;
        const avatarUrl = data.avatar || '';
        const roleSlug = data.role_slug || '';
        const roleDescription = data.role_description || '';
        const profitPercentage = String(normalizePercentInt(data.profit_percentage ?? 0));
        const isMainAuthor = data.is_main_author || false;
        const hasSelectedUser = Boolean(userId && userName);

        const iconHtml = avatarUrl ? `<img src="${avatarUrl}" class="pcg-item-avatar">` : '<span class="dashicons dashicons-admin-users"></span>';

        const removeBtnHtml = isMainAuthor ? '' : `
            <button type="button" class="pcg-item-btn-remove pcg-teacher-remove" title="Eliminar">
                <span class="dashicons dashicons-trash"></span>
            </button>
        `;

        const itemHtml = `
            <div class="pcg-content-item pcg-teacher-item" data-user-id="${userId}" ${isMainAuthor ? 'data-main="true"' : ''}>
                <div class="pcg-item-header">
                    <div class="pcg-item-expand" title="Ver detalles">
                        <span class="dashicons dashicons-arrow-right-alt2"></span>
                    </div>
                    <div class="pcg-item-icon">
                        ${iconHtml}
                    </div>
                    <div class="pcg-item-input-wrapper">
                        <input type="text" class="pcg-item-input pcg-teacher-name-input" 
                               value="${hasSelectedUser ? '' : userName}" 
                               placeholder="Buscar colaborador..." 
                               ${isMainAuthor ? 'readonly' : ''} 
                               autocomplete="off"
                               style="${hasSelectedUser ? 'display:none;' : ''}">
                        <div class="pcg-teacher-identity ${hasSelectedUser ? '' : 'pcg-teacher-identity-hidden'}">
                            <span class="pcg-teacher-full-name">${userName}</span>
                            <span class="pcg-teacher-email">${userEmail}</span>
                        </div>
                        <div class="pcg-search-results" style="display:none;"></div>
                    </div>
                    <div class="pcg-item-actions">
                        <span class="pcg-teacher-share-badge">${profitPercentage}%</span>
                        ${isMainAuthor ? '<span class="pcg-badge-main-author">Principal</span>' : ''}
                        ${removeBtnHtml}
                    </div>
                </div>
                <div class="pcg-item-details" style="display:none;">
                    <div class="pcg-detail-row">
                        <div class="pcg-detail-field">
                            <label>Rol</label>
                            <input type="text" class="pcg-teacher-role-slug" value="${roleSlug}" placeholder="Ej: Editor de video, Diseñador...">
                        </div>
                        <div class="pcg-detail-field">
                            <label>Participación (%)</label>
                            <input type="number" class="pcg-teacher-profit" value="${profitPercentage}" min="0" max="100" step="1">
                        </div>
                    </div>
                    <div class="pcg-detail-row">
                        <div class="pcg-detail-field" style="flex:1;">
                            <label>Descripción del rol</label>
                            <textarea class="pcg-teacher-description" placeholder="Describe las responsabilidades...">${roleDescription}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        `;

        const $newItem = $(itemHtml);
        $teachersList.append($newItem);
        if (!userName) $newItem.find('.pcg-teacher-name-input').focus();
    }

    // Add Teacher PLUS button
    $(document).on('click', '#pcg-btn-add-teacher', function () {
        addTeacherItem();
    });

    // Remove Teacher
    $(document).on('click', '.pcg-teacher-remove', function () {
        const $item = $(this).closest('.pcg-teacher-item');
        $item.fadeOut(300, function () {
            $(this).remove();
            if ($teachersList.children('.pcg-teacher-item').length === 0) {
                $('.pcg-empty-teachers-state').fadeIn(300);
            }
        });
    });

    // Teacher input search logic
    $(document).on('input', '.pcg-teacher-name-input', function () {
        const $input = $(this);
        const $wrapper = $input.closest('.pcg-item-input-wrapper');
        const $results = $wrapper.find('.pcg-search-results');
        const query = $input.val().trim();

        clearTimeout(teacherSearchTimeout);
        if (query.length < 2) {
            $results.hide().empty();
            return;
        }

        teacherSearchTimeout = setTimeout(() => {
            $.ajax({
                url: pcgCreatorData.ajaxUrl,
                type: 'POST',
                data: {
                    action: pcgCreatorData.teacherSearchAction,
                    nonce: pcgCreatorData.teacherSearchNonce,
                    q: query
                },
                success: function (response) {
                    if (response.success && response.data.length > 0) {
                        $results.empty().show();
                        response.data.forEach(user => {
                            const $resItem = $(`
                                <div class="pcg-search-result-item">
                                    <img src="${user.avatar}" class="pcg-result-avatar">
                                    <div class="pcg-result-info">
                                        <span class="pcg-result-name">${user.name}</span>
                                    </div>
                                </div>
                            `);
                            $resItem.on('click', function () {
                                const selectedIdentity = normalizeTeacherIdentity(user.name || '', user.email || '');
                                $input.val('');
                                const $item = $input.closest('.pcg-teacher-item');
                                $item.attr('data-user-id', user.id);
                                $item.find('.pcg-item-icon').html(`<img src="${user.avatar}" class="pcg-item-avatar">`);
                                $item.find('.pcg-teacher-full-name').text(selectedIdentity.name);
                                $item.find('.pcg-teacher-email').text(selectedIdentity.email);
                                $item.find('.pcg-teacher-identity').removeClass('pcg-teacher-identity-hidden');
                                $input.hide();
                                $results.hide().empty();
                            });
                            $results.append($resItem);
                        });
                    } else {
                        $results.hide().empty();
                    }
                }
            });
        }, 300);
    });

    // Hide teacher search results when clicking outside
    $(document).on('click', function (e) {
        if (!$(e.target).closest('.pcg-item-input-wrapper').length) {
            $('.pcg-teacher-item .pcg-search-results').hide().empty();
        }
    });

    $(document).on('input change', '.pcg-teacher-profit', function () {
        const intValue = normalizePercentInt($(this).val());
        $(this).val(intValue);
        $(this).closest('.pcg-teacher-item').find('.pcg-teacher-share-badge').text(`${intValue}%`);
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
        PL_Cropper.open({
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
        PL_Cropper.open({
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
        // Trigger Quiz Save if the editor is active
        $(document).trigger('pqc_save');

        const $btn = $(this);

        const courseData = {
            id: currentCourseId,
            title: $('#pcg-course-title').val(),
            description: $('#pcg-course-description').val(),
            excerpt: $('#pcg-course-excerpt').val(),
            price: $('#pcg-course-price').val(),
            thumbnail_id: thumbnailId,
            cover_photo_id: coverPhotoId,
            progression: $('#pcg-course-progression').is(':checked') ? 'on' : '',
            teachers: [],
            content: []
        };

        // Collect Teachers Data
        $('#pcg-teachers-list .pcg-teacher-item').each(function () {
            const userId = $(this).attr('data-user-id');
            if (userId) {
                courseData.teachers.push({
                    user_id: userId,
                    role_slug: $(this).find('.pcg-teacher-role-slug').val(),
                    profit_percentage: normalizePercentInt($(this).find('.pcg-teacher-profit').val()),
                    role_description: $(this).find('.pcg-teacher-description').val()
                });
            }
        });

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

        $btn.addClass('loading').prop('disabled', true);

        $.ajax({
            url: pcgCreatorData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pcg_save_course',
                nonce: pcgCreatorData.nonce,
                course_data: courseData
            },
            success: function (response) {
                $btn.removeClass('loading');
                if (response.success) {
                    currentCourseId = response.data.course_id;
                    $('#pcg-current-course-id').val(currentCourseId);
                    $btn.addClass('success');
                    loadMyCourses();
                    setTimeout(() => {
                        $btn.prop('disabled', false).removeClass('success');
                    }, 2000);

                    if (response.data.permalink) {
                        currentCoursePermalink = response.data.permalink;
                        $previewBtn.fadeIn();
                    }
                } else {
                    alert('Error: ' + response.data.message);
                    $btn.prop('disabled', false);
                }
            },
            error: function () {
                $btn.removeClass('loading');
                alert('Ocurrió un error al guardar el curso.');
                $btn.prop('disabled', false);
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

    function showEditLoadingState() {
        resetForm();
        $('#pcg-my-courses-section').hide();
        $('#pcg-course-form-section').show();
        $('.pcg-mode-content').hide();

        if (!$('#pcg-edit-loading').length) {
            $('#pcg-course-form-section').append(`
                <div id="pcg-edit-loading" class="pcg-loading-placeholder">
                    <span class="dashicons dashicons-update spin"></span>
                    <p>Cargando curso...</p>
                </div>
            `);
        }

        $('#pcg-edit-loading').show();
    }

    function hideEditLoadingState() {
        $('#pcg-edit-loading').hide();
        $('#pcg-mode-curso').show();
    }

    // Edit Course
    $(document).on('click', '.pcg-btn-edit-course', function () {
        const $editBtn = $(this);
        const courseId = $editBtn.closest('.pcg-course-card').data('id');
        if (!courseId) return;

        showEditLoadingState();
        $editBtn.prop('disabled', true);

        if (editCourseRequest && editCourseRequest.readyState !== 4) {
            editCourseRequest.abort();
        }

        editCourseRequest = $.ajax({
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

                    // Populate Teachers
                    $teachersList.empty();
                    if (data.teachers && data.teachers.length > 0) {
                        $('.pcg-empty-teachers-state').hide();
                        data.teachers.forEach(teacher => {
                            addTeacherItem({
                                user_id: teacher.id,
                                user_name: teacher.name,
                                avatar: teacher.avatar || '',
                                role_slug: teacher.role_slug || '',
                                profit_percentage: teacher.profit_percentage || '0',
                                role_description: teacher.role_description || '',
                                is_main_author: teacher.id == data.author_id
                            });
                        });
                    } else {
                        // Fallback: If no teachers in table, add current author
                        addTeacherItem({
                            user_id: data.author_id,
                            user_name: data.author_name,
                            avatar: data.author_avatar || '',
                            is_main_author: true,
                            role_slug: 'Autor principal',
                            profit_percentage: 100
                        });
                    }

                    // Reset Tabs to "CURSO"
                    $('.pcg-segment').removeClass('active');
                    $('.pcg-segment[data-value="curso"]').addClass('active');
                    $('.pcg-mode-content').hide();
                    $('#pcg-mode-curso').show();

                    hideEditLoadingState();
                } else {
                    $('#pcg-course-form-section').hide();
                    $('#pcg-my-courses-section').show();
                    alert('Error al obtener los datos del curso: ' + (response.data ? response.data.message : 'Error desconocido'));
                }
            },
            error: function (jqXHR, textStatus) {
                if (textStatus === 'abort') {
                    return;
                }
                $('#pcg-course-form-section').hide();
                $('#pcg-my-courses-section').show();
                alert('Ocurrió un error al cargar el curso para editar.');
            },
            complete: function () {
                $editBtn.prop('disabled', false);
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
