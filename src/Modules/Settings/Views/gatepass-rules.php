<?php
$rules    = $rules ?? [];
$statuses = $statuses ?? [];
$flash    = $flash ?? null;

$checkoutSelected = array_map('strtolower', $rules['checkout_statuses'] ?? []);
$checkinSelected  = array_map('strtolower', $rules['checkin_statuses'] ?? []);
?>

<div class="page-header">
    <h1 class="page-heading">
        <i class="fa-solid fa-diagram-project"></i> Gatepass Rules
    </h1>
    <div class="page-actions">
        <a href="/settings" class="btn btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> Back
        </a>
    </div>
</div>

<?php if ($flash): ?>
    <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>">
        <?= htmlspecialchars($flash['message']) ?>
    </div>
<?php endif; ?>

<div class="alert alert-info">
    <i class="fa-solid fa-circle-info"></i>
    These rules control which gatepass statuses allow Check-In or Check-Out to appear at all —
    on top of whatever the gatepass <strong>type</strong> itself already allows (Settings &rarr;
    Gatepass Types). Both have to agree for a button to show. One thing here is never
    configurable: Check-In can never happen before Check-Out has actually occurred — that
    sequencing is enforced regardless of what's selected below.
</div>

<div class="form-card">
    <div class="form-card__body">
        <form method="POST" action="/settings/gatepass-rules">
            <?= csrf_field() ?>

            <div class="gp-rules-columns" style="display:flex;gap:32px;flex-wrap:wrap;">

                <div style="flex:1;min-width:240px;">
                    <h4>Statuses that allow <strong>Check-Out</strong></h4>
                    <p class="text-muted" style="font-size:0.85rem;">
                        A gatepass has to be in one of these statuses before Check-Out is offered.
                    </p>
                    <?php foreach ($statuses as $status): ?>
                        <label class="form-check" style="display:block;margin-bottom:6px;">
                            <input type="checkbox"
                                   name="checkout_statuses[]"
                                   value="<?= htmlspecialchars($status['code']) ?>"
                                   <?= in_array(strtolower($status['code']), $checkoutSelected, true) ? 'checked' : '' ?>>
                            <?= htmlspecialchars($status['name']) ?>
                            <code style="font-size:0.75rem;color:var(--color-text-muted);"><?= htmlspecialchars($status['code']) ?></code>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div style="flex:1;min-width:240px;">
                    <h4>Statuses that allow <strong>Check-In</strong></h4>
                    <p class="text-muted" style="font-size:0.85rem;">
                        Only relevant for returnable gatepasses — a gatepass has to already be in
                        one of these statuses (normally just "Checked Out") before Check-In is offered.
                    </p>
                    <?php foreach ($statuses as $status): ?>
                        <label class="form-check" style="display:block;margin-bottom:6px;">
                            <input type="checkbox"
                                   name="checkin_statuses[]"
                                   value="<?= htmlspecialchars($status['code']) ?>"
                                   <?= in_array(strtolower($status['code']), $checkinSelected, true) ? 'checked' : '' ?>>
                            <?= htmlspecialchars($status['name']) ?>
                            <code style="font-size:0.75rem;color:var(--color-text-muted);"><?= htmlspecialchars($status['code']) ?></code>
                        </label>
                    <?php endforeach; ?>
                </div>

            </div>

            <hr style="margin:20px 0;border-color:#eee;">

            <div class="form-group">
                <label class="form-check">
                    <input type="checkbox" name="checkin_requires_returnable" value="1"
                        <?= !empty($rules['checkin_requires_returnable']) ? 'checked' : '' ?>>
                    Check-In requires the gatepass to be marked <strong>returnable</strong>
                </label>
                <small class="text-muted">
                    On by default, and recommended to leave on — turning this off means Check-In can
                    be offered on a gatepass that was never expected to come back at all.
                </small>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-save"></i> Save Rules
                </button>
            </div>
        </form>
    </div>
</div>
