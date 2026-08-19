<div class="page-header">
    <h1 class="page-heading">
        <i class="fa-solid fa-shield-halved"></i> Roles
    </h1>
    <div class="page-actions">
        <a href="/roles/create" class="btn btn-primary">
            <i class="fa-solid fa-plus"></i> New Role
        </a>
    </div>
</div>

<div class="table-card">
    <table class="table">
        <thead>
            <tr>
                <th>#</th>
                <th>Name</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($roles)): ?>
                <tr>
                    <td colspan="3" class="text-center text-muted">No roles found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($roles as $role): ?>
                    <tr>
                        <td><?= (int) $role['id'] ?></td>
                        <td><?= htmlspecialchars($role['name']) ?></td>
                        <td class="table-actions">
                            <a href="/roles/<?= (int) $role['id'] ?>/permissions" class="btn btn-sm view-btn">
                                <i class="fa-solid fa-key"></i> Permissions
                            </a>
                            <a href="/roles/<?= (int) $role['id'] ?>/edit" class="btn btn-sm edit-btn">
                                <i class="fa-solid fa-pen"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
