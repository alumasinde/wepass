<div class="card">

    <div class="card-header">
        <h5>
            <i class="fa-solid fa-diagram-project"></i>
            Create Workflow
        </h5>
    </div>

    <div class="card-body">

        <form method="POST" action="/settings/workflows">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="name">
                    Workflow Name <span class="required">*</span>
                </label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    class="form-control"
                    value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
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
                ><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-check"></i> Save
                </button>

                <a href="/settings/workflows" class="btn btn-secondary">
                    Cancel
                </a>
            </div>

        </form>

    </div>
</div>