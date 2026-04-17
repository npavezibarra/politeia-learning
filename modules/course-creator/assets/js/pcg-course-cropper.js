/**
 * PCG Course Creator Cropper Utility
 * Handles image uploading and cropping with 360x238 dimensions.
 */
var PL_Cropper = (function ($) {
    'use strict';

    let cropper = null;
    let selectedFile = null;
    let currentOptions = {};

    function t(key, fallback) {
        try {
            const val = pcgCropperData && pcgCropperData.i18n ? pcgCropperData.i18n[key] : null;
            return val ? val : (fallback || key);
        } catch (_) {
            return fallback || key;
        }
    }

    const defaults = {
        width: 360,
        height: 238,
        // Multiply export resolution (keeps aspect ratio). Use >1 for retina/sharp thumbnails.
        // If `outputMaxWidth/Height` are provided, they take precedence.
        outputScale: 1,
        // Optional caps for export resolution. Useful to avoid storing tiny thumbnails (blurred),
        // while keeping file sizes under control.
        outputMaxWidth: 0,
        outputMaxHeight: 0,
        // JPEG quality for export (0..1). Higher = less artifacts.
        quality: 0.9,
        freeCrop: false,
        circleMask: false,
        title: '',
        onSave: function (dataUrl) { console.log('Cropped Image:', dataUrl); },
        onCancel: function () { }
    };

    /**
     * Open the cropper modal for a specific target
     */
    function open(options) {
        const withI18nDefaults = $.extend({}, defaults, {
            title: t('uploadImage', 'Upload Image'),
        });
        currentOptions = $.extend({}, withI18nDefaults, options);
        renderModal();
        bindEvents();
    }

    function renderModal() {
        // Remove existing if any
        $('.pcg-cropper-modal').remove();

        const circleClass = currentOptions.circleMask ? 'pcg-cropper-circle-mask' : '';

        const baseW = Number(currentOptions.width || 0) || 0;
        const baseH = Number(currentOptions.height || 0) || 0;
        const maxW = Number(currentOptions.outputMaxWidth || 0) || 0;
        const maxH = Number(currentOptions.outputMaxHeight || 0) || 0;
        let scale = Number(currentOptions.outputScale || 1) || 1;
        if (!currentOptions.freeCrop && baseW > 0 && baseH > 0 && (maxW > 0 || maxH > 0)) {
            const sW = maxW > 0 ? (maxW / baseW) : Infinity;
            const sH = maxH > 0 ? (maxH / baseH) : Infinity;
            scale = Math.min(sW, sH);
        }
        if (!isFinite(scale) || scale <= 0) scale = 1;
        const outW = currentOptions.freeCrop ? 0 : Math.max(1, Math.round(baseW * scale));
        const outH = currentOptions.freeCrop ? 0 : Math.max(1, Math.round(baseH * scale));

        const html = `
            <div class="pcg-cropper-modal ${circleClass}">
                <div class="pcg-cropper-content">
                    <div class="pcg-cropper-header">
                        <h3>${currentOptions.title}</h3>
                        <button type="button" class="pcg-cropper-close">&times;</button>
                    </div>
                    <div class="pcg-cropper-body">
                        <div class="pcg-cropper-stage" id="pcg-cropper-stage">
                            <div class="pcg-cropper-placeholder">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                    <polyline points="17 8 12 3 7 8"></polyline>
                                    <line x1="12" y1="3" x2="12" y2="15"></line>
                                </svg>
                                <p>${t('dragDropHere', 'Drag and drop your image here')}</p>
                                <span>${t('clickToBrowse', 'or click to browse files')}</span>
                            </div>
                            <div class="pcg-cropper-container" style="display:none;">
                                <img id="pcg-cropper-image" src="">
                            </div>
                        </div>
                        <input type="file" id="pcg-cropper-file-input" class="pcg-hidden-input" accept="image/jpeg,image/png">
                    </div>
                    <div class="pcg-cropper-footer">
                        <span class="pcg-cropper-status">${currentOptions.freeCrop ? t('freeCrop', 'Free crop size') : t('recommendedSize', 'Recommended size:') + ' ' + outW + 'x' + outH + 'px'}</span>
                        <div class="pcg-cropper-actions">
                            <button type="button" class="pcg-btn-cropper pcg-btn-cropper-cancel">${t('cancel', 'Cancel')}</button>
                            <button type="button" class="pcg-btn-cropper pcg-btn-cropper-save" disabled>${t('saveImage', 'Save Image')}</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        $('body').append(html);
        $('.pcg-cropper-modal').fadeIn(200);
    }

    function bindEvents() {
        const $modal = $('.pcg-cropper-modal');
        const $stage = $('#pcg-cropper-stage');
        const $fileInput = $('#pcg-cropper-file-input');

        // Close on X or Cancel
        $modal.on('click', '.pcg-cropper-close, .pcg-btn-cropper-cancel', function () {
            destroy();
            if (typeof currentOptions.onCancel === 'function') currentOptions.onCancel();
        });

        // Click on stage to trigger file input
        $stage.on('click', function () {
            if (!cropper) $fileInput.trigger('click');
        });

        // File Selection
        $fileInput.on('change', function (e) {
            handleFiles(e.target.files);
        });

        // Drag and Drop
        $stage.on('dragover', function (e) {
            e.preventDefault();
            $(this).addClass('drag-active');
        }).on('dragleave drop', function (e) {
            e.preventDefault();
            $(this).removeClass('drag-active');
            if (e.type === 'drop') {
                handleFiles(e.originalEvent.dataTransfer.files);
            }
        });

        // Save
        $modal.on('click', '.pcg-btn-cropper-save', function () {
            if (!cropper) return;

            const $btn = $(this);
            $btn.prop('disabled', true).text(t('saving', 'Saving...'));

            let canvasOptions = {
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high',
            };
            if (!currentOptions.freeCrop) {
                const baseW = Number(currentOptions.width || 0) || 0;
                const baseH = Number(currentOptions.height || 0) || 0;
                const maxW = Number(currentOptions.outputMaxWidth || 0) || 0;
                const maxH = Number(currentOptions.outputMaxHeight || 0) || 0;
                let scale = Number(currentOptions.outputScale || 1) || 1;
                if (baseW > 0 && baseH > 0 && (maxW > 0 || maxH > 0)) {
                    const sW = maxW > 0 ? (maxW / baseW) : Infinity;
                    const sH = maxH > 0 ? (maxH / baseH) : Infinity;
                    scale = Math.min(sW, sH);
                }
                if (!isFinite(scale) || scale <= 0) scale = 1;
                canvasOptions.width = Math.max(1, Math.round(baseW * scale));
                canvasOptions.height = Math.max(1, Math.round(baseH * scale));
            }

            const canvas = cropper.getCroppedCanvas(canvasOptions);

            const q = Math.min(1, Math.max(0.5, Number(currentOptions.quality || 0.9) || 0.9));
            const dataUrl = canvas.toDataURL('image/jpeg', q);

            if (typeof currentOptions.onSave === 'function') {
                currentOptions.onSave(dataUrl);
            }

            destroy();
        });
    }

    function handleFiles(files) {
        if (!files || !files.length) return;
        const file = files[0];

        if (!file.type.match('image.*')) {
            alert(t('selectImageFile', 'Please select an image file (JPG or PNG).'));
            return;
        }

        selectedFile = file;
        const reader = new FileReader();

        reader.onload = function (e) {
            initCropper(e.target.result);
        };

        reader.readAsDataURL(file);
    }

    function initCropper(src) {
        const $container = $('.pcg-cropper-container');
        const $placeholder = $('.pcg-cropper-placeholder');
        const $img = $('#pcg-cropper-image');
        const $saveBtn = $('.pcg-btn-cropper-save');

        $placeholder.hide();
        $container.show();
        $img.attr('src', src);

        if (cropper) {
            cropper.destroy();
        }

        cropper = new Cropper($img[0], {
            aspectRatio: currentOptions.freeCrop ? NaN : currentOptions.width / currentOptions.height,
            viewMode: 1,
            autoCropArea: 0.8,
            responsive: true,
            restore: false,
            guides: true,
            center: true,
            highlight: false,
            cropBoxMovable: true,
            cropBoxResizable: true,
            toggleDragModeOnDblclick: false,
            ready: function () {
                $saveBtn.prop('disabled', false);
            }
        });
    }

    function destroy() {
        if (cropper) {
            cropper.destroy();
            cropper = null;
        }
        $('.pcg-cropper-modal').fadeOut(200, function () {
            $(this).remove();
        });
        selectedFile = null;
    }

    return {
        open: open
    };

})(jQuery);
