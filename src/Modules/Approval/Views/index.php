<div class="page-header">
    <h1 class="page-heading">
        <i class="fa-solid fa-circle-check"></i> Approvals
    </h1>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger">
        <i class="fa-solid fa-circle-xmark"></i>
        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<?php if (!empty($stalled)): ?>
    <div class="alert alert-warning">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <strong><?= count($stalled) ?> workflow(s) stalled</strong> — waiting on a step with nobody assigned to approve it:
        <ul style="margin:8px 0 0 20px;">
            <?php foreach ($stalled as $s): ?>
                <li>
                    <a href="/gatepasses/<?= (int) $s['gatepass_id'] ?>" class="table-link">
                        <?= htmlspecialchars($s['gatepass_number']) ?>
                    </a>
                    is stuck at "<?= htmlspecialchars($s['step_name']) ?>" —
                    no active <?= htmlspecialchars($s['role_name'] ?? 'role') ?> assigned
                    in department <?= (int) ($s['step_department_id'] ?? $s['gatepass_department_id']) ?>.
                    <a href="/settings/users" class="table-link">Assign someone</a> to unblock it.
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="table-card">
    <table class="table">
        <thead>
            <tr>
                <th>Gatepass</th>
                <th>Requested By</th>
                <th>Step</th>
                <th>Status</th>
                <th>Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($approvals)): ?>
                <tr>
                    <td colspan="6" class="text-center text-muted">No pending approvals.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($approvals as $a): $approvalId = (int) ($a['id'] ?? 0); ?>
                    <tr data-approval-row="<?= $approvalId ?>">
                        <td>
                            <a href="/gatepasses/<?= (int) ($a['gatepass_id'] ?? 0) ?>" class="table-link">
                                <?= htmlspecialchars($a['gatepass_number'] ?? '—') ?>
                            </a>
                        </td>
                        <td><?= htmlspecialchars($a['requester_name'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($a['step_name'] ?? '—') ?></td>
                        <td data-status-cell>
                            <?php $st = strtolower($a['status'] ?? ''); ?>
                            <span class="badge badge-<?= $st === 'pending' ? 'warning' : ($st === 'approved' ? 'success' : 'danger') ?>">
                                <?= htmlspecialchars(ucfirst($a['status'] ?? '—')) ?>
                            </span>
                        </td>
                        <td><?= !empty($a['created_at']) ? date('d M Y', strtotime($a['created_at'])) : '—' ?></td>
                        <td class="table-actions">
                            <a href="/approvals/<?= $approvalId ?>" class="btn btn-sm view-btn">
                                <i class="fa-solid fa-eye"></i> Review
                            </a>

                            <form method="POST" action="/approvals/<?= $approvalId ?>/approve" class="gp-approve-form" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                <button type="submit" class="btn btn-sm btn-success">
                                    <i class="fa-solid fa-check"></i> Approve
                                </button>
                            </form>

                            <button type="button" class="btn btn-sm btn-danger gp-reject-toggle" data-approval-id="<?= $approvalId ?>">
                                <i class="fa-solid fa-xmark"></i> Reject
                            </button>
                        </td>
                    </tr>
                    <tr class="gp-reject-panel" data-reject-panel="<?= $approvalId ?>" style="display:none;">
                        <td colspan="6" style="background:var(--color-surface-subtle);">
                            <form method="POST" action="/approvals/<?= $approvalId ?>/reject" class="gp-reject-form" style="display:flex;gap:8px;align-items:flex-start;padding:8px 0;">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                <textarea name="comment" class="form-control" rows="2" placeholder="Reason for rejecting (required)" style="flex:1;" required></textarea>
                                <button type="submit" class="btn btn-sm btn-danger">Confirm Reject</button>
                                <button type="button" class="btn btn-sm btn-secondary gp-reject-cancel" data-approval-id="<?= $approvalId ?>">Cancel</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script nonce="<?= csp_nonce() ?>">
/**
 * Same pattern as the Gatepasses list (AJAX check-in/check-out) —
 * condenses what used to be a 3-page journey (list -> review ->
 * confirmation page -> submit) into one click for Approve, or one
 * inline reason field for Reject, without leaving this page.
 * "Review" still links to the full detail page for anyone who wants
 * to see everything before deciding; the confirmation pages this
 * bypasses still work exactly as before for anyone who lands there
 * directly (e.g. a notification email link).
 */
function gpShowToast(type, message) {
    const wrapper = document.querySelector('.content-wrapper') || document.body;
    const alert = document.createElement('div');
    alert.className = 'alert alert-' + type + ' auto-dismiss';
    alert.textContent = message;
    wrapper.insertBefore(alert, wrapper.firstChild);

    setTimeout(function () {
        alert.style.transition = 'opacity 0.4s';
        alert.style.opacity = '0';
        setTimeout(function () { alert.remove(); }, 400);
    }, 4500);
}

function gpRemoveApprovalRow(approvalId) {
    const row = document.querySelector('tr[data-approval-row="' + approvalId + '"]');
    const panel = document.querySelector('tr[data-reject-panel="' + approvalId + '"]');
    [row, panel].forEach(function (el) {
        if (!el) return;
        el.style.transition = 'opacity 0.3s';
        el.style.opacity = '0';
        setTimeout(function () { el.remove(); }, 300);
    });

    // If that was the last one, show the empty state rather than
    // leaving a table with only a header.
    setTimeout(function () {
        const remaining = document.querySelectorAll('tr[data-approval-row]').length;
        const tbody = document.querySelector('.table-card tbody');
        if (remaining === 0 && tbody && !tbody.querySelector('.gp-empty-row')) {
            const emptyRow = document.createElement('tr');
            emptyRow.className = 'gp-empty-row';
            emptyRow.innerHTML = '<td colspan="6" class="text-center text-muted">No pending approvals.</td>';
            tbody.appendChild(emptyRow);
        }
    }, 320);
}

// Reject panel toggling
document.querySelectorAll('.gp-reject-toggle').forEach(function (btn) {
    btn.addEventListener('click', function () {
        const id = btn.dataset.approvalId;
        const panel = document.querySelector('tr[data-reject-panel="' + id + '"]');
        if (panel) {
            panel.style.display = panel.style.display === 'none' ? 'table-row' : 'none';
            const textarea = panel.querySelector('textarea');
            if (textarea && panel.style.display !== 'none') textarea.focus();
        }
    });
});
document.querySelectorAll('.gp-reject-cancel').forEach(function (btn) {
    btn.addEventListener('click', function () {
        const panel = document.querySelector('tr[data-reject-panel="' + btn.dataset.approvalId + '"]');
        if (panel) panel.style.display = 'none';
    });
});

// Approve — one click, no confirmation step needed
document.querySelectorAll('.gp-approve-form').forEach(function (form) {
    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        const row = form.closest('tr[data-approval-row]');
        const approvalId = row ? row.dataset.approvalRow : null;
        const btn = form.querySelector('button[type="submit"]');
        if (btn) { btn.disabled = true; btn.style.opacity = '0.6'; }

        try {
            const csrfToken = form.querySelector('input[name="csrf_token"]').value;
            const response = await fetch(form.action, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
                body: 'csrf_token=' + encodeURIComponent(csrfToken)
            });
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || 'Approval failed.');

            gpShowToast('success', data.message || 'Approved.');
            if (approvalId) gpRemoveApprovalRow(approvalId);

        } catch (error) {
            gpShowToast('danger', error.message);
            if (btn) { btn.disabled = false; btn.style.opacity = '1'; }
        }
    });
});

// Reject — the reason field is IN the form, errors show inline there
// too (the user is actively engaged with this specific row, a toast
// alone would be easy to miss)
document.querySelectorAll('.gp-reject-form').forEach(function (form) {
    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        const row = form.closest('tr[data-reject-panel]');
        const approvalId = row ? row.dataset.rejectPanel : null;
        const btn = form.querySelector('button[type="submit"]');
        const textarea = form.querySelector('textarea');

        let existingError = form.querySelector('.gp-reject-error');
        if (existingError) existingError.remove();

        if (btn) { btn.disabled = true; btn.style.opacity = '0.6'; }

        try {
            const csrfToken = form.querySelector('input[name="csrf_token"]').value;
            const response = await fetch(form.action, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
                body: 'csrf_token=' + encodeURIComponent(csrfToken) + '&comment=' + encodeURIComponent(textarea.value)
            });
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || 'Rejection failed.');

            gpShowToast('success', data.message || 'Rejected.');
            if (approvalId) gpRemoveApprovalRow(approvalId);

        } catch (error) {
            const errorEl = document.createElement('div');
            errorEl.className = 'text-danger gp-reject-error';
            errorEl.style.cssText = 'font-size:0.8rem;width:100%;margin-top:4px;';
            errorEl.textContent = error.message;
            form.appendChild(errorEl);

            if (btn) { btn.disabled = false; btn.style.opacity = '1'; }
        }
    });
});
</script>

