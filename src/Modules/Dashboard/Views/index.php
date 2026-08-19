<?php
// Safe defaults to avoid undefined index errors
$userName = htmlspecialchars($_SESSION['user']['first_name'] ?? '');

$totalGatepasses  = (int) ($stats['total_gatepasses']     ?? 0);
$pendingApprovals = (int) ($stats['my_pending_approvals'] ?? 0);
$checkedInToday   = (int) ($stats['checked_in_today']     ?? 0);
$checkedOutToday  = (int) ($stats['checked_out_today']    ?? 0);
$activeVisitors   = (int) ($stats['active_visitors']      ?? 0);
$totalVisitors    = (int) ($stats['total_visitors']       ?? 0);
$stalledWorkflows = (int) ($stats['stalled_workflows']    ?? 0);

// Each dashboard section is gated by PERMISSION, not role name —
// role names are tenant-editable (Settings -> Roles), so checking
// against a specific string like "Security Manager" would silently
// break the moment a tenant renamed or restructured their roles.
// Permission keys (module.action) are the actual authorization
// primitive this app already uses everywhere else.
$showGateActivity = can('gatepasses.checkin') || can('gatepasses.checkout') || can('visits.checkin');
$showApprovals    = can('approval.approve');
$showOverview      = can('reports.view') || can('tenant.update') || can('settings.update');
$showStalledCard   = $showApprovals && can('settings.update');

$hasAnySection = $showGateActivity || $showApprovals || $showOverview;
?>

<div class="dashboard-container">

    <h1 class="dashboard-title">
        Welcome, <?= $userName ?>
    </h1>

    <?php if (!$hasAnySection): ?>
        <div class="alert alert-info">
            Nothing to show here yet — your role doesn't currently have visibility into any dashboard section.
            If that seems wrong, check with your administrator.
        </div>
    <?php endif; ?>

    <?php if ($showGateActivity): ?>
    <section class="gp-dash-section" data-collapse-key="dash-gate-activity">
        <div class="gp-dash-section__header">
            <h2><i class="fa-solid fa-door-open"></i> Gate Activity</h2>
            <button type="button" class="gp-dash-toggle" aria-label="Collapse section">
                <i class="fa-solid fa-chevron-up"></i>
            </button>
        </div>
        <div class="gp-dash-section__body">
            <div class="dashboard-grid">
                <div class="card card-success">
                    <h3>Checked In Today</h3>
                    <p class="card-value"><?= $checkedInToday ?></p>
                </div>
                <div class="card card-warning">
                    <h3>Checked Out Today</h3>
                    <p class="card-value"><?= $checkedOutToday ?></p>
                </div>
                <div class="card card-primary">
                    <h3>Active Visitors</h3>
                    <p class="card-value"><?= $activeVisitors ?></p>
                </div>
                <div class="card">
                    <h3>Total Visitors</h3>
                    <p class="card-value"><?= $totalVisitors ?></p>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($showApprovals): ?>
    <section class="gp-dash-section" data-collapse-key="dash-approvals">
        <div class="gp-dash-section__header">
            <h2><i class="fa-solid fa-circle-check"></i> My Approvals</h2>
            <button type="button" class="gp-dash-toggle" aria-label="Collapse section">
                <i class="fa-solid fa-chevron-up"></i>
            </button>
        </div>
        <div class="gp-dash-section__body">
            <div class="dashboard-grid">
                <div class="card <?= $pendingApprovals > 0 ? 'card-warning' : '' ?>">
                    <h3>My Pending Approvals</h3>
                    <p class="card-value"><?= $pendingApprovals ?></p>
                    <?php if ($pendingApprovals > 0): ?>
                        <a href="/approvals" class="table-link" style="font-size:0.8rem;">Review now &rarr;</a>
                    <?php endif; ?>
                </div>
                <?php if ($showStalledCard): ?>
                    <div class="card <?= $stalledWorkflows > 0 ? 'card-danger' : '' ?>">
                        <h3>Stalled Workflows</h3>
                        <p class="card-value"><?= $stalledWorkflows ?></p>
                        <?php if ($stalledWorkflows > 0): ?>
                            <a href="/approvals" class="table-link" style="font-size:0.8rem;">Needs attention &rarr;</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($showOverview): ?>
    <section class="gp-dash-section" data-collapse-key="dash-overview">
        <div class="gp-dash-section__header">
            <h2><i class="fa-solid fa-chart-line"></i> Organization Overview</h2>
            <button type="button" class="gp-dash-toggle" aria-label="Collapse section">
                <i class="fa-solid fa-chevron-up"></i>
            </button>
        </div>
        <div class="gp-dash-section__body">
            <div class="dashboard-grid">
                <div class="card">
                    <h3>Total Gatepasses</h3>
                    <p class="card-value"><?= $totalGatepasses ?></p>
                </div>
            </div>

            <?php require __DIR__ . '/charts.php'; ?>
        </div>
    </section>
    <?php endif; ?>

</div>

<script nonce="<?= csp_nonce() ?>">
/**
 * Collapsible dashboard sections and (inside charts.php) individual
 * chart cards — same mechanism for both, state remembered per
 * browser via localStorage so a collapsed section stays collapsed
 * next time this page loads, not just for the current visit.
 */
function gpInitCollapsible(el) {
    const key = el.dataset.collapseKey;
    const toggle = el.querySelector(':scope > .gp-dash-section__header .gp-dash-toggle, :scope > .gp-chart-card__header .gp-dash-toggle');
    const body = el.querySelector(':scope > .gp-dash-section__body, :scope > .gp-chart-card__body');
    if (!toggle || !body) return;

    function setCollapsed(collapsed) {
        body.style.display = collapsed ? 'none' : '';
        const icon = toggle.querySelector('i');
        if (icon) icon.className = collapsed ? 'fa-solid fa-chevron-down' : 'fa-solid fa-chevron-up';
        if (key) {
            try { localStorage.setItem(key, collapsed ? '1' : '0'); } catch (e) { /* storage unavailable — fine, just won't persist */ }
        }

        // Chart.js gets canvas sizing wrong if it rendered (or never
        // got a chance to re-measure) while its container was
        // display:none — resize() on un-collapse fixes that, for
        // every canvas this section/card happens to contain.
        if (!collapsed && window.gpDashboardCharts) {
            body.querySelectorAll('canvas').forEach(function (canvas) {
                const chart = window.gpDashboardCharts[canvas.id];
                if (chart && typeof chart.resize === 'function') {
                    chart.resize();
                }
            });
        }
    }

    let startCollapsed = false;
    if (key) {
        try { startCollapsed = localStorage.getItem(key) === '1'; } catch (e) { /* ignore */ }
    }
    setCollapsed(startCollapsed);

    toggle.addEventListener('click', function () {
        setCollapsed(body.style.display !== 'none');
    });
}

document.querySelectorAll('.gp-dash-section, .gp-chart-card').forEach(gpInitCollapsible);
</script>
