<h1 class="page-heading">
    <i class="fa-solid fa-building"></i> Company Settings
</h1>

<div class="form-card">

    <form method="POST" action="/settings/company">
        <?= csrf_field() ?>

        <div class="form-group">
            <label class="form-label">Company Name</label>
            <input type="text"
                   name="company_name"
                   class="form-control"
                   value="<?= htmlspecialchars($company['name'] ?? '') ?>"
                   required>
        </div>

        <div class="form-group">
            <label class="form-label">Email</label>
            <input type="email"
                   name="email"
                   class="form-control"
                   value="<?= htmlspecialchars($company['email'] ?? '') ?>">
        </div>

         <div class="form-group">
            <label class="form-label">Company Code</label>
            <input type="text"
                   class="form-control"
                   value="<?= htmlspecialchars($company['code'] ?? '') ?>"
                   readonly
                   disabled
                   style="background:var(--color-surface-subtle);cursor:not-allowed;">
            <small class="text-muted">This is your subdomain (<code><?= htmlspecialchars($company['code'] ?? '') ?>.albatechsolutions.co.ke</code>) — contact support to change it, since it affects your login URL.</small>
        </div>

        <div class="form-group">
            <label class="form-label">Phone</label>
            <input type="text"
                   name="phone"
                   class="form-control"
                   value="<?= htmlspecialchars($company['phone'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label class="form-label">Country</label>
            <input type="text"
                   name="country"
                   class="form-control"
                   value="<?= htmlspecialchars($company['country'] ?? '') ?>">
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-save"></i> Save Settings
            </button>

            <a href="/settings" class="btn btn-secondary">
            Back
        </a>
        </div>
        

    </form>

</div>

<div class="form-card" style="margin-top:20px;">
    <div class="form-card__header">Logo</div>
    <div class="form-card__body">

        <?php if (!empty($company['logo'])): ?>
            <div style="margin-bottom:16px;">
                <img src="<?= htmlspecialchars($company['logo']) ?>" alt="Current logo"
                     style="max-height:60px;max-width:200px;object-fit:contain;border:1px solid var(--color-border);border-radius:var(--radius-sm);padding:8px;">
            </div>
        <?php else: ?>
            <p class="text-muted" style="margin-bottom:16px;">No logo uploaded yet — the sidebar shows a
                generic icon until one is set.</p>
        <?php endif; ?>

        <form method="POST" action="/settings/company/logo" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label">Upload New Logo</label>
                <input type="file" name="logo" class="form-control" accept="image/png,image/jpeg,image/webp" required>
                <small class="text-muted">PNG, JPEG, or WebP. Max 2MB.</small>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-upload"></i> Upload Logo
                </button>
            </div>
        </form>

    </div>
</div>