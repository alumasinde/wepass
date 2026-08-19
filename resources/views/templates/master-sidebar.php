<?php
$current = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
?>
<aside class="sidebar" id="sidebar">

    <div class="brand">
        <i class="fa-solid fa-server"></i>
    </div>

    <ul class="menu">
        <?php
        // Platform-level nav only — this is deliberately NOT the
        // tenant app's menu (Gatepasses/Visitors/Approvals/etc.).
        // Add to this list as more master-admin-only features are
        // built (billing, master admin accounts, platform settings).
        $navItems = [
            ['href' => '/master/tenants', 'icon' => 'fa-building-shield', 'label' => 'Tenants'],
        ];
        foreach ($navItems as $item):
            $isActive = str_starts_with($current, $item['href']);
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
        <span class="sidebar-version">v<?= htmlspecialchars(app_version()) ?></span>
        <span class="sidebar-plan">PLATFORM</span>
    </div>

</aside>
