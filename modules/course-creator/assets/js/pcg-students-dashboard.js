/* global Chart, pcgStudentsData */
(function () {
    function qs(root, sel) {
        return (root || document).querySelector(sel);
    }

    function qsa(root, sel) {
        return Array.from((root || document).querySelectorAll(sel));
    }

    function displayDate(iso, locale) {
        const loc = locale || 'es-CL';
        const d = new Date(iso + 'T00:00:00');
        if (isNaN(d.getTime())) return iso;
        return d.toLocaleDateString(loc, { month: 'short', day: 'numeric' });
    }

    function initDashboard(root) {
        if (!root || root.__pcgStudentsInit) return;
        root.__pcgStudentsInit = true;

        const chartCanvas = qs(root, 'canvas[data-pcg-sales-chart]');
        if (!chartCanvas) return;

        const btns = qsa(root, '[data-timeframe]');
        const custom = qs(root, '[data-custom-range]');
        const startInput = qs(root, 'input[data-start-date]');
        const endInput = qs(root, 'input[data-end-date]');

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

            currentChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.map(d => displayDate(d.date, currentLocale)),
                    datasets: [
                        { label: 'Cursos', data: data.map(d => d.courses || 0), backgroundColor: '#C79F32', borderWidth: 0, borderRadius: 0 },
                        { label: 'Libros', data: data.map(d => d.books || 0), backgroundColor: '#D1D1D1', borderWidth: 0, borderRadius: 0 },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top', align: 'end', labels: { boxWidth: 10, font: { size: 10, weight: '600' }, padding: 16, color: '#000' } },
                        tooltip: {
                            backgroundColor: '#fff',
                            titleColor: '#000',
                            bodyColor: '#333',
                            borderColor: '#E5E5E5',
                            borderWidth: 1,
                            cornerRadius: 6,
                            padding: 10,
                            displayColors: false,
                            callbacks: { label: (c) => `${c.dataset.label.toUpperCase()}: ${Number(c.raw || 0)} ESTUDIANTES` },
                        },
                    },
                    scales: {
                        x: { stacked: true, grid: { display: false }, ticks: { font: { size: 10, weight: '600' }, color: '#A8A8A8' } },
                        y: { stacked: true, grid: { color: '#EEEEEE' }, ticks: { font: { size: 10, weight: '600' }, color: '#A8A8A8', precision: 0 } },
                    },
                },
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

        if (startInput) startInput.addEventListener('change', () => currentTimeframe === 'custom' && fetchData());
        if (endInput) endInput.addEventListener('change', () => currentTimeframe === 'custom' && fetchData());

        setActiveBtn(currentTimeframe);
        if (custom) custom.style.display = 'none';
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
