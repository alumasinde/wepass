<div class="page-header">
    <h1 class="page-heading">
        <i class="fa-solid fa-plus"></i> Create Role
    </h1>
    <div class="page-actions">
        <a href="/roles" class="btn btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> Back
        </a>
    </div>
</div>

<div class="form-card">
    <div class="form-card__header">Role Details</div>
    <div class="form-card__body">
        <form method="POST" action="/roles">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
            <div class="form-group">
                <label class="form-label">Role Name <span class="required">*</span></label>
                <input type="text" name="name" class="form-control"
                       value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                       placeholder="e.g. Security Officer" required>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-save"></i> Save Role
                </button>
                <a href="/roles" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
