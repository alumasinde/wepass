<div class="page-header">
    <h1 class="page-heading">
        <i class="fa-solid fa-building"></i> Departments
    </h1>
</div>

<!-- Create form -->
<div class="form-card">
    <div class="form-card__header">Add Department</div>
    <div class="form-card__body">
        <form method="POST" action="/settings/departments/create">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Department Name <span class="required">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Human Resources" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Department Code <span class="required">*</span></label>
                    <input type="text" name="code" class="form-control" placeholder="e.g. HR" required>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-plus"></i> Create Department
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Departments list -->
<div class="table-card">
    <div class="table-card__header">
        <h3>All Departments</h3>
    </div>
    <table class="table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Code</th>
                <th>Status</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($departments)): ?>
                <tr>
                    <td colspan="5" class="text-center text-muted">No departments yet.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($departments as $dept): ?>
                    <tr>
                        <td><?= htmlspecialchars($dept->name) ?></td>
                        <td><code><?= htmlspecialchars($dept->code) ?></code></td>
                        <td>
                            <span class="badge badge-<?= $dept->isActive ? 'success' : 'secondary' ?>">
                                <?= $dept->isActive ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td><?= $dept->createdAt ? date('d M Y', strtotime($dept->createdAt)) : '—' ?></td>
                        <td class="table-actions">
                            <form method="POST" action="/settings/departments/<?= $dept->id ?>/toggle" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                <button type="submit" class="btn btn-sm <?= $dept->isActive ? 'btn-warning' : 'btn-success' ?>">
                                    <?= $dept->isActive ? 'Deactivate' : 'Activate' ?>
                                </button>
                            </form>
                            <form method="POST" action="/settings/departments/<?= $dept->id ?>/delete"
                                  style="display:inline;"
                                  data-confirm="Delete this department?">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                <button type="submit" class="btn btn-sm btn-danger">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
