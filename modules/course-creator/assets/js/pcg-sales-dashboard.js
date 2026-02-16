/* global Chart */
(function () {
    function qs(root, sel) {
        return (root || document).querySelector(sel);
    }

    function qsa(root, sel) {
        return Array.from((root || document).querySelectorAll(sel));
    }

    function money(value) {
        return new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 }).format(value);
    }

    function pct(value, total) {
        const safe = total || 1;
        return `${((value / safe) * 100).toFixed(1)}%`;
    }

    function isoDate(d) {
        return d.toISOString().split('T')[0];
    }

    function displayDate(d) {
        return d.toLocaleDateString('es-CL', { month: 'short', day: 'numeric' });
    }

    function generateMockData(days) {
        const data = [];
        const now = new Date();
        for (let i = days; i >= 0; i--) {
            const date = new Date(now);
            date.setDate(now.getDate() - i);
            const courses = Math.floor(Math.random() * 50000) + 20000;
            const books = Math.floor(Math.random() * 30000) + 10000;
            const patronage = Math.floor(Math.random() * 15000) + 5000;
            data.push({
                date: isoDate(date),
                displayDate: displayDate(date),
                courses,
                books,
                patronage,
                total: courses + books + patronage,
            });
        }
        return data;
    }

    function initDashboard(root) {
        if (!root || root.__pcgSalesInit) return;
        root.__pcgSalesInit = true;

        const chartCanvas = qs(root, 'canvas[data-pcg-sales-chart]');
        if (!chartCanvas) return;

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

        const allData = generateMockData(365);
        let currentTimeframe = 'month';
        let currentChart = null;

        function setActiveBtn(tf) {
            btns.forEach(b => b.classList.toggle('active', b.getAttribute('data-timeframe') === tf));
        }

        function filterData() {
            const now = new Date();
            let start = new Date(now);

            if (currentTimeframe === 'day') {
                start.setDate(now.getDate() - 1);
            } else if (currentTimeframe === 'week') {
                start.setDate(now.getDate() - 7);
            } else if (currentTimeframe === 'month') {
                start.setMonth(now.getMonth() - 1);
            } else if (currentTimeframe === 'year') {
                start.setFullYear(now.getFullYear() - 1);
            } else if (currentTimeframe === 'custom') {
                const s = startInput && startInput.value;
                const e = endInput && endInput.value;
                if (!s || !e) return [];
                return allData.filter(d => d.date >= s && d.date <= e);
            }

            const startStr = isoDate(start);
            return allData.filter(d => d.date >= startStr);
        }

        function renderMetrics(data) {
            const totals = data.reduce(
                (acc, cur) => ({
                    total: acc.total + cur.total,
                    courses: acc.courses + cur.courses,
                    books: acc.books + cur.books,
                    patronage: acc.patronage + cur.patronage,
                }),
                { total: 0, courses: 0, books: 0, patronage: 0 }
            );

            if (elTotal) elTotal.textContent = money(totals.total);
            if (elCourses) elCourses.textContent = money(totals.courses);
            if (elBooks) elBooks.textContent = money(totals.books);
            if (elPatronage) elPatronage.textContent = money(totals.patronage);

            if (elCoursesPct) elCoursesPct.textContent = `${pct(totals.courses, totals.total)} ${elCoursesPct.getAttribute('data-suffix') || ''}`.trim();
            if (elBooksPct) elBooksPct.textContent = `${pct(totals.books, totals.total)} ${elBooksPct.getAttribute('data-suffix') || ''}`.trim();
            if (elPatronagePct) elPatronagePct.textContent = `${pct(totals.patronage, totals.total)} ${elPatronagePct.getAttribute('data-suffix') || ''}`.trim();
        }

        function renderChart(data) {
            if (typeof Chart === 'undefined') return;
            const ctx = chartCanvas.getContext('2d');
            if (currentChart) currentChart.destroy();

            currentChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.map(d => d.displayDate),
                    datasets: [
                        { label: 'Cursos', data: data.map(d => d.courses), backgroundColor: '#C79F32', borderWidth: 0, borderRadius: 0 },
                        { label: 'Libros', data: data.map(d => d.books), backgroundColor: '#D1D1D1', borderWidth: 0, borderRadius: 0 },
                        { label: 'Patrocinio', data: data.map(d => d.patronage), backgroundColor: '#B87333', borderWidth: 0, borderRadius: 0 },
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
                            callbacks: { label: (c) => `${c.dataset.label.toUpperCase()}: ${money(c.raw)}` },
                        },
                    },
                    scales: {
                        x: { stacked: true, grid: { display: false }, ticks: { font: { size: 10, weight: '600' }, color: '#A8A8A8' } },
                        y: { stacked: true, grid: { color: '#EEEEEE' }, ticks: { font: { size: 10, weight: '600' }, color: '#A8A8A8' } },
                    },
                },
            });
        }

        function update() {
            const data = filterData();
            if (!data.length) return;
            renderMetrics(data);
            renderChart(data);
        }

        btns.forEach(btn => {
            btn.addEventListener('click', () => {
                currentTimeframe = btn.getAttribute('data-timeframe');
                setActiveBtn(currentTimeframe);
                if (custom) custom.style.display = currentTimeframe === 'custom' ? '' : 'none';
                if (currentTimeframe !== 'custom') update();
            });
        });

        if (startInput) startInput.addEventListener('change', update);
        if (endInput) endInput.addEventListener('change', update);

        // Default state
        setActiveBtn(currentTimeframe);
        if (custom) custom.style.display = 'none';
        update();

        // When switching tabs, re-render so Chart.js recalculates sizes.
        window.addEventListener('pcg:sales-tab-changed', (e) => {
            if (!e || !e.detail || !e.detail.tab) return;
            if (e.detail.tab === 'general') {
                setTimeout(update, 0);
            }
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        qsa(document, '.pcg-sales-dashboard').forEach(initDashboard);
    });
})();
