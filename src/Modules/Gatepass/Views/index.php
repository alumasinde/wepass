<div class="page-header">
    <h1 class="page-heading">
        <i class="fa-solid fa-file-signature"></i> Gatepasses
    </h1>
    <div class="page-actions">
        <a href="/gatepasses/create" class="btn btn-primary">
            <i class="fa-solid fa-plus"></i> New Gatepass
        </a>
    </div>
</div>

<?php
// Include search if available
$searchFile = base_path('resources/views/components/global-search.php');
if (file_exists($searchFile)) {
    $action = '/gatepasses';
    include $searchFile;
}
?>

<div class="table-card">
    <table class="table">
        <thead>
            <tr>
                <th>#</th>
                <th>Number</th>
                <th>Status</th>
                <th>Type</th>
                <th>Visitor</th>
                <th>Returnable</th>
                <th>Approval</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($gatepasses)): ?>
                <tr>
                    <td colspan="9" class="text-center text-muted">No gatepasses found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($gatepasses as $g): ?>
                    <tr data-gatepass-row="<?= (int) $g['id'] ?>">
                        <td><?= (int) $g['id'] ?></td>
                        <td>
                            <a href="/gatepasses/<?= (int) $g['id'] ?>" class="table-link">
                                <?= htmlspecialchars($g['gatepass_number']) ?>
                            </a>
                        </td>
                        <td data-status-cell>
                            <?php $sc = strtolower($g['status_code'] ?? ''); ?>
                            <span class="badge badge-<?= htmlspecialchars($sc) ?>">
                                <?= htmlspecialchars($g['status_name'] ?? '—') ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($g['gatepass_type_name'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($g['visitor_name'] ?? '—') ?></td>
                        <td>
                            <span class="badge badge-<?= $g['is_returnable'] ? 'success' : 'secondary' ?>">
                                <?= $g['is_returnable'] ? 'Yes' : 'No' ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge badge-<?= $g['needs_approval'] ? 'warning' : 'secondary' ?>">
                                <?= $g['needs_approval'] ? 'Yes' : 'No' ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars(date('d M Y', strtotime($g['created_at']))) ?></td>
                        <td class="table-actions" data-actions-cell>
                            <a href="/gatepasses/<?= (int) $g['id'] ?>" class="btn btn-sm view-btn">
                                <i class="fa-solid fa-eye"></i>
                            </a>
                            <a href="/gatepasses/<?= (int) $g['id'] ?>/edit" class="btn btn-sm edit-btn">
                                <i class="fa-solid fa-pen"></i>
                            </a>
                            <form method="POST" action="/gatepasses/<?= (int) $g['id'] ?>/checkin"
                                  class="gp-ajax-action gp-checkin-form" style="display:<?= !empty($g['can_checkin']) ? 'inline' : 'none' ?>;">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                <button type="submit" class="btn btn-sm checkin-btn">
                                    <i class="fa-solid fa-arrow-right-to-bracket"></i> In
                                </button>
                            </form>
                            <form method="POST" action="/gatepasses/<?= (int) $g['id'] ?>/checkout"
                                  class="gp-ajax-action gp-checkout-form" style="display:<?= !empty($g['can_checkout']) ? 'inline' : 'none' ?>;">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                <button type="submit" class="btn btn-sm checkout-btn">
                                    <i class="fa-solid fa-arrow-right-from-bracket"></i> Out
                                </button>
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
 * AJAX-enhanced check-in/check-out — the top UX gap flagged in the
 * last review: every action on this page was a full-page reload,
 * which is genuinely bad for a screen meant to be used quickly and
 * repeatedly (a guard processing many gatepasses in a row). This is
 * progressive enhancement, not a replacement — the underlying route
 * still works exactly the same for a plain form POST (JS disabled,
 * or any other caller); this just intercepts it when JS is available
 * and asks the server for JSON instead of a redirect.
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

document.querySelectorAll('.gp-ajax-action').forEach(function (form) {
    form.addEventListener('submit', async function (event) {
        event.preventDefault();

        const row = form.closest('tr[data-gatepass-row]');
        const btn = form.querySelector('button[type="submit"]');
        const csrfToken = form.querySelector('input[name="csrf_token"]').value;

        if (btn) {
            btn.disabled = true;
            btn.style.opacity = '0.6';
        }

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'Accept': 'application/json'
                },
                body: 'csrf_token=' + encodeURIComponent(csrfToken)
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Action failed.');
            }

            // Update the status badge in place
            if (row) {
                const statusCell = row.querySelector('[data-status-cell]');
                if (statusCell && data.status_code) {
                    statusCell.innerHTML = '<span class="badge badge-' + data.status_code + '">'
                        + (data.status_name || '') + '</span>';
                }

                const checkinForm  = row.querySelector('.gp-checkin-form');
                const checkoutForm = row.querySelector('.gp-checkout-form');
                if (checkinForm)  checkinForm.style.display  = data.can_checkin  ? 'inline' : 'none';
                if (checkoutForm) checkoutForm.style.display = data.can_checkout ? 'inline' : 'none';
            }

            gpShowToast('success', data.message);

        } catch (error) {
            gpShowToast('danger', error.message);
            if (btn) {
                btn.disabled = false;
                btn.style.opacity = '1';
            }
        }
    });
});
</script>

