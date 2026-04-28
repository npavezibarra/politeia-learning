window.PRS_Add_Book = window.PRS_Add_Book || {};

(function (exports) {
    var config = exports.config;

    exports.api = {
        fetchJson: function (targetUrl) {
            return fetch(targetUrl).then(function (response) {
                if (!response.ok) throw new Error('Request failed');
                return response.json();
            });
        },

        fetchCanonicalSuggestions: function (query, controller) {
            if (!config.ajaxUrl || !config.nonce) return Promise.resolve([]);

            var params = new window.URLSearchParams();
            params.append('action', 'prs_canonical_title_search');
            params.append('nonce', config.nonce);
            params.append('query', query);

            var fetchOptions = {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: params.toString()
            };
            if (controller) fetchOptions.signal = controller.signal;

            return fetch(config.ajaxUrl, fetchOptions)
                .then(function (response) {
                    if (!response.ok) throw new Error('Request failed');
                    return response.json();
                })
                .then(function (data) {
                    var payload = data && data.data ? data.data : data;
                    if (!payload || !payload.items) return [];
                    return payload.items.map(function(item) {
                        var authors = Array.isArray(item.authors) ? item.authors : [];
                        return {
                            id: item.id,
                            title: item.title,
                            year: item.year || '',
                            slug: item.slug || '',
                            author: authors.length ? authors[0] : '',
                            authors: authors,
                            source: 'canonical'
                        };
                    });
                })
                .catch(function (error) {
                    if (error && error.name === 'AbortError') throw error;
                    return [];
                });
        },

        addBookToLibrary: function (formData) {
            if (!config.ajaxUrl || !config.nonce) return Promise.reject('Missing config');

            formData.append('action', 'prs_add_book');
            formData.append('nonce', config.nonce);

            return fetch(config.ajaxUrl, {
                method: 'POST',
                body: formData
            }).then(function (response) {
                if (!response.ok) throw new Error('Request failed');
                return response.json();
            });
        }
    };

})(window.PRS_Add_Book);
