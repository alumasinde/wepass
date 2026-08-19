document.addEventListener('DOMContentLoaded', function () {

    let workflowChart = null;
    let monthlyChart = null;
    let weeklyChart = null;

    function safeDestroy(chart) {
        if (chart && typeof chart.destroy === 'function') {
            chart.destroy();
        }
    }

    function formatDate(dateStr) {
        const d = new Date(dateStr);
        if (isNaN(d)) return dateStr;
        return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
    }

    function renderCharts(data) {

        if (!data) return;

        const workflowCanvas = document.getElementById('workflowChart');
        const monthlyCanvas = document.getElementById('monthlyChart');
        const weeklyCanvas = document.getElementById('weeklyChart');

        if (!workflowCanvas || !monthlyCanvas || !weeklyCanvas) return;

        safeDestroy(workflowChart);
        safeDestroy(monthlyChart);
        safeDestroy(weeklyChart);

        const workflow = data.workflow_status || {labels:[],data:[]};
        const daily = data.daily_gatepasses || {labels:[],data:[]};
        const weekly = data.weekly_visits || {labels:[],data:[]};

        workflowChart = new Chart(workflowCanvas, {
            type: 'doughnut',
            data: {
                labels: workflow.labels,
                datasets: [{
                    data: workflow.data
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false
            }
        });

        monthlyChart = new Chart(monthlyCanvas, {
            type: 'bar',
            data: {
                labels: daily.labels.map(formatDate),
                datasets: [{
                    label: 'Gatepasses',
                    data: daily.data
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false
            }
        });

        weeklyChart = new Chart(weeklyCanvas, {
            type: 'line',
            data: {
                labels: weekly.labels.map(formatDate),
                datasets: [{
                    label: 'Visits',
                    data: weekly.data,
                    fill: false,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false
            }
        });

        // Exposed on window so collapsible section/card toggles
        // (see index.php) can call .resize() on the relevant chart
        // when its container goes from display:none back to
        // visible — Chart.js computes canvas size at render time,
        // and gets it wrong (usually 0-height) if that happens while
        // hidden, or just never corrects itself afterward without an
        // explicit resize() call once space is actually available.
        window.gpDashboardCharts = {
            workflowChart: workflowChart,
            monthlyChart: monthlyChart,
            weeklyChart: weeklyChart
        };
    }

    async function loadCharts(days = 30) {

        try {

            const response = await fetch('/dashboard/charts?days=' + days, {
                headers: { 'Accept': 'application/json' }
            });

            if (!response.ok) {
                throw new Error('Failed to load chart data');
            }

            const data = await response.json();

            renderCharts(data);

        } catch (error) {

            console.error('Chart loading error:', error);

        }
    }

    const chartRange = document.getElementById('chartRange');

    if (chartRange) {

        chartRange.addEventListener('change', function () {

            loadCharts(this.value);

        });

    }

    loadCharts(30);

});