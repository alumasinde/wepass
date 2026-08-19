<div class="page-header">
    <h1 class="page-heading">
        <i class="fa-solid fa-users-gear"></i> Users
    </h1>
    <div class="page-actions">
        <a href="/settings/users/create" class="btn btn-primary">
            <i class="fa-solid fa-plus"></i> New User
        </a>
    </div>
</div>

<div class="table-card">
    <table class="table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Department</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($users)): ?>
                <tr>
                    <td colspan="6" class="text-center text-muted">No users found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td><?= htmlspecialchars($u['role'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($u['department_name'] ?? '—') ?></td>
                        <td>
                            <span class="badge badge-<?= $u['is_active'] ? 'success' : 'secondary' ?>">
                                <?= $u['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td class="table-actions">
                            <a href="/settings/users/<?= (int) $u['id'] ?>/edit" class="btn btn-sm edit-btn">
                                <i class="fa-solid fa-pen"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
