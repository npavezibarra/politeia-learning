/**
 * Course Creator - Media Handlers (Uploads & Previews)
 */
jQuery(document).ready(function($) {

    function openThumbnailUploader() {
        PL_Cropper.open({
            title: t('courseCover'),
            width: 360,
            height: 238,
            outputMaxWidth: 1600,
            quality: 0.92,
            onSave: function (dataUrl) {
                saveCroppedImage(dataUrl, 'thumbnail');
            }
        });
    }

    function openCoverUploader() {
        PL_Cropper.open({
            title: t('coverPhoto'),
            width: 1024,
            height: 768,
            outputMaxWidth: 2400,
            quality: 0.9,
            onSave: function (dataUrl) {
                saveCroppedImage(dataUrl, 'cover');
            }
        });
    }

    function openCertificateLogoUploader() {
        PL_Cropper.open({
            title: 'Logo',
            width: 600,
            height: 200,
            onSave: function (dataUrl) {
                saveCroppedImage(dataUrl, 'certificate_logo');
            }
        });
    }

    function openCertificateSignatureUploader() {
        PL_Cropper.open({
            title: 'Firma',
            width: 600,
            height: 200,
            onSave: function (dataUrl) {
                saveCroppedImage(dataUrl, 'certificate_signature');
            }
        });
    }

    $(document).on('click', '#pcg-course-form-section .pcg-media-card__empty', function (e) {
        e.preventDefault();
        const type = $(this).attr('data-upload') || '';
        if (type === 'thumbnail') openThumbnailUploader();
        if (type === 'cover') openCoverUploader();
        if (type === 'certificate_logo') openCertificateLogoUploader();
        if (type === 'certificate_signature') openCertificateSignatureUploader();
    });

    $(document).on('keydown', '#pcg-course-form-section .pcg-media-card__empty[role="button"]', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            $(this).trigger('click');
        }
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
                        window.pcgCourseState.thumbnailId = attachment.id;
                        $('#pcg-thumbnail-preview img').attr('src', attachment.url);
                        $('#pcg-thumbnail-preview').fadeIn();
                    } else if (type === 'cover') {
                        window.pcgCourseState.coverPhotoId = attachment.id;
                        $('#pcg-cover-preview img').attr('src', attachment.url);
                        $('#pcg-cover-preview').fadeIn();
                    } else if (type === 'certificate_logo') {
                        window.pcgCourseState.certificateLogoAttachmentId = attachment.id;
                        $('#pcg-certificate-logo-preview img').attr('src', attachment.url);
                        $('#pcg-certificate-logo-preview').fadeIn();
                        if (typeof window.updateCertificatePreview === 'function') window.updateCertificatePreview();
                    } else if (type === 'certificate_signature') {
                        window.pcgCourseState.certificateSignatureAttachmentId = attachment.id;
                        $('#pcg-certificate-signature-preview img').attr('src', attachment.url);
                        $('#pcg-certificate-signature-preview').fadeIn();
                        if (typeof window.updateCertificatePreview === 'function') window.updateCertificatePreview();
                    }
                } else {
                    window.pcgShowToast(t('errorPrefix') + response.data.message, 'error');
                }
            },
            error: function () {
                window.pcgShowToast(t('errorUploadingImage'), 'error');
            }
        });
    }

    $('#pcg-remove-thumbnail').on('click', function () {
        window.pcgCourseState.thumbnailId = 0;
        $('#pcg-thumbnail-preview').fadeOut();
    });

    $('#pcg-remove-cover').on('click', function () {
        window.pcgCourseState.coverPhotoId = 0;
        $('#pcg-cover-preview').fadeOut();
    });

    $('#pcg-remove-certificate-logo').on('click', function () {
        window.pcgCourseState.certificateLogoAttachmentId = 0;
        $('#pcg-certificate-logo-preview').fadeOut();
        if (typeof window.updateCertificatePreview === 'function') window.updateCertificatePreview();
    });

    $('#pcg-remove-certificate-signature').on('click', function () {
        window.pcgCourseState.certificateSignatureAttachmentId = 0;
        $('#pcg-certificate-signature-preview').fadeOut();
        if (typeof window.updateCertificatePreview === 'function') window.updateCertificatePreview();
    });

});
