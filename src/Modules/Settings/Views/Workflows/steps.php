<?php /** @var array $workflow */ ?>
<?php /** @var array $steps */ ?>
<?php /** @var array $roles */ ?>
<?php /** @var array $departments */ ?>

<div class="card">

    <div class="card-header">
        <div class="header-left">
            <h5>
                <i class="fa-solid fa-list-ol"></i>
                Configure Steps – <?= htmlspecialchars($workflow['name']) ?>
            </h5>
        </div>

        <div class="header-actions">
            <a href="/settings/workflows" class="btn btn-secondary btn-sm">
                Back
            </a>
        </div>
    </div>

    <div class="card-body">

        <!-- Add Step Form -->
        <form method="POST" action="/settings/workflows/<?= (int)$workflow['id'] ?>/steps" id="add-step-form">
            <?= csrf_field() ?>

            <div class="form-grid">

                <div class="form-group">
                    <label for="step_order">Step Order <span class="required">*</span></label>
                    <input
                        type="number"
                        id="step_order"
                        name="step_order"
                        class="form-control"
                        value="<?= htmlspecialchars($_POST['step_order'] ?? '') ?>"
                        placeholder="1, 2, 3..."
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
                        value="<?= htmlspecialchars($_POST['step_name'] ?? '') ?>"
                        placeholder="e.g. HOD Approval"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="role_id">Label Role <span class="required">*</span></label>
                    <select id="role_id" name="role_id" class="form-control" required>
                        <option value="">Select Role</option>
                        <?php foreach ($roles as $role): ?>
                            <option
                                value="<?= (int)$role['id'] ?>"
                                <?= ((int)($_POST['role_id'] ?? 0) === (int)$role['id']) ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($role['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-text text-muted">Always required — used as the step's display label, and as the actual matching role when "Role + Department" is selected below.</small>
                </div>

                <div class="form-group">
                    <label for="assignment_type">Who Can Approve</label>
                    <select id="assignment_type" name="assignment_type" class="form-control">
                        <option value="role_department">Role + Department (dynamic — matches anyone with this role, filtered by department)</option>
                        <option value="explicit">Specific People (explicit list — pick exactly who, regardless of department)</option>
                    </select>
                </div>

                <div class="form-group" id="department-scope-group">
                    <label for="department_scope">Department Scope</label>
                    <select id="department_scope" name="department_scope" class="form-control">
                        <option value="same_as_request">Same department as the request (e.g. a Department Head approving only their own department)</option>
                        <option value="fixed">One fixed department, regardless of the request (e.g. a central desk)</option>
                        <option value="any">Any department — role match only</option>
                    </select>
                </div>

                <div class="form-group" id="department-id-group" style="display:none;">
                    <label for="department_id">Fixed Department</label>
                    <select id="department_id" name="department_id" class="form-control">
                        <option value="">Select Department</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= (int)$dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="approval_rule">If Multiple People Are Eligible</label>
                    <select id="approval_rule" name="approval_rule" class="form-control">
                        <option value="all">All of them must approve (unanimous)</option>
                        <option value="any">Any one of them approving is enough</option>
                    </select>
                </div>

            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-plus"></i> Add Step
                </button>
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

        <div class="section-divider"></div>

        <!-- Existing Steps -->
        <div class="section-header">
            <h6>Existing Steps</h6>
        </div>

        <div class="table-card">

            <?php if (empty($steps)): ?>
                <div class="alert alert-info">
                    No steps configured yet.
                </div>
            <?php else: ?>

                <table class="table">
                    <thead>
                        <tr>
                            <th width="80">Order</th>
                            <th>Name</th>
                            <th>Role Label</th>
                            <th>Approvers</th>
                            <th>Rule</th>
                            <th width="220">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($steps as $step): ?>
                            <tr>
                                <td><?= (int)$step['step_order'] ?></td>
                                <td><?= htmlspecialchars($step['name']) ?></td>
                                <td><?= htmlspecialchars($step['role_name']) ?></td>
                                <td>
                                    <?php if (($step['assignment_type'] ?? 'role_department') === 'explicit'): ?>
                                        <span class="badge badge-light">Specific people</span>
                                        <span class="badge badge-<?= ((int)$step['approver_count'] > 0) ? 'success' : 'secondary' ?>">
                                            <?= (int)$step['approver_count'] ?> assigned
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-light">
                                            Role + Department
                                            (<?php
                                                $scope = $step['department_scope'] ?? 'same_as_request';
                                                echo $scope === 'fixed' ? 'fixed department' : ($scope === 'any' ? 'any department' : 'same as request');
                                            ?>)
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-secondary">
                                        <?= (($step['approval_rule'] ?? 'all') === 'any') ? 'Any one' : 'All' ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="/settings/workflows/<?= (int)$workflow['id'] ?>/steps/<?= (int)$step['id'] ?>/edit" class="btn btn-sm btn-secondary">
                                        Edit
                                    </a>
                                    <?php if (($step['assignment_type'] ?? 'role_department') === 'explicit'): ?>
                                        <a href="/settings/workflows/<?= (int)$workflow['id'] ?>/steps/<?= (int)$step['id'] ?>/approvers" class="btn btn-sm btn-primary">
                                            Assign Approvers
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

            <?php endif; ?>

        </div>

    </div>
</div>
