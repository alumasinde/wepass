<?php 
$title = $title ?? 'Glee GPMS'; 
$content = $content ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> - <?= htmlspecialchars(config('tenant.name', 'Glee GPMS')) ?></title>

      <!-- DM Sans font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/app.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'].'/assets/css/app.css') ?>">
    <?= theme_css_vars() ?>
</head>
<body>

<div class="app-container">

    <?php require base_path('resources/views/templates/sidebar.php'); ?>

    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <div class="main-content">

        <?php require base_path('resources/views/templates/navbar.php'); ?>

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

        <?php require base_path('resources/views/templates/footer.php'); ?>

    </div><!-- .main-content -->

</div><!-- .app-container -->

<script nonce="<?= csp_nonce() ?>">
// Shared confirm-before-submit — add data-confirm="Your message" to
// any <form> and this handles the rest. Exists because every one of
// these used to be an inline onsubmit="return confirm(...)" attribute,
// which this app's CSP silently blocks (nonces only authorize <script>
// tags, not inline event attributes) — meaning every delete/reset
// action using that pattern was submitting with NO confirmation
// dialog at all, silently, not just "the dialog looked wrong."
// Delegated on document so it works for forms rendered inside a loop
// (e.g. one delete form per row) without needing a unique id each.
document.addEventListener('submit', function (event) {
    const form = event.target;
    if (form instanceof HTMLFormElement && form.dataset.confirm) {
        if (!confirm(form.dataset.confirm)) {
            event.preventDefault();
        }
    }
});

// Shared modal-close handling for resources/views/components/modal.php
// — add data-modal-target="<modal-id>" to any button and this closes
// that modal. closeModal() itself never existed anywhere in this
// codebase before now; the component's close button has never
// actually worked under any circumstances until this.
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
    // Sidebar toggle
    const toggleBtn = document.getElementById('sidebarToggle');
    const sidebar   = document.getElementById('sidebar');
    const backdrop  = document.getElementById('sidebarBackdrop');

    // Must match the CSS breakpoint that puts the sidebar off-canvas
    // (@media max-width: 992px in utilities.css). These used to be
    // out of sync (JS said 768, CSS said 992) — in that gap, the
    // sidebar was positioned off-screen by CSS but the toggle button
    // ran the desktop "collapse" branch instead of "open", so there
    // was no way to open it at all between ~769-992px.
    const MOBILE_BREAKPOINT = 992;

    function isMobileLayout() {
        return window.innerWidth <= MOBILE_BREAKPOINT;
    }

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

        // Tap outside the open drawer to close it — standard mobile
        // drawer behavior; previously the only way to close it again
        // was tapping the toggle button itself.
        backdrop.addEventListener('click', closeMobileSidebar);

        // Close the drawer after following a link, so it doesn't
        // stay open over the next page's content while it loads.
        sidebar.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () {
                if (isMobileLayout()) {
                    closeMobileSidebar();
                }
            });
        });

        // If the window is resized/rotated past the breakpoint while
        // the drawer is open, drop the open state rather than leaving
        // it stuck (e.g. a tablet rotated from portrait to landscape).
        window.addEventListener('resize', function () {
            if (!isMobileLayout()) {
                closeMobileSidebar();
            }
        });
    }

    // Auto-dismiss flash alerts
    document.querySelectorAll('.auto-dismiss').forEach(function (alert) {
        setTimeout(function () {
            alert.style.transition = 'opacity 0.4s';
            alert.style.opacity    = '0';
            setTimeout(function () { alert.remove(); }, 400);
        }, 4500);
    });
});
</script>

</body>
</html>
