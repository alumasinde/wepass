<div class="page-header">
    <h1 class="page-heading">
        <i class="fa-solid fa-circle-check"></i> Approve Gatepass
    </h1>
    <div class="page-actions">
        <a href="/approvals" class="btn btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> Back
        </a>
    </div>
</div>

<div class="form-card">
    <div class="form-card__body">
        <p>
            You're approving gatepass
            <strong><?= htmlspecialchars($approval['gatepass_number'] ?? ('#' . $approval['gatepass_id'])) ?></strong>
            at step "<?= htmlspecialchars($approval['step_name'] ?? '') ?>".
        </p>

        <form method="POST" action="/approvals/<?= (int) $approval['id'] ?>/approve">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '', ENT_QUOTES, 'UTF-8') ?>">

            <div class="form-group">
                <label class="form-label">Comment (optional)</label>
                <textarea name="comment" class="form-control" rows="3"></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-check"></i> Confirm Approval
                </button>
                <a href="/approvals" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
