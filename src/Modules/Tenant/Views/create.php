<?php
$old         = $old ?? [];
$errors      = $errors ?? [];
$baseDomain  = $base_domain ?? '';
?>

<div class="page-header">
    <h1 class="page-heading">
        <i class="fa-solid fa-plus"></i> New Tenant
    </h1>
    <div class="page-actions">
        <a href="/master/tenants" class="btn btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> Back
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <i class="fa-solid fa-circle-xmark"></i>
        <?php foreach ($errors as $error): ?>
            <div><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="form-card">
    <div class="form-card__header">Company Details</div>
    <div class="form-card__body">
        <form method="POST" action="/master/tenants">
            <?= csrf_field() ?>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Company Name <span class="required">*</span></label>
                    <input type="text" name="name" class="form-control"
                           value="<?= old('name') ?>" placeholder="e.g. Acme Logistics Ltd" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Tenant Code <span class="required">*</span></label>
                    <input type="text" name="code" id="tenantCode" class="form-control"
                           value="<?= old('code') ?>" placeholder="e.g. acme" pattern="[a-z0-9-]{2,40}" required>
                    <?php if ($baseDomain !== ''): ?>
                        <small class="text-muted">
                            Will be live at <code id="subdomainPreview">https://<?= htmlspecialchars($old['code'] ?? 'code', ENT_QUOTES, 'UTF-8') ?>.<?= htmlspecialchars($baseDomain, ENT_QUOTES, 'UTF-8') ?></code>
                        </small>
                    <?php else: ?>
                        <small class="text-muted">Lowercase letters, numbers, hyphens only. Can't be changed later.</small>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($baseDomain !== ''): ?>
                <div class="form-group">
                    <label class="form-label">Custom Domain (optional)</label>
                    <input type="text" name="custom_domain" class="form-control"
                           value="<?= old('custom_domain') ?>" placeholder="e.g. gatepass.theirdomain.co.ke">
                    <small class="text-muted">
                        If the client wants their own domain instead of the subdomain above, enter it here.
                        They'll need to CNAME it to <?= htmlspecialchars($baseDomain, ENT_QUOTES, 'UTF-8') ?> — it goes live once that DNS record propagates.
                    </small>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label class="form-label">Plan</label>
                <select name="plan" class="form-control">
                    <?php foreach (['starter', 'standard', 'enterprise'] as $plan): ?>
                        <option value="<?= $plan ?>" <?= ($old['plan'] ?? 'starter') === $plan ? 'selected' : '' ?>>
                            <?= ucfirst($plan) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <hr style="margin:20px 0;border-color:#eee;">

            <div class="form-group">
                <label class="form-check">
                    <input type="checkbox" id="hostedSeparately" name="hosted_separately" value="1"
                        <?= !empty($old['hosted_separately']) ? 'checked' : '' ?>>
                    Database hosted separately (not on this server)
                </label>
                <small class="text-muted">
                    Check this if this client's database already exists somewhere else — their own server, a
                    test machine, anything not managed through DirectAdmin on this host — and you've already
                    run <code>database/001_schema.sql</code> through <code>011_visitor_notes.sql</code>
                    against it by hand. Leave unchecked for the normal case (this server creates and manages
                    the database automatically).
                </small>
            </div>

            <div id="connectionFields" style="display:none;">
                <div class="alert alert-info">
                    <i class="fa-solid fa-circle-info"></i>
                    This server will connect to what you enter below — it will NOT create a database or run
                    any migrations. Make sure the schema is already set up and this server can actually reach
                    it (DNS/firewall/tunnel) before submitting.
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Host</label>
                        <input type="text" id="nt_host" name="conn_host" class="form-control"
                               value="<?= htmlspecialchars($old['conn_host'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                               placeholder="e.g. 203.0.113.9 or a tunnel hostname">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Port</label>
                        <input type="number" id="nt_port" name="conn_port" class="form-control"
                               value="<?= htmlspecialchars((string) ($old['conn_port'] ?? 3306), ENT_QUOTES, 'UTF-8') ?>" min="1" max="65535">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Database Name</label>
                        <input type="text" id="nt_database" name="conn_database" class="form-control"
                               value="<?= htmlspecialchars($old['conn_database'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Username</label>
                        <input type="text" id="nt_username" name="conn_username" class="form-control"
                               value="<?= htmlspecialchars($old['conn_username'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" id="nt_password" name="conn_password" class="form-control" autocomplete="new-password">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-check">
                            <input type="checkbox" id="nt_ssl" name="conn_ssl" value="1" <?= !empty($old['conn_ssl']) ? 'checked' : '' ?>> Require SSL
                        </label>
                    </div>
                    <div class="form-group">
                        <label class="form-check">
                            <input type="checkbox" id="nt_ssl_verify" name="conn_ssl_verify" value="1" <?= ($old === [] || !empty($old['conn_ssl_verify'])) ? 'checked' : '' ?>> Verify SSL certificate
                        </label>
                    </div>
                </div>

                <div id="testResult" style="display:none;margin-bottom:8px;"></div>
                <button type="button" class="btn btn-secondary btn-sm" id="testConnBtnNt">
                    <i class="fa-solid fa-plug"></i> Test Connection
                </button>
            </div>

            <hr style="margin:20px 0;border-color:#eee;">
            <p class="text-muted" style="margin-bottom:12px;">
                This client's own first admin account — not you. They'll use this to log into their install.
            </p>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Admin First Name <span class="required">*</span></label>
                    <input type="text" name="admin_first_name" class="form-control"
                           value="<?= old('admin_first_name') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Admin Last Name</label>
                    <input type="text" name="admin_last_name" class="form-control"
                           value="<?= old('admin_last_name') ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Admin Email <span class="required">*</span></label>
                    <input type="email" name="admin_email" class="form-control"
                           value="<?= old('admin_email') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Admin Password <span class="required">*</span></label>
                    <input type="password" name="admin_password" class="form-control"
                           minlength="12" placeholder="At least 12 characters" required autocomplete="new-password">
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-building-shield"></i> Create Tenant
                </button>
                <a href="/master/tenants" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php if ($baseDomain !== ''): ?>
<script nonce="<?= csp_nonce() ?>">
document.getElementById('tenantCode')?.addEventListener('input', function (e) {
    const preview = document.getElementById('subdomainPreview');
    if (preview) {
        const code = e.target.value.trim().toLowerCase() || 'code';
        preview.textContent = 'https://' + code + '.<?= htmlspecialchars($baseDomain, ENT_QUOTES, "UTF-8") ?>';
    }
});
</script>
<?php endif; ?>

<script nonce="<?= csp_nonce() ?>">
function toggleHostedSeparately() {
    const checked = document.getElementById('hostedSeparately').checked;
    document.getElementById('connectionFields').style.display = checked ? '' : 'none';
}
// FIX: previously relied solely on an inline onchange="" attribute,
// silently blocked by this app's CSP — the connection fields never
// actually toggled when you clicked the checkbox after page load.
document.getElementById('hostedSeparately')?.addEventListener('change', toggleHostedSeparately);
document.getElementById('testConnBtnNt')?.addEventListener('click', function () { testConnection('nt_'); });
// Re-apply on load too — e.g. if a validation error re-rendered this
// form with the checkbox already checked from what was submitted.
toggleHostedSeparately();
</script>

