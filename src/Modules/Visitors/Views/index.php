<div class="page-header">
    <h1 class="page-heading">
        <i class="fa-solid fa-users"></i> Visitors
    </h1>
    <div class="page-actions">
        <a href="/visitors/create" class="btn btn-primary">
            <i class="fa-solid fa-plus"></i> New Visitor
        </a>
    </div>
</div>

<?php
$searchFile = base_path('resources/views/components/global-search.php');
if (file_exists($searchFile)) {
    $action = '/visitors';
    include $searchFile;
}
?>

<div class="table-card">
    <table class="table">
        <thead>
            <tr>
                <th>Name</th>
                <th>ID Type</th>
                <th>ID Number</th>
                <th>Company</th>
                <th>Risk</th>
                <th>Status</th>
                <th>Visits</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($visitors)): ?>
                <tr>
                    <td colspan="8" class="text-center text-muted">No visitors found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($visitors as $v): ?>
                    <tr>
                        <td>
                            <a href="/visitors/<?= (int) $v['id'] ?>" class="table-link">
                                <?= htmlspecialchars($v['first_name'] . ' ' . $v['last_name']) ?>
                            </a>
                        </td>
                        <td><?= htmlspecialchars($v['id_type_name'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($v['id_number'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($v['company_name'] ?? '—') ?></td>
                        <td>
                            <?php
                                $risk      = (int) ($v['risk_score'] ?? 0);
                                $riskClass = $risk >= 70 ? 'danger' : ($risk >= 40 ? 'warning' : 'success');
                            ?>
                            <span class="badge badge-<?= $riskClass ?>"><?= $risk ?></span>
                        </td>
                        <td>
                            <?php $bl = (int) ($v['is_blacklisted'] ?? 0); ?>
                            <span class="badge badge-<?= $bl ? 'danger' : 'success' ?>">
                                <?= $bl ? 'Blacklisted' : 'Clear' ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge badge-info"><?= (int) ($v['total_visits'] ?? 0) ?></span>
                        </td>
                        <td class="table-actions">
                            <a href="/visitors/<?= (int) $v['id'] ?>" class="btn btn-sm view-btn">
                                <i class="fa-solid fa-eye"></i>
                            </a>
                            <a href="/visitors/<?= (int) $v['id'] ?>/edit" class="btn btn-sm edit-btn">
                                <i class="fa-solid fa-pen"></i>
                            </a>
                            <a href="/visits/create?visitor_id=<?= (int) $v['id'] ?>" class="btn btn-sm btn-primary">
                                <i class="fa-solid fa-plus"></i> Visit
                            </a>
                            <?php if ($bl): ?>
                                <form method="POST" action="/visitors/<?= (int) $v['id'] ?>/unblacklist" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                    <button type="submit" class="btn btn-sm btn-success">Unblacklist</button>
                                </form>
                            <?php else: ?>
                                <form method="POST" action="/visitors/<?= (int) $v['id'] ?>/blacklist" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                    <button type="submit" class="btn btn-sm btn-warning">Blacklist</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
