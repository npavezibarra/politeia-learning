/**
 * Course Creator Dashboard JS - Main Orchestrator
 */
jQuery(document).ready(function ($) {
    console.log('Politeia Course Creator Dashboard Initialized');

    // ───────────────────────────────────────────────────────────
    // Global Course State
    // ───────────────────────────────────────────────────────────
    window.pcgCourseState = {
        id: 0,
        thumbnailId: 0,
        coverPhotoId: 0,
        certificateAttachmentId: 0,
        certificateLogoAttachmentId: 0,
        certificateSignatureAttachmentId: 0,
        permalink: '',
        status: 'publish'
    };

    // Pending approvals index (received from shared logic)
    let pendingApprovalsIndex = { group: {}, program: {} };

    const $list = $('#pcg-lessons-list');
    const $teachersList = $('#pcg-teachers-list');
    const $courseLabel = $('#pcg-current-course-label');
    const $previewBtn = $('#pcg-btn-preview-course');

    // ───────────────────────────────────────────────────────────
    // UI Logic & Utilities
    // ───────────────────────────────────────────────────────────

    // Tab Switcher
    $(document).on('click', '.pcg-desc-tab', function () {
        var target = $(this).data('target');
        $('.pcg-desc-tab').removeClass('active');
        $(this).addClass('active');
        $('.pcg-tab-content').removeClass('active');
        $('#' + target).addClass('active');
    });

    // Word Counter
    function countWords(text) {
        text = text.trim();
        if (!text) return 0;
        return text.split(/\s+/).length;
    }

    function updateWordCount(textareaId, counterId, maxWords) {
        var text = $(textareaId).val();
        var count = countWords(text);
        var $counter = $(counterId);
        $counter.text(count + ' / ' + maxWords + ' ' + t('words'));
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

    function updatePublishButton() {
        const $btn = $('#pcg-btn-toggle-publish-course');
        if (!$btn.length) return;
        const isPublished = window.pcgCourseState.status === 'publish';
        $btn.attr('data-status', window.pcgCourseState.status);
        $btn.toggleClass('is-unpublish', isPublished);
        $btn.text(isPublished ? 'UNPUBLISH' : 'PUBLISH');
    }

    $(document).on('click', '#pcg-btn-toggle-publish-course', function () {
        if (!window.pcgCourseState.id) return;
        window.pcgCourseState.status = window.pcgCourseState.status === 'publish' ? 'draft' : 'publish';
        updatePublishButton();
        $('.pcg-btn-save-course').trigger('click');
    });

    // Mirror price inputs across tabs
    $(document).on('input change', '#pcg-course-price', function () {
        const val = $(this).val();
        const price = parseFloat(val) || 0;
        
        // Sync values to secondary price inputs
        $('[id^="pcg-course-price-"]').each(function() {
            if ($(this).val() !== val) $(this).val(val);
        });

        // Toggle "Gratis" indicators
        $('.pcg-price-free-indicator').toggle(price === 0);
    });

    // Secondary price inputs mirror back to main
    $(document).on('input change', '[id^="pcg-course-price-"]', function () {
        const val = $(this).val();
        const $main = $('#pcg-course-price');
        if ($main.length && $main.val() !== val) {
            $main.val(val).trigger('input');
        }
    });

    // ───────────────────────────────────────────────────────────
    // Form & Data Orchestration
    // ───────────────────────────────────────────────────────────

    function setCourseMode(mode) {
        const m = String(mode || 'curso');
        const $seg = $(`#pcg-course-form-section .pcg-segment[data-value="${m}"]`);
        $('.pcg-segment').removeClass('active');
        if ($seg.length) $seg.addClass('active');

        $('.pcg-mode-content').removeClass('is-visible').hide();
        const $target = $(`#pcg-mode-${m}`);
        if ($target.length) {
            $target.show();
            if ($target[0]) $target[0].offsetHeight;
            $target.addClass('is-visible');
        }

        placeCourseSidebar(m);

        if (m === 'lecciones' && typeof window.initSortableLessons === 'function') window.initSortableLessons();
        if (m === 'meta' && typeof plLearningMeta !== 'undefined') plLearningMeta.render('course');
    }

    function resetForm() {
        window.pcgCourseState.id = 0;
        window.pcgCourseState.thumbnailId = 0;
        window.pcgCourseState.coverPhotoId = 0;
        window.pcgCourseState.certificateAttachmentId = 0;
        window.pcgCourseState.certificateLogoAttachmentId = 0;
        window.pcgCourseState.certificateSignatureAttachmentId = 0;
        window.pcgCourseState.permalink = '';
        window.pcgCourseState.status = 'publish';

        $('#pcg-current-course-id').val(0);
        $('#pcg-course-title').val('');
        $('#pcg-course-description').val('');
        $('#pcg-course-excerpt').val('');
        $('#pcg-course-price').val('').trigger('input');
        
        updateWordCount('#pcg-course-description', '#pcg-desc-word-count', 700);
        updateWordCount('#pcg-course-excerpt', '#pcg-excerpt-word-count', 50);
        
        $('#pcg-thumbnail-preview, #pcg-cover-preview, #pcg-certificate-logo-preview, #pcg-certificate-signature-preview').hide().find('img').attr('src', '');
        
        $('#pcg-certificate-title, #pcg-certificate-congrats, #pcg-cert-signature-label').val('');
        updateWordCount('#pcg-certificate-congrats', '#pcg-cert-word-count', 50);
        
        if (typeof window.updateCertificatePreview === 'function') window.updateCertificatePreview();
        updatePublishButton();

        $list.empty();
        $('#pcg-course-progression').prop('checked', false);
        $('.pcg-empty-lessons-state').show();
        $courseLabel.text('').hide();
        $previewBtn.hide();

        if (typeof window.resetTeachersList === 'function') window.resetTeachersList($teachersList);
        if (typeof plLearningMeta !== 'undefined') plLearningMeta.reset('course');

        // Reset Tabs to "CURSO"
        setCourseMode('curso');

        $('.pcg-desc-tab').removeClass('active');
        $('.pcg-desc-tab[data-target="pcg-tab-description"]').addClass('active');
        $('.pcg-tab-content').removeClass('active');
        $('#pcg-tab-description').addClass('active');
    }

    // Expose create handler for smartphone action bar (mobile custom UI)
    window.pcgOpenCourseCreate = function () {
        $('#pcg-my-courses-section').fadeOut(300, function () {
            resetForm();
            if (typeof window.addTeacherItem === 'function') {
                window.addTeacherItem({
                    user_id: pcgCreatorData.currentUserId,
                    user_name: pcgCreatorData.currentUserName,
                    avatar: pcgCreatorData.currentUserAvatar,
                    is_main_author: true,
                    role_slug: t('mainAuthorRoleSlug'),
                    profit_percentage: 100
                }, $teachersList);
            }
            $('#pcg-course-form-section').fadeIn(400);
        });
    };

    // CREATE COURSE (delegated: the list section can be re-rendered via AJAX)
    $(document).on('click', '#pcg-show-creator-form', function (e) {
        if (e && typeof e.preventDefault === 'function') e.preventDefault();
        if (typeof window.pcgOpenCourseCreate === 'function') window.pcgOpenCourseCreate();
    });

    // CANCEL / BACK
    $(document).on('click', '#pcg-btn-back-to-list, #pcg-btn-cancel-edit', function (e) {
        if (e && typeof e.preventDefault === 'function') e.preventDefault();
        $('#pcg-course-form-section').fadeOut(300, function () {
            $('#pcg-my-courses-section').fadeIn();
            resetForm();
        });
    });

    $('#pcg-course-title').on('input', function () {
        const title = $(this).val();
        $courseLabel.text(title).toggle(!!title);
    });

    // ───────────────────────────────────────────────────────────
    // Save Logic
    // ───────────────────────────────────────────────────────────

    $('.pcg-btn-save-course').on('click', function () {
        const $btn = $(this);

        function setSaveButtonState(state) {
            const $icon = $btn.find('.dashicons').first();
            if ($icon.length) {
                $icon.removeClass('dashicons-saved dashicons-update dashicons-yes-alt dashicons-warning');
                if (state === 'loading') $icon.addClass('dashicons-update');
                else if (state === 'success') $icon.addClass('dashicons-yes-alt');
                else if (state === 'error') $icon.addClass('dashicons-warning');
                else $icon.addClass('dashicons-saved');
            }
        }

        // Trigger Quiz Save if needed
        if (typeof window.triggerQuizSave === 'function') window.triggerQuizSave(true);

        const meta = typeof plLearningMeta !== 'undefined' ? plLearningMeta.getPayload('course') : { category_ids: [], tag_ids: [] };
        
        const courseData = {
            id: window.pcgCourseState.id,
            status: window.pcgCourseState.status,
            title: $('#pcg-course-title').val(),
            description: $('#pcg-course-description').val(),
            excerpt: $('#pcg-course-excerpt').val(),
            price: $('#pcg-course-price').val(),
            thumbnail_id: window.pcgCourseState.thumbnailId,
            cover_photo_id: window.pcgCourseState.coverPhotoId,
            certificate_attachment_id: window.pcgCourseState.certificateAttachmentId,
            certificate_title: $('#pcg-certificate-title').val(),
            certificate_congrats: $('#pcg-certificate-congrats').val(),
            certificate_logo_attachment_id: window.pcgCourseState.certificateLogoAttachmentId,
            certificate_signature_attachment_id: window.pcgCourseState.certificateSignatureAttachmentId,
            certificate_signature_label: $('#pcg-cert-signature-label').val(),
            progression: $('#pcg-course-progression').is(':checked') ? 'on' : '',
            teachers: typeof window.collectTeachers === 'function' ? window.collectTeachers($teachersList) : [],
            content: [],
            category_ids: meta.category_ids,
            tag_ids: meta.tag_ids,
        };

        // Collect lessons/sections
        $('#pcg-lessons-list .pcg-content-item').each(function () {
            const $escrito = $(this).find('.pcg-lesson-escrito-id');
            courseData.content.push({
                type: $(this).data('type'),
                title: $(this).find('.pcg-item-input').val(),
                video_url: $(this).find('.pcg-lesson-video-url').val() || '',
                available_date: $(this).find('.pcg-lesson-available-date').val() || '',
                escrito_id: $escrito.length ? (Number($escrito.val() || 0) || 0) : 0
            });
        });

        if (!courseData.title) {
            window.pcgShowToast(t('pleaseEnterCourseTitle'), 'error');
            return;
        }

        setSaveButtonState('loading');
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
                    window.pcgCourseState.id = response.data.course_id;
                    if (response.data && response.data.status) {
                        window.pcgCourseState.status = response.data.status;
                        updatePublishButton();
                    }
                    $('#pcg-current-course-id').val(window.pcgCourseState.id);
                    setSaveButtonState('success');
                    $btn.addClass('success');
                    
                    if (typeof window.refreshActiveList === 'function') window.refreshActiveList();

                    setTimeout(() => {
                        $btn.prop('disabled', false).removeClass('success');
                        setSaveButtonState('default');
                    }, 2000);

                    if (response.data.permalink) {
                        window.pcgCourseState.permalink = response.data.permalink;
                        $previewBtn.fadeIn();
                    }
                } else {
                    window.pcgShowToast(t('errorPrefix') + response.data.message, 'error');
                    setSaveButtonState('error');
                    $btn.prop('disabled', false);
                    setTimeout(() => setSaveButtonState('default'), 2000);
                }
            },
            error: function () {
                $btn.removeClass('loading');
                window.pcgShowToast(t('errorSavingCourse'), 'error');
                setSaveButtonState('error');
                $btn.prop('disabled', false);
                setTimeout(() => setSaveButtonState('default'), 2000);
            }
        });
    });

    // Preview Button click
    $previewBtn.on('click', function () {
        if (window.pcgCourseState.permalink) {
            window.open(window.pcgCourseState.permalink, '_blank');
        }
    });

    // ───────────────────────────────────────────────────────────
    // Load & Render Orchestration
    // ───────────────────────────────────────────────────────────

    window.refreshActiveList = function () {
        const context = getListContext();
        if (context === 'specializations' && typeof window.loadMySpecializations === 'function') return window.loadMySpecializations();
        if (context === 'programas' && typeof window.loadMyProgramas === 'function') return window.loadMyProgramas();
        if (context === 'escritos') return loadMyEscritos();
        return loadMyCourses();
    };

    function getListContext() {
        if ($('#specialization-grid').length > 0) return 'specializations';
        if ($('#programas-grid').length > 0) return 'programas';
        if ($('#pcg-my-escritos-grid').length > 0) return 'escritos';
        return 'courses';
    }

    function getActiveGrid() {
        const context = getListContext();
        if (context === 'specializations') return $('#specialization-grid');
        if (context === 'programas') return $('#programas-grid');
        if (context === 'escritos') return $('#pcg-my-escritos-grid');
        return $('#pcg-my-courses-grid');
    }

    function loadMyCourses() {
        $.ajax({
            url: pcgCreatorData.ajaxUrl,
            type: 'POST',
            data: { action: 'pcg_get_my_courses', nonce: pcgCreatorData.nonce },
            success: function (response) {
                if (response.success) renderCourses(response.data);
            }
        });
    }

    function loadMyEscritos() {
        $.ajax({
            url: pcgCreatorData.ajaxUrl,
            type: 'POST',
            data: { action: 'pcg_get_my_escritos', nonce: pcgCreatorData.nonce },
            success: function (response) {
                if (response.success && typeof window.renderEscritos === 'function') window.renderEscritos(response.data);
            }
        });
    }

    function renderCourses(courses) {
        const $grid = getActiveGrid();
        $grid.empty();

        if (courses.length === 0) {
            $grid.append(`<p class="pcg-empty-msg">${t('noPublishedCoursesYet')}</p>`);
            return;
        }

        courses.forEach(course => {
            const thumb = course.thumbnail_url || '';
            const thumbClass = thumb ? '' : ' pcg-course-thumb--no-image';
            const cardHtml = `
                <div class="pcg-course-card" data-id="${course.id}">
                    <div class="pcg-course-thumb${thumbClass}">
                        ${thumb ? `<img src="${thumb}" alt="${course.title}">` : ''}
                        <div class="pcg-course-badges">
                            <span class="pcg-badge pcg-badge-count">${course.lesson_count} ${t('lessons')}</span>
                        </div>
                    </div>
                    <div class="pcg-course-content">
                        <h4>${course.title}</h4>
                        <div class="pcg-course-meta">
                            <span class="pcg-course-price">${course.price}</span>
                            <div class="pcg-course-actions">
                                <button class="pcg-btn-edit-course pcg-card-action-edit" title="${t('edit')}" type="button">EDITAR</button>
                                <button class="pcg-btn-delete-course pcg-card-action-delete" aria-label="Delete" title="${t('delete')}" type="button">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            $grid.append(cardHtml);
        });
    }

    // EDIT COURSE
    $(document).on('click', '.pcg-btn-edit-course', function () {
        const courseId = $(this).closest('.pcg-course-card').data('id');
        if (!courseId) return;

        resetForm();
        $('#pcg-my-courses-section').hide();
        $('#pcg-course-form-section').show();

        // Set initial tab to CURSO with new transition system
        $('.pcg-segment').removeClass('active');
        $('.pcg-segment[data-value="curso"]').addClass('active');
        $('.pcg-mode-content').removeClass('is-visible').hide();
        
        const $target = $('#pcg-mode-curso');
        $target.show();
        // Trigger reflow for CSS transition
        if ($target[0]) $target[0].offsetHeight;
        $target.addClass('is-visible');

        placeCourseSidebar('curso');

        $.ajax({
            url: pcgCreatorData.ajaxUrl,
            type: 'POST',
            data: { action: 'pcg_get_course_for_edit', nonce: pcgCreatorData.nonce, course_id: courseId },
            success: function (response) {
                if (response.success) {
                    const data = response.data;
                    window.pcgCourseState.id = data.id;
                    window.pcgCourseState.status = data.status;
                    window.pcgCourseState.permalink = data.permalink || '';
                    
                    $('#pcg-current-course-id').val(data.id);
                    $('#pcg-course-title').val(data.title).trigger('input');
                    $('#pcg-course-description').val(data.description).trigger('input');
                    $('#pcg-course-excerpt').val(data.excerpt).trigger('input');
                    $('#pcg-course-price').val(data.price).trigger('input');
                    
                    $('#pcg-course-progression').prop('checked', data.progression === 'on');
                    
                    window.pcgCourseState.thumbnailId = data.thumbnail_id;
                    if (data.thumbnail_url) {
                        $('#pcg-thumbnail-preview img').attr('src', data.thumbnail_url);
                        $('#pcg-thumbnail-preview').show();
                    }

                    window.pcgCourseState.coverPhotoId = data.cover_photo_id;
                    if (data.cover_photo_url) {
                        $('#pcg-cover-preview img').attr('src', data.cover_photo_url);
                        $('#pcg-cover-preview').show();
                    }

                    window.pcgCourseState.certificateLogoAttachmentId = data.certificate_logo_attachment_id;
                    if (data.certificate_logo_url) {
                        $('#pcg-certificate-logo-preview img').attr('src', data.certificate_logo_url);
                        $('#pcg-certificate-logo-preview').show();
                    }

                    window.pcgCourseState.certificateSignatureAttachmentId = data.certificate_signature_attachment_id;
                    if (data.certificate_signature_url) {
                        $('#pcg-certificate-signature-preview img').attr('src', data.certificate_signature_url);
                        $('#pcg-certificate-signature-preview').show();
                    }

                    $('#pcg-certificate-title').val(data.certificate_title);
                    $('#pcg-certificate-congrats').val(data.certificate_congrats).trigger('input');
                    $('#pcg-cert-signature-label').val(data.certificate_signature_label);
                    
                    if (typeof window.updateCertificatePreview === 'function') window.updateCertificatePreview();
                    updatePublishButton();
                    if (window.pcgCourseState.permalink) $previewBtn.show();

                    // Load Content
                    if (Array.isArray(data.content)) {
                        data.content.forEach(item => {
                            if (typeof window.addCourseContentItem === 'function') {
                                window.addCourseContentItem(item.type, item);
                            }
                        });
                    }

                    // Load Teachers
                    if (Array.isArray(data.teachers) && typeof window.addTeacherItem === 'function') {
                        data.teachers.forEach(t => window.addTeacherItem(t, $teachersList));
                    }

                    // Load Meta
                    if (data.categories || data.tags) {
                        if (typeof plLearningMeta !== 'undefined') {
                            plLearningMeta.set('course', { category_ids: data.categories || [], tag_ids: data.tags || [] });
                        }
                    }
                }
            }
        });
    });

    // ───────────────────────────────────────────────────────────
    // Sidebar & Checklist Orchestration
    // ───────────────────────────────────────────────────────────

    function placeCourseActions(mode) {
        const $actions = $('#pcg-course-actions');
        if (!$actions.length) return;
        const slots = {
            curso: '#pcg-mode-curso .pcg-sidecard__section',
            evaluacion: '#pcg-mode-evaluacion .pcg-sidecard__actions-slot',
            lecciones: '#pcg-mode-lecciones .pcg-sidecard__actions-slot',
            meta: '#pcg-mode-meta .pcg-sidecard__actions-slot',
            certificado: '#pcg-mode-certificado .pcg-sidecard__actions-slot'
        };
        const $slot = $(slots[mode] || slots.curso);
        if ($slot.length) {
            if (mode === 'curso') $slot.prepend($actions);
            else $slot.append($actions);
        }
    }

    function placeCourseChecklist(mode) {
        const $checklist = $('#pcg-course-checklist');
        if (!$checklist.length) return;
        const slots = {
            curso: '#pcg-mode-curso .pcg-course-editor__right',
            evaluacion: '#pcg-mode-evaluacion .pcg-checklist-slot',
            lecciones: '#pcg-mode-lecciones .pcg-checklist-slot',
            meta: '#pcg-mode-meta .pcg-checklist-slot',
            certificado: '#pcg-mode-certificado .pcg-checklist-slot'
        };
        const $slot = $(slots[mode] || slots.curso);
        if ($slot.length) $slot.append($checklist);
    }

    function placeCourseSidebar(mode) {
        placeCourseActions(mode);
        placeCourseChecklist(mode);
    }

    let checklistTimeout = null;
    function updateCourseChecklist() {
        if (checklistTimeout) clearTimeout(checklistTimeout);
        checklistTimeout = setTimeout(() => {
            const $root = $('#pcg-course-checklist');
            if (!$root.length) return;

            const setDone = (key, done) => { $root.find(`.pcg-checklist-item[data-check="${key}"]`).toggleClass('is-done', !!done); };
            const hasV = (s) => String($(s).val() || '').trim().length > 0;
            const hasI = (s) => String($(s).find('img').attr('src') || '').trim().length > 0;

            setDone('title', hasV('#pcg-course-title'));
            setDone('price', hasV('#pcg-course-price'));
            setDone('description', hasV('#pcg-course-description'));
            setDone('excerpt', hasV('#pcg-course-excerpt'));
            setDone('thumbnail', hasI('#pcg-thumbnail-preview'));
            setDone('cover', hasI('#pcg-cover-preview'));
            setDone('teachers', $teachersList.find('.pcg-teacher-item').length > 0);
            setDone('lessons', $list.find('.pcg-content-item').length > 0);
            
            const quizCount = $('#pcg-quiz-creator-container .pqc-slide').length;
            setDone('evaluation', quizCount > 0);
            $('#pcg-check-lessons-count').text($list.find('.pcg-content-item').length || '');
            $('#pcg-check-eval-count').text(quizCount || '');
        }, 150);
    }

    function initChecklistObservers() {
        if (!window.MutationObserver) return;
        const schedule = () => setTimeout(updateCourseChecklist, 100);
        const observe = (id) => {
            const el = document.getElementById(id);
            if (el) new MutationObserver(schedule).observe(el, { childList: true, subtree: true, attributes: true });
        };
        ['pcg-teachers-list', 'pcg-quiz-creator-container', 'pcg-thumbnail-preview', 'pcg-cover-preview', 'pcg-lessons-list'].forEach(observe);
    }

    // Segments navigation logic
    $(document).on('click', '#pcg-course-form-section .pcg-segment', function () {
        const mode = $(this).data('value');
        setCourseMode(mode);
    });

    initChecklistObservers();
    $(document).on('input change', 'input, textarea, select', updateCourseChecklist);
    updateCourseChecklist();
    refreshActiveList();

});
