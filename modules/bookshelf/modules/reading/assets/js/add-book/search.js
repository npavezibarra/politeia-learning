window.PRS_Add_Book = window.PRS_Add_Book || {};

(function (exports) {
    exports.search = {
        resetSuggestions: function () {
            if (exports.DOM && exports.DOM.suggestionContainer) {
                exports.DOM.suggestionContainer.innerHTML = '';
                exports.DOM.suggestionContainer.classList.remove('is-visible');
            }
            if (exports.DOM && exports.DOM.titleInput) {
                exports.DOM.titleInput.setAttribute('aria-expanded', 'false');
            }
        },

        applySuggestionValues: function (item) {
            if (!item || !exports.form) return;
            if (exports.DOM && exports.DOM.titleInput) exports.DOM.titleInput.value = item.title || '';
            
            // Handle authors
            if (typeof exports.form.setAuthors === 'function') {
                exports.form.setAuthors(item.authors || item.author);
            }
            
            if (exports.DOM && exports.DOM.yearInput) exports.DOM.yearInput.value = item.year || '';
            if (exports.DOM && exports.DOM.isbnInput) exports.DOM.isbnInput.value = item.isbn || '';
            if (exports.DOM && exports.DOM.pagesInput) exports.DOM.pagesInput.value = item.pages || '';
            
            if (item.cover && typeof exports.form.setCoverPreview === 'function') {
                exports.form.setCoverPreview(item.cover);
            }
            
            if (typeof exports.form.toggleFieldLabel === 'function') {
                if (exports.DOM && exports.DOM.yearInput) exports.form.toggleFieldLabel(exports.DOM.yearInput);
                if (exports.DOM && exports.DOM.isbnInput) exports.form.toggleFieldLabel(exports.DOM.isbnInput);
                if (exports.DOM && exports.DOM.pagesInput) exports.form.toggleFieldLabel(exports.DOM.pagesInput);
            }
        },

        showSuggestions: function (items) {
            if (!exports.DOM || !exports.DOM.suggestionContainer) return;
            exports.DOM.suggestionContainer.innerHTML = '';
            
            if (!items || !items.length) {
                exports.search.resetSuggestions();
                return;
            }

            items.forEach(function (item, i) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'prs-add-book__suggestion prs-add-book__suggestion--' + (item.source || 'canonical');
                btn.innerHTML = '<span class="prs-add-book__suggestion-title">' + item.title + '</span>' +
                                (item.author ? '<span class="prs-add-book__suggestion-author">' + item.author + '</span>' : '');
                btn.addEventListener('click', function () {
                    exports.search.applySuggestionValues(item);
                    exports.search.resetSuggestions();
                });
                exports.DOM.suggestionContainer.appendChild(btn);
            });

            exports.DOM.suggestionContainer.classList.add('is-visible');
            if (exports.DOM.titleInput) exports.DOM.titleInput.setAttribute('aria-expanded', 'true');
        },

        fetchSuggestions: function (query) {
            if (!query || query.length < 3) {
                exports.search.resetSuggestions();
                return;
            }

            if (exports.state.abortController) exports.state.abortController.abort();
            if (typeof window.AbortController !== 'undefined') {
                exports.state.abortController = new window.AbortController();
            }

            if (exports.api && typeof exports.api.fetchCanonicalSuggestions === 'function') {
                exports.api.fetchCanonicalSuggestions(query, exports.state.abortController).then(function (items) {
                    exports.search.showSuggestions(items);
                });
            }
        }
    };
})(window.PRS_Add_Book);
