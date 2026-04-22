/* global Chart, pcgStudentsData */
(function () {
    function qs(root, sel) {
        return (root || document).querySelector(sel);
    }

    function qsa(root, sel) {
        return Array.from((root || document).querySelectorAll(sel));
    }

    function isMobileLayout() {
        return window.matchMedia && window.matchMedia('(max-width: 850px)').matches;
    }

    function displayDate(iso, locale) {
        const loc = locale || 'es-CL';
        const d = new Date(iso + 'T00:00:00');
        if (isNaN(d.getTime())) return iso;
        return d.toLocaleDateString(loc, { month: 'short', day: 'numeric' });
    }

    function dayNumberLabel(iso) {
        const d = new Date(iso + 'T00:00:00');
        if (isNaN(d.getTime())) return iso;
        return String(d.getDate());
    }

    function weekday2(iso) {
        const d = new Date(iso + 'T00:00:00');
        if (isNaN(d.getTime())) return iso;
        const map = ['DO', 'LU', 'MA', 'MI', 'JU', 'VI', 'SA'];
        return map[d.getDay()] || iso;
    }

    function isoDate(d) {
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${y}-${m}-${day}`;
    }

    function initDashboard(root) {
        if (!root || root.__pcgStudentsInit) return;
        root.__pcgStudentsInit = true;

        const chartCanvas = qs(root, 'canvas[data-pcg-sales-chart]');
        if (!chartCanvas) return;

        const legendContainers = qsa(root, '[data-pcg-students-legend]');

        const btns = qsa(root, '[data-timeframe]');
        const custom = qs(root, '[data-custom-range]');
        const startInput = qs(root, 'input[data-start-date]');
        const endInput = qs(root, 'input[data-end-date]');

        const rangeOverlay = qs(root, '[data-pcg-students-range-overlay]');
        const rangeBackdrop = qs(root, '[data-pcg-students-range-backdrop]');
        const rangeModal = qs(root, '[data-pcg-students-range-modal]');
        const rangeClose = qs(root, '[data-pcg-students-range-close]');
        const rangeApply = qs(root, '[data-pcg-students-range-apply]');
        const rangeOptions = rangeOverlay ? qsa(rangeOverlay, '[data-pcg-students-range]') : [];
        let pendingRange = 'month';

        const elStudentsTotal = qs(document, '[data-students-metric="students_total"]');
        const elAvgCoursesPerStudent = qs(document, '[data-students-metric="avg_courses_per_student"]');
        const elAvgCompletionDays = qs(document, '[data-students-metric="avg_course_completion_days"]');
        const elAvgCoursesCompletedPerStudent = qs(document, '[data-students-metric="avg_courses_completed_per_student"]');
        const elAssessmentDelta = qs(document, '[data-students-metric="assessment_delta_pct"]');

        let currentTimeframe = 'month';
        let currentChart = null;
        let currentLocale = 'es-CL';
        let inFlight = null;

        function setActiveBtn(tf) {
            btns.forEach(b => b.classList.toggle('active', b.getAttribute('data-timeframe') === tf));
        }

        function setActiveRangeOption(value) {
            if (!rangeOptions || rangeOptions.length === 0) return;
            rangeOptions.forEach((btn) => {
                btn.classList.toggle('is-active', btn.getAttribute('data-pcg-students-range') === value);
            });
        }

        function openRange() {
            if (!rangeOverlay) return;
            pendingRange = currentTimeframe === 'custom' ? 'this_month' : currentTimeframe;
            if (!['week', 'month', 'this_month'].includes(pendingRange)) pendingRange = 'month';
            setActiveRangeOption(pendingRange);
            rangeOverlay.classList.remove('pcg-students-range-overlay--hidden');
            rangeOverlay.setAttribute('aria-hidden', 'false');
        }

        function closeRange() {
            if (!rangeOverlay) return;
            rangeOverlay.classList.add('pcg-students-range-overlay--hidden');
            rangeOverlay.setAttribute('aria-hidden', 'true');
        }

        function setLoading(isLoading) {
            btns.forEach(b => {
                b.disabled = !!isLoading;
                b.style.opacity = isLoading ? '0.7' : '';
            });
        }

        function setMetricText(el, text) {
            if (!el) return;
            el.textContent = text;
        }

        function fmt(value, digits) {
            const d = typeof digits === 'number' ? digits : 2;
            const n = Number(value);
            if (!isFinite(n)) return '0';
            return n.toFixed(d).replace(/\.00$/, '');
        }

        function renderDelta(value) {
            if (!elAssessmentDelta) return;
            const n = Number(value);
            if (!isFinite(n)) {
                elAssessmentDelta.textContent = '0%';
                return;
            }

            const pct = `${Math.abs(n).toFixed(0)}%`;
            elAssessmentDelta.textContent = pct;

            elAssessmentDelta.classList.remove('pcg-students-delta--positive', 'pcg-students-delta--negative', 'pcg-students-delta--neutral');
            const prevIcon = elAssessmentDelta.querySelector('.dashicons');
            if (prevIcon) prevIcon.remove();

            if (n > 0) {
                elAssessmentDelta.classList.add('pcg-students-delta--positive');
                const icon = document.createElement('span');
                icon.className = 'dashicons dashicons-arrow-up-alt';
                elAssessmentDelta.prepend(icon);
            } else if (n < 0) {
                elAssessmentDelta.classList.add('pcg-students-delta--negative');
            } else {
                elAssessmentDelta.classList.add('pcg-students-delta--neutral');
            }
        }

        function renderMetrics(data) {
            const totals = (data && data.totals) ? data.totals : null;
            const studentsTotal = totals && typeof totals.total === 'number' ? totals.total : 0;
            const coursesTotal = totals && typeof totals.courses === 'number' ? totals.courses : 0;
            const avgCompletionDays = totals && typeof totals.avg_course_completion_days === 'number' ? totals.avg_course_completion_days : 0;
            const avgCoursesCompleted = totals && typeof totals.avg_courses_completed_per_student === 'number' ? totals.avg_courses_completed_per_student : 0;
            const deltaPct = totals && typeof totals.assessment_delta_pct === 'number' ? totals.assessment_delta_pct : 0;

            setMetricText(elStudentsTotal, String(studentsTotal));

            const avgCourses = studentsTotal > 0 ? (coursesTotal / studentsTotal) : 0;
            setMetricText(elAvgCoursesPerStudent, fmt(avgCourses, 2));

            setMetricText(elAvgCompletionDays, fmt(avgCompletionDays, 0));
            setMetricText(elAvgCoursesCompletedPerStudent, fmt(avgCoursesCompleted, 2));
            renderDelta(deltaPct);
        }

        function renderChart(series) {
            if (typeof Chart === 'undefined') return;
            const ctx = chartCanvas.getContext('2d');
            if (currentChart) currentChart.destroy();

            const data = Array.isArray(series) ? series : [];
            const mobile = isMobileLayout();
            const labels = data.map(d => {
                if (mobile && currentTimeframe === 'week') return weekday2(d.date);
                if (currentTimeframe === 'month' || currentTimeframe === 'custom') return dayNumberLabel(d.date);
                return displayDate(d.date, currentLocale);
            });

            function renderHtmlLegend(chart) {
                if (!legendContainers || legendContainers.length === 0) return;
                legendContainers.forEach((c) => {
                    c.innerHTML = '';
                });

                const items = chart?.options?.plugins?.legend?.labels?.generateLabels
                    ? chart.options.plugins.legend.labels.generateLabels(chart)
                    : [];

                items.forEach((it) => {
                    legendContainers.forEach((container) => {
                        const el = document.createElement('div');
                        el.className = 'pcg-sales-chart-legend-item';
                        el.innerHTML = `
                            <span class="pcg-sales-chart-legend-swatch" style="background:${it.fillStyle};"></span>
                            <span>${it.text}</span>
                        `;
                        container.appendChild(el);
                    });
                });
            }

            const htmlLegendPlugin = {
                id: 'pcgStudentsHtmlLegend',
                afterUpdate(chart) {
                    renderHtmlLegend(chart);
                }
            };

            currentChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [
                        { label: 'Cursos', data: data.map(d => d.courses || 0), backgroundColor: '#C79F32', borderWidth: 0, borderRadius: 0 },
                        { label: 'Libros', data: data.map(d => d.books || 0), backgroundColor: '#D1D1D1', borderWidth: 0, borderRadius: 0 },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false, labels: { boxWidth: 10, font: { size: 10, weight: '600' }, padding: 16, color: '#000' } },
                        tooltip: {
                            backgroundColor: '#fff',
                            titleColor: '#000',
                            bodyColor: '#333',
                            borderColor: '#E5E5E5',
                            borderWidth: 1,
                            cornerRadius: 6,
                            padding: 10,
                            displayColors: false,
                            callbacks: { label: (c) => `${String(c.dataset.label || '').toUpperCase()}: ${Number(c.raw || 0)}` },
                        },
                    },
                    scales: {
                        x: {
                            stacked: true,
                            grid: { display: true, color: '#F3F4F6', drawBorder: false },
                            ticks: { font: { size: 10, weight: '600' }, color: '#A8A8A8' }
                        },
                        y: { stacked: true, grid: { color: '#EEEEEE' }, ticks: { font: { size: 10, weight: '600' }, color: '#A8A8A8', precision: 0 } },
                    },
                },
                plugins: [htmlLegendPlugin],
            });
        }

        function fetchData() {
            if (typeof pcgStudentsData === 'undefined' || !pcgStudentsData.ajaxUrl) {
                renderMetrics(null);
                renderChart([]);
                return;
            }

            const params = new URLSearchParams();
            params.set('action', pcgStudentsData.action);
            params.set('nonce', pcgStudentsData.nonce);
            params.set('timeframe', currentTimeframe);

            if (currentTimeframe === 'custom') {
                const s = startInput && startInput.value;
                const e = endInput && endInput.value;
                if (!s || !e) {
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

            fetch(pcgStudentsData.ajaxUrl + '?' + params.toString(), {
                method: 'GET',
                credentials: 'same-origin',
                signal: controller.signal,
            })
                .then(r => r.json())
                .then(res => {
                    if (!res || !res.success) {
                        renderMetrics(null);
                        renderChart([]);
                        return;
                    }

                    const data = res.data || {};
                    currentLocale = data.locale || currentLocale;
                    renderMetrics(data);
                    renderChart(data.series || []);
                })
                .catch(err => {
                    if (err && err.name === 'AbortError') return;
                    renderMetrics(null);
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

        if (rangeOptions && rangeOptions.length) {
            rangeOptions.forEach((btn) => {
                btn.addEventListener('click', () => {
                    pendingRange = btn.getAttribute('data-pcg-students-range') || 'week';
                    setActiveRangeOption(pendingRange);
                });
            });
        }

        if (rangeApply) {
            rangeApply.addEventListener('click', () => {
                const sel = pendingRange || 'week';
                if (sel === 'this_month') {
                    const now = new Date();
                    const start = new Date(now.getFullYear(), now.getMonth(), 1);
                    const end = new Date(now.getFullYear(), now.getMonth(), now.getDate());
                    if (startInput) startInput.value = isoDate(start);
                    if (endInput) endInput.value = isoDate(end);
                    currentTimeframe = 'custom';
                    setActiveBtn('custom');
                    if (custom) custom.style.display = '';
                    fetchData();
                } else {
                    currentTimeframe = sel === 'month' ? 'month' : 'week';
                    setActiveBtn(currentTimeframe);
                    if (custom) custom.style.display = 'none';
                    fetchData();
                }
                closeRange();
            });
        }

        if (rangeClose) rangeClose.addEventListener('click', closeRange);
        if (rangeBackdrop) rangeBackdrop.addEventListener('click', closeRange);
        if (rangeModal) {
            rangeModal.addEventListener('click', (e) => {
                if (e && typeof e.stopPropagation === 'function') e.stopPropagation();
            });
        }

        window.addEventListener('pcg:students-open-range', openRange);
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeRange();
        });

        if (startInput) startInput.addEventListener('change', () => currentTimeframe === 'custom' && fetchData());
        if (endInput) endInput.addEventListener('change', () => currentTimeframe === 'custom' && fetchData());

        setActiveBtn(currentTimeframe);
        if (custom) custom.style.display = currentTimeframe === 'custom' ? '' : 'none';
        fetchData();

        window.addEventListener('pcg:sales-tab-changed', (e) => {
            if (!e || !e.detail || !e.detail.tab) return;
            if (e.detail.tab === 'general') {
                setTimeout(fetchData, 0);
            }
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        qsa(document, '.pcg-sales-dashboard[data-pcg-students-dashboard]').forEach(initDashboard);
    });
})();
