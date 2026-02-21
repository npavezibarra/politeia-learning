/**
 * Course Creator Dashboard JS
 */
jQuery(document).ready(function ($) {
    console.log('Politeia Course Creator Dashboard Initialized');

    function t(key) {
        try {
            return (pcgCreatorData && pcgCreatorData.i18n && pcgCreatorData.i18n[key]) ? pcgCreatorData.i18n[key] : key;
        } catch (_) {
            return key;
        }
    }

    function formatPercent(value) {
        const num = typeof value === 'number' ? value : Number(value || 0);
        if (!isFinite(num)) return '0';
        const rounded = Math.round(num * 100) / 100;
        if (Math.abs(rounded - Math.round(rounded)) < 0.001) {
            return String(Math.round(rounded));
        }
        return String(rounded);
    }

    // Pending approvals index for the current user (receiver-side).
    // Structure: { group: { [containerId]: { snapshot_id, profit_percentage, created_by_name } }, program: { ... } }
    let pendingApprovalsIndex = { group: {}, program: {} };

    // ───────────────────────────────────────────────────────────
    // Specialization (LearnDash Group) Creator UI
    // ───────────────────────────────────────────────────────────
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
            $('#pcg-spec-mode-especializacion').show();
            $('#pcg-spec-mode-cursos').hide();

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

            // Destroy if already initialized
            try {
                if ($wrap.data('ui-sortable')) {
                    $wrap.sortable('destroy');
                }
            } catch (_) { }

            $wrap.sortable({
                axis: 'y',
                // Use a body-appended clone helper to avoid cursor offset issues caused by
                // transformed/positioned ancestors in the front-end layout.
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
	            syncQuizCoursePicker(cachedCourses);
	        }

        function removeCourseFromSpecialization(courseId) {
            const id = Number(courseId) || 0;
            if (!id) return;
            selectedCourseIds = selectedCourseIds.filter(x => Number(x) !== id);
            renderAddedCourses();
            renderAllCourses();
            syncQuizCoursePicker(cachedCourses);
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
                syncQuizCoursePicker(courses);
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
	                        alert(t('errorLoadingSpecialization'));
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

                    if (data.title) {
                        $('#pcg-current-specialization-label').text(data.title).show();
                    }

                    const priceNum = parseFloat(String(data.price || '').replace(',', '.')) || 0;
                    if (priceNum === 0) {
                        $('#pcg-group-price-free-indicator').show();
                    }

	                    // Default to ESPECIALIZACIÓN tab after load.
	                    $('.pcg-spec-segment').removeClass('active');
	                    $('.pcg-spec-segment[data-value="especializacion"]').addClass('active');
	                    $('#pcg-spec-mode-especializacion').show();
	                    $('#pcg-spec-mode-cursos').hide();

		                    // Teachers
		                    populateTeachersList($('#pcg-group-teachers-list'), data.teachers || [], {
		                        id: Number(data.author_id || 0),
		                        name: data.author_name || '',
		                        avatar: data.author_avatar || ''
		                    });
		                    // Ensure all included course authors are present as participants.
		                    (data.included_authors || []).forEach(a => {
		                        ensureTeacherForUser($('#pcg-group-teachers-list'), {
		                            id: Number(a.id),
		                            name: a.name || '',
		                            email: a.email || '',
		                            avatar: a.avatar || ''
		                        });
		                    });

		                    loadCoursesForSpecialization().done(function () {
		                        // Ensure all selected course authors are included as participants.
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
                    alert(t('errorLoadingSpecializationGeneric'));
                    $('#pcg-specialization-form-section').hide();
                    $('#pcg-my-specializations-section').show();
                });
            });
        }

        function syncQuizCoursePicker() {}

	        function getSpecializationPayload() {
	            return {
	                id: currentGroupId,
	                title: $('#pcg-group-title').val(),
	                description: $('#pcg-group-description').val(),
	                price: $('#pcg-group-price').val(),
	                course_ids: selectedCourseIds,
	                order_required: orderRequired ? 1 : 0,
	                teachers: collectTeachers($('#pcg-group-teachers-list')),
	                split_locked: Boolean($('#pcg-group-teachers-list').data('splitLocked')),
	            };
	        }

        // Open form
        $('#pcg-show-specialization-form').on('click', function () {
            $('#pcg-my-specializations-section').fadeOut(300, function () {
                resetSpecializationForm();
                $('#pcg-specialization-form-section').fadeIn(400);
                loadCoursesForSpecialization();
            });
        });

        // Back to list
        $('#pcg-btn-back-to-specializations').on('click', function () {
            $('#pcg-specialization-form-section').fadeOut(300, function () {
                $('#pcg-my-specializations-section').fadeIn();
                resetSpecializationForm();
            });
        });

        // Segment switcher
        $(document).on('click', '.pcg-spec-segment', function () {
            $('.pcg-spec-segment').removeClass('active');
            $(this).addClass('active');

            const mode = $(this).data('value');
            $('#pcg-spec-mode-especializacion').hide();
            $('#pcg-spec-mode-cursos').hide();

            if (mode === 'especializacion') {
                $('#pcg-spec-mode-especializacion').fadeIn(200);
            } else if (mode === 'cursos') {
                $('#pcg-spec-mode-cursos').fadeIn(200);
                loadCoursesForSpecialization();
            }
        });

        // Update label as user types
        $('#pcg-group-title').on('input', function () {
            const title = $(this).val();
            if (title) {
                $('#pcg-current-specialization-label').text(title).show();
            } else {
                $('#pcg-current-specialization-label').hide();
            }
        });

        // Free indicator
        $('#pcg-group-price').on('input change', function () {
            const price = parseFloat($(this).val()) || 0;
            if (price === 0) {
                $('#pcg-group-price-free-indicator').fadeIn(200);
            } else {
                $('#pcg-group-price-free-indicator').fadeOut(200);
            }
        });

        // Order required toggle
        $('#pcg-spec-order-required').on('change', function () {
            orderRequired = $(this).is(':checked');
        });

        // Filter + pagination in "Todos mis cursos"
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

        // Save specialization
        $('.pcg-btn-save-specialization').on('click', function () {
            const $btn = $(this);
            const payload = getSpecializationPayload();

                if (!payload.title) {
                alert(t('pleaseEnterSpecializationName'));
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
	                            alert(t('approvalRequestSent'));
	                        }
	                        $btn.addClass('success');
	                        refreshActiveList();
	                        setTimeout(() => {
	                            $btn.prop('disabled', false).removeClass('success');
	                        }, 2000);
                    } else {
                        alert(t('errorPrefix') + (response && response.data && response.data.message ? response.data.message : t('unknownError')));
                        $btn.prop('disabled', false);
                    }
                },
                error: function () {
                    $btn.removeClass('loading');
                    alert(t('errorSavingSpecialization'));
                    $btn.prop('disabled', false);
                }
            });
        });

        // Edit / Delete buttons from cards
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
                        refreshActiveList();
                    } else {
                        alert(t('errorPrefix') + (response && response.data && response.data.message ? response.data.message : t('couldNotDelete')));
                    }
                },
                error: function () {
                    alert(t('errorDeletingSpecialization'));
                }
            });
        });
    })();

    // ───────────────────────────────────────────────────────────
    // Programas (course_program) Creator UI
    // ───────────────────────────────────────────────────────────
    (function initProgramasCreator() {
        if (!$('#pcg-show-programa-form').length) {
            return;
        }

        let currentProgramaId = 0;
        let selectedGroupIds = [];
        let cachedSpecializations = [];
        let specsPage = 1;
        const specsPerPage = 10;

        function resetProgramaForm() {
            currentProgramaId = 0;
            selectedGroupIds = [];
            cachedSpecializations = [];
            specsPage = 1;

            $('#pcg-current-programa-id').val(0);
            $('#pcg-programa-title').val('');
            $('#pcg-programa-description').val('');
            $('#pcg-programa-price').val('');
            $('#pcg-programa-price-free-indicator').hide();
            $('#pcg-current-programa-label').text('').hide();
            $('#pcg-prog-spec-search').val('');

            $('.pcg-prog-segment').removeClass('active');
            $('.pcg-prog-segment[data-value="programa"]').addClass('active');
            $('#pcg-prog-mode-programa').show();
            $('#pcg-prog-mode-especializaciones').hide();

            $('#pcg-prog-all-specs').html(`
                <div class="pcg-loading-placeholder">
                    <span class="dashicons dashicons-update spin"></span>
                    <p>${t('loading')}</p>
                </div>
            `);
	            $('#pcg-prog-added-specs').html(`
	                <div class="pcg-loading-placeholder">
	                    <span class="dashicons dashicons-update spin"></span>
	                    <p>${t('loading')}</p>
	                </div>
	            `);
	            $('#pcg-prog-pagination').hide();

	            // Teachers tab
	            const seed = getCurrentUserTeacherSeed();
	            const $list = $('#pcg-program-teachers-list');
	            if ($list.length) {
	                populateTeachersList($list, [], seed);
	            }
	        }

        function renderAddedSpecs() {
            const $wrap = $('#pcg-prog-added-specs');

            if (!cachedSpecializations || cachedSpecializations.length === 0) {
                $wrap.html(`<p class="pcg-empty-msg">${t('noSpecializationsYet')}</p>`);
                return;
            }

            if (!selectedGroupIds || selectedGroupIds.length === 0) {
                $wrap.html(`<p class="pcg-empty-msg">${t('noSpecializationsAddedYet')}</p>`);
                return;
            }

            const items = selectedGroupIds
                .map(id => cachedSpecializations.find(g => Number(g.id) === Number(id)))
                .filter(Boolean);

            if (items.length === 0) {
                $wrap.html(`<p class="pcg-empty-msg">${t('noSpecializationsAddedYet')}</p>`);
                return;
            }

            const html = items.map(g => `
                <div class="pcg-spec-added-row" data-id="${g.id}">
                    <div class="pcg-spec-added-title">${g.title}</div>
                    <button type="button" class="pcg-btn-icon pcg-prog-remove-spec" title="${t('remove')}">
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                </div>
            `).join('');

            $wrap.html(html);
        }

        function getFilteredSpecs() {
            const q = String($('#pcg-prog-spec-search').val() || '').trim().toLowerCase();
            if (!q) return cachedSpecializations || [];
            return (cachedSpecializations || []).filter(g => String(g.title || '').toLowerCase().includes(q));
        }

        function renderAllSpecs() {
            const $wrap = $('#pcg-prog-all-specs');
            const specs = getFilteredSpecs();

            if (!specs || specs.length === 0) {
                $wrap.html(`<p class="pcg-empty-msg">${t('noSpecializations')}</p>`);
                $('#pcg-prog-pagination').hide();
                return;
            }

            const totalPages = Math.max(1, Math.ceil(specs.length / specsPerPage));
            if (specsPage > totalPages) specsPage = totalPages;
            if (specsPage < 1) specsPage = 1;

            const start = (specsPage - 1) * specsPerPage;
            const pageItems = specs.slice(start, start + specsPerPage);
            const selected = new Set(selectedGroupIds.map(id => Number(id)));

            const html = pageItems.map(g => {
                const isAdded = selected.has(Number(g.id));
                return `
                    <div class="pcg-prog-row" data-id="${g.id}">
                        <div class="pcg-prog-row-title">${g.title}</div>
                        <button type="button" class="pcg-spec-add-btn pcg-prog-add-spec" ${isAdded ? 'disabled' : ''}>
                            ${isAdded ? t('added') : t('add')}
                        </button>
                    </div>
                `;
            }).join('');

            $wrap.html(html);

            if (specs.length > specsPerPage) {
                $('#pcg-prog-pagination').show();
                $('#pcg-prog-page-info').text(`${specsPage} / ${totalPages}`);
                $('#pcg-prog-page-prev').prop('disabled', specsPage <= 1);
                $('#pcg-prog-page-next').prop('disabled', specsPage >= totalPages);
            } else {
                $('#pcg-prog-pagination').hide();
            }
        }

	        function addSpecToPrograma(groupId) {
	            const id = Number(groupId) || 0;
	            if (!id) return;
	            if (!selectedGroupIds.includes(id)) selectedGroupIds.push(id);

	            const spec = (cachedSpecializations || []).find(g => Number(g.id) === id);
	            if (spec && spec.author_id) {
	                ensureTeacherForUser($('#pcg-program-teachers-list'), {
	                    id: Number(spec.author_id),
	                    name: spec.author_name || '',
	                    email: spec.author_email || '',
	                    avatar: spec.author_avatar || ''
	                });
	            }

	            renderAddedSpecs();
	            renderAllSpecs();
	        }

        function removeSpecFromPrograma(groupId) {
            const id = Number(groupId) || 0;
            if (!id) return;
            selectedGroupIds = selectedGroupIds.filter(x => Number(x) !== id);
            renderAddedSpecs();
            renderAllSpecs();
        }

	        function loadSpecializationsForPrograma() {
	            return $.ajax({
	                url: pcgCreatorData.ajaxUrl,
	                type: 'POST',
	                data: {
	                    action: 'pcg_get_published_specializations',
	                    nonce: pcgCreatorData.nonce
	                }
	            }).done(function (response) {
                if (!response || !response.success) {
                    $('#pcg-prog-all-specs').html(`<p class="pcg-empty-msg">${t('failedToLoadSpecializations')}</p>`);
                    $('#pcg-prog-added-specs').html(`<p class="pcg-empty-msg">${t('failedToLoadSpecializations')}</p>`);
                    $('#pcg-prog-pagination').hide();
                    return;
                }

                cachedSpecializations = response.data || [];
                renderAddedSpecs();
                renderAllSpecs();
            });
        }

	        function openProgramaFormForEdit(programaId) {
            const id = Number(programaId) || 0;
            if (!id) return;

            $('#pcg-my-programas-section').fadeOut(200, function () {
                resetProgramaForm();
                $('#pcg-programa-form-section').show();
                $('#pcg-programa-form-section').append(`
                    <div id="pcg-prog-edit-loading" class="pcg-loading-placeholder">
                        <span class="dashicons dashicons-update spin"></span>
                        <p>${t('loadingProgram')}</p>
                    </div>
                `);

                $.ajax({
                    url: pcgCreatorData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'pcg_get_programa_for_edit',
                        nonce: pcgCreatorData.nonce,
                        programa_id: id
                    }
	                }).done(function (response) {
	                    $('#pcg-prog-edit-loading').remove();
	                    if (!response || !response.success) {
	                        alert(t('errorLoadingProgram'));
	                        $('#pcg-programa-form-section').hide();
	                        $('#pcg-my-programas-section').show();
	                        return;
	                    }

		                    const data = response.data;
		                    currentProgramaId = Number(data.id) || 0;
		                    selectedGroupIds = (data.group_ids || []).map(x => Number(x));

                    $('#pcg-current-programa-id').val(currentProgramaId);
                    $('#pcg-programa-title').val(data.title || '');
                    $('#pcg-programa-description').val(data.description || '');
                    $('#pcg-programa-price').val(data.price || '');

                    if (data.title) {
                        $('#pcg-current-programa-label').text(data.title).show();
                    }

                    const priceNum = parseFloat(String(data.price || '').replace(',', '.')) || 0;
                    if (priceNum === 0) {
                        $('#pcg-programa-price-free-indicator').show();
                    }

	                    $('.pcg-prog-segment').removeClass('active');
	                    $('.pcg-prog-segment[data-value="programa"]').addClass('active');
	                    $('#pcg-prog-mode-programa').show();
	                    $('#pcg-prog-mode-especializaciones').hide();

		                    // Teachers
		                    populateTeachersList($('#pcg-program-teachers-list'), data.teachers || [], {
		                        id: Number(data.author_id || 0),
		                        name: data.author_name || '',
		                        avatar: data.author_avatar || ''
		                    });
		                    (data.included_authors || []).forEach(a => {
		                        ensureTeacherForUser($('#pcg-program-teachers-list'), {
		                            id: Number(a.id),
		                            name: a.name || '',
		                            email: a.email || '',
		                            avatar: a.avatar || ''
		                        });
		                    });

		                    loadSpecializationsForPrograma().done(function () {
		                        (selectedGroupIds || []).forEach(gid => {
		                            const spec = (cachedSpecializations || []).find(g => Number(g.id) === Number(gid));
		                            if (spec && spec.author_id) {
		                                ensureTeacherForUser($('#pcg-program-teachers-list'), {
		                                    id: Number(spec.author_id),
		                                    name: spec.author_name || '',
		                                    email: spec.author_email || '',
		                                    avatar: spec.author_avatar || ''
		                                });
		                            }
		                        });
		                    });
		                }).fail(function () {
                    $('#pcg-prog-edit-loading').remove();
                    alert(t('errorLoadingProgramGeneric'));
                    $('#pcg-programa-form-section').hide();
                    $('#pcg-my-programas-section').show();
                });
            });
        }

	        function getProgramaPayload() {
	            return {
	                id: currentProgramaId,
	                title: $('#pcg-programa-title').val(),
	                description: $('#pcg-programa-description').val(),
	                price: $('#pcg-programa-price').val(),
	                group_ids: selectedGroupIds,
	                teachers: collectTeachers($('#pcg-program-teachers-list')),
	                split_locked: Boolean($('#pcg-program-teachers-list').data('splitLocked')),
	            };
	        }

        // Open form
        $('#pcg-show-programa-form').on('click', function () {
            $('#pcg-my-programas-section').fadeOut(300, function () {
                resetProgramaForm();
                $('#pcg-programa-form-section').fadeIn(400);
                loadSpecializationsForPrograma();
            });
        });

        // Back to list
        $('#pcg-btn-back-to-programas').on('click', function () {
            $('#pcg-programa-form-section').fadeOut(300, function () {
                $('#pcg-my-programas-section').fadeIn();
                resetProgramaForm();
            });
        });

        // Segment switcher
        $(document).on('click', '.pcg-prog-segment', function () {
            const $form = $('#pcg-programa-form-section');
            $form.find('.pcg-prog-segment').removeClass('active');
            $(this).addClass('active');

            const mode = $(this).data('value');
            $('#pcg-prog-mode-programa').hide();
            $('#pcg-prog-mode-especializaciones').hide();

            if (mode === 'programa') {
                $('#pcg-prog-mode-programa').fadeIn(200);
            } else if (mode === 'especializaciones') {
                $('#pcg-prog-mode-especializaciones').fadeIn(200);
                loadSpecializationsForPrograma();
            }
        });

        // Update label as user types
        $('#pcg-programa-title').on('input', function () {
            const title = $(this).val();
            if (title) {
                $('#pcg-current-programa-label').text(title).show();
            } else {
                $('#pcg-current-programa-label').hide();
            }
        });

        // Free indicator
        $('#pcg-programa-price').on('input change', function () {
            const price = parseFloat($(this).val()) || 0;
            if (price === 0) {
                $('#pcg-programa-price-free-indicator').fadeIn(200);
            } else {
                $('#pcg-programa-price-free-indicator').fadeOut(200);
            }
        });

        // Filter + pagination
        $('#pcg-prog-spec-search').on('input', function () {
            specsPage = 1;
            renderAllSpecs();
        });

        $('#pcg-prog-spec-search').on('keydown', function (e) {
            if (e.key === 'Escape') {
                $(this).val('');
                specsPage = 1;
                renderAllSpecs();
            }
        });

        $('#pcg-prog-page-prev').on('click', function () {
            specsPage = Math.max(1, specsPage - 1);
            renderAllSpecs();
        });

        $('#pcg-prog-page-next').on('click', function () {
            specsPage = specsPage + 1;
            renderAllSpecs();
        });

        $(document).on('click', '.pcg-prog-add-spec', function () {
            const groupId = $(this).closest('.pcg-prog-row').data('id');
            addSpecToPrograma(groupId);
        });

        $(document).on('click', '.pcg-prog-remove-spec', function () {
            const groupId = $(this).closest('.pcg-spec-added-row').data('id');
            removeSpecFromPrograma(groupId);
        });

        // Save programa
        $('.pcg-btn-save-programa').on('click', function () {
            const $btn = $(this);
            const payload = getProgramaPayload();

            if (!payload.title) {
                alert(t('pleaseEnterProgramName'));
                return;
            }

            $btn.addClass('loading').prop('disabled', true);

            $.ajax({
                url: pcgCreatorData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pcg_save_programa',
                    nonce: pcgCreatorData.nonce,
                    programa_data: payload
                },
	                success: function (response) {
	                    $btn.removeClass('loading');
	                    if (response && response.success) {
	                        currentProgramaId = response.data.programa_id;
	                        $('#pcg-current-programa-id').val(currentProgramaId);
	                        if (response.data && response.data.snapshot_status === 'pending') {
	                            alert(t('approvalRequestSent'));
	                        }
	                        $btn.addClass('success');
	                        refreshActiveList();
	                        setTimeout(() => {
	                            $btn.prop('disabled', false).removeClass('success');
	                        }, 2000);
                    } else {
                        alert(t('errorPrefix') + (response && response.data && response.data.message ? response.data.message : t('unknownError')));
                        $btn.prop('disabled', false);
                    }
                },
                error: function () {
                    $btn.removeClass('loading');
                    alert(t('errorSavingProgram'));
                    $btn.prop('disabled', false);
                }
            });
        });

        // Edit / Delete from cards
        $(document).on('click', '.pcg-btn-edit-programa', function (e) {
            e.preventDefault();
            e.stopPropagation();
            const programaId = $(this).closest('.pcg-programa-card').data('id');
            openProgramaFormForEdit(programaId);
        });

        $(document).on('click', '.pcg-btn-delete-programa', function (e) {
            e.preventDefault();
            e.stopPropagation();
            const programaId = $(this).closest('.pcg-programa-card').data('id');
            if (!programaId) return;
            if (!confirm(t('confirmDeleteProgram'))) return;

            $.ajax({
                url: pcgCreatorData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pcg_delete_programa',
                    nonce: pcgCreatorData.nonce,
                    programa_id: programaId
                },
                success: function (response) {
                    if (response && response.success) {
                        refreshActiveList();
                    } else {
                        alert(t('errorPrefix') + (response && response.data && response.data.message ? response.data.message : t('couldNotDelete')));
                    }
                },
                error: function () {
                    alert(t('errorDeletingProgram'));
                }
            });
        });
    })();

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

	        resetTeachersList($teachersList);

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
	                role_slug: t('mainAuthorRoleSlug'),
	                profit_percentage: 100
	            }, $('#pcg-teachers-list'));
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

    // Toggle between Curso, Lecciones and Evaluaciones (course form only)
    $(document).on('click', '#pcg-course-form-section .pcg-segment', function () {
        const $form = $('#pcg-course-form-section');

        $form.find('.pcg-segment').removeClass('active');
        $(this).addClass('active');

        const mode = $(this).data('value');
        $form.find('.pcg-mode-content').hide();

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

	    function getCurrentUserTeacherSeed() {
	        const id = Number(pcgCreatorData && pcgCreatorData.currentUserId ? pcgCreatorData.currentUserId : 0) || 0;
	        const name = (pcgCreatorData && pcgCreatorData.currentUserFullNameEmail) ? String(pcgCreatorData.currentUserFullNameEmail) : '';
	        const avatar = (pcgCreatorData && pcgCreatorData.currentUserAvatar) ? String(pcgCreatorData.currentUserAvatar) : '';
	        return { id, name, avatar };
	    }

	    function ensureTeachersEmptyState($list) {
	        if (!$list || !$list.length) return;
	        if ($list.find('.pcg-empty-teachers-state').length) return;
	        $list.append(`
	            <div class="pcg-empty-teachers-state">
	                <p>${t('noCollaboratorsAssigned')}</p>
	            </div>
	        `);
	    }

	    function resetTeachersList($list) {
	        if (!$list || !$list.length) return;
	        $list.empty();
	        $list.data('splitLocked', false);
	        ensureTeachersEmptyState($list);
	        $list.find('.pcg-empty-teachers-state').show();
	    }

	    function isEqualSplit(teachers) {
	        const items = Array.isArray(teachers) ? teachers : [];
	        const n = items.length;
	        if (n <= 0) return true;

	        const base = Math.floor(10000 / n);
	        const remainder = 10000 - (base * n);
	        const expected = items.map((_, i) => (base + (i === 0 ? remainder : 0)) / 100);

	        const actual = items.map(t => Number(normalizePercentInt(t.profit_percentage ?? 0)));
	        expected.sort((a, b) => a - b);
	        actual.sort((a, b) => a - b);
	        for (let i = 0; i < n; i++) {
	            if (Math.abs(Number(expected[i]) - Number(actual[i])) > 0.01) return false;
	        }
	        return true;
	    }

	    function rebalanceTeachersEqual($list) {
	        if (!$list || !$list.length) return;
	        const $items = $list.find('.pcg-teacher-item');
	        const n = $items.length;
	        if (n <= 0) return;

	        const base = Math.floor(10000 / n);
	        const remainder = 10000 - (base * n);

	        $items.each(function (idx) {
	            const val = (base + (idx === 0 ? remainder : 0)) / 100;
	            const intValue = normalizePercentInt(val);
	            $(this).find('.pcg-teacher-profit').val(intValue);
	            $(this).find('.pcg-teacher-share-badge').text(`${intValue}%`);
	        });
	    }

	    function rebalanceMainAuthorRemainder($list, $changedItem = null) {
	        if (!$list || !$list.length) return;
	        const $items = $list.find('.pcg-teacher-item');
	        if (!$items.length) return;

	        let $main = $list.find('.pcg-teacher-item[data-main="true"]').first();
	        if (!$main.length) {
	            $main = $items.first();
	        }

	        const isChangedMain = $changedItem && $changedItem.length && $changedItem.is($main);

	        const getVal = ($item) => normalizePercentInt($item.find('.pcg-teacher-profit').val());

	        const $nonMain = $items.not($main);
	        if (!$nonMain.length) {
	            $main.find('.pcg-teacher-profit').val(100);
	            $main.find('.pcg-teacher-share-badge').text('100%');
	            return;
	        }

	        if (isChangedMain) {
	            // Main author is always the remainder, so recompute it.
	            let sumOthers = 0;
	            $nonMain.each(function () {
	                sumOthers += getVal($(this));
	            });
	            const mainVal = Math.max(0, 100 - sumOthers);
	            $main.find('.pcg-teacher-profit').val(mainVal);
	            $main.find('.pcg-teacher-share-badge').text(`${mainVal}%`);
	            return;
	        }

	        // Clamp changed item so total never exceeds 100.
	        if ($changedItem && $changedItem.length) {
	            let otherOthersSum = 0;
	            $nonMain.not($changedItem).each(function () {
	                otherOthersSum += getVal($(this));
	            });
	            const maxForChanged = Math.max(0, 100 - otherOthersSum);
	            let changedVal = getVal($changedItem);
	            if (changedVal > maxForChanged) {
	                changedVal = maxForChanged;
	                $changedItem.find('.pcg-teacher-profit').val(changedVal);
	                $changedItem.find('.pcg-teacher-share-badge').text(`${changedVal}%`);
	            }
	        }

	        let sumNonMain = 0;
	        $nonMain.each(function () {
	            sumNonMain += getVal($(this));
	        });

	        const mainVal = Math.max(0, 100 - sumNonMain);
	        $main.find('.pcg-teacher-profit').val(mainVal);
	        $main.find('.pcg-teacher-share-badge').text(`${mainVal}%`);
	    }

	    function ensureTeacherForUser($list, user) {
	        if (!$list || !$list.length) return;
	        const userId = Number(user && user.id ? user.id : 0) || 0;
	        if (!userId) return;

	        const exists = $list.find(`.pcg-teacher-item[data-user-id="${userId}"]`).length > 0;
	        if (exists) return;

	        const defaultPct = $list.data('splitLocked') ? 1 : 0;
	        addTeacherItem({
	            user_id: userId,
	            user_name: String(user.name || ''),
	            user_email: String(user.email || ''),
	            avatar: String(user.avatar || ''),
	            role_slug: t('mainAuthorRoleSlug'),
	            profit_percentage: defaultPct,
	            role_description: ''
	        }, $list);

	        if (!$list.data('splitLocked')) {
	            rebalanceTeachersEqual($list);
	        } else {
	            rebalanceMainAuthorRemainder($list);
	        }
	    }

	    function populateTeachersList($list, teachers, authorFallback) {
	        resetTeachersList($list);

	        const items = Array.isArray(teachers) ? teachers : [];
	        if (items.length > 0) {
	            $list.data('splitLocked', !isEqualSplit(items));
	            items.forEach((teacher) => {
	                addTeacherItem({
	                    user_id: teacher.id,
	                    user_name: teacher.name,
	                    avatar: teacher.avatar || '',
	                    role_slug: teacher.role_slug || '',
	                    profit_percentage: teacher.profit_percentage || '0',
	                    role_description: teacher.role_description || '',
	                    is_main_author: teacher.is_main_author || false,
	                    approval_status: teacher.approval_status || '',
	                }, $list);
	            });
	            return;
	        }

	        if (authorFallback && authorFallback.id) {
	            addTeacherItem({
	                user_id: authorFallback.id,
	                user_name: authorFallback.name,
	                avatar: authorFallback.avatar || '',
	                is_main_author: true,
	                role_slug: t('mainAuthorRoleSlug'),
	                profit_percentage: 100
	            }, $list);
	        }
	    }

	    function collectTeachers($list) {
	        const teachers = [];
	        if (!$list || !$list.length) return teachers;

	        $list.find('.pcg-teacher-item').each(function () {
	            const userId = $(this).attr('data-user-id');
	            if (!userId) return;
	            teachers.push({
	                user_id: userId,
	                role_slug: $(this).find('.pcg-teacher-role-slug').val(),
	                profit_percentage: normalizePercentInt($(this).find('.pcg-teacher-profit').val()),
	                role_description: $(this).find('.pcg-teacher-description').val()
	            });
	        });

	        return teachers;
	    }

	    function addTeacherItem(data = {}, $targetList = null) {
	        const $list = ($targetList && $targetList.length) ? $targetList : $('#pcg-teachers-list');
	        $list.find('.pcg-empty-teachers-state').hide();

        const userId = data.user_id || '';
        const identity = normalizeTeacherIdentity(data.user_name || '', data.user_email || data.email || '');
        const userName = identity.name;
        const userEmail = identity.email;
        const avatarUrl = data.avatar || '';
        const roleSlug = data.role_slug || '';
        const roleDescription = data.role_description || '';
	        const profitPercentage = String(normalizePercentInt(data.profit_percentage ?? 0));
	        const isMainAuthor = data.is_main_author || false;
	        const approvalStatus = String(data.approval_status || '');
        const hasSelectedUser = Boolean(userId && userName);

        const iconHtml = avatarUrl ? `<img src="${avatarUrl}" class="pcg-item-avatar">` : '<span class="dashicons dashicons-admin-users"></span>';

	        const removeBtnHtml = isMainAuthor ? '' : `
	            <button type="button" class="pcg-item-btn-remove pcg-teacher-remove" title="${t('delete')}">
	                <span class="dashicons dashicons-trash"></span>
	            </button>
	        `;

	        const itemHtml = `
		            <div class="pcg-content-item pcg-teacher-item" data-user-id="${userId}" ${isMainAuthor ? 'data-main="true"' : ''}>
	                <div class="pcg-item-header">
	                    <div class="pcg-item-expand" title="${t('viewDetails')}">
	                        <span class="dashicons dashicons-arrow-right-alt2"></span>
	                    </div>
                    <div class="pcg-item-icon">
                        ${iconHtml}
                    </div>
	                    <div class="pcg-item-input-wrapper">
	                        <input type="text" class="pcg-item-input pcg-teacher-name-input" 
	                               value="${hasSelectedUser ? '' : userName}" 
	                               placeholder="${t('searchCollaborator')}" 
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
		                        ${approvalStatus === 'pending' ? `<span class="pcg-badge pcg-badge--pending pcg-badge--teacher">${t('waitingApproval')}</span>` : ''}
		                        ${isMainAuthor ? `<span class="pcg-badge-main-author">${t('mainAuthor')}</span>` : ''}
		                        ${removeBtnHtml}
		                    </div>
	                </div>
	                <div class="pcg-item-details" style="display:none;">
	                    <div class="pcg-detail-row">
	                        <div class="pcg-detail-field">
	                            <label>${t('role')}</label>
	                            <input type="text" class="pcg-teacher-role-slug" value="${roleSlug}" placeholder="${t('roleSlugPlaceholder')}">
	                        </div>
	                        <div class="pcg-detail-field">
	                            <label>${t('participationLabel')}</label>
	                            <input type="number" class="pcg-teacher-profit" value="${profitPercentage}" min="0" max="100" step="1">
	                        </div>
	                    </div>
	                    <div class="pcg-detail-row">
	                        <div class="pcg-detail-field" style="flex:1;">
	                            <label>${t('roleDescriptionLabel')}</label>
	                            <textarea class="pcg-teacher-description" placeholder="${t('describeResponsibilities')}">${roleDescription}</textarea>
	                        </div>
	                    </div>
	                </div>
	            </div>
	        `;

	        const $newItem = $(itemHtml);
	        $list.append($newItem);
	        if (!userName) $newItem.find('.pcg-teacher-name-input').focus();
	    }

	    // Add Teacher PLUS button
	    $(document).on('click', '.pcg-btn-add-teacher, #pcg-btn-add-teacher', function () {
	        const targetSel = $(this).attr('data-target') || '#pcg-teachers-list';
	        addTeacherItem({}, $(targetSel));
	    });

    // Remove Teacher
	    $(document).on('click', '.pcg-teacher-remove', function () {
	        const $item = $(this).closest('.pcg-teacher-item');
	        const $list = $item.closest('.pcg-items-list');
	        $item.fadeOut(300, function () {
	            $(this).remove();
	            if ($list.children('.pcg-teacher-item').length === 0) {
	                $list.find('.pcg-empty-teachers-state').fadeIn(300);
	            } else if (!$list.data('splitLocked')) {
	                rebalanceTeachersEqual($list);
	            } else {
	                rebalanceMainAuthorRemainder($list);
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
	                                const $list = $item.closest('.pcg-items-list');
	                                $item.attr('data-user-id', user.id);
	                                $item.find('.pcg-item-icon').html(`<img src="${user.avatar}" class="pcg-item-avatar">`);
	                                $item.find('.pcg-teacher-full-name').text(selectedIdentity.name);
	                                $item.find('.pcg-teacher-email').text(selectedIdentity.email);
	                                $item.find('.pcg-teacher-identity').removeClass('pcg-teacher-identity-hidden');
	                                $input.hide();
	                                $results.hide().empty();

	                                if (!$list.data('splitLocked')) {
	                                    rebalanceTeachersEqual($list);
	                                } else {
	                                    rebalanceMainAuthorRemainder($list, $item);
	                                }
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
	        const $list = $(this).closest('.pcg-items-list');
	        $list.data('splitLocked', true);
	        $(this).closest('.pcg-teacher-item').find('.pcg-teacher-share-badge').text(`${intValue}%`);
	        rebalanceMainAuthorRemainder($list, $(this).closest('.pcg-teacher-item'));
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
	        const typeLabel = type === 'section' ? t('newSection') : t('newLesson');
	        const itemClass = type === 'section' ? 'item-section' : 'item-lesson';

        const title = typeof data === 'string' ? data : (data.title || '');
        const videoUrl = data.video_url || '';
        const availableDate = data.available_date || '';

        let expandHtml = '';
        let detailsHtml = '';

	        if (type === 'lesson') {
	            expandHtml = `
	                <div class="pcg-item-expand" title="${t('expandDetails')}">
	                    <span class="dashicons dashicons-arrow-right-alt2"></span>
	                </div>
	            `;
	            detailsHtml = `
	                <div class="pcg-item-details" style="display:none;">
	                    <div class="pcg-detail-row">
	                        <div class="pcg-detail-field">
	                            <label>${t('youtubeUrl')}</label>
	                            <input type="text" class="pcg-lesson-video-url" value="${videoUrl}" placeholder="https://youtube.com/watch?v=...">
	                        </div>
	                        <div class="pcg-detail-field">
	                            <label>${t('availableOn')}</label>
	                            <input type="date" class="pcg-lesson-available-date" value="${availableDate}">
	                        </div>
	                    </div>
	                    <div class="pcg-detail-actions">
	                        <button type="button" class="pcg-btn-add-text">${t('addText')}</button>
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
	                        <button type="button" class="pcg-item-btn-remove" title="${t('removeItem')}">
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
            title: t('courseCover'),
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
            title: t('coverPhoto'),
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
                    alert(t('errorPrefix') + response.data.message);
                }
            },
            error: function () {
                alert(t('errorUploadingImage'));
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
    $('.pcg-btn-save-course').on('click', function () {
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

	        courseData.teachers = collectTeachers($('#pcg-teachers-list'));

        $('#pcg-lessons-list .pcg-content-item').each(function () {
            courseData.content.push({
                type: $(this).data('type'),
                title: $(this).find('.pcg-item-input').val(),
                video_url: $(this).find('.pcg-lesson-video-url').val() || '',
                available_date: $(this).find('.pcg-lesson-available-date').val() || ''
            });
        });

        if (!courseData.title) {
            alert(t('pleaseEnterCourseTitle'));
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
                    refreshActiveList();
                    setTimeout(() => {
                        $btn.prop('disabled', false).removeClass('success');
                    }, 2000);

                    if (response.data.permalink) {
                        currentCoursePermalink = response.data.permalink;
                        $previewBtn.fadeIn();
                    }
                } else {
                    alert(t('errorPrefix') + response.data.message);
                    $btn.prop('disabled', false);
                }
            },
            error: function () {
                $btn.removeClass('loading');
                alert(t('errorSavingCourse'));
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

    function getListContext() {
        if ($('#specialization-grid').length > 0) return 'specializations';
        if ($('#programas-grid').length > 0) return 'programas';
        return 'courses';
    }

    function getActiveGrid() {
        const context = getListContext();
        if (context === 'specializations') return $('#specialization-grid');
        if (context === 'programas') return $('#programas-grid');
        return $('#pcg-my-courses-grid');
    }

    function refreshActiveList() {
        const context = getListContext();
        if (context === 'specializations') return loadMySpecializations();
        if (context === 'programas') return loadMyProgramas();
        return loadMyCourses();
    }

    // Load My Specializations (LearnDash Groups)
    function loadMySpecializations() {
        $.ajax({
            url: pcgCreatorData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pcg_get_my_specializations',
                nonce: pcgCreatorData.nonce
            },
            success: function (response) {
                if (response.success) {
                    renderSpecializations(response.data);
                }
            }
        });
    }

    function loadMyProgramas() {
        $.ajax({
            url: pcgCreatorData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pcg_get_my_programas',
                nonce: pcgCreatorData.nonce
            },
            success: function (response) {
                if (response.success) {
                    renderProgramas(response.data);
                }
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
                                <button class="pcg-btn-icon pcg-btn-edit-course" title="${t('edit')}">
                                    <span class="dashicons dashicons-edit"></span>
                                </button>
                                <button class="pcg-btn-icon pcg-btn-delete-course" title="${t('delete')}">
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

	    function renderSpecializations(groups) {
        const $grid = getActiveGrid();
        $grid.empty();

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        if (!groups || groups.length === 0) {
            $grid.append(`
                <div class="pcg-specialization-card pcg-specialization-card--empty">
                    <div class="pcg-specialization-thumb pcg-course-thumb">
                        <div class="pcg-empty-specialization-message">${t('createYourSpecialization')}</div>
                    </div>
                    <div class="pcg-specialization-content pcg-course-content">
                        <div class="pcg-specialization-meta pcg-course-meta"></div>
                    </div>
                </div>
            `);
            return;
        }

	        groups.forEach(group => {
	            const isPending = Boolean(group.is_pending_approval);
                const approval = (pendingApprovalsIndex.group && pendingApprovalsIndex.group[Number(group.id)]) ? pendingApprovalsIndex.group[Number(group.id)] : null;
	            const canEdit = group.can_edit !== undefined ? Boolean(group.can_edit) : true;
	            const countLabel = (group.course_count === 1) ? `1 ${t('courseSingular')}` : `${group.course_count} ${t('coursesPlural')}`;
	            const thumb = group.thumbnail_url || '';
	            const thumbClass = thumb ? '' : ' pcg-course-thumb--no-image';
	            const permalink = group.permalink || '';
            const canDelete = Boolean(group.can_delete);
            const deleteBtnHtml = canDelete ? `
                <button class="pcg-btn-icon pcg-btn-delete-specialization" title="${t('delete')}" type="button">
                    <span class="dashicons dashicons-trash"></span>
                </button>
            ` : '';
            const courseTitles = Array.isArray(group.course_titles) ? group.course_titles : [];
            const courseListHtml = courseTitles.length ? `
                <ul class="pcg-specialization-course-list">
                    ${courseTitles.map(t => `<li>${escapeHtml(t)}</li>`).join('')}
                </ul>
            ` : '';

            const approvalActionsHtml = approval ? `
                <div class="pcg-card-approval-actions" data-snapshot-id="${approval.snapshot_id}">
                    <span class="pcg-card-approval-pct">${formatPercent(approval.profit_percentage)}%</span>
                    <button type="button" class="pcg-btn-outline pcg-btn-outline--small pcg-card-approval-approve">${t('approve')}</button>
                    <button type="button" class="pcg-btn-outline pcg-btn-outline--small pcg-btn-outline--danger pcg-card-approval-reject">${t('reject')}</button>
                </div>
            ` : '';

	            const editBtnHtml = canEdit ? `
	                    <button class="pcg-btn-icon pcg-btn-edit-specialization" title="${t('edit')}" type="button">
	                        <span class="dashicons dashicons-edit"></span>
	                    </button>
	            ` : '';

	            const cardHtml = `
	                <div class="pcg-specialization-card${isPending ? ' pcg-card--pending' : ''}" data-id="${group.id}" data-permalink="${permalink}" data-pending="${isPending ? 1 : 0}">
	                    <div class="pcg-specialization-thumb pcg-course-thumb${thumbClass}">
	                        ${thumb ? `<img src="${thumb}" alt="${escapeHtml(group.title)}">` : ''}
		                        <div class="pcg-course-badges">
		                            <span class="pcg-badge pcg-badge-count">${countLabel}</span>
		                            ${isPending ? `<span class="pcg-badge pcg-badge--pending">${t('pendingApproval')}</span>` : ''}
		                        </div>
		                        <div class="pcg-specialization-thumb-actions">
		                            ${editBtnHtml}
		                            ${deleteBtnHtml}
		                        </div>
	                    </div>
                    <div class="pcg-specialization-content pcg-course-content">
                        <h4>${escapeHtml(group.title)}</h4>
                        ${courseListHtml}
                        ${approvalActionsHtml}
                        <div class="pcg-specialization-meta pcg-course-meta">
                            <span class="pcg-course-price"></span>
                            <div class="pcg-course-actions"></div>
                        </div>
                    </div>
                </div>
            `;
            $grid.append(cardHtml);
        });
    }

	    function renderProgramas(programas) {
        const $grid = getActiveGrid();
        $grid.empty();

	        if (!programas || programas.length === 0) {
	            $grid.append(`
	                <div class="pcg-course-card pcg-course-card--empty">
	                    <div class="pcg-course-thumb">
	                        <div class="pcg-empty-specialization-message">${t('createYourProgram')}</div>
	                    </div>
	                    <div class="pcg-course-content">
	                        <div class="pcg-course-meta"></div>
	                    </div>
	                </div>
            `);
            return;
        }

	        programas.forEach(programa => {
	            const isPending = Boolean(programa.is_pending_approval);
                const approval = (pendingApprovalsIndex.program && pendingApprovalsIndex.program[Number(programa.id)]) ? pendingApprovalsIndex.program[Number(programa.id)] : null;
	            const canEdit = programa.can_edit !== undefined ? Boolean(programa.can_edit) : true;
	            const countLabel = (programa.group_count === 1) ? `1 ${t('groupSingular')}` : `${programa.group_count} ${t('groupsPlural')}`;
	            const thumb = programa.thumbnail_url || '';
	            const thumbClass = thumb ? '' : ' pcg-course-thumb--no-image';
	            const permalink = programa.permalink || '';
	            const price = programa.price ? programa.price : '';
	            const canDelete = Boolean(programa.can_delete);
	            const deleteBtnHtml = canDelete ? `
	                <button class="pcg-btn-icon pcg-btn-delete-programa" title="${t('delete')}" type="button">
	                    <span class="dashicons dashicons-trash"></span>
	                </button>
	            ` : '';
	            const editBtnHtml = canEdit ? `
		                                <button class="pcg-btn-icon pcg-btn-edit-programa" title="${t('edit')}" type="button">
		                                    <span class="dashicons dashicons-edit"></span>
		                                </button>
	            ` : '';

                const approvalActionsHtml = approval ? `
                    <div class="pcg-card-approval-actions" data-snapshot-id="${approval.snapshot_id}">
                        <span class="pcg-card-approval-pct">${formatPercent(approval.profit_percentage)}%</span>
                        <button type="button" class="pcg-btn-outline pcg-btn-outline--small pcg-card-approval-approve">${t('approve')}</button>
                        <button type="button" class="pcg-btn-outline pcg-btn-outline--small pcg-btn-outline--danger pcg-card-approval-reject">${t('reject')}</button>
                    </div>
                ` : '';

	            const cardHtml = `
	                <div class="pcg-programa-card pcg-course-card${isPending ? ' pcg-card--pending' : ''}" data-id="${programa.id}" data-permalink="${permalink}" data-pending="${isPending ? 1 : 0}">
	                    <div class="pcg-course-thumb${thumbClass}">
	                        ${thumb ? `<img src="${thumb}" alt="${programa.title}">` : ''}
	                        <div class="pcg-course-badges">
	                            <span class="pcg-badge pcg-badge-count">${countLabel}</span>
	                            ${isPending ? `<span class="pcg-badge pcg-badge--pending">${t('pendingApproval')}</span>` : ''}
	                        </div>
	                    </div>
	                    <div class="pcg-course-content">
	                        <h4>${programa.title}</h4>
                            ${approvalActionsHtml}
	                        <div class="pcg-course-meta">
		                            <span class="pcg-course-price">${price}</span>
		                            <div class="pcg-course-actions">
		                                ${editBtnHtml}
		                                ${deleteBtnHtml}
		                            </div>
		                        </div>
	                    </div>
                </div>
            `;
            $grid.append(cardHtml);
        });
    }

    // Programa card navigation (open permalink when clicking the card)
	    $(document).on('click', '.pcg-programa-card', function (e) {
        if ($(e.target).closest('button, a, input, select, textarea').length) {
            return;
        }

	        const isPending = Number($(this).attr('data-pending') || 0) === 1;
	        if (isPending) {
	            alert(t('pendingApprovalNotice'));
	            return;
	        }

	        const permalink = $(this).attr('data-permalink') || '';
	        if (permalink) window.location.href = permalink;
	    });

    // Specialization card navigation (open permalink when clicking the card)
	    $(document).on('click', '.pcg-specialization-card', function (e) {
        if ($(e.target).closest('button, a, input, select, textarea').length) {
            return;
        }

	        const isPending = Number($(this).attr('data-pending') || 0) === 1;
	        if (isPending) {
	            alert(t('pendingApprovalNotice'));
	            return;
	        }

	        const permalink = $(this).attr('data-permalink') || '';
	        if (permalink) window.location.href = permalink;
	    });

    function showEditLoadingState() {
        resetForm();
        $('#pcg-my-courses-section').hide();
        $('#pcg-course-form-section').show();
        $('.pcg-mode-content').hide();

	        if (!$('#pcg-edit-loading').length) {
	            $('#pcg-course-form-section').append(`
	                <div id="pcg-edit-loading" class="pcg-loading-placeholder">
	                    <span class="dashicons dashicons-update spin"></span>
	                    <p>${t('loadingCourse')}</p>
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
	                    populateTeachersList($('#pcg-teachers-list'), data.teachers || [], {
	                        id: Number(data.author_id || 0),
	                        name: data.author_name || '',
	                        avatar: data.author_avatar || ''
	                    });

                    // Reset Tabs to "CURSO"
                    $('.pcg-segment').removeClass('active');
                    $('.pcg-segment[data-value="curso"]').addClass('active');
                    $('.pcg-mode-content').hide();
                    $('#pcg-mode-curso').show();

                    hideEditLoadingState();
                } else {
	                    $('#pcg-course-form-section').hide();
	                    $('#pcg-my-courses-section').show();
	                    alert(t('errorGettingCourseData') + (response.data ? response.data.message : t('unknownError')));
	                }
	            },
            error: function (jqXHR, textStatus) {
                if (textStatus === 'abort') {
                    return;
	                }
	                $('#pcg-course-form-section').hide();
	                $('#pcg-my-courses-section').show();
	                alert(t('errorLoadingCourseGeneric'));
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
	        if (!confirm(t('confirmDeleteCourse'))) return;
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
                        if (getActiveGrid().children().length === 0) refreshActiveList();
                    });
                }
            }
        });
    });

    function indexPendingApprovals(items) {
        pendingApprovalsIndex = { group: {}, program: {} };
        (Array.isArray(items) ? items : []).forEach(item => {
            const type = String(item.container_type || '');
            const id = Number(item.container_id || 0);
            const snapshotId = Number(item.snapshot_id || 0);
            if (!type || !id || !snapshotId) return;
            if (!pendingApprovalsIndex[type]) pendingApprovalsIndex[type] = {};
            pendingApprovalsIndex[type][id] = {
                snapshot_id: snapshotId,
                profit_percentage: item.profit_percentage,
                created_by_name: item.created_by_name
            };
        });
    }

    function fetchPendingApprovals() {
        return $.ajax({
            url: pcgCreatorData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pcg_get_my_pending_approvals',
                nonce: pcgCreatorData.nonce
            },
            success: function (response) {
                if (response && response.success) {
                    const items = response.data || [];
                    indexPendingApprovals(items);
                } else {
                    indexPendingApprovals([]);
                }
            }
        });
    }

    // Approve/Reject actions directly from cards (specializations/programs).
    $(document).on('click', '.pcg-card-approval-approve', function () {
        const snapshotId = Number($(this).closest('.pcg-card-approval-actions').data('snapshot-id')) || 0;
        if (!snapshotId) return;
        $.ajax({
            url: pcgCreatorData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pcg_approve_inclusion_snapshot',
                nonce: pcgCreatorData.nonce,
                snapshot_id: snapshotId
            },
            success: function (response) {
                if (response && response.success) {
                    fetchPendingApprovals().always(function () {
                        refreshActiveList();
                    });
                } else {
                    alert(t('approvalActionFailed'));
                }
            }
        });
    });

    $(document).on('click', '.pcg-card-approval-reject', function () {
        const snapshotId = Number($(this).closest('.pcg-card-approval-actions').data('snapshot-id')) || 0;
        if (!snapshotId) return;
        if (!confirm(t('confirmReject'))) return;
        $.ajax({
            url: pcgCreatorData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pcg_reject_inclusion_snapshot',
                nonce: pcgCreatorData.nonce,
                snapshot_id: snapshotId
            },
            success: function (response) {
                if (response && response.success) {
                    fetchPendingApprovals().always(function () {
                        refreshActiveList();
                    });
                } else {
                    alert(t('approvalActionFailed'));
                }
            }
        });
    });

    fetchPendingApprovals().always(function () {
        refreshActiveList();
    });
});
