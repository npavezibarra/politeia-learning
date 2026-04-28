window.PRS_Cover_Upload = window.PRS_Cover_Upload || {};

(function(exports) {
    exports.el = (t, cls) => {
        const e = document.createElement(t);
        if (cls) e.className = cls;
        return e;
    };

    exports.getContext = () => {
        const g = (window.PRS_BOOK || {});
        return {
            user_book_id: g.user_book_id || 0,
            book_id: g.book_id || 0
        };
    };

    exports.getBookDetails = () => {
        const g = (window.PRS_BOOK || {});
        const authors = Array.isArray(g.authors) ? g.authors.filter(Boolean).join(", ") : (g.authors || "");
        return {
            title: g.title || '',
            author: authors,
            language: g.language || ''
        };
    };

    exports.normalizeLanguageCode = (code) => {
        if (!code || typeof code !== 'string') return '';
        let normalized = code.trim().toLowerCase();
        if (!normalized) return '';
        normalized = normalized.replace(/^\/?languages\//, '');
        normalized = normalized.replace(/^\/?lang\//, '');
        normalized = normalized.replace(/_/g, '-');

        if (normalized.length === 2) {
            return normalized;
        }

        const map = {
            eng: 'en',
            spa: 'es',
            esl: 'es',
            fre: 'fr',
            fra: 'fr',
            por: 'pt',
            ger: 'de',
            deu: 'de',
            ita: 'it',
            cat: 'ca',
            glg: 'gl',
        };

        if (map[normalized]) {
            return map[normalized];
        }

        if (normalized.length > 2) {
            return normalized.slice(0, 2);
        }

        return normalized;
    };

    exports.resolveBookLanguage = (details) => {
        const metaCode = exports.normalizeLanguageCode(details.language);
        if (metaCode) return metaCode;
        return '';
    };

})(window.PRS_Cover_Upload);
