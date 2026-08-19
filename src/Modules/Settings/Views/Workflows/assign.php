<?php /** @var array $workflow */ ?>
<?php /** @var array $gatepassTypes */ ?>

<div class="card">

    <div class="card-header">
        <div class="header-left">
            <h5>
                <i class="fa-solid fa-link"></i>
                Assign Workflow – <?= htmlspecialchars($workflow['name']) ?>
            </h5>
        </div>

        <div class="header-actions">
            <a href="/settings/workflows" class="btn btn-secondary btn-sm">
                Back
            </a>
        </div>
    </div>

    <div class="card-body">

        <?php if (empty($gatepassTypes)): ?>
            <div class="alert alert-info">
                No gatepass types available for assignment.
            </div>
        <?php else: ?>

        <form method="POST" action="/settings/workflows/<?= (int)$workflow['id'] ?>/assign">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="gatepass_type_id">
                    Select Gatepass Type <span class="required">*</span>
                </label>

                <select
                    id="gatepass_type_id"
                    name="gatepass_type_id"
                    class="form-control"
                    required
                >
                    <option value="">-- Select Type --</option>

                    <?php foreach ($gatepassTypes as $type): ?>
                        <option
                            value="<?= (int)$type['id'] ?>"
                            <?= ((int)($_POST['gatepass_type_id'] ?? 0) === (int)$type['id']) ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars($type['name']) ?>
                        </option>
                    <?php endforeach; ?>

                </select>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-check"></i> Assign
                </button>

                <a href="/settings/workflows" class="btn btn-secondary">
                    Cancel
                </a>
            </div>

        </form>

        <?php endif; ?>

    </div>
</div>