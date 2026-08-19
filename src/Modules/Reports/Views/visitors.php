<h2><?= $title ?></h2>

<?php include __DIR__ . '/partials/filters.php'; ?>

<?php if (!empty($data['data'])): ?>
    <div class="report-card">

<table class="report-table">
    <thead class="report-header">
        <tr>
            <?php include __DIR__ . '/partials/table-header.php'; ?>
            <?php sortLink('first_name', 'First Name'); ?>
            <?php sortLink('created_at', 'Registered'); ?>
            <th>Phone</th>
            <th>Email</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($data['data'] as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['first_name']) ?></td>
                <td><?= htmlspecialchars($row['created_at']) ?></td>
                <td><?= htmlspecialchars($row['phone']) ?></td>
                <td><?= htmlspecialchars($row['email']) ?></td>
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