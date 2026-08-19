<?php
$title   = $title ?? 'Platform Admin';
$content = $content ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> - GPMS Platform Admin</title>

    <!-- DM Sans font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/app.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'].'/assets/css/app.css') ?>">
    <!--
        Deliberately NOT calling theme_css_vars() here — that renders
        a specific TENANT's saved colors (tenant_settings), which
        makes no sense on the master admin panel: there is no one
        tenant in scope here, and theme_css_vars() reads from
        whatever tenant happens to be resolved (none, on the admin
        host) or would need special-casing to avoid throwing. The
        platform admin panel intentionally always uses the default
        token.css palette, not any client's branding.
    -->
</head>
<body>

<div class="app-container">

    <?php require base_path('resources/views/templates/master-sidebar.php'); ?>

    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <div class="main-content">

        <?php require base_path('resources/views/templates/master-navbar.php'); ?>

        <div class="content-wrapper">

            <?php if (!empty($_SESSION['flash'])): ?>
                <?php
                    $flashType    = $_SESSION['flash']['type']    ?? 'info';
                    $flashMessage = $_SESSION['flash']['message'] ?? '';
                    unset($_SESSION['flash']);
                ?>
                <?php if ($flashMessage !== ''): ?>
                    <div class="alert alert-<?= htmlspecialchars($flashType) ?> auto-dismiss">
                        <i class="fa-solid <?= $flashType === 'success' ? 'fa-circle-check' : ($flashType === 'danger' ? 'fa-circle-xmark' : 'fa-circle-info') ?>"></i>
                        <?= htmlspecialchars($flashMessage) ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?= $content ?>

        </div><!-- .content-wrapper -->

        <footer class="footer">
            <small>&copy; <?= date('Y') ?> GPMS Platform Admin. <span class="text-muted"> · v<?= htmlspecialchars(app_version()) ?></span></small>
        </footer>

    </div><!-- .main-content -->

</div><!-- .app-container -->

<script nonce="<?= csp_nonce() ?>">
// Shared confirm-before-submit — see resources/views/layouts/app.php
// for the full explanation. Same mechanism, needed here too since
// this layout's pages (e.g. Tenant Connection screen) have their own
// destructive-action confirmations.
document.addEventListener('submit', function (event) {
    const form = event.target;
    if (form instanceof HTMLFormElement && form.dataset.confirm) {
        if (!confirm(form.dataset.confirm)) {
            event.preventDefault();
        }
    }
});

// Shared modal-close handling — see resources/views/layouts/app.php
// for the full explanation.
function closeModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.remove('show', 'open');
        modal.style.display = 'none';
    }
}
document.addEventListener('click', function (event) {
    const btn = event.target.closest('[data-modal-target]');
    if (btn) {
        closeModal(btn.dataset.modalTarget);
    }
});

document.addEventListener('DOMContentLoaded', function () {
    const toggleBtn = document.getElementById('sidebarToggle');
    const sidebar   = document.getElementById('sidebar');
    const backdrop  = document.getElementById('sidebarBackdrop');
    const MOBILE_BREAKPOINT = 992;

    function isMobileLayout() { return window.innerWidth <= MOBILE_BREAKPOINT; }
    function openMobileSidebar() {
        sidebar.classList.add('open');
        backdrop.classList.add('show');
        document.body.classList.add('sidebar-open-lock');
    }
    function closeMobileSidebar() {
        sidebar.classList.remove('open');
        backdrop.classList.remove('show');
        document.body.classList.remove('sidebar-open-lock');
    }

    if (toggleBtn && sidebar && backdrop) {
        toggleBtn.addEventListener('click', function () {
            if (isMobileLayout()) {
                sidebar.classList.contains('open') ? closeMobileSidebar() : openMobileSidebar();
            } else {
                document.body.classList.toggle('sidebar-collapsed');
            }
        });
        backdrop.addEventListener('click', closeMobileSidebar);
        sidebar.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () {
                if (isMobileLayout()) closeMobileSidebar();
            });
        });
        window.addEventListener('resize', function () {
            if (!isMobileLayout()) closeMobileSidebar();
        });
    }

    document.querySelectorAll('.auto-dismiss').forEach(function (alert) {
        setTimeout(function () {
            alert.style.transition = 'opacity 0.4s';
            alert.style.opacity    = '0';
            setTimeout(function () { alert.remove(); }, 400);
        }, 4500);
    });
});

const GPMS_TEST_CONN_CSRF = <?= json_encode(csrf_token()) ?>;

/**
 * "Test Connection" — used on both the tenant Connection screen and
 * the New Tenant "hosted separately" section. Reads whatever fields
 * exist with the given id prefix (each page uses its own prefix, so
 * this works even though both forms have fields with the same base
 * names), posts them to the shared test endpoint, and shows the
 * result inline — nothing is saved by this action.
 */
async function testConnection(prefix) {
    const val = (id) => {
        const el = document.getElementById(prefix + id);
        if (!el) return '';
        return el.type === 'checkbox' ? (el.checked ? '1' : '') : el.value;
    };

    const resultBox = document.getElementById('testResult');
    if (!resultBox) return;

    resultBox.style.display = 'block';
    resultBox.className = 'alert alert-info';
    resultBox.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Testing connection...';

    try {
        const response = await fetch('/master/tenants/test-connection', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({
                csrf_token: GPMS_TEST_CONN_CSRF,
                host: val('host'),
                port: val('port'),
                database: val('database'),
                username: val('username'),
                password: val('password'),
                ssl: val('ssl'),
                ssl_verify: val('ssl_verify'),
                ssl_ca: val('ssl_ca')
            })
        });

        const data = await response.json();

        if (data.success && data.schema_ready) {
            resultBox.className = 'alert alert-success';
            resultBox.innerHTML = '<i class="fa-solid fa-circle-check"></i> ' + data.message;
        } else if (data.success) {
            resultBox.className = 'alert alert-warning';
            resultBox.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> ' + data.message;
        } else {
            resultBox.className = 'alert alert-danger';
            resultBox.innerHTML = '<i class="fa-solid fa-circle-xmark"></i> ' + data.message;
        }
    } catch (err) {
        resultBox.className = 'alert alert-danger';
        resultBox.innerHTML = '<i class="fa-solid fa-circle-xmark"></i> Test request failed: ' + err.message;
    }
}
</script>

</body>
</html>
