<?php
$user       = $_SESSION['user'] ?? [];
$firstName  = htmlspecialchars($user['first_name'] ?? '');
$tenantName = htmlspecialchars(config('tenant.name', 'Glee GPMS'));
?>
<nav class="navbar">

    <div class="nav-left">
        <button id="sidebarToggle" class="toggle-btn" aria-label="Toggle sidebar">
            <i class="fa-solid fa-bars"></i>
        </button>
        <span class="page-title"><?= htmlspecialchars($title ?? '') ?></span>
    </div>

    <div class="nav-center">
        <span class="tenant-name"><?= $tenantName ?></span>
    </div>

    <div class="nav-right">
        <a href="/settings/users/profile" class="user-name" title="My Profile">
            <i class="fa-solid fa-user-circle"></i>
            <span class="user-name-text"><?= $firstName ?></span>
        </a>

        <form method="POST" action="/logout" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="logout-btn" title="Sign out">
                <i class="fa-solid fa-right-from-bracket"></i>
            </button>
        </form>
    </div>

</nav>
