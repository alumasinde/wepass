<?php $config = $config ?? []; ?>

<div class="page-header">
    <h1 class="page-heading">
        <i class="fa-solid fa-hashtag"></i> Gatepass Numbering
    </h1>
    <div class="page-actions">
        <a href="/settings" class="btn btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> Back
        </a>
    </div>
</div>

<div class="form-card">
    <div class="form-card__header">Numbering Format</div>
    <div class="form-card__body">
        <form method="POST" action="/settings/gatepass-numbering">
            <?= csrf_field() ?>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Prefix</label>
                    <input type="text" name="prefix" class="form-control"
                           value="<?= e($config['prefix']) ?>" placeholder="e.g. GP">
                </div>
                <div class="form-group">
                    <label class="form-label">Number Padding</label>
                    <input type="number" name="padding" class="form-control" min="1" max="10"
                           value="<?= (int) $config['padding'] ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">
                        <input type="checkbox" name="include_year" value="1" <?= !empty($config['include_year']) ? 'checked' : '' ?>>
                        Include Year
                    </label>
                </div>
                <div class="form-group">
                    <label class="form-label">
                        <input type="checkbox" name="include_month" value="1" <?= !empty($config['include_month']) ? 'checked' : '' ?>>
                        Include Month
                    </label>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">
                        <input type="checkbox" name="reset_yearly" value="1" <?= !empty($config['reset_yearly']) ? 'checked' : '' ?>>
                        Reset Sequence Yearly
                    </label>
                </div>
                <div class="form-group">
                    <label class="form-label">Current Sequence</label>
                    <input type="number" name="sequence" class="form-control" min="1"
                           value="<?= (int) $config['sequence'] ?>">
                    <small class="text-muted">Next gatepass created will use this number, then increment.</small>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-save"></i> Save Settings
                </button>
                <a href="/settings" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
