window.PRS_Cover_Upload = window.PRS_Cover_Upload || {};

(function(exports) {
    const I18N = window.PRS_COVER_I18N || {};
    exports.text = (key, fallback) => (I18N && I18N[key]) ? I18N[key] : fallback;
    exports.format = (key, fallback, value) => exports.text(key, fallback).replace('%s', value);

    exports.ERROR_MAP = {
        auth: exports.text('error_auth', 'You must be logged in.'),
        bad_nonce: exports.text('error_bad_nonce', 'Your session expired. Please refresh and try again.'),
        invalid_payload: exports.text('error_invalid_payload', 'Invalid data received.'),
        not_found: exports.text('error_not_found', 'Record not found.'),
        db_error: exports.text('error_db', 'Database error. Please try again.'),
        forbidden: exports.text('error_forbidden', 'Permission denied.'),
        decode_fail: exports.text('error_decode', 'Unable to decode the image.'),
        missing_params: exports.text('error_missing_params', 'Missing required data.'),
        bad_url: exports.text('error_bad_url', 'Invalid URL.'),
        unsupported_scheme: exports.text('error_unsupported_scheme', 'Unsupported URL scheme.'),
        invalid_image_host: exports.text('error_invalid_image_host', 'Invalid image host.'),
        bad_source_url: exports.text('error_bad_source_url', 'Invalid source URL.'),
        unsupported_source_scheme: exports.text('error_unsupported_source_scheme', 'Invalid source URL scheme.'),
        invalid_source_host: exports.text('error_invalid_source_host', 'Source host not permitted.'),
        missing_title: exports.text('missing_title', 'No book title available. Add a title to search or upload a cover manually.'),
        no_results: exports.text('no_covers_found', 'No covers found. You can upload your own image instead.'),
        search_failed: exports.text('search_error', 'There was an error searching for covers. Please try again later.'),
        remove_failed: exports.text('remove_failed', 'Could not remove the cover. Please try again.'),
        api_error: exports.text('search_error', 'There was an error searching for covers. Please try again later.'),
        'Permission denied': exports.text('error_forbidden', 'Permission denied.'),
        'Permission denied.': exports.text('error_forbidden', 'Permission denied.'),
        'No image data received': exports.text('error_no_image_data', 'No image data received.'),
        'Invalid image payload': exports.text('error_invalid_image_payload', 'Invalid image payload.'),
        'Upload directory unavailable': exports.text('error_upload_dir', 'Upload directory unavailable.'),
        'Failed to write image': exports.text('error_write_failed', 'Failed to write image.'),
        'Attachment creation failed': exports.text('error_attachment_failed', 'Attachment creation failed.'),
        'Cover host not permitted.': exports.text('error_invalid_image_host', 'Cover host not permitted.'),
        'Invalid source URL.': exports.text('error_bad_source_url', 'Invalid source URL.'),
        'Invalid source URL scheme.': exports.text('error_unsupported_source_scheme', 'Invalid source URL scheme.'),
        'Source host not permitted.': exports.text('error_invalid_source_host', 'Source host not permitted.'),
        'Database update failed.': exports.text('error_db', 'Database error. Please try again.'),
    };

    exports.resolveMessage = (message) => {
        if (!message) return '';
        const key = String(message).trim();
        return exports.ERROR_MAP[key] || message;
    };

})(window.PRS_Cover_Upload);
