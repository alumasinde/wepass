<?php
// Convert applied permissions to fast lookup map
$appliedMap = array_flip($rolePermissions);

// Group permissions by module
$permissionsByModule = [];
foreach ($allPermissions as $perm) {
    $permissionsByModule[$perm['module']][] = $perm;
}

$totalApplied = count($appliedMap);
?>

<h1 class="page-heading">
    Assign Permissions: <?= htmlspecialchars($role['name']) ?>
</h1>

<div class="summary-bar">
    <span class="summary-count">
        <?= $totalApplied ?> permission(s) applied
    </span>
</div>

<form method="POST" action="/roles/<?= (int)$role['id'] ?>/permissions">
    <?= csrf_field() ?>

<?php foreach ($permissionsByModule as $module => $perms): 
    $moduleKey = md5($module);

    $moduleApplied = 0;
    foreach ($perms as $perm) {
        if (isset($appliedMap[$perm['id']])) {
            $moduleApplied++;
        }
    }
?>

    <div class="card">

        <div class="card-header"
             data-module="<?= $moduleKey ?>">

            <div class="module-title">
                <strong><?= htmlspecialchars($module) ?></strong>
                <span class="badge badge-total">
                    <?= count($perms) ?>
                </span>

                <?php if ($moduleApplied > 0): ?>
                    <span class="badge badge-applied">
                        <?= $moduleApplied ?> applied
                    </span>
                <?php endif; ?>
            </div>

            <div class="module-actions">
                <label>
                    <input type="checkbox"
                           class="toggle-module"
                           data-module="<?= $moduleKey ?>">
                    Toggle All
                </label>
            </div>
        </div>

        <div class="card-body module-body module-<?= $moduleKey ?>
            <?= $moduleApplied > 0 ? 'expanded' : '' ?>">

            <?php foreach ($perms as $perm): 
                $isApplied = isset($appliedMap[$perm['id']]);
            ?>
                <div class="permission-item <?= $isApplied ? 'applied' : '' ?>">

                    <input type="checkbox"
                           class="permission-checkbox module-<?= $moduleKey ?>"
                           name="permissions[]"
                           value="<?= (int)$perm['id'] ?>"
                           id="perm-<?= (int)$perm['id'] ?>"
                           <?= $isApplied ? 'checked' : '' ?>>

                    <label for="perm-<?= (int)$perm['id'] ?>">
                        <?= htmlspecialchars($perm['action']) ?>
                    </label>

                    <?php if ($isApplied): ?>
                        <span class="badge badge-applied-small">
                            Applied
                        </span>
                    <?php endif; ?>

                </div>
            <?php endforeach; ?>

        </div>
    </div>

<?php endforeach; ?>

    <div class="form-actions">
        <button type="submit" class="btn-primary">
            Save Permissions
        </button>
        <a href="/roles" class="btn-secondary">Cancel</a>
    </div>

</form>

<script nonce="<?= csp_nonce() ?>">
document.querySelectorAll('.toggle-module').forEach(toggle => {

    const moduleClass = 'module-' + toggle.dataset.module;
    const checkboxes = document.querySelectorAll('.' + moduleClass);

    const updateState = () => {
        const total = checkboxes.length;
        const checked = Array.from(checkboxes).filter(c => c.checked).length;

        toggle.checked = checked === total;
        toggle.indeterminate = checked > 0 && checked < total;
    };

    updateState();

    toggle.addEventListener('change', function() {
        checkboxes.forEach(cb => cb.checked = this.checked);
    });

    checkboxes.forEach(cb => {
        cb.addEventListener('change', updateState);
    });
});

// Expand / collapse module
document.querySelectorAll('.card-header').forEach(header => {
    header.addEventListener('click', function(e) {
        if (e.target.tagName === 'INPUT') return;

        const moduleKey = this.dataset.module;
        const body = document.querySelector('.module-' + moduleKey);
        body.classList.toggle('expanded');
    });
});
</script>
