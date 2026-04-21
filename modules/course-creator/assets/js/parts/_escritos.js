/**
 * Course Creator - Escritos (Posts) Logic
 */
jQuery(document).ready(function($) {

    (function initEscritosCreator() {
        if (!$('#pcg-show-escritos-form').length) {
            return;
        }

        // Helper to ensure editor focus
        function focusEditor() {
            const editor = document.getElementById('pcg-escrito-content-editor');
            if (editor && document.activeElement !== editor) {
                editor.focus();
            }
        }

        function getEditorEl() {
            return document.getElementById('pcg-escrito-content-editor');
        }

        let savedEditorRange = null;
        let isSelectionLocked = false;

        function isRangeInsideEditor(range, editor) {
            if (!range || !editor) return false;
            return editor.contains(range.startContainer) && editor.contains(range.endContainer);
        }

        function saveEditorSelection(force = false) {
            if (isSelectionLocked && !force) return;
            const editor = getEditorEl();
            if (!editor) return;
            const sel = window.getSelection ? window.getSelection() : null;
            if (!sel || sel.rangeCount === 0) return;
            const range = sel.getRangeAt(0);
            if (!isRangeInsideEditor(range, editor)) return;
            savedEditorRange = range.cloneRange();
        }

        function restoreEditorSelection() {
            const editor = getEditorEl();
            if (!editor) return false;

            if (savedEditorRange && isRangeInsideEditor(savedEditorRange, editor)) {
                const sel = window.getSelection ? window.getSelection() : null;
                if (!sel) return false;
                sel.removeAllRanges();
                sel.addRange(savedEditorRange);
                return true;
            }

            return false;
        }

        function placeCaretAtEnd(editor) {
            if (!editor || !window.getSelection || !document.createRange) return;
            const range = document.createRange();
            range.selectNodeContents(editor);
            range.collapse(false);
            const sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(range);
        }

        function insertHtmlAtEditorSelection(html) {
            const editor = getEditorEl();
            if (!editor) return false;

            const sel = window.getSelection ? window.getSelection() : null;
            let range = (sel && sel.rangeCount > 0) ? sel.getRangeAt(0) : null;

            if (!isRangeInsideEditor(range, editor)) {
                range = (savedEditorRange && isRangeInsideEditor(savedEditorRange, editor)) ? savedEditorRange.cloneRange() : null;
            }
            if (!range) {
                placeCaretAtEnd(editor);
                if (sel && sel.rangeCount > 0) {
                    range = sel.getRangeAt(0);
                }
            }
            if (!range) return false;

            range.deleteContents();
            const container = document.createElement('div');
            container.innerHTML = html;
            const frag = document.createDocumentFragment();
            let lastNode = null;
            while (container.firstChild) {
                lastNode = frag.appendChild(container.firstChild);
            }
            range.insertNode(frag);

            if (sel && lastNode) {
                const next = document.createRange();
                next.setStartAfter(lastNode);
                next.collapse(true);
                sel.removeAllRanges();
                sel.addRange(next);
                savedEditorRange = next.cloneRange();
            }

            return true;
        }

        function getClosestEditorBlock(node, editor) {
            if (!node || !editor) return null;
            let current = node.nodeType === Node.ELEMENT_NODE ? node : node.parentNode;
            while (current && current !== editor) {
                if (
                    current.nodeType === Node.ELEMENT_NODE &&
                    /^(P|DIV|H1|H2|H3|H4|H5|H6)$/i.test(current.tagName)
                ) {
                    return current;
                }
                current = current.parentNode;
            }
            return null;
        }

        function replaceBlockTag(block, tagName) {
            if (!block || !block.parentNode || !tagName) return null;
            const nextTag = String(tagName).toUpperCase();
            if (block.tagName === nextTag) return block;

            const replacement = document.createElement(nextTag);
            replacement.innerHTML = block.innerHTML;

            // Preserve editor-specific metadata if ever added to these blocks.
            Array.from(block.attributes || []).forEach(function (attr) {
                if (attr && attr.name && attr.name !== 'style') {
                    replacement.setAttribute(attr.name, attr.value);
                }
            });

            block.parentNode.replaceChild(replacement, block);
            return replacement;
        }

        function applyBlockTagFallback(tag) {
            const editor = getEditorEl();
            if (!editor) return false;

            const sel = window.getSelection ? window.getSelection() : null;
            let range = (sel && sel.rangeCount > 0) ? sel.getRangeAt(0) : null;
            if (!isRangeInsideEditor(range, editor)) {
                range = (savedEditorRange && isRangeInsideEditor(savedEditorRange, editor)) ? savedEditorRange.cloneRange() : null;
            }
            if (!range) return false;

            const block = getClosestEditorBlock(range.startContainer, editor) || getClosestEditorBlock(range.commonAncestorContainer, editor);
            if (!block) return false;

            const replacement = replaceBlockTag(block, tag);
            if (!replacement || !sel) return false;

            const nextRange = document.createRange();
            nextRange.selectNodeContents(replacement);
            nextRange.collapse(false);
            sel.removeAllRanges();
            sel.addRange(nextRange);
            savedEditorRange = nextRange.cloneRange();
            return true;
        }

        // Expose helpers for inline toolbar buttons.
        window.pcgEscritoExec = function (cmd, value) {
            try {
                focusEditor();
                restoreEditorSelection();
                document.execCommand(cmd, false, value ?? null);
                saveEditorSelection(true);
            } catch (_) {
                // no-op
            }
        };

        window.pcgEscritoFormatBlock = function (tag) {
            const editor = document.getElementById('pcg-escrito-content-editor');
            if (!editor || !editor.innerHTML.trim()) {
                if (editor) editor.innerHTML = '<p><br></p>';
            }

            focusEditor();
            restoreEditorSelection();

            let applied = false;
            try {
                applied = document.execCommand('formatBlock', false, tag);
            } catch (err) {
                applied = false;
            }

            if (!applied) {
                try {
                    applied = document.execCommand('formatBlock', false, '<' + tag + '>');
                } catch (err) {
                    applied = false;
                }
            }

            if (!applied) {
                applyBlockTagFallback(tag);
            } else {
                saveEditorSelection(true);
            }
        };

        $(document).on('mousedown', '.pcg-toolbar-btn, .pcg-dropdown-content button', function (e) {
            saveEditorSelection(true);
            e.preventDefault();
        });

        $(document).on('click', '.pcg-toolbar-btn, .pcg-dropdown-content button', function () {
            if ($(this).attr('onclick')) {
                focusEditor();
                restoreEditorSelection();
            }
        });

        // robust placeholder logic for contenteditable
        function handlePlaceholder() {
            const $ed = $('#pcg-escrito-content-editor');
            if (!$ed.length) return;
            // Get raw text (ignores HTML tags)
            const text = $ed.text().trim();
            const html = $ed.html().trim().toLowerCase();
            const hasImages = html.includes('<img') || html.includes('<figure');
            // It's empty if there's no actual text, AND the HTML is either entirely empty or just browsers' default empty blocks
            const isEmpty = (!hasImages && text === '' && (html === '' || html === '<br>' || html === '<p><br></p>' || html === '<p></p>' || html === '<br><div></div>' || html.replace(/<[^>]*>/g, '').trim() === ''));
            $ed.toggleClass('pcg-is-empty', isEmpty);
            $('#pcg-editor-placeholder').toggle(isEmpty);
        }

        $(document).on('input keyup blur focus change', '#pcg-escrito-content-editor', handlePlaceholder);
        $(document).on('keyup mouseup focus blur input', '#pcg-escrito-content-editor', saveEditorSelection);
        $(document).on('selectionchange', function () {
            saveEditorSelection();
        });

        let currentEscritoId = 0;
        let escritoThumbnailId = 0;

        function resetEscritoForm() {
            currentEscritoId = 0;
            escritoThumbnailId = 0;
            $('#pcg-current-escrito-id').val(0);
            $('#pcg-escrito-title').val('').css('height', 'auto');
            $('#pcg-escrito-content-editor').html('');
            $('#pcg-escrito-content').val('');
            $('#pcg-escrito-excerpt').val('');
            $('#pcg-escrito-thumbnail-preview').hide().find('img').attr('src', '');
            $('#pcg-escrito-upload-ui').show();
            $('#pcg-current-escrito-label').text('').hide();
            $('#pcg-btn-preview-escrito').hide();
            handlePlaceholder();
        }

        $('#pcg-show-escritos-form').on('click', function () {
            $('#pcg-my-escritos-section').fadeOut(300, function () {
                resetEscritoForm();
                $('#pcg-escritos-form-section').fadeIn(300, function () {
                    initInlineImageMicroText();
                    normalizeInlineImages(getEditorEl());
                });
            });
        });

        $('#pcg-btn-back-to-escritos').on('click', function () {
            $('#pcg-escritos-form-section').fadeOut(300, function () {
                $('#pcg-my-escritos-section').fadeIn();
                resetEscritoForm();
            });
        });

        $(document).on('click', '.pcg-btn-save-escrito', function () {
            const $btn = $(this);
            const action = $btn.data('action') || 'publish';
            const content = $('#pcg-escrito-content-editor').html();
            const payload = {
                id: currentEscritoId,
                title: $('#pcg-escrito-title').val(),
                content: content,
                excerpt: $('#pcg-escrito-excerpt').val(),
                thumbnail_id: escritoThumbnailId,
                status: action
            };

            if (!payload.title) {
                window.pcgShowToast(t('pleaseEnterEscritoTitle'), 'error');
                return;
            }

            $('.pcg-btn-save-escrito').prop('disabled', true);
            $btn.addClass('loading');

            $.ajax({
                url: pcgCreatorData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pcg_save_escrito',
                    nonce: pcgCreatorData.nonce,
                    escrito_data: payload
                },
                success: function (response) {
                    $btn.removeClass('loading');
                    $('.pcg-btn-save-escrito').prop('disabled', false);

                    if (response.success) {
                        currentEscritoId = response.data.escrito_id;
                        $('#pcg-current-escrito-id').val(currentEscritoId);
                        $btn.addClass('success');
                        
                        if (typeof window.refreshActiveList === 'function') {
                            window.refreshActiveList();
                        }

                        // Toggle logic for draft icon
                        if (action === 'draft') {
                            $('#pcg-publish-status-icon').show();
                        } else {
                            $('#pcg-publish-status-icon').hide();
                        }

                        if (response.data.permalink) {
                            $('#pcg-btn-preview-escrito').attr('href', response.data.permalink).show();
                        }

                        setTimeout(() => {
                            $btn.removeClass('success');
                        }, 2000);
                    } else {
                        window.pcgShowToast(t('errorSavingEscrito') + ': ' + (response.data ? response.data.message : t('unknownError')), 'error');
                    }
                },
                error: function () {
                    $btn.removeClass('loading');
                    $('.pcg-btn-save-escrito').prop('disabled', false);
                    window.pcgShowToast(t('errorSavingEscrito'), 'error');
                }
            });
        });

        // Edit
        $(document).on('click', '.pcg-btn-edit-escrito', function () {
            const escritoId = $(this).closest('.pcg-course-card').data('id');
            if (!escritoId) return;

            resetEscritoForm();
            $('#pcg-my-escritos-section').hide();
            $('#pcg-escritos-form-section').show();

            $.ajax({
                url: pcgCreatorData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pcg_get_escrito_for_edit',
                    nonce: pcgCreatorData.nonce,
                    escrito_id: escritoId
                },
                success: function (response) {
                    if (response.success) {
                        const data = response.data;
                        currentEscritoId = data.id;
                        $('#pcg-current-escrito-id').val(data.id);
                        $('#pcg-escrito-title').val(data.title);
                        $('#pcg-escrito-content-editor').html(data.content);
                        $('#pcg-escrito-content').val(data.content);
                        $('#pcg-escrito-excerpt').val(data.excerpt);
                        $('#pcg-current-escrito-label').text(data.title).show();

                        escritoThumbnailId = data.thumbnail_id;
                        if (data.thumbnail_url) {
                            $('#pcg-escrito-thumbnail-preview img').attr('src', data.thumbnail_url);
                            $('#pcg-escrito-thumbnail-preview').show();
                            $('#pcg-escrito-upload-ui').hide();
                        }
                        handlePlaceholder();
                        initInlineImageMicroText();
                        normalizeInlineImages(getEditorEl());

                        if (data.permalink) {
                            $('#pcg-btn-preview-escrito').attr('href', data.permalink).show();
                        }

                        if (data.status === 'draft') {
                            $('#pcg-publish-status-icon').show();
                        } else {
                            $('#pcg-publish-status-icon').hide();
                        }

                        // Auto-resize title after loading
                        setTimeout(() => {
                            const $title = $('#pcg-escrito-title');
                            $title.css('height', 'auto').css('height', $title[0].scrollHeight + 'px');
                        }, 50);
                    } else {
                        window.pcgShowToast(response.data.message, 'error');
                        $('#pcg-btn-back-to-escritos').trigger('click');
                    }
                }
            });
        });

        // Delete
        $(document).on('click', '.pcg-btn-delete-escrito', function () {
            const escritoId = $(this).closest('.pcg-course-card').data('id');
            if (!confirm(t('confirmDeleteCourse'))) return;

            $.ajax({
                url: pcgCreatorData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pcg_delete_escrito',
                    nonce: pcgCreatorData.nonce,
                    escrito_id: escritoId
                },
                success: function (response) {
                    if (response.success) {
                        if (typeof window.refreshActiveList === 'function') {
                            window.refreshActiveList();
                        }
                    }
                }
            });
        });

        // Image upload
        $(document).on('click', '[data-upload="escrito-thumbnail"]', function () {
            PL_Cropper.open({
                // Match the cover preview aspect ratio (16:9) and export at higher resolution,
                // capped to keep the uploaded file under ~400KB.
                width: 800,
                height: 450,
                outputMaxWidth: 1600,
                outputMaxHeight: 900,
                maxBytes: 400 * 1024,
                quality: 0.9,
                minQuality: 0.7,
                title: t('uploadImage'),
                onSave: function (dataUrl) {
                    $.ajax({
                        url: pcgCreatorData.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'pcg_upload_cropped_image',
                            nonce: pcgCreatorData.nonce,
                            image_data: dataUrl,
                            type: 'escrito'
                        },
                        success: function (response) {
                            if (response.success) {
                                escritoThumbnailId = response.data.id;
                                $('#pcg-escrito-thumbnail-preview img').attr('src', response.data.url);
                                $('#pcg-escrito-thumbnail-preview').show();
                                $('#pcg-escrito-upload-ui').hide();
                            } else {
                                window.pcgShowToast(t('errorUploadingImage'), 'error');
                            }
                        }
                    });
                }
            });
        });

        $(document).on('click', '#pcg-remove-escrito-thumbnail', function () {
            escritoThumbnailId = 0;
            $('#pcg-escrito-thumbnail-preview').hide().find('img').attr('src', '');
            $('#pcg-escrito-upload-ui').show();
        });

        $(document).on('mousedown', '#pcg-btn-escrito-add-image', function () {
            saveEditorSelection(true);
        });

        // Inline Image Insertion via custom Cropper
        $(document).on('click', '#pcg-btn-escrito-add-image', function (e) {
            e.preventDefault();
            saveEditorSelection(true);
            isSelectionLocked = true;
            const markerId = 'pcg-insert-marker-' + Date.now();
            const preModalScrollY = window.pageYOffset || document.documentElement.scrollTop || 0;

            const editor = getEditorEl();
            if (editor) {
                focusEditor();
                if (!restoreEditorSelection()) {
                    placeCaretAtEnd(editor);
                }
            }

            const markerInserted = insertHtmlAtEditorSelection(`<span id="${markerId}" class="pcg-insert-marker" contenteditable="false"></span>`);
            if (!markerInserted && editor) {
                editor.insertAdjacentHTML('beforeend', `<span id="${markerId}" class="pcg-insert-marker" contenteditable="false"></span>`);
            }

            PL_Cropper.open({
                width: 800,
                height: 600,
                freeCrop: true,
                title: t('uploadImage'),
                onCancel: function () {
                    isSelectionLocked = false;
                    $('#' + markerId).remove();
                },
                onSave: function (dataUrl) {
                    const tempId = 'pcg-loading-' + Date.now();
                    const $marker = $('#' + markerId);
                    if ($marker.length) {
                        $marker.replaceWith(`<span id="${tempId}" class="pcg-img-loading">Cargando imagen...</span>`);
                    } else if (editor) {
                        editor.insertAdjacentHTML('beforeend', `<span id="${tempId}" class="pcg-img-loading">Cargando imagen...</span>`);
                    }
                    isSelectionLocked = false;
                    saveEditorSelection(true);

                    $.ajax({
                        url: pcgCreatorData.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'pcg_upload_cropped_image',
                            nonce: pcgCreatorData.nonce,
                            image_data: dataUrl,
                            type: 'inline',
                            entity_id: currentEscritoId
                        },
                        success: function (response) {
	                            if (response.success) {
	                                const loader = document.getElementById(tempId);
                                const attachmentId = response && response.data && response.data.id ? String(response.data.id) : '';
                                const imageId = attachmentId ? `pcg-inline-img-${attachmentId}` : `pcg-inline-img-${Date.now()}`;
                                const figureHtml = `
                                    <figure class="pcg-inline-figure">
                                        <img id="${imageId}" data-attachment-id="${attachmentId}" src="${response.data.url}" />
                                        <figcaption class="pcg-inline-caption" contenteditable="false" data-placeholder="Escribe un texto para esta imagen..." data-editing="false"></figcaption>
                                    </figure>
                                `.replace(/\s{2,}/g, ' ').trim();
	                                if (loader) {
	                                    $(loader).replaceWith(figureHtml);
	                                    const insertedFigure = document.querySelector('#pcg-escrito-content-editor figure.pcg-inline-figure:last-of-type');
	                                    if (insertedFigure) {
	                                        insertedFigure.scrollIntoView({ block: 'center', inline: 'nearest' });
                                            normalizeInlineImages(getEditorEl());
	                                    } else {
	                                        window.scrollTo(0, preModalScrollY);
	                                    }
	                                } else {
	                                    insertHtmlAtEditorSelection(figureHtml);
                                        normalizeInlineImages(getEditorEl());
	                                    window.scrollTo(0, preModalScrollY);
	                                }
	                            } else {
                                window.pcgShowToast(t('errorUploadingImage'), 'error');
                                $('#' + tempId).remove();
                            }
                        },
                        error: function () {
                            window.pcgShowToast(t('errorUploadingImage'), 'error');
                            $('#' + tempId).remove();
                        }
                    });
                }
            });
        });

        // Floating Remove Button for Inline Images
        let activeHoverImg = null;
        const $floatRemoveBtn = $('<button class="pcg-float-remove-btn" title="Remover imagen">&times;</button>').appendTo('body');

        $(document).on('click', '#pcg-escrito-content-editor img', function () {
            activeHoverImg = $(this);
            const offset = activeHoverImg.offset();
            $floatRemoveBtn.css({
                top: offset.top + 10,
                left: offset.left + activeHoverImg.width() - 40,
                display: 'flex'
            });
        });

        $(document).on('click', function (e) {
            if (!$floatRemoveBtn.is(':visible')) return;
            if ($(e.target).closest('#pcg-escrito-content-editor img').length) return;
            if ($(e.target).closest('.pcg-float-remove-btn').length) return;
            $floatRemoveBtn.hide();
            activeHoverImg = null;
        });

	        $floatRemoveBtn.on('click', function (e) {
	            e.preventDefault();
	            if (activeHoverImg) {
	                const $figure = activeHoverImg.closest('figure.pcg-inline-figure');
	                if ($figure.length) {
	                    $figure.remove();
	                } else {
	                    activeHoverImg.remove();
	                }
	                $floatRemoveBtn.hide();
	                activeHoverImg = null;
	            }
	        });

        function updateCaptionState(captionEl) {
            const caption = captionEl instanceof HTMLElement ? captionEl : null;
            if (!caption) return;
            const figure = caption.closest('figure.pcg-inline-figure');
            if (!figure) return;
	            const text = (caption.textContent || '').replace(/\u00a0/g, ' ').trim();
	            if (text.length === 0) {
	                // Remove stray <br> that browsers often insert into empty contenteditables
	                caption.innerHTML = '';
            }
            figure.classList.toggle('pcg-has-caption-text', text.length > 0);
        }

        let inlineImageObserverInitialized = false;
        let isNormalizingInlineImages = false;

        function ensureUniqueId(id, el) {
            if (!id) return '';
            let candidate = id;
            let i = 2;
            while (document.getElementById(candidate) && document.getElementById(candidate) !== el) {
                candidate = `${id}-${i++}`;
            }
            return candidate;
        }

        function normalizeInlineImages(editor) {
            if (!editor || isNormalizingInlineImages) return;
            isNormalizingInlineImages = true;
            try {
                const images = Array.from(editor.querySelectorAll('img'));
                images.forEach((img) => {
                    if (!(img instanceof HTMLElement)) return;

                    const attachmentId = (img.getAttribute('data-attachment-id') || '').trim();
                    if (!img.id) {
                        const baseId = attachmentId ? `pcg-inline-img-${attachmentId}` : `pcg-inline-img-${Date.now()}-${Math.random().toString(16).slice(2)}`;
                        img.id = ensureUniqueId(baseId, img);
                    } else {
                        img.id = ensureUniqueId(img.id, img);
                    }

                    const existingFigure = img.closest('figure');
                    if (existingFigure && existingFigure.classList.contains('pcg-inline-figure')) {
                        let caption = existingFigure.querySelector('figcaption');
                        if (!caption) {
                            caption = document.createElement('figcaption');
                            existingFigure.appendChild(caption);
                        }
                        caption.classList.add('pcg-inline-caption');
                        caption.setAttribute('data-placeholder', caption.getAttribute('data-placeholder') || 'Escribe un texto para esta imagen...');
                        caption.setAttribute('data-editing', 'false');
                        caption.setAttribute('contenteditable', 'false');
                        updateCaptionState(caption);
                        return;
                    }

                    if (existingFigure && !existingFigure.classList.contains('pcg-inline-figure')) {
                        existingFigure.classList.add('pcg-inline-figure');
                        let caption = existingFigure.querySelector('figcaption');
                        if (!caption) {
                            caption = document.createElement('figcaption');
                            existingFigure.appendChild(caption);
                        }
                        caption.classList.add('pcg-inline-caption');
                        caption.setAttribute('data-placeholder', caption.getAttribute('data-placeholder') || 'Escribe un texto para esta imagen...');
                        caption.setAttribute('data-editing', 'false');
                        caption.setAttribute('contenteditable', 'false');
                        updateCaptionState(caption);
                        return;
                    }

                    // Avoid restructuring images inside tables.
                    if (img.closest('table')) return;

                    const parent = img.parentElement;
                    const shouldReplaceParent = parent && parent.tagName === 'P' && parent.childNodes.length === 1;
                    const figure = document.createElement('figure');
                    figure.className = 'pcg-inline-figure';

                    const caption = document.createElement('figcaption');
                    caption.className = 'pcg-inline-caption';
                    caption.setAttribute('contenteditable', 'false');
                    caption.setAttribute('data-placeholder', 'Escribe un texto para esta imagen...');
                    caption.setAttribute('data-editing', 'false');

                    if (shouldReplaceParent) {
                        parent.parentNode && parent.parentNode.replaceChild(figure, parent);
                    } else if (parent) {
                        parent.insertBefore(figure, img);
                    }

                    figure.appendChild(img);
                    figure.appendChild(caption);
                    updateCaptionState(caption);
                });
            } finally {
                isNormalizingInlineImages = false;
            }
        }

        function initInlineImageMicroText() {
            const editor = getEditorEl();
            if (!editor || inlineImageObserverInitialized || typeof MutationObserver === 'undefined') return;
            inlineImageObserverInitialized = true;

            const observer = new MutationObserver((mutations) => {
                if (isNormalizingInlineImages) return;
                let hasImageChange = false;
                for (const m of mutations) {
                    if (m.type !== 'childList') continue;
                    for (const node of Array.from(m.addedNodes || [])) {
                        if (!node || node.nodeType !== 1) continue;
                        const el = node;
                        if (el.tagName === 'IMG' || (el.querySelector && el.querySelector('img'))) {
                            hasImageChange = true;
                            break;
                        }
                    }
                    if (hasImageChange) break;
                }
                if (hasImageChange) normalizeInlineImages(editor);
            });

            observer.observe(editor, { childList: true, subtree: true });
            normalizeInlineImages(editor);
        }

        function focusCaptionAtEnd(caption) {
            if (!caption || !window.getSelection || !document.createRange) return;
            const range = document.createRange();
            range.selectNodeContents(caption);
            range.collapse(false);
            const selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(range);
            caption.focus();
        }

        $(document).on('click', '#pcg-escrito-content-editor figcaption.pcg-inline-caption', function (e) {
            const caption = this;
            const isEditing = caption.getAttribute('data-editing') === 'true';
            if (!isEditing) {
                e.preventDefault();
                caption.setAttribute('contenteditable', 'true');
                caption.setAttribute('data-editing', 'true');
                focusCaptionAtEnd(caption);
            }
        });

        $(document).on('keydown', '#pcg-escrito-content-editor figcaption.pcg-inline-caption', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.blur();
            }
        });

        $(document).on('input blur', '#pcg-escrito-content-editor figcaption.pcg-inline-caption', function (e) {
            if (e.type === 'blur') {
                const text = (this.textContent || '').replace(/\u00a0/g, ' ').trim();
                this.setAttribute('data-editing', 'false');
                this.setAttribute('contenteditable', 'false');
            }
            updateCaptionState(this);
        });

        // Auto-resize title
        $(document).on('input', '#pcg-escrito-title', function () {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });

        // Improved clean paste that preserves structure (p, h1-h4) but strips all garbage
        $(document).on('paste', '#pcg-escrito-content-editor', function (e) {
            e.preventDefault();
            let html = (e.originalEvent || e).clipboardData.getData('text/html');
            const text = (e.originalEvent || e).clipboardData.getData('text/plain');

            if (html) {
                // Pre-clean: Replace common block wrappers with P to avoid collapsing
                html = html.replace(/<div[^>]*>/gi, '<p>').replace(/<\/div>/gi, '</p>');

                const $temp = $('<div>').html(html);
                const allowedTags = ['P', 'H1', 'H2', 'H3', 'A', 'BR', 'UL', 'OL', 'LI', 'TABLE', 'THEAD', 'TBODY', 'TR', 'TH', 'TD', 'STRONG', 'EM', 'B', 'I', 'IMG', 'FIGURE', 'FIGCAPTION'];

                // Recursive cleanup
                function cleanNode(node) {
                    $(node).children().each(function () {
                        cleanNode(this);
                    });

                    const tag = node.tagName;
                    if (tag === 'DIV') { // Secondary check for nested divs
                        $(node).contents().unwrap();
                        return;
                    }

                    if (!allowedTags.includes(tag) && tag !== 'BODY' && tag !== 'HTML') {
                        $(node).contents().unwrap();
                    } else if (allowedTags.includes(tag)) {
                        const attributes = node.attributes;
                        let allowedAttrs = [];
                        if (tag === 'A') {
                            allowedAttrs = ['href', 'target'];
                        } else if (tag === 'IMG') {
                            allowedAttrs = ['src', 'alt', 'class', 'width', 'height', 'id', 'data-attachment-id'];
                        } else if (tag === 'FIGURE') {
                            allowedAttrs = ['class'];
                        } else if (tag === 'FIGCAPTION') {
                            allowedAttrs = ['class', 'data-placeholder', 'data-editing'];
                        }

                        for (let i = attributes.length - 1; i >= 0; i--) {
                            if (!allowedAttrs.includes(attributes[i].name)) {
                                node.removeAttribute(attributes[i].name);
                            }
                        }
                        $(node).removeAttr('style');
                    }
                }

                $temp.contents().each(function () {
                    if (this.nodeType === 1) cleanNode(this);
                });

                // Final pass: Remove empty paragraphs or weird artifacts
                $temp.find('p').each(function () {
                    if (!$(this).text().trim() && !$(this).find('br, img, iframe').length) {
                        $(this).remove();
                    }
                });

                document.execCommand('insertHTML', false, $temp.html());
            } else {
                // If only text, split by double newlines and wrap in P
                const paragraphs = text.trim().split(/\n\s*\n/);
                const cleanHTML = paragraphs.map(p => `<p>${p.replace(/\n/g, '<br>')}</p>`).join('');
                document.execCommand('insertHTML', false, cleanHTML);
            }
        });

    })();

});
