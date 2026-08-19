<?php /** @var array $workflow */ ?>
<?php /** @var array $step */ ?>
<?php /** @var array $roles */ ?>
<?php /** @var array $departments */ ?>

<div class="card">

    <div class="card-header">
        <div class="header-left">
            <h5>
                <i class="fa-solid fa-pen-to-square"></i>
                Edit Step – <?= htmlspecialchars($workflow['name']) ?>
            </h5>
        </div>

        <div class="header-actions">
            <a href="/settings/workflows/<?= (int)$workflow['id'] ?>/steps" class="btn btn-secondary btn-sm">
                Back
            </a>
        </div>
    </div>

    <div class="card-body">

        <form method="POST" action="/settings/workflows/<?= (int)$workflow['id'] ?>/steps/<?= (int)$step['id'] ?>/update">
            <?= csrf_field() ?>

            <div class="form-grid">

                <div class="form-group">
                    <label for="step_order">Step Order <span class="required">*</span></label>
                    <input
                        type="number"
                        id="step_order"
                        name="step_order"
                        class="form-control"
                        value="<?= (int)$step['step_order'] ?>"
                        min="1"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="step_name">Step Name <span class="required">*</span></label>
                    <input
                        type="text"
                        id="step_name"
                        name="step_name"
                        class="form-control"
                        value="<?= htmlspecialchars($step['name']) ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="role_id">Label Role <span class="required">*</span></label>
                    <select id="role_id" name="role_id" class="form-control" required>
                        <?php foreach ($roles as $role): ?>
                            <option
                                value="<?= (int)$role['id'] ?>"
                                <?= ((int)$step['role_id'] === (int)$role['id']) ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($role['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="assignment_type">Who Can Approve</label>
                    <select id="assignment_type" name="assignment_type" class="form-control">
                        <option value="role_department" <?= ($step['assignment_type'] ?? 'role_department') === 'role_department' ? 'selected' : '' ?>>
                            Role + Department (dynamic)
                        </option>
                        <option value="explicit" <?= ($step['assignment_type'] ?? '') === 'explicit' ? 'selected' : '' ?>>
                            Specific People (explicit list)
                        </option>
                    </select>
                </div>

                <div class="form-group" id="department-scope-group">
                    <label for="department_scope">Department Scope</label>
                    <select id="department_scope" name="department_scope" class="form-control">
                        <?php $scope = $step['department_scope'] ?? 'same_as_request'; ?>
                        <option value="same_as_request" <?= $scope === 'same_as_request' ? 'selected' : '' ?>>
                            Same department as the request
                        </option>
                        <option value="fixed" <?= $scope === 'fixed' ? 'selected' : '' ?>>
                            One fixed department, regardless of the request
                        </option>
                        <option value="any" <?= $scope === 'any' ? 'selected' : '' ?>>
                            Any department — role match only
                        </option>
                    </select>
                </div>

                <div class="form-group" id="department-id-group" style="display:none;">
                    <label for="department_id">Fixed Department</label>
                    <select id="department_id" name="department_id" class="form-control">
                        <option value="">Select Department</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= (int)$dept['id'] ?>" <?= ((int)($step['department_id'] ?? 0) === (int)$dept['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($dept['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="approval_rule">If Multiple People Are Eligible</label>
                    <select id="approval_rule" name="approval_rule" class="form-control">
                        <?php $rule = $step['approval_rule'] ?? 'all'; ?>
                        <option value="all" <?= $rule === 'all' ? 'selected' : '' ?>>All of them must approve (unanimous)</option>
                        <option value="any" <?= $rule === 'any' ? 'selected' : '' ?>>Any one of them approving is enough</option>
                    </select>
                </div>

            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <?php if (($step['assignment_type'] ?? 'role_department') === 'explicit'): ?>
                    <a href="/settings/workflows/<?= (int)$workflow['id'] ?>/steps/<?= (int)$step['id'] ?>/approvers" class="btn btn-secondary">
                        Assign Approvers →
                    </a>
                <?php endif; ?>
            </div>

        </form>

        <script nonce="<?= csp_nonce() ?>">
            function toggleStepRouting() {
                var type = document.getElementById('assignment_type').value;
                var scopeGroup = document.getElementById('department-scope-group');
                scopeGroup.style.display = (type === 'role_department') ? '' : 'none';
                if (type !== 'role_department') {
                    document.getElementById('department-id-group').style.display = 'none';
                } else {
                    toggleDepartmentField();
                }
            }
            function toggleDepartmentField() {
                var scope = document.getElementById('department_scope').value;
                document.getElementById('department-id-group').style.display = (scope === 'fixed') ? '' : 'none';
            }
            document.getElementById('assignment_type')?.addEventListener('change', toggleStepRouting);
            document.getElementById('department_scope')?.addEventListener('change', toggleDepartmentField);
            // FIX: previously relied solely on inline onchange="" attributes,
            // silently blocked by this app's CSP — these fields never
            // actually reacted to a change after page load.
            toggleStepRouting();
        </script>

    </div>
</div>
