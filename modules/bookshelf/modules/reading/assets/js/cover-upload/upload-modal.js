window.PRS_Cover_Upload = window.PRS_Cover_Upload || {};

(function(exports) {
    let modal, stage, imgEl, saveBtn, fileInput, statusEl, placeholderEl;
    let selectedFile = null;

    exports.openUploadModal = () => {
        if (modal) modal.remove();
        modal = exports.el('div', 'prs-cover-modal');

        let panel = null;
        const template = document.getElementById('prs-cover-modal-template');
        if (template && 'content' in template) {
            const fragment = template.content.cloneNode(true);
            panel = fragment.querySelector('.prs-cover-modal__content');
            if (panel) modal.appendChild(panel);
        }

        if (!panel) {
            panel = exports.el('div', 'prs-cover-modal__content');
            panel.innerHTML = `
                <div class="prs-cover-modal__title">${exports.text('modal_title', 'Upload Book Cover')}</div>
                <div class="prs-cover-modal__grid">
                    <div class="prs-crop-wrap" id="drag-drop-area">
                        <div id="cropStage" class="prs-crop-stage" title="${exports.text('drop_here_title', 'Drop JPEG or PNG file here')}">
                            <div id="cropPlaceholder" class="prs-crop-placeholder">
                                <svg class="prs-upload-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M4 14.9V8a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3.1" />
                                    <path d="M16 16l-4-4-4 4" />
                                    <path d="M12 12v9" />
                                </svg>
                                <p>${exports.text('drag_here', 'Drag JPEG or PNG here (220x350 Preview)')}</p>
                                <span>${exports.text('click_upload', 'or click upload')}</span>
                            </div>
                            <img id="previewImage" src="" alt="${exports.text('preview_alt', 'Book Cover Preview')}" style="display:none;">
                        </div>
                    </div>
                    <div class="prs-cover-controls">
                        <input type="file" id="fileInput" accept="image/jpeg, image/png" class="prs-hidden-input" style="display:none;">
                        <div class="prs-btn-group">
                            <button class="prs-btn prs-btn--ghost" type="button" id="prs-cover-cancel">${exports.text('cancel', 'Cancel')}</button>
                            <button class="prs-btn" type="button" id="prs-cover-save">${exports.text('save', 'Save')}</button>
                        </div>
                    </div>
                </div>
            `;
            modal.appendChild(panel);
        }

        stage = panel.querySelector('#cropStage');
        placeholderEl = panel.querySelector('#cropPlaceholder');
        fileInput = panel.querySelector('#fileInput');
        statusEl = panel.querySelector('#statusMessage');
        saveBtn = panel.querySelector('#prs-cover-save');
        imgEl = panel.querySelector('#previewImage');

        fileInput?.addEventListener('change', (e) => handleFiles(e.target.files));
        stage?.addEventListener('click', () => fileInput.click());
        
        ['dragenter', 'dragover'].forEach(name => stage?.addEventListener(name, (e) => { e.preventDefault(); stage.classList.add('drag-active'); }));
        ['dragleave', 'dragend', 'drop'].forEach(name => stage?.addEventListener(name, (e) => { e.preventDefault(); stage.classList.remove('drag-active'); }));
        stage?.addEventListener('drop', (e) => handleFiles(e.dataTransfer.files));

        modal.addEventListener('click', (e) => { if (e.target === modal) exports.closeUploadModal(); });
        panel.querySelector('#prs-cover-cancel')?.addEventListener('click', exports.closeUploadModal);
        saveBtn?.addEventListener('click', onSaveUpload);

        document.body.appendChild(modal);
        setStatus(exports.text('status_awaiting', 'Awaiting file upload.'));
    };

    exports.closeUploadModal = () => {
        if (modal) modal.remove();
        modal = stage = imgEl = saveBtn = fileInput = statusEl = placeholderEl = selectedFile = null;
    };

    function setStatus(txt, color) {
        if (!statusEl) return;
        statusEl.style.color = color || '#6b7280';
        statusEl.textContent = txt || '';
    }

    function handleFiles(files) {
        if (!files?.length) return;
        const file = files[0];
        if (!['image/jpeg', 'image/png'].includes(file.type)) {
            stage?.classList.add('error');
            setStatus(exports.text('error_invalid_type', 'Error: Only JPEG and PNG images are accepted.'), '#ef4444');
            return;
        }
        selectedFile = file;
        const reader = new FileReader();
        reader.onload = (e) => {
            imgEl.onload = () => {
                if (placeholderEl) { placeholderEl.style.opacity = '0'; placeholderEl.style.pointerEvents = 'none'; }
                imgEl.style.display = 'block';
                setStatus(exports.format('file_loaded', 'File loaded: %s', `${file.name} (${(file.size / 1024).toFixed(1)} KB)`), '#16a34a');
            };
            imgEl.src = e.target.result;
        };
        reader.readAsDataURL(file);
    }

    function onSaveUpload() {
        if (!selectedFile) return setStatus(exports.text('choose_image', 'Choose an image'), '#ef4444');
        saveBtn.disabled = true;
        setStatus(exports.text('status_saving', 'Saving…'));
        exports.uploadCoverFile({ file: selectedFile })
            .then(data => {
                exports.replaceCover(data.url || data.src, true, '');
                exports.closeUploadModal();
            })
            .catch(err => setStatus(exports.resolveMessage(err.message), '#ef4444'))
            .finally(() => { if (saveBtn) saveBtn.disabled = false; });
    }

})(window.PRS_Cover_Upload);
