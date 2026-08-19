<?php /** @var array $workflows */ 

?>

<div class="page-heading">

    <!-- Header -->
    <div class="card-header">
        <div class="header-left">
            <h5>
                <i class="fa-solid fa-diagram-project"></i>
                <?= htmlspecialchars($title ?? 'Workflows') ?>
            </h5>
        </div>

        <div class="header-actions">
            <a href="/settings/workflows/create" class="btn btn-primary btn-sm">
                <i class="fa-solid fa-plus"></i> New Workflow
            </a>

            <a href="/settings" class="btn btn-secondary btn-sm">
                <i class="fa-solid fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- Table -->
    <div class="table-card">

        <?php if (empty($workflows)): ?>
            <div class="alert alert-info">
                <i class="fa-solid fa-circle-info"></i>
                No workflows configured.
            </div>
        <?php else: ?>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Description</th>
                        <th width="80">Steps</th>
                        <th width="120">Status</th>
                        <th width="220" class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($workflows as $workflow): ?>
                        <tr>
                            <td class="fw-semibold">
                                <?= htmlspecialchars($workflow['name']) ?>
                            </td>

                            <td class="text-muted">
                                <?= htmlspecialchars($workflow['description'] ?? '-') ?>
                            </td>

                            <td>
                                <span class="badge badge-light">
                                    <?= (int)$workflow['step_count'] ?>
                                </span>
                            </td>

                            <td>
                                <?php if ((int)$workflow['is_active'] === 1): ?>
                                    <span class="badge badge-success">
                                        Active
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">
                                        Inactive
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td class="text-right">
                                <div class="action-group">
                                    <a href="/settings/workflows/<?= (int)$workflow['id'] ?>/edit"
                                       class="btn btn-warning btn-sm">
                                        Edit
                                    </a>

                                    <a href="/settings/workflows/<?= (int)$workflow['id'] ?>/steps"
                                       class="btn btn-info btn-sm">
                                        Steps
                                    </a>

                                    <a href="/settings/workflows/<?= (int)$workflow['id'] ?>/assign"
                                       class="btn btn-dark btn-sm">
                                        Assign
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php endif; ?>

    </div>
</div>