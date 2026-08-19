<h2><?= htmlspecialchars($title ?? 'Visits') ?></h2>

<?php include __DIR__ . '/partials/filters.php'; ?>

<?php if (!empty($data['data'])): ?>
    <div class="report-card">

        <table class="report-table">
            <thead class="report-header">
                <tr>
                    <?php include __DIR__ . '/partials/table-header.php'; ?>

                    <?php sortLink('visitor_name', 'Visitor'); ?>
                    <?php sortLink('host_name', 'Host'); ?>
                    <?php sortLink('department_name', 'Department'); ?>
                    <?php sortLink('status_name', 'Status'); ?>
                    <?php sortLink('visit_type_name', 'Type'); ?>
                    <?php sortLink('vs.checkin_time', 'Check In'); ?>
                    <?php sortLink('vs.created_at', 'Created'); ?>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($data['data'] as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['visitor_name'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['host_name'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['department_name'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['status_name'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['visit_type_name'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['checkin_time'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['created_at'] ?? '-') ?></td>
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