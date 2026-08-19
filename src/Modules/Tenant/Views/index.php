<?php
$tenants = $tenants ?? [];
$snippet = $_SESSION['new_tenant_snippet'] ?? null;
unset($_SESSION['new_tenant_snippet']);
$baseDomain = trim((string) config('platform.base_domain', ''));
?>

<div class="page-header">
    <h1 class="page-heading">
        <i class="fa-solid fa-building-shield"></i> Tenants
    </h1>
    <div class="page-actions">
        <a href="/master/tenants/create" class="btn btn-primary">
            <i class="fa-solid fa-plus"></i> New Tenant
        </a>
    </div>
</div>

<?php if ($snippet): ?>
    <div class="alert alert-success">
        <i class="fa-solid fa-circle-check"></i>
        <?php if ($baseDomain !== ''): ?>
            Tenant created and already live — no deployment step needed in dynamic domain mode.
        <?php else: ?>
            Tenant created. Give this to whoever sets up that client's deployment —
            it's the <code>config.ini</code> content for their install (copy into a
            fresh <code>config/config.ini</code> alongside <code>[mysql]</code>/
            <code>[master_db]</code>/<code>[session]</code>/<code>[mail]</code>
            sections shared with your other installs).
        <?php endif; ?>
    </div>
    <div class="form-card">
        <div class="form-card__header"><?= $baseDomain !== '' ? 'Tenant is live at' : 'config.ini snippet' ?></div>
        <div class="form-card__body">
            <pre style="background:#0f1115;color:#c9d1d9;padding:16px;border-radius:8px;overflow-x:auto;font-size:13px;"><?= htmlspecialchars($snippet, ENT_QUOTES, 'UTF-8') ?></pre>
        </div>
    </div>
<?php endif; ?>

<div class="table-card">
    <div class="table-card__header">
        <h3>All Tenants</h3>
    </div>
    <table class="table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Code</th>
                <th><?= $baseDomain !== '' ? 'Live URL' : 'Database' ?></th>
                <th>Plan</th>
                <th>Status</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($tenants)): ?>
                <tr>
                    <td colspan="7" class="text-center text-muted">No tenants yet — create the first one.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($tenants as $tenant): ?>
                    <tr>
                        <td><?= htmlspecialchars($tenant['name']) ?></td>
                        <td><code><?= htmlspecialchars($tenant['code']) ?></code></td>
                        <td>
                            <?php if ($baseDomain !== ''): ?>
                                <?php $host = !empty($tenant['custom_domain']) ? $tenant['custom_domain'] : ($tenant['code'] . '.' . $baseDomain); ?>
                                <a href="https://<?= htmlspecialchars($host) ?>" target="_blank" rel="noopener" class="table-link">
                                    <?= htmlspecialchars($host) ?> <i class="fa-solid fa-arrow-up-right-from-square" style="font-size:11px;"></i>
                                </a>
                            <?php else: ?>
                                <code><?= htmlspecialchars($tenant['db_name']) ?></code>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge badge-secondary"><?= htmlspecialchars($tenant['plan']) ?></span></td>
                        <td>
                            <span class="badge badge-<?= $tenant['is_active'] ? 'success' : 'secondary' ?>">
                                <?= $tenant['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td><?= format_date($tenant['created_at'], 'd M Y') ?></td>
                        <td>
                            <a href="/master/tenants/<?= (int) $tenant['id'] ?>/connection" class="btn btn-sm btn-secondary">
                                <i class="fa-solid fa-server"></i> Connection
                                <?php if (!empty($tenant['connection_string'])): ?>
                                    <span class="badge badge-success" style="margin-left:4px;">custom</span>
                                <?php endif; ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
