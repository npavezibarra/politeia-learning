window.PRS_Cover_Upload = window.PRS_Cover_Upload || {};

(function(exports) {
    let modal, messageEl, gridEl, setBtn, selectedOption, prevFocus, keydownListener;

    exports.openSearchModal = async () => {
        exports.closeSearchModal();
        prevFocus = document.activeElement;

        modal = exports.el('div', 'prs-cover-search-modal');
        const panel = exports.el('div', 'prs-cover-search-modal__content');
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-modal', 'true');
        panel.tabIndex = -1;

        const title = exports.el('h2', 'prs-cover-search-modal__title');
        title.textContent = exports.text('search_title', 'Select a Cover');
        
        messageEl = exports.el('p', 'prs-cover-search-modal__message');
        gridEl = exports.el('div', 'prs-cover-search-modal__grid prs-cover-grid');
        
        const footer = exports.el('div', 'prs-cover-search-modal__footer');
        const cancel = exports.el('button', 'prs-btn prs-btn--ghost');
        cancel.textContent = exports.text('cancel', 'Cancel');
        setBtn = exports.el('button', 'prs-btn prs-cover-set');
        setBtn.textContent = exports.text('set_cover', 'Set Cover');
        setBtn.disabled = true;
        footer.append(cancel, setBtn);

        panel.append(title, messageEl, gridEl, footer);
        modal.appendChild(panel);
        document.body.appendChild(modal);

        modal.addEventListener('click', (e) => { if (e.target === modal) exports.closeSearchModal(); });
        cancel.addEventListener('click', exports.closeSearchModal);
        setBtn.addEventListener('click', onSearchSetCover);
        panel.focus();

        // Search logic
        document.body.classList.add('prs-cover-search--loading');
        messageEl.textContent = exports.text('searching_covers', 'Searching for covers…');
        
        const details = exports.getBookDetails();
        if (!details.title) {
            document.body.classList.remove('prs-cover-search--loading');
            messageEl.textContent = exports.text('missing_title', 'No title available.');
            return;
        }

        try {
            const lang = exports.resolveBookLanguage(details);
            const results = await exports.fetchGoogleCovers(details.title, details.author, lang);
            document.body.classList.remove('prs-cover-search--loading');
            renderSearchResults(results, lang);
        } catch (err) {
            document.body.classList.remove('prs-cover-search--loading');
            messageEl.textContent = exports.resolveMessage(err.message);
        }
    };

    exports.closeSearchModal = () => {
        if (modal) modal.remove();
        modal = messageEl = gridEl = setBtn = selectedOption = null;
        document.body.classList.remove('prs-cover-search--loading');
        prevFocus?.focus();
    };

    function renderSearchResults(items, lang) {
        gridEl.innerHTML = '';
        if (!items.length) {
            messageEl.textContent = exports.text('no_covers_found', 'No covers found.');
            return;
        }
        messageEl.textContent = exports.text('select_cover', 'Select a cover below.');
        
        items.forEach(item => {
            const opt = exports.el('div', 'prs-cover-option');
            opt.innerHTML = `<figure class="prs-cover-figure"><div class="prs-cover-frame"><img src="${item.url}"></div></figure>`;
            opt.addEventListener('click', () => {
                selectedOption = item;
                Array.from(gridEl.children).forEach(c => c.classList.remove('is-selected'));
                opt.classList.add('is-selected');
                setBtn.disabled = false;
            });
            gridEl.appendChild(opt);
        });
    }

    async function onSearchSetCover() {
        if (!selectedOption) return;
        setBtn.disabled = true;
        const originalText = setBtn.textContent;
        setBtn.textContent = exports.text('status_saving', 'Saving…');

        const ajax = exports.getAjaxConfig();
        const { book_id } = exports.getContext();

        try {
            const res = await jQuery.ajax({
                url: ajax.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'prs_save_cover_url',
                    nonce: ajax.nonce,
                    book_id: book_id,
                    cover_url: selectedOption.url,
                    cover_source: selectedOption.source || ''
                }
            });
            if (res.success) {
                exports.replaceCover(res.data.src || selectedOption.url, false, res.data.source || selectedOption.source);
                exports.closeSearchModal();
            } else {
                throw new Error(res.data);
            }
        } catch (err) {
            messageEl.textContent = exports.resolveMessage(err.message);
            setBtn.disabled = false;
            setBtn.textContent = originalText;
        }
    }

})(window.PRS_Cover_Upload);
