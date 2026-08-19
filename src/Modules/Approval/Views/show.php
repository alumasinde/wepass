<?php /** @var array $approval */
$statusBadge = match ($approval['workflow_status'] ?? '') {
    'in_progress' => 'warning',
    'approved'    => 'success',
    'rejected'    => 'danger',
    default       => 'secondary',
};
?>

<div class="page-header">
    <h1 class="page-heading">
        <i class="fa-solid fa-circle-check"></i> Approval Details
    </h1>
    <div class="page-actions">
        <a href="/approvals" class="btn btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> Back
        </a>
    </div>
</div>

<div class="approval-card">
    <div class="approval-header" style="display:flex;justify-content:space-between;align-items:center;">
        <strong>Gatepass <?= htmlspecialchars($approval['gatepass_number'] ?? ('#' . $approval['gatepass_id'])) ?></strong>
        <span class="badge badge-<?= $statusBadge ?>">
            <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $approval['workflow_status'] ?? '—'))) ?>
        </span>
    </div>

    <div class="approval-body">
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Current Step</label>
                <div><?= htmlspecialchars($approval['step_name'] ?? '—') ?></div>
            </div>
            <div class="form-group">
                <label class="form-label">Requested By</label>
                <div><?= htmlspecialchars($approval['requested_by_name'] ?? '—') ?></div>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Created</label>
            <div><?= format_date($approval['created_at'] ?? null) ?></div>
        </div>

        <div class="form-group">
            <label class="form-label">Purpose</label>
            <div><?= nl2br(htmlspecialchars($approval['purpose'] ?? '—')) ?></div>
        </div>

        <div class="form-actions">
            <?php if (($approval['workflow_status'] ?? '') === 'in_progress'): ?>
                <a href="/approvals/<?= (int) $approval['id'] ?>/approve" class="btn btn-primary">
                    <i class="fa-solid fa-check"></i> Approve
                </a>
                <a href="/approvals/<?= (int) $approval['id'] ?>/reject" class="btn btn-danger">
                    <i class="fa-solid fa-xmark"></i> Reject
                </a>
            <?php else: ?>
                <div class="alert alert-info">This approval has already been processed.</div>
            <?php endif; ?>

            <a href="/approvals" class="btn btn-secondary">Back</a>
        </div>
    </div>
</div>
