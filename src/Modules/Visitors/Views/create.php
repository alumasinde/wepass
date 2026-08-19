<h2 class="gp-heading">Create Visitor</h2>

<?php if (!empty($error)): ?>
    <div class="gp-alert gp-alert--danger">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<div class="gp-card">
    <form method="POST" action="/visitors">
        <?= csrf_field() ?>

        <div class="gp-field">
            <label class="gp-label">First Name</label>
            <input class="gp-input" name="first_name" required>
        </div>

        <div class="gp-field">
            <label class="gp-label">Last Name</label>
            <input class="gp-input" name="last_name" required>
        </div>

        <?php $old = $_POST['id_type_id'] ?? ''; ?>

        <div class="gp-field">
            <label class="gp-label">ID Type</label>
            <select class="gp-select" name="id_type_id">
                <option value="">Select ID Type</option>

                <?php foreach ($idTypes ?? [] as $type): ?>
                    <option value="<?= (int)$type['id'] ?>"
                        <?= $old == $type['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($type['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="gp-field">
            <label class="gp-label">ID Number</label>
            <input class="gp-input" name="id_number">
        </div>

        <div class="gp-field">
            <label class="gp-label">Phone</label>
            <input class="gp-input" name="phone">
        </div>

        <div class="gp-field">
            <label class="gp-label">Email</label>
            <input class="gp-input" name="email" type="email">
        </div>

        
  <?php $oldCompany = $_POST['company_id'] ?? ''; ?>
    
<?php $oldNewCompany = $_POST['new_company_name'] ?? ''; ?>

<div class="gp-field">
    <label class="gp-label">Company</label>

    <select class="gp-select" name="company_id" id="companySelect">
        <option value="">Select Existing Company</option>

        <?php foreach ($companies ?? [] as $company): ?>
            <option value="<?= (int)$company['id'] ?>"
                <?= $oldCompany == $company['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($company['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <small style="display:block;margin:8px 0;">— OR —</small>

    <input class="gp-input"
        type="text"
        name="new_company_name"
        placeholder="Enter New Company Name"
        value="<?= htmlspecialchars($oldNewCompany) ?>">
</div>

<div class="gp-field">
    <label class="gp-label">Notes <span class="gp-hint">(optional)</span></label>
    <textarea class="gp-textarea" name="notes" rows="3"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
</div>

    <button class="gp-btn gp-btn-primary">Create Visitor</button>

</form>
</div>