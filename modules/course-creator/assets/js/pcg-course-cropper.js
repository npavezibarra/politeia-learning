/**
 * PCG Course Creator Cropper Utility
 * Handles image uploading and cropping with 360x238 dimensions.
 */
var PCG_Cropper = (function ($) {
    'use strict';

    let cropper = null;
    let selectedFile = null;
    let currentOptions = {};

    const defaults = {
        width: 360,
        height: 238,
        title: 'Upload Image',
        onSave: function (dataUrl) { console.log('Cropped Image:', dataUrl); },
        onCancel: function () { }
    };

    /**
     * Open the cropper modal for a specific target
     */
    function open(options) {
        currentOptions = $.extend({}, defaults, options);
        renderModal();
        bindEvents();
    }

    function renderModal() {
        // Remove existing if any
        $('.pcg-cropper-modal').remove();

        const html = `
            <div class="pcg-cropper-modal">
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
                                <p>Drag and drop your image here</p>
                                <span>or click to browse files</span>
                            </div>
                            <div class="pcg-cropper-container" style="display:none;">
                                <img id="pcg-cropper-image" src="">
                            </div>
                        </div>
                        <input type="file" id="pcg-cropper-file-input" class="pcg-hidden-input" accept="image/jpeg,image/png">
                    </div>
                    <div class="pcg-cropper-footer">
                        <span class="pcg-cropper-status">Recommended size: ${currentOptions.width}x${currentOptions.height}px</span>
                        <div class="pcg-cropper-actions">
                            <button type="button" class="pcg-btn-cropper pcg-btn-cropper-cancel">Cancel</button>
                            <button type="button" class="pcg-btn-cropper pcg-btn-cropper-save" disabled>Save Image</button>
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
            $btn.prop('disabled', true).text('Saving...');

            const canvas = cropper.getCroppedCanvas({
                width: currentOptions.width,
                height: currentOptions.height,
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high',
            });

            const dataUrl = canvas.toDataURL('image/png');

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
            alert('Please select an image file (JPG or PNG).');
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
            aspectRatio: currentOptions.width / currentOptions.height,
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
