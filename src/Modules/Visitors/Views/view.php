<div class="page-header">
    <h1 class="page-heading">
        <i class="fa-solid fa-user"></i>
        <?= htmlspecialchars($visitor['first_name'] . ' ' . $visitor['last_name']) ?>
    </h1>
    <div class="page-actions">
        <a href="/visitors" class="btn btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> Back
        </a>
        <a href="/visitors/<?= (int) $visitor['id'] ?>/edit" class="btn btn-warning">
            <i class="fa-solid fa-pen"></i> Edit
        </a>
        <a href="/visits/create?visitor_id=<?= (int) $visitor['id'] ?>" class="btn btn-primary">
            <i class="fa-solid fa-plus"></i> New Visit
        </a>
    </div>
</div>

<div class="show-grid">
    <div class="detail-card">
        <div class="detail-card__header">Visitor Details</div>
        <div class="detail-card__body">
            <div class="detail-row">
                <span class="detail-label">ID Type</span>
                <span class="detail-value"><?= htmlspecialchars($visitor['id_type_name'] ?? '—') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">ID Number</span>
                <span class="detail-value"><?= htmlspecialchars($visitor['id_number'] ?? '—') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Phone</span>
                <span class="detail-value"><?= htmlspecialchars($visitor['phone'] ?? '—') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Email</span>
                <span class="detail-value"><?= htmlspecialchars($visitor['email'] ?? '—') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Company</span>
                <span class="detail-value"><?= htmlspecialchars($visitor['company_name'] ?? '—') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Risk Score</span>
                <span class="detail-value">
                    <?php $risk = (int) $visitor['risk_score']; $rc = $risk >= 70 ? 'danger' : ($risk >= 40 ? 'warning' : 'success'); ?>
                    <span class="badge badge-<?= $rc ?>"><?= $risk ?></span>
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Status</span>
                <span class="detail-value">
                    <?php $bl = (int) ($visitor['is_blacklisted'] ?? 0); ?>
                    <span class="badge badge-<?= $bl ? 'danger' : 'success' ?>">
                        <?= $bl ? 'Blacklisted' : 'Clear' ?>
                    </span>
                </span>
            </div>
        </div>
    </div>
</div>

<div class="table-card" style="margin-top: var(--space-6);">
    <div class="table-card__header">
        <h3><i class="fa-solid fa-clock-rotate-left"></i> Visit History</h3>
    </div>
    <table class="table">
        <thead>
            <tr>
                <th>#</th>
                <th>Department</th>
                <th>Status</th>
                <th>Check In</th>
                <th>Check Out</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($visitor['visits'])): ?>
                <tr>
                    <td colspan="5" class="text-center text-muted">No visits recorded.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($visitor['visits'] as $visit): ?>
                    <tr>
                        <td>#<?= (int) $visit['id'] ?></td>
                        <td><?= htmlspecialchars($visit['department_name'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($visit['status_name'] ?? '—') ?></td>
                        <td><?= $visit['checkin_time'] ?? '—' ?></td>
                        <td><?= $visit['checkout_time'] ?? '—' ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
