<?php
$type = $type ?? 'info';
$message = $message ?? '';

$icon = match ($type) {
    'success' => 'fa-circle-check',
    'danger'  => 'fa-circle-xmark',
    'warning' => 'fa-triangle-exclamation',
    default   => 'fa-circle-info',
};
?>

<?php if (!empty($message)): ?>
<div class="alert alert-<?= htmlspecialchars($type) ?> alert-dismissible fade show" role="alert">
    <i class="fa-solid <?= $icon ?> me-2"></i>
    <?= htmlspecialchars($message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>