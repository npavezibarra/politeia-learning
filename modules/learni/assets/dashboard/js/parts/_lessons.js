/**
 * Course Creator - Lessons & Section Management
 */
jQuery(document).ready(function($) {

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
    
    // Expose initSortable for tab switching or initial load
    window.initSortableLessons = initSortable;

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
        const escritoId = Number(data.escrito_id || 0) || 0;
        const escritoTitle = String(data.escrito_title || '').trim();

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
                             <input type="hidden" class="pcg-lesson-escrito-id" value="${escritoId ? String(escritoId) : ''}">
                             <div class="pcg-lesson-escrito-selected" ${escritoId ? '' : 'style="display:none;"'}>
                                 <span class="pcg-lesson-escrito-selected__label">TEXTO</span>
                                 <span class="pcg-lesson-escrito-selected__title">${escritoTitle ? escritoTitle : ''}</span>
                             </div>
	                        <button type="button" class="pcg-btn-add-text">${t('addText')}</button>
	                        <button type="button" class="pcg-btn-remove-text" ${escritoId ? '' : 'style="display:none;"'}>${t('remove')}</button>
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
    
    // Expose addContentItem for course loading logic
    window.addCourseContentItem = addContentItem;

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

    // ───────────────────────────────────────────────────────────
    // Lecciones: link "texto" desde Mis Escritos (posts)
    // ───────────────────────────────────────────────────────────
    let pcgEscritoPickerState = {
        $overlay: null,
        $list: null,
        $search: null,
        $accept: null,
        $close: null,
        currentLessonItem: null,
        escritosCache: null,
        inFlight: null,
    };

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function ensureEscritoPicker() {
        if (pcgEscritoPickerState.$overlay) return pcgEscritoPickerState;

        pcgEscritoPickerState.$overlay = $('#pcg-escrito-picker-overlay');
        if (pcgEscritoPickerState.$overlay.length && !pcgEscritoPickerState.$overlay.parent().is('body')) {
            pcgEscritoPickerState.$overlay.appendTo(document.body);
        }
        pcgEscritoPickerState.$list = pcgEscritoPickerState.$overlay.find('[data-pcg-escrito-picker-list]');
        pcgEscritoPickerState.$search = $('#pcg-escrito-picker-search');
        pcgEscritoPickerState.$accept = pcgEscritoPickerState.$overlay.find('[data-pcg-escrito-picker-accept]');
        pcgEscritoPickerState.$close = pcgEscritoPickerState.$overlay.find('[data-pcg-escrito-picker-close]');

        pcgEscritoPickerState.$close.on('click', closeEscritoPicker);
        pcgEscritoPickerState.$overlay.on('keydown', function (e) {
            if (e.key === 'Escape') closeEscritoPicker();
        });

        pcgEscritoPickerState.$accept.on('click', function () {
            const state = ensureEscritoPicker();
            if (!state.currentLessonItem) return;
            const chosen = state.$list.find('input[name="pcg_escrito_pick"]:checked').val();
            const escritoId = Number(chosen || 0) || 0;
            const $lesson = $(state.currentLessonItem);
            const $hidden = $lesson.find('.pcg-lesson-escrito-id');
            const $selected = $lesson.find('.pcg-lesson-escrito-selected');
            const $titleEl = $lesson.find('.pcg-lesson-escrito-selected__title');
            const $removeBtn = $lesson.find('.pcg-btn-remove-text');

            if (escritoId > 0 && Array.isArray(state.escritosCache)) {
                const match = state.escritosCache.find(p => Number(p.id || 0) === escritoId);
                const title = match ? String(match.title || '').trim() : '';
                $hidden.val(String(escritoId));
                $titleEl.text(title);
                $selected.show();
                $removeBtn.show();
            } else {
                $hidden.val('');
                $titleEl.text('');
                $selected.hide();
                $removeBtn.hide();
            }

            closeEscritoPicker();
        });

        pcgEscritoPickerState.$search.on('input', function () {
            const q = String($(this).val() || '').toLowerCase().trim();
            pcgEscritoPickerState.$list.find('[data-pcg-escrito-title]').each(function () {
                const title = String($(this).attr('data-pcg-escrito-title') || '').toLowerCase();
                $(this).toggle(q === '' || title.includes(q));
            });
        });

        return pcgEscritoPickerState;
    }

    function openEscritoPicker($lessonItem) {
        const state = ensureEscritoPicker();
        state.currentLessonItem = $lessonItem && $lessonItem.length ? $lessonItem.get(0) : null;
        if (!state.$overlay.length) return;

        const currentId = state.currentLessonItem
            ? (Number($(state.currentLessonItem).find('.pcg-lesson-escrito-id').val() || 0) || 0)
            : 0;

        state.$overlay.removeClass('pcg-escrito-picker-overlay--hidden').attr('aria-hidden', 'false');
        $('body').addClass('pcg-modal-open');
        if (state.$search.length) state.$search.val('').trigger('input').focus();

        renderEscritoPickerList(state, state.escritosCache, currentId, true);

        if (state.inFlight && state.inFlight.abort) {
            try { state.inFlight.abort(); } catch (_) {}
        }

        state.inFlight = $.ajax({
            url: pcgCreatorData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pcg_get_my_escritos',
                nonce: pcgCreatorData.nonce
            },
            success: function (response) {
                if (!response || !response.success) {
                    renderEscritoPickerList(state, [], currentId, false);
                    return;
                }
                state.escritosCache = Array.isArray(response.data) ? response.data : [];
                renderEscritoPickerList(state, state.escritosCache, currentId, false);
            },
            error: function () {
                renderEscritoPickerList(state, [], currentId, false);
            }
        });
    }

    function closeEscritoPicker() {
        const state = ensureEscritoPicker();
        if (!state.$overlay || !state.$overlay.length) return;
        state.$overlay.addClass('pcg-escrito-picker-overlay--hidden').attr('aria-hidden', 'true');
        $('body').removeClass('pcg-modal-open');
        state.currentLessonItem = null;
        state.$accept.prop('disabled', true);
    }

    function renderEscritoPickerList(state, escritos, selectedId, isLoading) {
        const list = Array.isArray(escritos) ? escritos : [];
        const current = Number(selectedId || 0) || 0;

        state.$list.empty();

        if (isLoading) {
            state.$list.append(`<div class="pcg-empty-msg" style="padding:14px;">${escapeHtml(t('loadingEscritos'))}</div>`);
            state.$accept.prop('disabled', true);
            return;
        }

        if (!list || list.length === 0) {
            state.$list.append(`<div class="pcg-empty-msg" style="padding:14px;">${escapeHtml(t('noEscritosYet'))}</div>`);
            state.$accept.prop('disabled', true);
            return;
        }

        // Option to clear selection.
        state.$list.append(`
            <label class="pcg-escrito-picker-item" data-pcg-escrito-title="">
                <input type="radio" name="pcg_escrito_pick" value="0" ${current === 0 ? 'checked' : ''}>
                <span class="pcg-escrito-picker-item__meta">
                    <span class="pcg-escrito-picker-item__title">(Sin texto)</span>
                </span>
            </label>
        `);

        list.forEach(function (escrito) {
            const id = Number(escrito && escrito.id ? escrito.id : 0) || 0;
            const title = String(escrito && escrito.title ? escrito.title : '').trim();
            const status = String(escrito && escrito.status ? escrito.status : '').trim();
            const date = String(escrito && escrito.date ? escrito.date : '').trim();
            const isDraft = status === 'draft';
            const badge = isDraft ? `<span class="pcg-escrito-picker-badge pcg-escrito-picker-badge--draft">BORRADOR</span>` : '';

            state.$list.append(`
                <label class="pcg-escrito-picker-item" data-pcg-escrito-title="${escapeHtml(title)}">
                    <input type="radio" name="pcg_escrito_pick" value="${id}" ${id === current ? 'checked' : ''}>
                    <span class="pcg-escrito-picker-item__meta">
                        <span class="pcg-escrito-picker-item__title">${escapeHtml(title || '(Sin título)')}</span>
                        <span class="pcg-escrito-picker-item__sub">
                            ${badge}
                            ${date ? `<span>${escapeHtml(date)}</span>` : ''}
                        </span>
                    </span>
                </label>
            `);
        });

        state.$accept.prop('disabled', false);
    }

    $(document).on('change', '#pcg-escrito-picker-overlay input[name="pcg_escrito_pick"]', function () {
        const state = ensureEscritoPicker();
        state.$accept.prop('disabled', false);
    });

    $(document).on('click', '.pcg-btn-add-text', function (e) {
        e.preventDefault();
        const $lessonItem = $(this).closest('.pcg-content-item.item-lesson');
        if (!$lessonItem.length) return;
        openEscritoPicker($lessonItem);
    });

    $(document).on('click', '.pcg-btn-remove-text', function (e) {
        e.preventDefault();
        const $lesson = $(this).closest('.pcg-content-item.item-lesson');
        if (!$lesson.length) return;
        $lesson.find('.pcg-lesson-escrito-id').val('');
        $lesson.find('.pcg-lesson-escrito-selected__title').text('');
        $lesson.find('.pcg-lesson-escrito-selected').hide();
        $(this).hide();
    });

});
