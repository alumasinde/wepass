<footer class="footer">
    <small>
        &copy; <?= date('Y') ?>
        <?= htmlspecialchars(\App\Core\Tenant::name() ?? 'GPMS') ?>
        . All rights reserved.
        <span class="text-muted"> · v<?= htmlspecialchars(app_version()) ?></span>
    </small>
</footer>
