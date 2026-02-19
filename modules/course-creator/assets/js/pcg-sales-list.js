/* global pcgSalesListData */
(function () {
    function qs(root, sel) {
        return (root || document).querySelector(sel);
    }

    function qsa(root, sel) {
        return Array.from((root || document).querySelectorAll(sel));
    }

    function normalize(v) {
        return (v ?? '').toString().toLowerCase().trim();
    }

    function escapeHtml(str) {
        return (str ?? '').toString()
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function initials(name) {
        const parts = (name || '').trim().split(/\s+/).filter(Boolean);
        const a = parts[0]?.[0] || '?';
        const b = parts.length > 1 ? parts[parts.length - 1][0] : '';
        return (a + b).toUpperCase();
    }

    function statusBadge(status) {
        const s = normalize(status);
        if (s === 'paid') return { label: 'Paid', dot: 'good' };
        if (s === 'pending') return { label: 'Pending', dot: 'warn' };
        if (s === 'refunded') return { label: 'Refunded', dot: 'bad' };
        return { label: status || 'Unknown', dot: '' };
    }

    function fmtMoney(amount, locale, currency) {
        const n = Number(amount || 0);
        const loc = locale || undefined;
        const cur = currency || 'CLP';
        try {
            return new Intl.NumberFormat(loc, { style: 'currency', currency: cur, maximumFractionDigits: 0 }).format(n);
        } catch {
            return `${cur} ${Math.round(n).toLocaleString()}`;
        }
    }

    function buildSummary(transactions) {
        const byKey = new Map();

        for (const t of transactions) {
            const key = t.customerKey || String(t.userId || t.email || t.name || '');
            if (!byKey.has(key)) {
                byKey.set(key, {
                    customerKey: key,
                    name: t.name,
                    email: t.email,
                    courses: 0,
                    books: 0,
                    patronage: 0,
                    totalValue: 0,
                    currency: t.currency,
                    productsForSearch: new Set(),
                });
            }

            const u = byKey.get(key);
            const isPaid = normalize(t.status) === 'paid';

            if (isPaid) {
                if (t.productType === 'course') u.courses += 1;
                if (t.productType === 'book') u.books += 1;
                if (t.productType === 'patronage') u.patronage += 1;
                u.totalValue += Number(t.paid || 0);
            }

            if (t.product) u.productsForSearch.add(t.product);
        }

        return Array.from(byKey.values()).map(u => ({
            ...u,
            productsForSearch: Array.from(u.productsForSearch).join(' · '),
        }));
    }

    function init(root) {
        if (!root || root.__pcgSalesListInit) return;
        root.__pcgSalesListInit = true;

        const tabOperational = qs(root, '[data-pcg-sales-list-tab="operational"]');
        const tabSummary = qs(root, '[data-pcg-sales-list-tab="summary"]');
        const panelOperational = qs(root, '[data-pcg-sales-list-panel="operational"]');
        const panelSummary = qs(root, '[data-pcg-sales-list-panel="summary"]');

        const pagePrev = qs(root, 'button[data-pcg-sales-page-prev]');
        const pageNext = qs(root, 'button[data-pcg-sales-page-next]');
        const pageLabel = qs(root, '[data-pcg-sales-page-label]');

        const qOperational = qs(root, 'input[data-pcg-sales-op-search]');
        const clearOperational = qs(root, 'button[data-pcg-sales-op-clear]');
        const qSummary = qs(root, 'input[data-pcg-sales-sum-search]');
        const clearSummary = qs(root, 'button[data-pcg-sales-sum-clear]');

        const opBody = qs(root, 'tbody[data-pcg-sales-op-body]');
        const opEmpty = qs(root, '[data-pcg-sales-op-empty]');
        const opCount = qs(root, '[data-pcg-sales-op-count]');
        const opTotal = qs(root, '[data-pcg-sales-op-total]');

        const sumBody = qs(root, 'tbody[data-pcg-sales-sum-body]');
        const sumEmpty = qs(root, '[data-pcg-sales-sum-empty]');
        const sumCount = qs(root, '[data-pcg-sales-sum-count]');
        const sumTotal = qs(root, '[data-pcg-sales-sum-total]');

        let transactions = [];
        let summary = [];
        let locale = 'es-CL';
        let currency = 'CLP';
        let activeTable = 'operational';
        const pageSize = 12;
        let opPage = 1;
        let sumPage = 1;

        function setTab(which) {
            const isOp = which === 'operational';
            activeTable = which;
            if (tabOperational) tabOperational.setAttribute('aria-selected', String(isOp));
            if (tabSummary) tabSummary.setAttribute('aria-selected', String(!isOp));
            if (panelOperational) panelOperational.hidden = !isOp;
            if (panelSummary) panelSummary.hidden = isOp;

            if (isOp && qOperational) qOperational.focus();
            if (!isOp && qSummary) qSummary.focus();

            updatePagination();
        }

        function clampPage(page, total) {
            const pageCount = Math.max(1, Math.ceil((total || 0) / pageSize));
            return Math.min(Math.max(1, page), pageCount);
        }

        function getPaged(rows, page) {
            const total = rows.length;
            const safePage = clampPage(page, total);
            const start = (safePage - 1) * pageSize;
            const end = start + pageSize;
            return { page: safePage, pageCount: Math.max(1, Math.ceil(total / pageSize)), total, rows: rows.slice(start, end) };
        }

        function updatePagination() {
            const isOp = activeTable === 'operational';
            const q = isOp ? (qOperational ? qOperational.value : '') : (qSummary ? qSummary.value : '');
            const filtered = isOp ? filterOperational(q) : filterSummary(q);
            const currentPage = isOp ? opPage : sumPage;
            const { page, pageCount, total } = getPaged(filtered, currentPage);

            if (isOp) opPage = page;
            else sumPage = page;

            if (pageLabel) {
                pageLabel.textContent = `${page} / ${pageCount}`;
            }
            if (pagePrev) pagePrev.disabled = page <= 1;
            if (pageNext) pageNext.disabled = page >= pageCount;

            // keep totals in sync with filtered results (matches "Mostrando x de y" UX)
            if (isOp) {
                if (opTotal) opTotal.textContent = String(total);
            } else {
                if (sumTotal) sumTotal.textContent = String(total);
            }
        }

        function renderOperational(rows) {
            if (!opBody) return;
            opBody.innerHTML = '';

            for (const r of rows) {
                const { label, dot } = statusBadge(r.status);
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>
                        <div class="pcg-sales-list-user">
                            <div class="pcg-sales-list-avatar" aria-hidden="true">${escapeHtml(initials(r.name))}</div>
                            <div class="pcg-sales-list-user-meta">
                                <div class="pcg-sales-list-user-name">${escapeHtml(r.name || '')}</div>
                                <div class="pcg-sales-list-user-email">${escapeHtml(r.email || '')}</div>
                            </div>
                        </div>
                    </td>
                    <td>${escapeHtml(r.product || '')}</td>
                    <td class="pcg-sales-list-muted">${escapeHtml(r.orderId || '')}</td>
                    <td>
                        <span class="pcg-sales-list-badge"><span class="pcg-sales-list-dot ${dot}"></span>${escapeHtml(label)}</span>
                    </td>
                    <td class="pcg-sales-list-money">${escapeHtml(fmtMoney(r.paid, locale, currency))}</td>
                    <td class="pcg-sales-list-muted">${escapeHtml(r.date || '')}</td>
                `;
                opBody.appendChild(tr);
            }

            if (opEmpty) opEmpty.hidden = rows.length !== 0;
            if (opCount) opCount.textContent = String(rows.length);
        }

        function renderSummary(rows) {
            if (!sumBody) return;
            sumBody.innerHTML = '';

            for (const u of rows) {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>
                        <div class="pcg-sales-list-user">
                            <div class="pcg-sales-list-avatar" aria-hidden="true">${escapeHtml(initials(u.name))}</div>
                            <div class="pcg-sales-list-user-meta">
                                <div class="pcg-sales-list-user-name">${escapeHtml(u.name || '')}</div>
                                <div class="pcg-sales-list-user-email">${escapeHtml(u.email || '')}</div>
                            </div>
                        </div>
                    </td>
                    <td><strong>${Number(u.courses || 0)}</strong></td>
                    <td><strong>${Number(u.books || 0)}</strong></td>
                    <td><strong>${Number(u.patronage || 0)}</strong></td>
                    <td class="pcg-sales-list-money">${escapeHtml(fmtMoney(u.totalValue, locale, currency))}</td>
                `;
                sumBody.appendChild(tr);
            }

            if (sumEmpty) sumEmpty.hidden = rows.length !== 0;
            if (sumCount) sumCount.textContent = String(rows.length);
        }

        function filterOperational(query) {
            const q = normalize(query);
            if (!q) return transactions;
            return transactions.filter(r => (
                normalize(r.name).includes(q) ||
                normalize(r.email).includes(q) ||
                normalize(r.product).includes(q)
            ));
        }

        function filterSummary(query) {
            const q = normalize(query);
            if (!q) return summary;
            return summary.filter(u => (
                normalize(u.name).includes(q) ||
                normalize(u.email).includes(q) ||
                normalize(u.productsForSearch).includes(q)
            ));
        }

        function refreshOperational() {
            const filtered = filterOperational(qOperational ? qOperational.value : '');
            opPage = clampPage(opPage, filtered.length);
            const paged = getPaged(filtered, opPage);
            renderOperational(paged.rows);
            if (opTotal) opTotal.textContent = String(paged.total);
            updatePagination();
        }

        function refreshSummary() {
            const filtered = filterSummary(qSummary ? qSummary.value : '');
            sumPage = clampPage(sumPage, filtered.length);
            const paged = getPaged(filtered, sumPage);
            renderSummary(paged.rows);
            if (sumTotal) sumTotal.textContent = String(paged.total);
            updatePagination();
        }

        function setLoading(isLoading) {
            root.classList.toggle('pcg-sales-list-loading', !!isLoading);
            [qOperational, clearOperational, qSummary, clearSummary, tabOperational, tabSummary, pagePrev, pageNext].forEach(el => {
                if (!el) return;
                el.disabled = !!isLoading;
            });
        }

        function fetchTransactions() {
            if (!pcgSalesListData || !pcgSalesListData.ajaxUrl) return Promise.resolve([]);
            const params = new URLSearchParams();
            params.set('action', pcgSalesListData.action);
            params.set('nonce', pcgSalesListData.nonce);

            setLoading(true);
            return fetch(pcgSalesListData.ajaxUrl + '?' + params.toString(), {
                method: 'GET',
                credentials: 'same-origin',
            })
                .then(r => r.json())
                .then(res => {
                    if (!res || !res.success || !res.data) return [];
                    locale = res.data.locale || locale;
                    currency = res.data.currency || currency;
                    return Array.isArray(res.data.rows) ? res.data.rows : [];
                })
                .catch(() => [])
                .finally(() => setLoading(false));
        }

        if (tabOperational) tabOperational.addEventListener('click', () => setTab('operational'));
        if (tabSummary) tabSummary.addEventListener('click', () => setTab('summary'));

        if (pagePrev) pagePrev.addEventListener('click', () => {
            if (activeTable === 'operational') {
                opPage = Math.max(1, opPage - 1);
                refreshOperational();
            } else {
                sumPage = Math.max(1, sumPage - 1);
                refreshSummary();
            }
        });

        if (pageNext) pageNext.addEventListener('click', () => {
            if (activeTable === 'operational') {
                opPage = opPage + 1;
                refreshOperational();
            } else {
                sumPage = sumPage + 1;
                refreshSummary();
            }
        });

        qsa(root, '.pcg-sales-list-tab').forEach((btn, idx, arr) => {
            btn.addEventListener('keydown', (e) => {
                if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return;
                e.preventDefault();
                const dir = e.key === 'ArrowRight' ? 1 : -1;
                const next = (idx + dir + arr.length) % arr.length;
                arr[next].focus();
                arr[next].click();
            });
        });

        if (qOperational) qOperational.addEventListener('input', refreshOperational);
        if (qSummary) qSummary.addEventListener('input', refreshSummary);

        if (clearOperational) clearOperational.addEventListener('click', () => {
            if (qOperational) qOperational.value = '';
            opPage = 1;
            refreshOperational();
            if (qOperational) qOperational.focus();
        });

        if (clearSummary) clearSummary.addEventListener('click', () => {
            if (qSummary) qSummary.value = '';
            sumPage = 1;
            refreshSummary();
            if (qSummary) qSummary.focus();
        });

        function hydrate(rows) {
            transactions = Array.isArray(rows) ? rows : [];
            summary = buildSummary(transactions);

            opPage = 1;
            sumPage = 1;

            refreshOperational();
            refreshSummary();
        }

        let fetched = false;
        function ensureFetched() {
            if (fetched) return;
            fetched = true;
            fetchTransactions().then(hydrate);
        }

        window.addEventListener('pcg:sales-tab-changed', (e) => {
            if (!e || !e.detail || e.detail.tab !== 'list') return;
            ensureFetched();
        });

        if (document.querySelector('#pcg-sales-tabs .pcg-segment.active[data-sales-tab="list"]')) {
            ensureFetched();
        }

        setTab('operational');
    }

    document.addEventListener('DOMContentLoaded', () => {
        qsa(document, '[data-pcg-sales-list]').forEach(init);
    });
})();
