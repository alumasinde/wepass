<div class="page-header">
    <h1 class="page-heading">
        <i class="fa-solid fa-circle-xmark"></i> Reject Gatepass
    </h1>
    <div class="page-actions">
        <a href="/approvals" class="btn btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> Back
        </a>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger">
        <i class="fa-solid fa-circle-xmark"></i>
        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<div class="form-card">
    <div class="form-card__body">
        <p>
            You're rejecting gatepass
            <strong><?= htmlspecialchars($approval['gatepass_number'] ?? ('#' . $approval['gatepass_id'])) ?></strong>
            at step "<?= htmlspecialchars($approval['step_name'] ?? '') ?>".
        </p>

        <form method="POST" action="/approvals/<?= (int) $approval['id'] ?>/reject">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '', ENT_QUOTES, 'UTF-8') ?>">

            <div class="form-group">
                <label class="form-label">Reason for Rejection <span class="required">*</span></label>
                <textarea name="comment" class="form-control" rows="3" required></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-danger">
                    <i class="fa-solid fa-xmark"></i> Confirm Rejection
                </button>
                <a href="/approvals" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
