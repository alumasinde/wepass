<?php /** @var array $workflow */ ?>
<?php /** @var array $old */ ?>


<div class="card">

    <div class="card-header">
        <h5>
            <i class="fa-solid fa-pen-to-square"></i>
            Edit Workflow
        </h5>
    </div>

    <div class="card-body">

        <form method="POST" action="/settings/workflows/<?= (int)$workflow['id'] ?>/update">
            <?= csrf_field() ?>

            <!-- CSRF Token -->
<input type="hidden" name="_token" value="<?= htmlspecialchars($csrf) ?>">

            <div class="form-group">
                <label for="name">
                    Workflow Name <span class="required">*</span>
                </label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    class="form-control"
                    value="<?= htmlspecialchars($old['name'] ?? $workflow['name']) ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea
                    id="description"
                    name="description"
                    class="form-control"
                    rows="4"
                ><?= htmlspecialchars($old['description'] ?? $workflow['description']) ?></textarea>
            </div>

            <div class="form-group">
                <label class="checkbox-label">
                    <input
                        type="checkbox"
                        name="is_active"
                        value="1"
                        <?= ((int)($old['is_active'] ?? $workflow['is_active']) === 1) ? 'checked' : '' ?>
                    >
                    Active
                </label>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-check"></i> Update
                </button>

                <a href="/settings/workflows" class="btn btn-secondary">
                    Cancel
                </a>
            </div>

        </form>

    </div>
</div>