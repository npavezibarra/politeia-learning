/* global Chart */
(function () {
    function qs(root, sel) {
        return (root || document).querySelector(sel);
    }

    function qsa(root, sel) {
        return Array.from((root || document).querySelectorAll(sel));
    }

    function money(value, locale, currency) {
        const loc = locale || 'es-CL';
        const cur = currency || 'CLP';
        return new Intl.NumberFormat(loc, { style: 'currency', currency: cur, maximumFractionDigits: 0 }).format(value || 0);
    }

    function pct(value, total) {
        const safe = total || 1;
        return `${((value / safe) * 100).toFixed(1)}%`;
    }

    function displayDate(iso, locale) {
        const loc = locale || 'es-CL';
        const d = new Date(iso + 'T00:00:00');
        if (isNaN(d.getTime())) return iso;
        return d.toLocaleDateString(loc, { month: 'short', day: 'numeric' });
    }

    function initDashboard(root) {
        if (!root || root.__pcgSalesInit) return;
        root.__pcgSalesInit = true;

        const chartCanvas = qs(root, 'canvas[data-pcg-sales-chart]');
        if (!chartCanvas) return;

        const legendContainer = qs(root, '[data-pcg-sales-legend]');

        const btns = qsa(root, '[data-timeframe]');
        const custom = qs(root, '[data-custom-range]');
        const startInput = qs(root, 'input[data-start-date]');
        const endInput = qs(root, 'input[data-end-date]');

        const elTotal = qs(root, '[data-metric="total"]');
        const elCourses = qs(root, '[data-metric="courses"]');
        const elBooks = qs(root, '[data-metric="books"]');
        const elPatronage = qs(root, '[data-metric="patronage"]');
        const elCoursesPct = qs(root, '[data-metric-pct="courses"]');
        const elBooksPct = qs(root, '[data-metric-pct="books"]');
        const elPatronagePct = qs(root, '[data-metric-pct="patronage"]');

        let currentTimeframe = 'month';
        let currentChart = null;
        let currentLocale = 'es-CL';
        let currentCurrency = 'CLP';
        let inFlight = null;
        const i18n = (typeof pcgSalesData !== 'undefined' && pcgSalesData && pcgSalesData.i18n) ? pcgSalesData.i18n : {};

        function setActiveBtn(tf) {
            btns.forEach(b => b.classList.toggle('active', b.getAttribute('data-timeframe') === tf));
        }

        function setMetricText(el, text) {
            if (!el) return;
            el.textContent = text;
        }

        function renderMetrics(data) {
            const t = data.totals || { total: 0, courses: 0, books: 0, patronage: 0 };
            const c = data.counts || { total: 0, courses: 0, books: 0, patronage: 0 };

            setMetricText(elTotal, money(t.total, currentLocale, currentCurrency));
            setMetricText(elCourses, money(t.courses, currentLocale, currentCurrency));
            setMetricText(elBooks, money(t.books, currentLocale, currentCurrency));
            setMetricText(elPatronage, money(t.patronage, currentLocale, currentCurrency));

            // Update labels with counts
            const labels = {
                total: `${c.total} ${i18n.productsSold || 'PRODUCTOS VENDIDOS'}`,
                courses: `${c.courses} ${i18n.coursesSold || 'CURSOS VENDIDOS'}`,
                books: `${c.books} ${i18n.booksSold || 'LIBROS VENDIDOS'}`,
                patronage: `${c.patronage} ${i18n.supportSold || 'APOYOS VENDIDOS'}`
            };

            Object.keys(labels).forEach(key => {
                const el = qs(root, `.pcg-metric-label[data-label="${key}"]`);
                if (el) el.textContent = labels[key];
            });

            if (elCoursesPct) setMetricText(elCoursesPct, `${pct(t.courses, t.total)} ${elCoursesPct.getAttribute('data-suffix') || ''}`.trim());
            if (elBooksPct) setMetricText(elBooksPct, `${pct(t.books, t.total)} ${elBooksPct.getAttribute('data-suffix') || ''}`.trim());
            if (elPatronagePct) setMetricText(elPatronagePct, `${pct(t.patronage, t.total)} ${elPatronagePct.getAttribute('data-suffix') || ''}`.trim());
        }

        function renderChart(series) {
            if (typeof Chart === 'undefined') return;
            const ctx = chartCanvas.getContext('2d');
            if (currentChart) currentChart.destroy();

            const data = Array.isArray(series) ? series : [];

            function renderHtmlLegend(chart) {
                if (!legendContainer) return;
                legendContainer.innerHTML = '';

                const items = chart?.options?.plugins?.legend?.labels?.generateLabels
                    ? chart.options.plugins.legend.labels.generateLabels(chart)
                    : [];

                items.forEach((it) => {
                    const el = document.createElement('div');
                    el.className = 'pcg-sales-chart-legend-item';
                    el.innerHTML = `
                        <span class="pcg-sales-chart-legend-swatch" style="background:${it.fillStyle};"></span>
                        <span>${it.text}</span>
                    `;
                    legendContainer.appendChild(el);
                });
            }

            const htmlLegendPlugin = {
                id: 'pcgHtmlLegend',
                afterUpdate(chart) {
                    renderHtmlLegend(chart);
                }
            };

            currentChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.map(d => displayDate(d.date, currentLocale)),
                    datasets: [
                        { label: 'Cursos', data: data.map(d => d.courses || 0), backgroundColor: '#C79F32', borderWidth: 0, borderRadius: 0 },
                        { label: 'Libros', data: data.map(d => d.books || 0), backgroundColor: '#D1D1D1', borderWidth: 0, borderRadius: 0 },
                        { label: 'Patrocinio', data: data.map(d => d.patronage || 0), backgroundColor: '#B87333', borderWidth: 0, borderRadius: 0 },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false,
                            labels: { boxWidth: 10, font: { size: 10, weight: '600' }, padding: 16, color: '#000' }
                        },
                        tooltip: {
                            backgroundColor: '#fff',
                            titleColor: '#000',
                            bodyColor: '#333',
                            borderColor: '#E5E5E5',
                            borderWidth: 1,
                            cornerRadius: 6,
                            padding: 10,
                            displayColors: false,
                            callbacks: { label: (c) => `${c.dataset.label.toUpperCase()}: ${money(c.raw, currentLocale, currentCurrency)}` },
                        },
                    },
                    scales: {
                        x: { stacked: true, grid: { display: false }, ticks: { font: { size: 10, weight: '600' }, color: '#A8A8A8' } },
                        y: { stacked: true, grid: { color: '#EEEEEE' }, ticks: { font: { size: 10, weight: '600' }, color: '#A8A8A8' } },
                    },
                },
                plugins: [htmlLegendPlugin],
            });
        }

        function setLoading(isLoading) {
            btns.forEach(b => {
                b.disabled = !!isLoading;
                b.style.opacity = isLoading ? '0.7' : '';
            });
        }

        function fetchData() {
            if (typeof pcgSalesData === 'undefined' || !pcgSalesData.ajaxUrl) {
                // If not configured, keep the UI stable (zeros).
                renderMetrics({ totals: null, counts: null });
                renderChart([]);
                return;
            }

            const params = new URLSearchParams();
            params.set('action', pcgSalesData.action);
            params.set('nonce', pcgSalesData.nonce);
            params.set('timeframe', currentTimeframe);

            if (currentTimeframe === 'custom') {
                const s = startInput && startInput.value;
                const e = endInput && endInput.value;
                if (!s || !e) {
                    renderMetrics({ total: 0, courses: 0, books: 0, patronage: 0 });
                    renderChart([]);
                    return;
                }
                params.set('start_date', s);
                params.set('end_date', e);
            }

            if (inFlight && typeof inFlight.abort === 'function') {
                inFlight.abort();
            }

            const controller = new AbortController();
            inFlight = controller;
            setLoading(true);

            fetch(pcgSalesData.ajaxUrl + '?' + params.toString(), {
                method: 'GET',
                credentials: 'same-origin',
                signal: controller.signal,
            })
                .then(r => r.json())
                .then(res => {
                    if (!res || !res.success) {
                        renderMetrics({ totals: null, counts: null });
                        renderChart([]);
                        return;
                    }

                    const data = res.data || {};
                    currentLocale = data.locale || currentLocale;
                    currentCurrency = data.currency || currentCurrency;

                    renderMetrics(data);
                    renderChart(data.series || []);
                })
                .catch(err => {
                    if (err && err.name === 'AbortError') return;
                    renderMetrics({ totals: null, counts: null });
                    renderChart([]);
                })
                .finally(() => {
                    setLoading(false);
                });
        }

        btns.forEach(btn => {
            btn.addEventListener('click', () => {
                currentTimeframe = btn.getAttribute('data-timeframe');
                setActiveBtn(currentTimeframe);
                if (custom) custom.style.display = currentTimeframe === 'custom' ? '' : 'none';
                if (currentTimeframe !== 'custom') fetchData();
            });
        });

        if (startInput) startInput.addEventListener('change', fetchData);
        if (endInput) endInput.addEventListener('change', fetchData);

        // Default state
        setActiveBtn(currentTimeframe);
        if (custom) custom.style.display = 'none';
        fetchData();

        // When switching tabs, re-render so Chart.js recalculates sizes.
        window.addEventListener('pcg:sales-tab-changed', (e) => {
            if (!e || !e.detail || !e.detail.tab) return;
            if (e.detail.tab === 'general') {
                setTimeout(fetchData, 0);
            }
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        qsa(document, '.pcg-sales-dashboard[data-pcg-sales-dashboard]').forEach(initDashboard);
    });
})();
