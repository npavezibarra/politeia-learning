/**
 * Programs Module
 * Handles Program (course_program) Creator UI.
 */
jQuery(document).ready(function ($) {
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

            $('.pcg-mode-content').removeClass('is-visible').hide();
            const $target = $('#pcg-prog-mode-programa');
            $target.show();
            if ($target[0]) $target[0].offsetHeight;
            $target.addClass('is-visible');

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

            plLearningMeta.reset('programa');
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
                        window.pcgShowToast(t('errorLoadingProgram'), 'error');
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

                    plLearningMeta.setSelection('programa', data.category_ids || [], data.tags || []);

                    if (data.title) {
                        $('#pcg-current-programa-label').text(data.title).show();
                    }

                    const priceNum = parseFloat(String(data.price || '').replace(',', '.')) || 0;
                    if (priceNum === 0) {
                        $('#pcg-programa-price-free-indicator').show();
                    }

                    $('.pcg-prog-segment').removeClass('active');
                    $('.pcg-prog-segment[data-value="programa"]').addClass('active');
                    
                    $('.pcg-mode-content').removeClass('is-visible').hide();
                    const $target = $('#pcg-prog-mode-programa');
                    $target.show();
                    if ($target[0]) $target[0].offsetHeight;
                    $target.addClass('is-visible');

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
                    window.pcgShowToast(t('errorLoadingProgramGeneric'), 'error');
                    $('#pcg-programa-form-section').hide();
                    $('#pcg-my-programas-section').show();
                });
            });
        }

        function getProgramaPayload() {
            const meta = plLearningMeta.getPayload('programa');
            return {
                id: currentProgramaId,
                title: $('#pcg-programa-title').val(),
                description: $('#pcg-programa-description').val(),
                price: $('#pcg-programa-price').val(),
                group_ids: selectedGroupIds,
                teachers: collectTeachers($('#pcg-program-teachers-list')),
                split_locked: Boolean($('#pcg-program-teachers-list').data('splitLocked')),
                category_ids: meta.category_ids,
                tag_ids: meta.tag_ids,
            };
        }

        $('#pcg-show-programa-form').on('click', function () {
            $('#pcg-my-programas-section').fadeOut(300, function () {
                resetProgramaForm();
                $('#pcg-programa-form-section').fadeIn(400);
                loadSpecializationsForPrograma();
            });
        });

        $('#pcg-btn-back-to-programas').on('click', function () {
            $('#pcg-programa-form-section').fadeOut(300, function () {
                $('#pcg-my-programas-section').fadeIn();
                resetProgramaForm();
            });
        });

        $(document).on('click', '.pcg-prog-segment', function () {
            const $form = $('#pcg-programa-form-section');
            $form.find('.pcg-prog-segment').removeClass('active');
            $(this).addClass('active');

            const mode = $(this).data('value');
            $('.pcg-mode-content').removeClass('is-visible').hide();
            
            const $target = $(`#pcg-prog-mode-${mode}`);
            $target.show();
            if ($target[0]) $target[0].offsetHeight;
            $target.addClass('is-visible');

            if (mode === 'especializaciones') {
                loadSpecializationsForPrograma();
            } else if (mode === 'meta') {
                plLearningMeta.render('programa');
            }
        });

        $('#pcg-programa-title').on('input', function () {
            const title = $(this).val();
            if (title) {
                $('#pcg-current-programa-label').text(title).show();
            } else {
                $('#pcg-current-programa-label').hide();
            }
        });

        $('#pcg-programa-price').on('input change', function () {
            const price = parseFloat($(this).val()) || 0;
            if (price === 0) {
                $('#pcg-programa-price-free-indicator').fadeIn(200);
            } else {
                $('#pcg-programa-price-free-indicator').fadeOut(200);
            }
        });

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

        $('.pcg-btn-save-programa').on('click', function () {
            const $btn = $(this);
            const payload = getProgramaPayload();

            if (!payload.title) {
                window.pcgShowToast(t('pleaseEnterProgramName'), 'error');
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
                    window.pcgShowToast(t('errorSavingProgram'), 'error');
                    $btn.prop('disabled', false);
                }
            });
        });

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
                        if (typeof refreshActiveList === 'function') refreshActiveList();
                    } else {
                        window.pcgShowToast(t('errorPrefix') + (response && response.data && response.data.message ? response.data.message : t('couldNotDelete')), 'error');
                    }
                },
                error: function () {
                    window.pcgShowToast(t('errorDeletingProgram'), 'error');
                }
            });
        });
    })();
});
