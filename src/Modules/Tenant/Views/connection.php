<?php
$tenant = $tenant ?? [];
$errors = $errors ?? [];
$flash  = $flash ?? null;
$hasCustom = !empty($tenant['connection_string']);
?>

<div class="page-header">
    <h1 class="page-heading">
        <i class="fa-solid fa-server"></i> Connection — <?= htmlspecialchars($tenant['name']) ?>
    </h1>
    <div class="page-actions">
        <a href="/master/tenants" class="btn btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> Back
        </a>
    </div>
</div>

<?php if ($flash): ?>
    <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>">
        <?= htmlspecialchars($flash['message']) ?>
    </div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <i class="fa-solid fa-circle-xmark"></i>
        <?php foreach ($errors as $error): ?>
            <div><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="alert alert-info">
    <?php if ($hasCustom): ?>
        <i class="fa-solid fa-circle-check"></i>
        This tenant currently connects using a <strong>custom, encrypted connection</strong> —
        it's not using the default shared database credentials.
    <?php else: ?>
        <i class="fa-solid fa-circle-info"></i>
        This tenant is currently using the <strong>default</strong> connection — the same
        shared server as every other tenant (database
        <code><?= htmlspecialchars($tenant['db_name'] ?? '') ?></code>). Fill in the form below only if
        this client's database has moved to its own separate infrastructure.
    <?php endif; ?>
</div>

<div class="form-card">
    <div class="form-card__header">Set Connection Details</div>
    <div class="form-card__body">

        <p class="text-muted" style="margin-bottom:16px;">
            For security, existing connection details are never shown here, even to you — this form
            always <strong>replaces</strong> the whole connection rather than editing it. Leave everything
            blank and don't submit if you don't need to change anything.
        </p>

        <form method="POST" action="/master/tenants/<?= (int) $tenant['id'] ?>/connection">
            <?= csrf_field() ?>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Host <span class="required">*</span></label>
                    <input type="text" id="ct_host" name="host" class="form-control" placeholder="e.g. 203.0.113.9 or db.client.local" autocomplete="off">
                </div>
                <div class="form-group">
                    <label class="form-label">Port</label>
                    <input type="number" id="ct_port" name="port" class="form-control" value="3306" min="1" max="65535">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Database Name <span class="required">*</span></label>
                    <input type="text" id="ct_database" name="database" class="form-control" autocomplete="off">
                </div>
                <div class="form-group">
                    <label class="form-label">Username <span class="required">*</span></label>
                    <input type="text" id="ct_username" name="username" class="form-control" autocomplete="off">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" id="ct_password" name="password" class="form-control" autocomplete="new-password">
                <small class="text-muted">Leave blank only if this database genuinely has no password (not recommended).</small>
            </div>

            <hr style="margin:20px 0;border-color:#eee;">

            <div class="form-row">
                <div class="form-group">
                    <label class="form-check">
                        <input type="checkbox" id="ct_ssl" name="ssl" value="1"> Require SSL
                    </label>
                </div>
                <div class="form-group">
                    <label class="form-check">
                        <input type="checkbox" id="ct_ssl_verify" name="ssl_verify" value="1" checked> Verify SSL certificate
                    </label>
                </div>
                <div class="form-group">
                    <label class="form-check">
                        <input type="checkbox" name="persistent" value="1"> Use persistent connections
                    </label>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">SSL CA Path <small class="text-muted">(optional)</small></label>
                    <input type="text" id="ct_ssl_ca" name="ssl_ca" class="form-control" placeholder="/path/to/ca.pem">
                </div>
                <div class="form-group">
                    <label class="form-label">SSL Cert Path <small class="text-muted">(optional)</small></label>
                    <input type="text" name="ssl_cert" class="form-control" placeholder="/path/to/client-cert.pem">
                </div>
                <div class="form-group">
                    <label class="form-label">SSL Key Path <small class="text-muted">(optional)</small></label>
                    <input type="text" name="ssl_key" class="form-control" placeholder="/path/to/client-key.pem">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Failover Host <small class="text-muted">(optional)</small></label>
                    <input type="text" name="failover_host" class="form-control" placeholder="Tried only if the primary host is unreachable">
                </div>
                <div class="form-group">
                    <label class="form-label">Failover Port <small class="text-muted">(optional)</small></label>
                    <input type="number" name="failover_port" class="form-control" min="1" max="65535" placeholder="Defaults to the primary port">
                </div>
            </div>

            <div id="testResult" style="display:none;margin-bottom:16px;"></div>

            <div class="form-actions">
                <button type="button" class="btn btn-secondary" id="testConnBtnCt">
                    <i class="fa-solid fa-plug"></i> Test Connection
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-lock"></i> Encrypt &amp; Save
                </button>
                <a href="/master/tenants" class="btn btn-secondary">Cancel</a>
            </div>
        </form>

        <?php if ($hasCustom): ?>
            <hr style="margin:24px 0;border-color:#eee;">
            <form method="POST" action="/master/tenants/<?= (int) $tenant['id'] ?>/connection/clear"
                  data-confirm="Revert this tenant to the default shared connection? This does not touch their actual database — only how this app connects to it.">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-secondary">
                    <i class="fa-solid fa-rotate-left"></i> Clear Custom Connection (revert to default)
                </button>
            </form>
        <?php endif; ?>

    </div>
</div>

<script nonce="<?= csp_nonce() ?>">
document.getElementById('testConnBtnCt')?.addEventListener('click', function () { testConnection('ct_'); });
</script>
