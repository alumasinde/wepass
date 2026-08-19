<?php
$gatepass = $gatepass ?? [];
$items     = $items ?? [];
?>
<div class="page-header">
    <h1 class="page-heading">
        <i class="fa-solid fa-file-signature"></i>
        Gatepass #<?= htmlspecialchars($gatepass['gatepass_number']) ?>
    </h1>
    <div class="page-actions">
        <a href="/gatepasses" class="btn btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> Back
        </a>
        <a href="/gatepasses/<?= (int) $gatepass['id'] ?>/edit" class="btn btn-warning">
            <i class="fa-solid fa-pen"></i> Edit
        </a>
        <form method="POST" action="/gatepasses/<?= (int) $gatepass['id'] ?>/delete"
              style="display:inline;"
              data-confirm="Delete this gatepass?">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
            <button type="submit" class="btn btn-danger">
                <i class="fa-solid fa-trash"></i> Delete
            </button>
        </form>
    </div>
</div>

<!-- Status bar -->
<div class="show-status-bar">
    <?php $sc = strtolower($gatepass['status_code'] ?? ''); ?>
    <span class="badge badge-<?= htmlspecialchars($sc) ?> badge-lg">
        <?= htmlspecialchars($gatepass['status_name'] ?? 'Unknown') ?>
    </span>
    <?php if (!empty($actions['can_checkin'])): ?>
        <form method="POST" action="/gatepasses/<?= (int) $gatepass['id'] ?>/checkin" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
            <button type="submit" class="btn btn-success">
                <i class="fa-solid fa-arrow-right-to-bracket"></i> Check In
            </button>
        </form>
    <?php endif; ?>
    <?php if (!empty($actions['can_checkout'])): ?>
        <form method="POST" action="/gatepasses/<?= (int) $gatepass['id'] ?>/checkout" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
            <button type="submit" class="btn btn-accent">
                <i class="fa-solid fa-arrow-right-from-bracket"></i> Check Out
            </button>
        </form>
    <?php endif; ?>
</div>

<div class="show-grid">

    <!-- Details card -->
    <div class="detail-card">
        <div class="detail-card__header">Gatepass Details</div>
        <div class="detail-card__body">
            <div class="detail-row">
                <span class="detail-label">Type</span>
                <span class="detail-value"><?= htmlspecialchars($gatepass['gatepass_type_name'] ?? '—') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Purpose</span>
                <span class="detail-value"><?= nl2br(htmlspecialchars($gatepass['purpose'] ?? '')) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Created By</span>
                <span class="detail-value">
                    <?= htmlspecialchars(trim(($gatepass['created_by_first_name'] ?? '') . ' ' . ($gatepass['created_by_last_name'] ?? ''))) ?: '—' ?>
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Created At</span>
                <span class="detail-value">
                    <?= !empty($gatepass['created_at']) ? date('d M Y, H:i', strtotime($gatepass['created_at'])) : '—' ?>
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Returnable</span>
                <span class="detail-value">
                    <span class="badge badge-<?= $gatepass['is_returnable'] ? 'success' : 'secondary' ?>">
                        <?= $gatepass['is_returnable'] ? 'Yes' : 'No' ?>
                    </span>
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Needs Approval</span>
                <span class="detail-value">
                    <span class="badge badge-<?= $gatepass['needs_approval'] ? 'warning' : 'secondary' ?>">
                        <?= $gatepass['needs_approval'] ? 'Yes' : 'No' ?>
                    </span>
                </span>
            </div>
            <?php if (!empty($gatepass['expected_return_date'])): ?>
            <div class="detail-row">
                <span class="detail-label">Expected Return</span>
                <span class="detail-value"><?= date('d M Y', strtotime($gatepass['expected_return_date'])) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Movement card -->
    <div class="detail-card">
        <div class="detail-card__header">Movement Log</div>
        <div class="detail-card__body">
            <div class="detail-row">
                <span class="detail-label">Checked In</span>
                <span class="detail-value">
                    <?= !empty($gatepass['actual_in']) ? date('d M Y, H:i', strtotime($gatepass['actual_in'])) : '—' ?>
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Checked Out</span>
                <span class="detail-value">
                    <?= !empty($gatepass['actual_out']) ? date('d M Y, H:i', strtotime($gatepass['actual_out'])) : '—' ?>
                </span>
            </div>
        </div>
    </div>

</div>

<!-- Items table -->
<div class="table-card" style="margin-top: var(--space-6);">
    <div class="table-card__header">
        <h3><i class="fa-solid fa-box-open"></i> Items</h3>
    </div>
    <table class="table">
        <thead>
            <tr>
                <th>Item</th>
                <th>Description</th>
                <th>Qty</th>
                <th>Serial</th>
                <th>Returnable</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($items)): ?>
                <tr>
                    <td colspan="5" class="text-center text-muted">No items attached.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['item_name'] ?? '') ?></td>
                        <td><?= htmlspecialchars($item['description'] ?? '—') ?></td>
                        <td><?= (int) ($item['quantity'] ?? 0) ?></td>
                        <td><?= htmlspecialchars($item['serial_number'] ?? '—') ?></td>
                        <td>
                            <span class="badge badge-<?= $item['is_returnable'] ? 'success' : 'secondary' ?>">
                                <?= $item['is_returnable'] ? 'Yes' : 'No' ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
