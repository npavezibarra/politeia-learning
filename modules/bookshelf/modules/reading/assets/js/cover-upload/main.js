window.PRS_Cover_Upload = window.PRS_Cover_Upload || {};

(function(exports) {

    function init() {
        // Initial attribution update
        const initialSource = (window.PRS_BOOK && typeof window.PRS_BOOK.cover_source === 'string')
            ? window.PRS_BOOK.cover_source
            : '';
        exports.updateCoverAttribution(initialSource || '');

        // Global event bindings
        document.addEventListener('click', (event) => {
            const removeLink = event.target.closest('#prs-cover-remove');
            if (removeLink) {
                event.preventDefault();
                const actions = removeLink.closest('.prs-cover-actions');
                const confirmMsg = actions?.getAttribute('data-remove-confirm') || exports.text('remove_confirm', 'Remove this book cover?');
                if (window.confirm(confirmMsg)) {
                    exports.removeCover(removeLink, actions);
                }
                return;
            }

            const uploadBtn = event.target.closest('#prs-cover-open');
            if (uploadBtn) {
                event.preventDefault();
                exports.openUploadModal();
                return;
            }

            const searchBtn = event.target.closest('#prs-cover-search');
            if (searchBtn) {
                event.preventDefault();
                exports.openSearchModal();
                return;
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})(window.PRS_Cover_Upload);
