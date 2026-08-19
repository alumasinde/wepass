<h2><?= $title ?></h2>

<?php include __DIR__ . '/partials/filters.php'; ?>

<?php if (!empty($data['data'])): ?>

    <div class="report-card">
<table class="report-table">
    <thead class="report-header">
        <tr>
            <?php include __DIR__ . '/partials/table-header.php'; ?>

            <?php sortLink('g.gatepass_number', 'Number'); ?>
            <?php sortLink('g.created_at', 'Created'); ?>
            <?php sortLink('gs.name', 'Status'); ?>
            <?php sortLink('u.first_name', 'Created By'); ?>

            <th>Purpose</th>
        </tr>
    </thead>

    <tbody>
        <?php foreach ($data['data'] as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['gatepass_number']) ?></td>
                <td><?= htmlspecialchars($row['created_at']) ?></td>
                <td><?= htmlspecialchars($row['status_name']) ?></td>
                <td>
                    <?= htmlspecialchars(
                        trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))
                    ) ?>
                </td>
                <td><?= htmlspecialchars($row['purpose']) ?></td>
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