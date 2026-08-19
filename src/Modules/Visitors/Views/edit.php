<?php
$details = $visitor ?? [];

if (empty($details)) {
    echo '<div class="alert alert-danger">Visitor not found.</div>';
    return;
}
?>

<div class="page-header">
    <h2>Edit Visitor</h2>
</div>

<?php if (!empty($_SESSION['flash'])): ?>
    <div class="alert alert-<?= htmlspecialchars($_SESSION['flash']['type']) ?>">
        <?= htmlspecialchars($_SESSION['flash']['message']) ?>
    </div>
    <?php unset($_SESSION['flash']); ?>
<?php endif; ?>

<form method="POST" action="/visitors/<?= (int)$details['id'] ?>/update" class="visitor-form">
    <?= csrf_field() ?>

    <div class="card">
        <div class="card-body">

            <div class="form-grid">

                <div class="form-group">
                    <label>First Name</label>
                    <input type="text"
                           name="first_name"
                           value="<?= htmlspecialchars($details['first_name'] ?? '') ?>"
                           required>
                </div>

                <div class="form-group">
                    <label>Last Name</label>
                    <input type="text"
                           name="last_name"
                           value="<?= htmlspecialchars($details['last_name'] ?? '') ?>"
                           required>
                </div>

                <div class="form-group">
                    <label>ID Type</label>
                    <select name="id_type_id" required>
                        <?php foreach ($idTypes as $type): ?>
                            <option value="<?= (int)$type['id'] ?>"
                                <?= ((int)$type['id'] === (int)($details['id_type_id'] ?? 0)) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($type['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>ID Number</label>
                    <input type="text"
                           name="id_number"
                           value="<?= htmlspecialchars($details['id_number'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Phone</label>
                    <input type="text"
                           name="phone"
                           value="<?= htmlspecialchars($details['phone'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Email</label>
                    <input type="email"
                           name="email"
                           value="<?= htmlspecialchars($details['email'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Company</label>
                    <select name="company_id">
                        <option value="">-- Select Company --</option>
                        <?php foreach ($companies as $company): ?>
                            <option value="<?= (int)$company['id'] ?>"
                                <?= ((int)$company['id'] === (int)($details['company_id'] ?? 0)) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($company['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Or Add New Company</label>
                    <input type="text"
                           name="new_company_name"
                           placeholder="Enter new company">
                </div>

                <div class="form-group">
                    <label>Notes <small class="text-muted">(optional)</small></label>
                    <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($details['notes'] ?? '') ?></textarea>
                </div>

            </div>

        </div>

        <div class="card-footer">
            <a href="/visitors/<?= (int)$details['id'] ?>" class="btn btn-secondary">
                Cancel
            </a>
            <button type="submit" class="btn btn-primary">
                Update Visitor
            </button>
        </div>
    </div>

</form>