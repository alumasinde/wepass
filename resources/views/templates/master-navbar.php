<?php
$user      = $_SESSION['user'] ?? [];
$firstName = htmlspecialchars($user['first_name'] ?? '');
?>
<nav class="navbar">

    <div class="nav-left">
        <button id="sidebarToggle" class="toggle-btn" aria-label="Toggle sidebar">
            <i class="fa-solid fa-bars"></i>
        </button>
        <span class="page-title"><?= htmlspecialchars($title ?? '') ?></span>
    </div>

    <div class="nav-center">
        <span class="tenant-name">GPMS Platform Admin</span>
    </div>

    <div class="nav-right">
        <span class="user-name" title="Signed in as Super Admin">
            <i class="fa-solid fa-user-shield"></i>
            <span class="user-name-text"><?= $firstName ?></span>
        </span>

        <form method="POST" action="/master/logout" style="display:inline;">
            <?= csrf_field() ?>
            <button type="submit" class="logout-btn" title="Sign out">
                <i class="fa-solid fa-right-from-bracket"></i>
            </button>
        </form>
    </div>

</nav>
