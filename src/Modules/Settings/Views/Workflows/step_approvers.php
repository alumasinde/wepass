<?php /** @var array $workflow */ ?>
<?php /** @var array $step */ ?>
<?php /** @var array $users */ ?>
<?php /** @var array $assignedUserIds */ ?>

<div class="card">

    <div class="card-header">
        <div class="header-left">
            <h5>
                <i class="fa-solid fa-users"></i>
                Assign Approvers – <?= htmlspecialchars($step['name']) ?>
            </h5>
            <small class="text-muted">
                <?= htmlspecialchars($workflow['name']) ?> · Step <?= (int)$step['step_order'] ?>
            </small>
        </div>

        <div class="header-actions">
            <a href="/settings/workflows/<?= (int)$workflow['id'] ?>/steps" class="btn btn-secondary btn-sm">
                Back to Steps
            </a>
        </div>
    </div>

    <div class="card-body">

        <div class="alert alert-info">
            Check every user who should be able to approve at this step, regardless of which department
            they're in. This step is department-agnostic — only the people checked below will ever see
            a pending approval here.
        </div>

        <?php if (empty($users)): ?>
            <div class="alert alert-warning">No active users to assign.</div>
        <?php else: ?>

            <form method="POST" action="/settings/workflows/<?= (int)$workflow['id'] ?>/steps/<?= (int)$step['id'] ?>/approvers">
                <?= csrf_field() ?>

                <div class="table-card">
                    <table class="table">
                        <thead>
                            <tr>
                                <th width="50"></th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Department</th>
                                <th>Role(s)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <?php $checked = in_array((int)$user['id'], $assignedUserIds, true); ?>
                                <tr>
                                    <td>
                                        <input
                                            type="checkbox"
                                            name="user_ids[]"
                                            value="<?= (int)$user['id'] ?>"
                                            id="user_<?= (int)$user['id'] ?>"
                                            <?= $checked ? 'checked' : '' ?>
                                        >
                                    </td>
                                    <td>
                                        <label for="user_<?= (int)$user['id'] ?>">
                                            <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
                                        </label>
                                    </td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td><?= htmlspecialchars($user['department_name'] ?? '—') ?></td>
                                    <td><?= htmlspecialchars($user['role_names'] ?? '—') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Approvers</button>
                </div>

            </form>

        <?php endif; ?>

    </div>
</div>
