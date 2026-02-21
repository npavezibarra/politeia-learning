/* global pcgStudentsRankingsData */
(() => {
    const qs = (root, sel) => (root || document).querySelector(sel);
    const qsa = (root, sel) => Array.from((root || document).querySelectorAll(sel));

    function esc(text) {
        const div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.textContent;
    }

    function t(key, fallback) {
        try {
            const i18n = pcgStudentsRankingsData && pcgStudentsRankingsData.i18n ? pcgStudentsRankingsData.i18n : null;
            return i18n && i18n[key] ? i18n[key] : (fallback || key);
        } catch (_) {
            return fallback || key;
        }
    }

    function setLoading(tbody, colspan) {
        tbody.innerHTML = `<tr><td colspan="${colspan}">${esc(t('loading', 'Cargando...'))}</td></tr>`;
    }

    function setEmpty(tbody, colspan) {
        tbody.innerHTML = `<tr><td colspan="${colspan}">${esc(t('empty', 'Sin datos'))}</td></tr>`;
    }

    function setError(tbody, colspan) {
        tbody.innerHTML = `<tr><td colspan="${colspan}">${esc(t('errorLoading', 'Error al cargar'))}</td></tr>`;
    }

    function renderUserCell(row) {
        const name = esc(row && row.name ? row.name : '');
        const avatar = row && row.avatar ? String(row.avatar) : '';
        const img = avatar ? `<img class="pcg-ranking-avatar" src="${esc(avatar)}" alt="${name}">` : '<span class="pcg-ranking-avatar pcg-ranking-avatar--empty"></span>';
        return `<div class="pcg-ranking-user">${img}<span class="pcg-ranking-user-name">${name}</span></div>`;
    }

    function renderPurchases(rows) {
        const tbody = qs(document, 'tbody[data-ranking-table="purchases"]');
        if (!tbody) return;
        if (!Array.isArray(rows) || rows.length === 0) return setEmpty(tbody, 2);

        tbody.innerHTML = rows
            .slice(0, 10)
            .map((r) => {
                const courses = Number(r.courses || 0);
                return `<tr><td>${renderUserCell(r)}</td><td class="pcg-ranking-num">${courses}</td></tr>`;
            })
            .join('');
    }

    function renderQuizImprovement(rows) {
        const tbody = qs(document, 'tbody[data-ranking-table="quiz_improvement"]');
        if (!tbody) return;
        if (!Array.isArray(rows) || rows.length === 0) return setEmpty(tbody, 3);

        tbody.innerHTML = rows
            .slice(0, 10)
            .map((r) => {
                const course = esc(r.course);
                const inc = Number(r.increase || 0);
                const incTxt = `${inc > 0 ? '+' : ''}${Math.round(inc)}%`;
                return `<tr><td>${renderUserCell(r)}</td><td>${course}</td><td class="pcg-ranking-num">${esc(incTxt)}</td></tr>`;
            })
            .join('');
    }

    function renderCompletion(rows, key) {
        const tbody = qs(document, `tbody[data-ranking-table="${key}"]`);
        if (!tbody) return;
        if (!Array.isArray(rows) || rows.length === 0) return setEmpty(tbody, 3);

        tbody.innerHTML = rows
            .slice(0, 10)
            .map((r) => {
                const course = esc(r.course);
                const days = Number(r.days || 0);
                const daysTxt = days.toFixed(1);
                return `<tr><td>${renderUserCell(r)}</td><td>${course}</td><td class="pcg-ranking-num">${esc(daysTxt)}</td></tr>`;
            })
            .join('');
    }

    let fetched = false;
    let inflight = null;

    function fetchRankings() {
        if (fetched) return Promise.resolve();
        if (inflight) return inflight;

        const tbodies = [
            ['purchases', 2],
            ['quiz_improvement', 3],
            ['fastest_completion', 3],
            ['slowest_completion', 3],
        ];
        tbodies.forEach(([key, colspan]) => {
            const tbody = qs(document, `tbody[data-ranking-table="${key}"]`);
            if (tbody) setLoading(tbody, colspan);
        });

        if (typeof pcgStudentsRankingsData === 'undefined' || !pcgStudentsRankingsData.ajaxUrl) {
            tbodies.forEach(([key, colspan]) => {
                const tbody = qs(document, `tbody[data-ranking-table="${key}"]`);
                if (tbody) setError(tbody, colspan);
            });
            fetched = true;
            return Promise.resolve();
        }

        const params = new URLSearchParams();
        params.set('action', pcgStudentsRankingsData.action);
        params.set('nonce', pcgStudentsRankingsData.nonce);

        inflight = fetch(`${pcgStudentsRankingsData.ajaxUrl}?${params.toString()}`, {
            method: 'GET',
            credentials: 'same-origin',
        })
            .then((r) => r.json())
            .then((res) => {
                if (!res || !res.success) {
                    throw new Error('bad_response');
                }
                const data = res.data || {};
                renderPurchases(data.purchases || []);
                renderQuizImprovement(data.quiz_improvement || []);
                renderCompletion(data.fastest_completion || [], 'fastest_completion');
                renderCompletion(data.slowest_completion || [], 'slowest_completion');
                fetched = true;
            })
            .catch(() => {
                tbodies.forEach(([key, colspan]) => {
                    const tbody = qs(document, `tbody[data-ranking-table="${key}"]`);
                    if (tbody) setError(tbody, colspan);
                });
            })
            .finally(() => {
                inflight = null;
            });

        return inflight;
    }

    function init() {
        if (!qs(document, '[data-pcg-students-rankings]')) return;

        window.addEventListener('pcg:sales-tab-changed', (e) => {
            if (!e || !e.detail || e.detail.tab !== 'ranking') return;
            fetchRankings();
        });
    }

    document.addEventListener('DOMContentLoaded', init);
})();
