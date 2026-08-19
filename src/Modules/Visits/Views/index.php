<div class="page-header">
    <h1 class="page-heading">
        <i class="fas fa-user-friends"></i> Visits
    </h1>
</div>

<?php if (!empty($_SESSION['flash'])): ?>
    <div class="alert alert-<?= htmlspecialchars($_SESSION['flash']['type']) ?>">
        <?= htmlspecialchars($_SESSION['flash']['message']) ?>
    </div>
    <?php unset($_SESSION['flash']); ?>
<?php endif; ?>

<?php
// Include search if available
$searchFile = base_path('resources/views/components/global-search.php');
if (file_exists($searchFile)) {
    $action = '/visits';
    include $searchFile;
}
?>

<div class="table-card">

<table class="table">
<thead>
<tr>
    <th>Visitor</th>
    <th>Company</th>
    <th>Department</th>
    <th>Purpose</th>
    <th>Check-In</th>
    <th>Check-Out</th>
    <th>Badge</th>
    <th width="320">Actions</th>
</tr>
</thead>

<tbody>

<?php if (!empty($visits)): ?>
<?php foreach ($visits as $visit): ?>

<tr>

<td><?= htmlspecialchars($visit['visitor_name']) ?></td>

<td><?= htmlspecialchars($visit['visitor_company'] ?? '-') ?></td>

<td><?= htmlspecialchars($visit['department_name'] ?? '-') ?></td>

<td>
    <?= htmlspecialchars($visit['purpose'] ?? '-') ?>
    <?php if (!empty($visit['contract_reference'])): ?>
        <br><small class="text-muted"><?= htmlspecialchars($visit['contract_reference']) ?></small>
    <?php endif; ?>
    <?php if (!empty($visit['escort_required'])): ?>
        <span class="badge badge-warning" title="Must be escorted while on-site">
            <i class="fa-solid fa-user-shield"></i> Escort
        </span>
    <?php endif; ?>
</td>

<td>
<?= $visit['checkin_time']
    ? htmlspecialchars($visit['checkin_time'])
    : '<span class="text-muted">Not Checked In</span>' ?>
</td>

<td>
<?= $visit['checkout_time']
    ? htmlspecialchars($visit['checkout_time'])
    : '<span class="text-muted">Not Checked Out</span>' ?>
</td>

<td>

<?php if (!empty($visit['badge_code'])): ?>

    <?php if (!empty($visit['badge_returned_at'])): ?>

        <span class="badge bg-success">
            <?= htmlspecialchars($visit['badge_code']) ?>
        </span>
        <small class="text-muted d-block">Returned</small>

    <?php elseif (!empty($visit['is_active'])): ?>

        <span class="badge bg-danger">
            <?= htmlspecialchars($visit['badge_code']) ?>
        </span>
        <small class="text-danger d-block">Active</small>

    <?php else: ?>

        <span class="badge bg-secondary">
            <?= htmlspecialchars($visit['badge_code']) ?>
        </span>

    <?php endif; ?>

<?php else: ?>

    <span class="text-muted">No Badge</span>

<?php endif; ?>

</td>


<td class="table-actions">

<!-- CHECK IN -->
<?php if (!$visit['checkin_time'] && !$visit['checkout_time']): ?>
<form method="POST" action="/visits/<?= $visit['id'] ?>/checkin">
    <?= csrf_field() ?>
<button class="btn btn-sm btn-primary">Check In</button>
</form>
<?php endif; ?>


<!-- ISSUE BADGE -->
<?php if ($visit['checkin_time'] && !$visit['checkout_time'] && empty($visit['badge_code'])): ?>
<form method="POST" action="/badges/<?= $visit['id'] ?>/issue">
    <?= csrf_field() ?>
<button class="btn btn-sm btn-secondary">Issue Badge</button>
</form>
<?php endif; ?>


<!-- RETURN BADGE -->
<?php if (!empty($visit['badge_code']) && empty($visit['badge_returned_at'])): ?>
<form method="POST" action="/badges/<?= $visit['id'] ?>/return">
    <?= csrf_field() ?>
<button class="btn btn-sm btn-warning">Return Badge</button>
</form>
<?php endif; ?>


<!-- CHECK OUT -->
<?php if ($visit['checkin_time'] && !$visit['checkout_time']): ?>

<?php
$badgeNotReturned =
    !empty($visit['badge_code']) &&
    empty($visit['badge_returned_at']);
?>

<form method="POST" action="/visits/<?= $visit['id'] ?>/checkout">
    <?= csrf_field() ?>

<button
    class="btn btn-sm btn-danger"
    <?= $badgeNotReturned ? 'disabled' : '' ?>
>
Check Out
</button>

</form>

<?php if ($badgeNotReturned): ?>
<small class="text-muted d-block mt-1">
Return badge before checkout.
</small>
<?php endif; ?>

<?php endif; ?>

</td>

</tr>

<?php endforeach; ?>

<?php else: ?>

<tr>
<td colspan="8" class="text-center text-muted">
No visits found.
</td>
</tr>

<?php endif; ?>

</tbody>
</table>

</div>