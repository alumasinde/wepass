<h2>Create Visitor</h2>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<form method="POST" action="/visitors">
    <?= csrf_field() ?>

    <div class="form-group">
        <label>First Name</label>
        <input name="first_name" required>
    </div>

    <div class="form-group">
        <label>Last Name</label>
        <input name="last_name" required>
    </div>

    <?php $old = $_POST['id_type_id'] ?? ''; ?>

<select name="id_type_id">
    <option value="">Select ID Type</option>

    <?php foreach ($idTypes ?? [] as $type): ?>
        <option value="<?= (int)$type['id'] ?>"
            <?= $old == $type['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($type['name']) ?>
        </option>
    <?php endforeach; ?>
</select>

    <div class="form-group">
        <label>ID Number</label>
        <input name="id_number">
    </div>

    <div class="form-group">
        <label>Phone</label>
        <input name="phone">
    </div>

    <div class="form-group">
        <label>Email</label>
        <input name="email" type="email">
    </div>


    
  <?php $oldCompany = $_POST['company_id'] ?? ''; ?>
<?php $oldNewCompany = $_POST['new_company_name'] ?? ''; ?>

<div class="form-group">
    <label>Company</label>

    <select name="company_id" id="companySelect">
        <option value="">Select Existing Company</option>

        <?php foreach ($companies ?? [] as $company): ?>
            <option value="<?= (int)$company['id'] ?>"
                <?= $oldCompany == $company['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($company['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <small style="display:block;margin:8px 0;">— OR —</small>

    <input 
        type="text"
        name="new_company_name"
        placeholder="Enter New Company Name"
        value="<?= htmlspecialchars($oldNewCompany) ?>">
</div>

    <button class="btn btn-primary">Create Visitor</button>

</form>