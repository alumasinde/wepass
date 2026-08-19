<?php
use App\Modules\Dashboard\Services\DashboardService;
$dashboardService = new DashboardService();
?>

<div class="chart-filters">
    <select id="chartRange">
        <option value="30">Last 30 Days</option>
        <option value="90">Last 90 Days</option>
    </select>
</div>

<div class="charts-grid">

    <div class="gp-chart-card" data-collapse-key="dash-chart-workflow">
        <div class="gp-chart-card__header">
            <h3>Workflow Status</h3>
            <button type="button" class="gp-dash-toggle" aria-label="Collapse chart">
                <i class="fa-solid fa-chevron-up"></i>
            </button>
        </div>
        <div class="gp-chart-card__body">
            <div class="chart-container">
                <canvas id="workflowChart"></canvas>
            </div>
        </div>
    </div>

    <div class="gp-chart-card" data-collapse-key="dash-chart-gatepasses">
        <div class="gp-chart-card__header">
            <h3>Gatepasses</h3>
            <button type="button" class="gp-dash-toggle" aria-label="Collapse chart">
                <i class="fa-solid fa-chevron-up"></i>
            </button>
        </div>
        <div class="gp-chart-card__body">
            <div class="chart-container">
                <canvas id="monthlyChart"></canvas>
            </div>
        </div>
    </div>

    <div class="gp-chart-card" data-collapse-key="dash-chart-visits">
        <div class="gp-chart-card__header">
            <h3>Visits</h3>
            <button type="button" class="gp-dash-toggle" aria-label="Collapse chart">
                <i class="fa-solid fa-chevron-up"></i>
            </button>
        </div>
        <div class="gp-chart-card__body">
            <div class="chart-container">
                <canvas id="weeklyChart"></canvas>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="/assets/js/dashboard-charts.js"></script>
