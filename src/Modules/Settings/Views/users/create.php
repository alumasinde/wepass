<?php
$roles = $data['roles'] ?? [];
$departments = $data['departments'] ?? [];
?>
<h1 class="page-heading mb-4">
    <i class="fa-solid fa-user-plus"></i> Create User
</h1>

<div class="card shadow-sm">
    <div class="card-body">

        <form method="POST" action="/settings/users">
            <?= csrf_field() ?>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">First Name</label>
                    <input type="text" name="first_name" class="form-control" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Last Name</label>
                    <input type="text" name="last_name" class="form-control" required>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>

             <div class="mb-3">
                <label class="form-label">Department</label>
                <select name="department_id" class="form-control">
                    <option value="">Select Department</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Assign Roles</label>
                <?php foreach ($roles as $role): ?>
                    <div class="form-check">
                        <input type="checkbox"
                               name="roles[]"
                               value="<?= $role['id'] ?>"
                               class="form-check-input"
                               id="role-<?= $role['id'] ?>">
                        <label class="form-check-label" for="role-<?= $role['id'] ?>">
                            <?= htmlspecialchars($role['name']) ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="mb-3">
                <label class="form-label">Status</label>
                <div class="form-check">
                    <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active" checked>
                    <label class="form-check-label" for="is_active">Active</label>
                </div>
            </div>


            <div class="text-end">
                <a href="/settings/users" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-save"></i> Save User
                </button>
            </div>

        </form>

    </div>
</div>