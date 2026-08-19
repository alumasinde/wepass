<h2><?= $title ?></h2>

<?php include __DIR__ . '/partials/filters.php'; ?>

<?php if (!empty($data['data'])): ?>
<div class="report-card">
<table class="report-table">
    <thead class="report-header">
        <tr>
            <?php include __DIR__ . '/partials/table-header.php'; ?>
            <?php sortLink('created_at', 'Date'); ?>
            <th>Entity</th>
            <th>Message</th>
            <th>User</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($data['data'] as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['created_at']) ?></td>
                <td><?= htmlspecialchars($row['entity_type']) ?></td>
                <td><?= htmlspecialchars($row['message']) ?></td>
                <td><?= htmlspecialchars($row['user_name']) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>
<a href="/reports" class="btn btn-secondary">
            Back
        </a>

<?php include __DIR__ . '/partials/pagination.php'; ?>

<?php else: ?>
    <?php include __DIR__ . '/partials/empty.php'; ?>
<?php endif; ?>