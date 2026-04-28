window.PRS_Cover_Upload = window.PRS_Cover_Upload || {};

(function(exports) {

    exports.updateCoverAttribution = (source) => {
        const wrap = document.getElementById('prs-cover-attribution-wrap');
        const link = document.getElementById('prs-cover-attribution');
        if (!wrap || !link) return;

        if (source) {
            link.href = source;
            wrap.classList.remove('is-hidden');
            link.classList.remove('is-hidden');
            wrap.setAttribute('aria-hidden', 'false');
            link.setAttribute('aria-hidden', 'false');
        } else {
            wrap.classList.add('is-hidden');
            link.classList.add('is-hidden');
            wrap.setAttribute('aria-hidden', 'true');
            link.setAttribute('aria-hidden', 'true');
            link.removeAttribute('href');
        }
    };

    exports.ensureCoverControls = (actions) => {
        if (!actions) return { search: null, remove: null };

        const frame = document.getElementById('prs-cover-frame');
        const frameSearchLabel = frame && frame.getAttribute('data-search-label');
        const searchLabel = String(actions.getAttribute('data-search-label') || frameSearchLabel || 'Search Cover').trim();
        
        let searchBtn = actions.querySelector('#prs-cover-search');
        if (!searchBtn) {
            searchBtn = exports.el('button', 'prs-btn prs-cover-btn prs-cover-search-button');
            searchBtn.type = 'button';
            searchBtn.id = 'prs-cover-search';
            actions.appendChild(searchBtn);
        }
        searchBtn.textContent = searchLabel;

        const frameRemoveLabel = frame && frame.getAttribute('data-remove-label');
        const removeLabel = String(actions.getAttribute('data-remove-label') || frameRemoveLabel || 'Remove book cover').trim();
        
        let removeLink = actions.querySelector('#prs-cover-remove');
        if (!removeLink) {
            removeLink = exports.el('a', 'prs-cover-remove');
            removeLink.id = 'prs-cover-remove';
            removeLink.href = '#';
            actions.appendChild(removeLink);
        }
        removeLink.textContent = removeLabel;

        return { search: searchBtn, remove: removeLink };
    };

    exports.replaceCover = (src, bustCache, source) => {
        if (!src) return;
        const frame = document.getElementById('prs-cover-frame');
        if (!frame) return;

        const placeholder = document.getElementById('prs-cover-placeholder');
        const figure = document.getElementById('prs-book-cover-figure');
        let transferredActions = placeholder ? placeholder.querySelector('.prs-cover-actions') : null;
        
        if (placeholder && placeholder.parentNode) placeholder.parentNode.removeChild(placeholder);

        let img = document.getElementById('prs-cover-img');
        if (!img) {
            img = exports.el('img', 'prs-cover-img');
            img.id = 'prs-cover-img';
            if (figure) figure.appendChild(img);
            else frame.appendChild(img);
        }

        img.src = bustCache ? `${src}${src.indexOf('?') >= 0 ? '&' : '?'}t=${Date.now()}` : src;
        const { title } = exports.getBookDetails();
        if (title) img.alt = title;

        let overlay = frame.querySelector('.prs-cover-overlay');
        if (!overlay) {
            overlay = exports.el('div', 'prs-cover-overlay');
            if (figure && figure.nextSibling) frame.insertBefore(overlay, figure.nextSibling);
            else frame.appendChild(overlay);
        }

        if (transferredActions) {
            const existingActions = overlay.querySelector('.prs-cover-actions');
            if (existingActions && existingActions !== transferredActions) existingActions.remove();
            overlay.appendChild(transferredActions);
            exports.ensureCoverControls(transferredActions);
        } else {
            const overlayActions = overlay.querySelector('.prs-cover-actions');
            if (overlayActions) exports.ensureCoverControls(overlayActions);
        }

        frame.classList.add('has-image');
        frame.setAttribute('data-cover-state', 'image');
        if (typeof source === 'string') exports.updateCoverAttribution(source);
        
        if (window.PRS_BOOK) {
            window.PRS_BOOK.cover_url = src;
            window.PRS_BOOK.cover_source = typeof source === 'string' ? source : '';
        }
    };

    exports.restoreCoverPlaceholder = (existingActions) => {
        const frame = document.getElementById('prs-cover-frame');
        if (!frame) return;

        const figure = document.getElementById('prs-book-cover-figure');
        if (!figure) return;

        let actions = existingActions || null;
        const overlay = frame.querySelector('.prs-cover-overlay');
        
        if (actions && actions.parentNode) actions.remove();
        if (!actions && overlay) {
            const overlayActions = overlay.querySelector('.prs-cover-actions');
            if (overlayActions) {
                overlayActions.remove();
                actions = overlayActions;
            }
        }

        if (overlay) overlay.remove();
        const img = document.getElementById('prs-cover-img');
        if (img) img.remove();

        const placeholder = exports.el('div', 'prs-cover-placeholder');
        placeholder.id = 'prs-cover-placeholder';
        placeholder.setAttribute('role', 'img');
        placeholder.setAttribute('aria-label', frame.getAttribute('data-placeholder-label') || 'Default book cover');

        const details = exports.getBookDetails();
        const titleEl = exports.el('h2', 'prs-cover-title');
        titleEl.textContent = details.title || frame.getAttribute('data-placeholder-title') || 'Untitled Book';
        placeholder.appendChild(titleEl);

        const authorEl = exports.el('h3', 'prs-cover-author');
        authorEl.textContent = details.author || frame.getAttribute('data-placeholder-author') || exports.text('unknown_author', 'Unknown Author');
        placeholder.appendChild(authorEl);

        if (!actions) actions = exports.el('div', 'prs-cover-actions');
        placeholder.appendChild(actions);
        exports.ensureCoverControls(actions);

        const attribution = document.getElementById('prs-cover-attribution-wrap');
        if (attribution && attribution.parentNode === figure) figure.insertBefore(placeholder, attribution);
        else figure.appendChild(placeholder);

        frame.classList.remove('has-image');
        frame.setAttribute('data-cover-state', 'empty');
        exports.updateCoverAttribution('');
        
        if (window.PRS_BOOK) {
            window.PRS_BOOK.cover_url = '';
            window.PRS_BOOK.cover_source = '';
        }
    };

})(window.PRS_Cover_Upload);
