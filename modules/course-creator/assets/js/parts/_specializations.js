/**
 * Specializations Module
 * Handles Specialization (LearnDash Group) Creator UI.
 */
jQuery(document).ready(function ($) {
    (function initSpecializationCreator() {
        if (!$('#pcg-show-specialization-form').length) {
            return;
        }

        let currentGroupId = 0;
        let selectedCourseIds = [];
        let cachedCourses = [];
        let allCoursesPage = 1;
        const allCoursesPerPage = 10;
        let orderRequired = false;

        function resetSpecializationForm() {
            currentGroupId = 0;
            selectedCourseIds = [];
            allCoursesPage = 1;
            $('#pcg-current-group-id').val(0);
            $('#pcg-group-title').val('');
            $('#pcg-group-description').val('');
            $('#pcg-group-price').val('');
            $('#pcg-group-price-free-indicator').hide();
            $('#pcg-current-specialization-label').text('').hide();
            $('#pcg-spec-course-search').val('');

            $('.pcg-spec-segment').removeClass('active');
            $('.pcg-spec-segment[data-value="especializacion"]').addClass('active');
            
            $('.pcg-mode-content').removeClass('is-visible').hide();
            const $target = $('#pcg-spec-mode-especializacion');
            $target.show();
            if ($target[0]) $target[0].offsetHeight;
            $target.addClass('is-visible');

            $('#pcg-spec-all-courses').html(`
                <div class="pcg-loading-placeholder">
                    <span class="dashicons dashicons-update spin"></span>
                    <p>${t('loadingCourses')}</p>
                </div>
            `);
            $('#pcg-spec-courses-pagination').hide();

            $('#pcg-spec-added-courses').html(`
                <div class="pcg-loading-placeholder">
                    <span class="dashicons dashicons-update spin"></span>
                    <p>${t('loadingCourses')}</p>
                </div>
            `);
            $('#pcg-spec-order-required').prop('checked', false);
            orderRequired = false;

            // Teachers tab
            const seed = getCurrentUserTeacherSeed();
            const $list = $('#pcg-group-teachers-list');
            if ($list.length) {
                populateTeachersList($list, [], seed);
            }

            plLearningMeta.reset('group');
        }

        function renderAddedCourses() {
            const $wrap = $('#pcg-spec-added-courses');

            if (!cachedCourses || cachedCourses.length === 0) {
                $wrap.html(`<p class="pcg-empty-msg">${t('noCoursesToAssign')}</p>`);
                return;
            }

            if (!selectedCourseIds || selectedCourseIds.length === 0) {
                $wrap.html(`<p class="pcg-empty-msg">${t('noCoursesAddedYet')}</p>`);
                return;
            }

            const items = selectedCourseIds
                .map(id => cachedCourses.find(c => Number(c.id) === Number(id)))
                .filter(Boolean);

            if (items.length === 0) {
                $wrap.html(`<p class="pcg-empty-msg">${t('noCoursesAddedYet')}</p>`);
                return;
            }

            const html = items.map(c => `
                <div class="pcg-spec-added-row" data-id="${c.id}">
                    <div class="pcg-spec-added-title">${c.title}</div>
                    <button type="button" class="pcg-btn-icon pcg-spec-remove-course" title="${t('remove')}">
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                </div>
            `).join('');

            $wrap.html(html);
            initAddedCoursesSortable();
        }

        function initAddedCoursesSortable() {
            const $wrap = $('#pcg-spec-added-courses');
            if (!$wrap.length || !$.fn.sortable) {
                return;
            }

            $wrap.addClass('pcg-sort-enabled');

            try {
                if ($wrap.data('ui-sortable')) {
                    $wrap.sortable('destroy');
                }
            } catch (_) { }

            $wrap.sortable({
                axis: 'y',
                helper: 'clone',
                appendTo: 'body',
                containment: 'document',
                placeholder: 'pcg-sortable-placeholder',
                forcePlaceholderSize: true,
                cancel: 'button, .pcg-spec-remove-course',
                opacity: 0.9,
                tolerance: 'pointer',
                zIndex: 999999,
                start: function (event, ui) {
                    ui.helper.css({
                        width: ui.item.outerWidth(),
                        boxSizing: 'border-box'
                    });
                },
                update: function () {
                    const ids = [];
                    $wrap.find('.pcg-spec-added-row').each(function () {
                        const id = Number($(this).attr('data-id')) || 0;
                        if (id) ids.push(id);
                    });
                    selectedCourseIds = ids;
                }
            });
        }

        function addCourseToSpecialization(courseId) {
            const id = Number(courseId) || 0;
            if (!id) return;
            if (!selectedCourseIds.includes(id)) {
                selectedCourseIds.push(id);
            }

            const course = (cachedCourses || []).find(c => Number(c.id) === id);
            if (course && course.author_id) {
                ensureTeacherForUser($('#pcg-group-teachers-list'), {
                    id: Number(course.author_id),
                    name: course.author_name || '',
                    email: course.author_email || '',
                    avatar: course.author_avatar || ''
                });
            }

            $('#pcg-spec-course-search').val('');
            renderAddedCourses();
            renderAllCourses();
        }

        function removeCourseFromSpecialization(courseId) {
            const id = Number(courseId) || 0;
            if (!id) return;
            selectedCourseIds = selectedCourseIds.filter(x => Number(x) !== id);
            renderAddedCourses();
            renderAllCourses();
        }

        function getFilteredCourses() {
            const q = String($('#pcg-spec-course-search').val() || '').trim().toLowerCase();
            if (!q) {
                return cachedCourses || [];
            }
            return (cachedCourses || []).filter(c => String(c.title || '').toLowerCase().includes(q));
        }

        function renderAllCourses() {
            const $wrap = $('#pcg-spec-all-courses');
            const courses = getFilteredCourses();

            if (!courses || courses.length === 0) {
                $wrap.html(`<p class="pcg-empty-msg">${t('noCourses')}</p>`);
                $('#pcg-spec-courses-pagination').hide();
                return;
            }

            const totalPages = Math.max(1, Math.ceil(courses.length / allCoursesPerPage));
            if (allCoursesPage > totalPages) {
                allCoursesPage = totalPages;
            }
            if (allCoursesPage < 1) {
                allCoursesPage = 1;
            }

            const start = (allCoursesPage - 1) * allCoursesPerPage;
            const pageItems = courses.slice(start, start + allCoursesPerPage);
            const selected = new Set(selectedCourseIds.map(id => Number(id)));

            const html = pageItems.map(c => {
                const isAdded = selected.has(Number(c.id));
                return `
                    <div class="pcg-spec-all-row" data-id="${c.id}">
                        <div class="pcg-spec-all-title">${c.title}</div>
                        <button type="button" class="pcg-spec-add-btn" ${isAdded ? 'disabled' : ''}>
                            ${isAdded ? t('added') : t('add')}
                        </button>
                    </div>
                `;
            }).join('');

            $wrap.html(html);

            if (courses.length > allCoursesPerPage) {
                $('#pcg-spec-courses-pagination').show();
                $('#pcg-spec-page-info').text(`${allCoursesPage} / ${totalPages}`);
                $('#pcg-spec-page-prev').prop('disabled', allCoursesPage <= 1);
                $('#pcg-spec-page-next').prop('disabled', allCoursesPage >= totalPages);
            } else {
                $('#pcg-spec-courses-pagination').hide();
            }
        }

        function loadCoursesForSpecialization() {
            return $.ajax({
                url: pcgCreatorData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pcg_get_published_courses',
                    nonce: pcgCreatorData.nonce
                }
            }).done(function (response) {
                if (!response || !response.success) {
                    $('#pcg-spec-all-courses').html(`<p class="pcg-empty-msg">${t('failedToLoadCourses')}</p>`);
                    $('#pcg-spec-added-courses').html(`<p class="pcg-empty-msg">${t('failedToLoadCourses')}</p>`);
                    $('#pcg-spec-courses-pagination').hide();
                    return;
                }

                const courses = response.data || [];
                cachedCourses = courses;
                renderAddedCourses();
                renderAllCourses();
            });
        }

        function openSpecializationFormForEdit(groupId) {
            const id = Number(groupId) || 0;
            if (!id) return;

            $('#pcg-my-specializations-section').fadeOut(200, function () {
                resetSpecializationForm();
                $('#pcg-specialization-form-section').show();
                $('#pcg-specialization-form-section').append(`
                    <div id="pcg-spec-edit-loading" class="pcg-loading-placeholder">
                        <span class="dashicons dashicons-update spin"></span>
                        <p>${t('loadingSpecialization')}</p>
                    </div>
                `);

                $.ajax({
                    url: pcgCreatorData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'pcg_get_specialization_for_edit',
                        nonce: pcgCreatorData.nonce,
                        group_id: id
                    }
                }).done(function (response) {
                    $('#pcg-spec-edit-loading').remove();
                    if (!response || !response.success) {
                        window.pcgShowToast(t('errorLoadingSpecialization'), 'error');
                        $('#pcg-specialization-form-section').hide();
                        $('#pcg-my-specializations-section').show();
                        return;
                    }

                    const data = response.data;
                    currentGroupId = Number(data.id) || 0;
                    selectedCourseIds = (data.course_ids || []).map(x => Number(x));
                    orderRequired = Boolean(data.order_required);

                    $('#pcg-current-group-id').val(currentGroupId);
                    $('#pcg-group-title').val(data.title || '');
                    $('#pcg-group-description').val(data.description || '');
                    $('#pcg-group-price').val(data.price || '');
                    $('#pcg-spec-order-required').prop('checked', orderRequired);

                    plLearningMeta.setSelection('group', data.category_ids || [], data.tags || []);

                    if (data.title) {
                        $('#pcg-current-specialization-label').text(data.title).show();
                    }

                    const priceNum = parseFloat(String(data.price || '').replace(',', '.')) || 0;
                    if (priceNum === 0) {
                        $('#pcg-group-price-free-indicator').show();
                    }

                    $('.pcg-spec-segment').removeClass('active');
                    $('.pcg-spec-segment[data-value="especializacion"]').addClass('active');
                    
                    $('.pcg-mode-content').removeClass('is-visible').hide();
                    const $target = $('#pcg-spec-mode-especializacion');
                    $target.show();
                    if ($target[0]) $target[0].offsetHeight;
                    $target.addClass('is-visible');

                    populateTeachersList($('#pcg-group-teachers-list'), data.teachers || [], {
                        id: Number(data.author_id || 0),
                        name: data.author_name || '',
                        avatar: data.author_avatar || ''
                    });
                    (data.included_authors || []).forEach(a => {
                        ensureTeacherForUser($('#pcg-group-teachers-list'), {
                            id: Number(a.id),
                            name: a.name || '',
                            email: a.email || '',
                            avatar: a.avatar || ''
                        });
                    });

                    loadCoursesForSpecialization().done(function () {
                        (selectedCourseIds || []).forEach(cid => {
                            const course = (cachedCourses || []).find(c => Number(c.id) === Number(cid));
                            if (course && course.author_id) {
                                ensureTeacherForUser($('#pcg-group-teachers-list'), {
                                    id: Number(course.author_id),
                                    name: course.author_name || '',
                                    email: course.author_email || '',
                                    avatar: course.author_avatar || ''
                                });
                            }
                        });
                    });
                }).fail(function () {
                    $('#pcg-spec-edit-loading').remove();
                    window.pcgShowToast(t('errorLoadingSpecializationGeneric'), 'error');
                    $('#pcg-specialization-form-section').hide();
                    $('#pcg-my-specializations-section').show();
                });
            });
        }

        function getSpecializationPayload() {
            const meta = plLearningMeta.getPayload('group');
            return {
                id: currentGroupId,
                title: $('#pcg-group-title').val(),
                description: $('#pcg-group-description').val(),
                price: $('#pcg-group-price').val(),
                course_ids: selectedCourseIds,
                order_required: orderRequired ? 1 : 0,
                teachers: collectTeachers($('#pcg-group-teachers-list')),
                split_locked: Boolean($('#pcg-group-teachers-list').data('splitLocked')),
                category_ids: meta.category_ids,
                tag_ids: meta.tag_ids,
            };
        }

        $('#pcg-show-specialization-form').on('click', function () {
            $('#pcg-my-specializations-section').fadeOut(300, function () {
                resetSpecializationForm();
                $('#pcg-specialization-form-section').fadeIn(400);
                loadCoursesForSpecialization();
            });
        });

        $('#pcg-btn-back-to-specializations').on('click', function () {
            $('#pcg-specialization-form-section').fadeOut(300, function () {
                $('#pcg-my-specializations-section').fadeIn();
                resetSpecializationForm();
            });
        });

        $(document).on('click', '.pcg-spec-segment', function () {
            $('.pcg-spec-segment').removeClass('active');
            $(this).addClass('active');

            const mode = $(this).data('value');
            $('.pcg-mode-content').removeClass('is-visible').hide();
            
            const $target = $(`#pcg-spec-mode-${mode}`);
            $target.show();
            if ($target[0]) $target[0].offsetHeight;
            $target.addClass('is-visible');

            if (mode === 'cursos') {
                loadCoursesForSpecialization();
            } else if (mode === 'meta') {
                plLearningMeta.render('group');
            }
        });

        $('#pcg-group-title').on('input', function () {
            const title = $(this).val();
            if (title) {
                $('#pcg-current-specialization-label').text(title).show();
            } else {
                $('#pcg-current-specialization-label').hide();
            }
        });

        $('#pcg-group-price').on('input change', function () {
            const price = parseFloat($(this).val()) || 0;
            if (price === 0) {
                $('#pcg-group-price-free-indicator').fadeIn(200);
            } else {
                $('#pcg-group-price-free-indicator').fadeOut(200);
            }
        });

        $('#pcg-spec-order-required').on('change', function () {
            orderRequired = $(this).is(':checked');
        });

        $('#pcg-spec-course-search').on('input', function () {
            allCoursesPage = 1;
            renderAllCourses();
        });

        $('#pcg-spec-course-search').on('keydown', function (e) {
            if (e.key === 'Escape') {
                $(this).val('');
                allCoursesPage = 1;
                renderAllCourses();
            }
        });

        $('#pcg-spec-page-prev').on('click', function () {
            allCoursesPage = Math.max(1, allCoursesPage - 1);
            renderAllCourses();
        });

        $('#pcg-spec-page-next').on('click', function () {
            allCoursesPage = allCoursesPage + 1;
            renderAllCourses();
        });

        $(document).on('click', '.pcg-spec-add-btn', function () {
            const courseId = $(this).closest('.pcg-spec-all-row').data('id');
            addCourseToSpecialization(courseId);
        });

        $(document).on('click', '.pcg-spec-remove-course', function () {
            const courseId = $(this).closest('.pcg-spec-added-row').data('id');
            removeCourseFromSpecialization(courseId);
        });

        $('.pcg-btn-save-specialization').on('click', function () {
            const $btn = $(this);
            const payload = getSpecializationPayload();

            if (!payload.title) {
                window.pcgShowToast(t('pleaseEnterSpecializationName'), 'error');
                return;
            }

            $btn.addClass('loading').prop('disabled', true);

            $.ajax({
                url: pcgCreatorData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pcg_save_specialization',
                    nonce: pcgCreatorData.nonce,
                    group_data: payload
                },
                success: function (response) {
                    $btn.removeClass('loading');
                    if (response && response.success) {
                        currentGroupId = response.data.group_id;
                        $('#pcg-current-group-id').val(currentGroupId);
                        if (response.data && response.data.snapshot_status === 'pending') {
                            window.pcgShowToast(t('approvalRequestSent'), 'info');
                        }
                        $btn.addClass('success');
                        if (typeof refreshActiveList === 'function') refreshActiveList();
                        setTimeout(() => {
                            $btn.prop('disabled', false).removeClass('success');
                        }, 2000);
                    } else {
                        window.pcgShowToast(t('errorPrefix') + (response && response.data && response.data.message ? response.data.message : t('unknownError')), 'error');
                        $btn.prop('disabled', false);
                    }
                },
                error: function () {
                    $btn.removeClass('loading');
                    window.pcgShowToast(t('errorSavingSpecialization'), 'error');
                    $btn.prop('disabled', false);
                }
            });
        });

        $(document).on('click', '.pcg-btn-edit-specialization', function (e) {
            e.preventDefault();
            e.stopPropagation();
            const groupId = $(this).closest('.pcg-specialization-card').data('id');
            openSpecializationFormForEdit(groupId);
        });

        $(document).on('click', '.pcg-btn-delete-specialization', function (e) {
            e.preventDefault();
            e.stopPropagation();
            const groupId = $(this).closest('.pcg-specialization-card').data('id');
            if (!groupId) return;
            if (!confirm(t('confirmDeleteSpecialization'))) return;

            $.ajax({
                url: pcgCreatorData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pcg_delete_specialization',
                    nonce: pcgCreatorData.nonce,
                    group_id: groupId
                },
                success: function (response) {
                    if (response && response.success) {
                        if (typeof refreshActiveList === 'function') refreshActiveList();
                    } else {
                        window.pcgShowToast(t('errorPrefix') + (response && response.data && response.data.message ? response.data.message : t('couldNotDelete')), 'error');
                    }
                },
                error: function () {
                    window.pcgShowToast(t('errorDeletingSpecialization'), 'error');
                }
            });
        });
    })();
});
