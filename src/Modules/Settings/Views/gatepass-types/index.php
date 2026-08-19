<h1 class="page-heading">
    <i class="fa-solid fa-id-badge"></i>
    Gatepass Types
</h1>

<div class="form-card">

    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
        <p class="text-muted">Manage gatepass types and permissions.</p>

        <a href="/settings/gatepass-types/create" class="btn btn-primary">
            <i class="fa fa-plus"></i> New Type
        </a>
    </div>

    <?php if (empty($types)): ?>
        <p>No gatepass types found.</p>
    <?php else: ?>

        <table class="table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Code</th>
                    <th>Direction</th>
                    <th>Check-in</th>
                    <th>Check-out</th>
                    <th>Workflow</th>
                    <th></th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($types as $type):
                    $actions = is_array($type->allowedActions)
                        ? $type->allowedActions
                        : json_decode($type->allowedActions ?? '{}', true);
                    $direction = $type->direction ?? 'outbound';
                ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($type->name) ?></strong></td>
                        <td><code><?= htmlspecialchars($type->code ?? '—') ?></code></td>

                        <td>
                            <span class="badge badge-<?= $direction === 'inbound' ? 'info' : 'secondary' ?>">
                                <?= $direction === 'inbound' ? 'Inbound' : 'Outbound' ?>
                            </span>
                        </td>

                        <td><?= !empty($actions['checkin']) ? '✔' : '—' ?></td>
                        <td><?= !empty($actions['checkout']) ? '✔' : '—' ?></td>

                        <!-- ✅ FIX: show workflow name -->
                        <td><?= htmlspecialchars($type->workflowName ?? 'None') ?></td>

                        <td style="text-align:right;">
                            
                            <!-- Option 1: go to edit page -->
                            <a href="/settings/gatepass-types/<?= (int)$type->id ?>/edit"
                               class="btn btn-secondary btn-sm">
                                Edit
                            </a>
                            
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    <?php endif; ?>
</div>