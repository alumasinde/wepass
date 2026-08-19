<?php
$current = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Previously always read the static [tenant] name/logo default from
// config.ini, regardless of which tenant was actually resolved —
// every tenant showed the exact same branding. TenantContext::tenant()
// already holds the real resolved row (bootstrap/app.php's tenant
// lookup is a plain SELECT *, so 'name'/'logo' are already there);
// this just actually uses it, falling back to the static default
// only when no tenant is resolved at all (e.g. legacy single-tenant
// mode, or somehow reached without a tenant — shouldn't normally
// happen on this template, but a safe fallback regardless).
$resolvedTenant = \App\Core\TenantContext::hasTenant() ? \App\Core\TenantContext::tenant() : null;

$tenantName = (!empty($resolvedTenant['name']))
    ? $resolvedTenant['name']
    : config('tenant.name', 'Glee GPMS');

$tenantLogo = (!empty($resolvedTenant['logo']))
    ? $resolvedTenant['logo']
    : config('tenant.logo', '');
?>
<aside class="sidebar" id="sidebar">

    <div class="brand">
        <?php if ($tenantLogo): ?>
            <img src="<?= htmlspecialchars($tenantLogo) ?>" alt="Logo" style="height:28px;width:auto;object-fit:contain; align: center;">
        <?php else: ?>
            <i class="fa-solid fa-id-badge"></i>
        <?php endif; ?>
    </div>

    <ul class="menu">
        <?php
        $navItems = [
            ['href' => '/dashboard',  'icon' => 'fa-gauge-high',     'label' => 'Dashboard'],
            ['href' => '/gatepasses', 'icon' => 'fa-file-signature', 'label' => 'Gatepasses'],
            ['href' => '/visitors',   'icon' => 'fa-user',           'label' => 'Visitors'],
            ['href' => '/visits',     'icon' => 'fa-right-to-bracket','label' => 'Visits'],
            ['href' => '/approvals',  'icon' => 'fa-circle-check',   'label' => 'Approvals'],
            ['href' => '/reports',    'icon' => 'fa-chart-column',   'label' => 'Reports'],
            ['href' => '/roles',      'icon' => 'fa-shield-halved',  'label' => 'Roles'],
            ['href' => '/settings',   'icon' => 'fa-gear',           'label' => 'Settings'],
        ];
        foreach ($navItems as $item):
            $isActive = $item['href'] === '/settings'
                ? str_starts_with($current, '/settings')
                : ($item['href'] === '/reports'
                    ? str_starts_with($current, '/reports')
                    : $current === $item['href']);
        ?>
        <li>
            <a href="<?= $item['href'] ?>" class="<?= $isActive ? 'active' : '' ?>">
                <i class="fa-solid <?= $item['icon'] ?>"></i>
                <span><?= $item['label'] ?></span>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>

    <div class="sidebar-footer">
        <span class="sidebar-version">v2.0</span>
        <span class="sidebar-plan"><?= htmlspecialchars(strtoupper(config('tenant.plan', 'starter'))) ?></span>
    </div>

</aside>