window.PRS_Cover_Upload = window.PRS_Cover_Upload || {};

(function(exports) {
    
    function getUploadConfig() {
        const coverConfig = window.PRS_COVER || {};
        const ajaxUrl = window.PRS_SAVE_URL
            || coverConfig.saveUrl
            || coverConfig.ajax
            || (window.prs_cover_data && window.prs_cover_data.ajaxurl)
            || window.ajaxurl
            || '';
        const globalNonce = (typeof window.PRS_NONCE === 'string' && window.PRS_NONCE) || '';
        const uploadNonce = coverConfig.saveNonce
            || (window.prs_cover_data && window.prs_cover_data.nonce)
            || globalNonce
            || '';
        const postId = window.PRS_POST_ID
            || coverConfig.postId
            || 0;

        return { ajaxUrl, nonce: uploadNonce, postId };
    }

    exports.getAjaxConfig = () => {
        const config = window.prs_cover_data || {};
        const ajaxUrl = config.ajaxurl || window.ajaxurl || (window.PRS_COVER && PRS_COVER.ajax) || '';
        const nonce = config.nonce || '';
        return { ajaxUrl, nonce };
    };

    exports.uploadCoverFile = async ({ file, postId }) => {
        const { ajaxUrl, nonce, postId: fallbackPostId } = getUploadConfig();
        const targetPostId = typeof postId !== 'undefined' ? postId : fallbackPostId;

        if (!ajaxUrl) throw new Error('Upload unavailable');
        if (!nonce) throw new Error('Missing nonce');
        if (!file) throw new Error('Missing image data');

        const payload = new FormData();
        payload.append('action', 'prs_cover_upload_file');
        payload.append('nonce', nonce);
        payload.append('prs_cover', file);

        const numericPostId = parseInt(targetPostId, 10);
        if (!Number.isNaN(numericPostId)) {
            payload.append('post_id', String(numericPostId));
        }

        const { user_book_id, book_id } = exports.getContext();
        if (user_book_id) payload.append('user_book_id', String(user_book_id));
        if (book_id) payload.append('book_id', String(book_id));

        const response = await fetch(ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: payload,
        });

        const json = await response.json().catch(() => null);

        if (!response.ok || !json) throw new Error('Upload failed');
        if (!json.success) throw new Error(json?.data?.message || json?.message || 'Upload failed');

        return json.data || {};
    };

    exports.fetchGoogleCovers = async (title, author, language) => {
        const body = new URLSearchParams({
            action: 'prs_cover_search_google',
            nonce: (window.PRS_COVER && PRS_COVER.searchNonce) || '',
            title,
            author: author || '',
            language: language || ''
        });

        const response = await fetch((window.PRS_COVER && PRS_COVER.ajax) || '', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            credentials: 'same-origin',
            body
        });

        const out = await response.json();
        if (!out || !out.success) {
            const message = (out && out.data && out.data.message) || out?.message || 'search_failed';
            const error = new Error(message);
            error.code = message;
            throw error;
        }

        const items = Array.isArray(out.data?.items) ? out.data.items : [];
        return items.slice(0, 3).map((item) => ({
            url: item.url || '',
            language: item.language || '',
            source: item.source || '',
            title: item.title || '',
            author: item.author || ''
        })).filter((item) => !!item.url);
    };

    exports.removeCover = async (link, actions) => {
        const ajax = exports.getAjaxConfig();
        const context = exports.getContext();
        const userBookId = parseInt(context.user_book_id || 0, 10) || 0;
        const bookId = parseInt(context.book_id || 0, 10) || 0;
        const nonceValue = ajax.nonce || (window.PRS_COVER && window.PRS_COVER.saveNonce) || '';

        if (!ajax.ajaxUrl || !nonceValue || (!userBookId && !bookId)) {
            window.alert(exports.text('remove_unavailable', 'Unable to remove the book cover.'));
            return;
        }

        link.classList.add('is-disabled');
        link.setAttribute('aria-busy', 'true');

        const payload = new URLSearchParams();
        payload.append('action', 'prs_remove_cover');
        payload.append('nonce', nonceValue);
        if (userBookId) payload.append('user_book_id', String(userBookId));
        if (bookId) payload.append('book_id', String(bookId));

        try {
            const response = await fetch(ajax.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                credentials: 'same-origin',
                body: payload,
            });
            const json = await response.json().catch(() => null);
            if (!response.ok || !json || !json.success) {
                throw new Error((json && json.data && json.data.message) || (json && json.data) || (json && json.message) || 'remove_failed');
            }
            exports.restoreCoverPlaceholder(actions || null);
        } catch (error) {
            console.error('[PRS] remove cover error', error);
            window.alert(exports.text('remove_failed', 'Could not remove the cover. Please try again.'));
        } finally {
            link.classList.remove('is-disabled');
            link.removeAttribute('aria-busy');
        }
    };

})(window.PRS_Cover_Upload);
