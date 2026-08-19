<?php
/** @var array $scans */
/** @var array $filters */
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Gate Scan History</h1>
            <p class="text-muted mb-0">Security activity from approved gate devices.</p>
        </div>
    </div>

    <form method="get" class="card card-body mb-4">
        <div class="row g-3">
            <div class="col-md-2"><label class="form-label">Gate ID</label><input class="form-control" name="gate_id" value="<?= htmlspecialchars((string)($filters['gate_id'] ?? '')) ?>"></div>
            <div class="col-md-2"><label class="form-label">Device ID</label><input class="form-control" name="device_id" value="<?= htmlspecialchars((string)($filters['device_id'] ?? '')) ?>"></div>
            <div class="col-md-2"><label class="form-label">Guard ID</label><input class="form-control" name="guard_user_id" value="<?= htmlspecialchars((string)($filters['guard_user_id'] ?? '')) ?>"></div>
            <div class="col-md-2"><label class="form-label">Result</label><select class="form-select" name="result"><option value="">All</option><?php foreach (['allowed','denied','error','processing'] as $v): ?><option value="<?= $v ?>" <?= ($filters['result'] ?? '') === $v ? 'selected' : '' ?>><?= ucfirst($v) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2"><label class="form-label">Type</label><select class="form-select" name="scan_type"><option value="">All</option><?php foreach (['checkin','checkout','validation','denied'] as $v): ?><option value="<?= $v ?>" <?= ($filters['scan_type'] ?? '') === $v ? 'selected' : '' ?>><?= ucfirst($v) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-1"><label class="form-label">From</label><input type="date" class="form-control" name="from" value="<?= htmlspecialchars((string)($filters['from'] ?? '')) ?>"></div>
            <div class="col-md-1"><label class="form-label">To</label><input type="date" class="form-control" name="to" value="<?= htmlspecialchars((string)($filters['to'] ?? '')) ?>"></div>
            <div class="col-12"><button class="btn btn-primary">Filter</button> <a class="btn btn-outline-secondary" href="/gatepasses/scan-history">Reset</a></div>
        </div>
    </form>

    <div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Time</th><th>Gate</th><th>Device</th><th>Guard</th><th>Gatepass</th><th>Type</th><th>Result</th><th>Reason</th><th>Request</th></tr></thead>
        <tbody>
        <?php foreach ($scans as $scan): ?>
            <tr>
                <td><?= htmlspecialchars((string)$scan['scanned_at']) ?></td>
                <td><?= htmlspecialchars((string)$scan['gate_name']) ?></td>
                <td><?= htmlspecialchars((string)$scan['device_name']) ?></td>
                <td><?= htmlspecialchars((string)($scan['username'] ?? '—')) ?></td>
                <td><?= htmlspecialchars((string)($scan['gatepass_number'] ?? '—')) ?></td>
                <td><?= htmlspecialchars((string)$scan['scan_type']) ?></td>
                <td><span class="badge text-bg-<?= $scan['result'] === 'allowed' ? 'success' : ($scan['result'] === 'processing' ? 'warning' : 'danger') ?>"><?= htmlspecialchars((string)$scan['result']) ?></span></td>
                <td><?= htmlspecialchars((string)($scan['reason_code'] ?? '—')) ?></td>
                <td><code><?= htmlspecialchars((string)$scan['request_id']) ?></code></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$scans): ?><tr><td colspan="9" class="text-center text-muted py-4">No scan events found.</td></tr><?php endif; ?>
        </tbody>
    </table></div></div>
</div>
