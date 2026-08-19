<?php
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$old = $_SESSION['old'] ?? [];
unset($_SESSION['old']);

$visitor = $visitor ?? null;
?>

<div class="form-group">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>Create New Visit</h4>
        <a href="/visits" class="btn btn-secondary btn-sm">Back</a>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>">
            <?= htmlspecialchars($flash['message']) ?>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">

            <form method="POST" action="/visits">
                <?= csrf_field() ?>

                <!-- VISITOR NAME -->
                <div class="mb-3">
                    <label class="form-label">Visitor</label>

                    <?php if ($visitor): ?>
                        <input type="text"
                               class="form-control"
                               value="<?= htmlspecialchars($visitor['first_name'] . ' ' . $visitor['last_name']) ?>"
                               readonly>

                        <input type="hidden"
                               name="visitor_id"
                               value="<?= (int) $visitor['id'] ?>">
                    <?php else: ?>
                        <div class="alert alert-warning">
                            No visitor selected.
                        </div>
                    <?php endif; ?>
                </div>

        <div class="mb-3">
    <label class="form-label">Host</label>
    <select name="host_user_id"
            id="hostSelect"
            class="form-select"
            required>

        <option value="">Select host</option>

        <?php foreach ($hosts ?? [] as $host): ?>
            <option value="<?= (int) $host['id'] ?>"
                    data-department="<?= (int) ($host['department_id'] ?? 0) ?>"
                <?= (($old['host_user_id'] ?? '') == $host['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars(
                    trim(($host['first_name'] ?? '') . ' ' . ($host['last_name'] ?? ''))
                ) ?>
            </option>
        <?php endforeach; ?>

    </select>
</div>

                <!-- DEPARTMENT (AUTO SELECTED) -->
                <div class="mb-3">
                    <label class="form-label">Department</label>
                    <select name="department_id"
                            id="departmentSelect"
                            class="form-select"
                            required>
                        <option value="">Select department</option>
                        <?php foreach ($departments ?? [] as $dept): ?>
                            <option value="<?= $dept['id'] ?>">
                                <?= htmlspecialchars($dept['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- VISIT TYPE -->
                <div class="mb-3">
                    <label class="form-label">Visit Type</label>
                    <select name="visit_type_id" id="visitTypeSelect" class="form-select">
                        <option value="">Select type</option>
                        <?php foreach ($visitTypes ?? [] as $type): ?>
                            <option value="<?= $type['id'] ?>" data-name="<?= htmlspecialchars(strtolower($type['name'])) ?>">
                                <?= htmlspecialchars($type['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- PURPOSE -->
                <div class="mb-3">
                    <label class="form-label">Purpose</label>
                    <textarea name="purpose"
                              class="form-control"
                              rows="3"
                              required><?= htmlspecialchars($old['purpose'] ?? '') ?></textarea>
                </div>

                <!-- CONTRACTOR-SPECIFIC FIELDS (shown for any visit type, but especially relevant for Contractor —
                     highlighted/auto-revealed when that type is selected) -->
                <div class="mb-3" id="contractReferenceField">
                    <label class="form-label">Contract / PO / Work Order Reference <small class="text-muted">(optional)</small></label>
                    <input type="text"
                           name="contract_reference"
                           class="form-control"
                           maxlength="150"
                           placeholder="e.g. PO-2026-0142"
                           value="<?= htmlspecialchars($old['contract_reference'] ?? '') ?>">
                </div>

                <div class="mb-3 form-check" id="escortRequiredField">
                    <input type="hidden" name="escort_required" value="0">
                    <input type="checkbox"
                           name="escort_required"
                           value="1"
                           class="form-check-input"
                           id="escortRequiredCheckbox"
                           <?= !empty($old['escort_required']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="escortRequiredCheckbox">
                        Must be escorted while on-site
                    </label>
                </div>

                <!-- EXPECTED IN -->
                <div class="mb-3">
                    <label class="form-label">Expected Check-In</label>
                    <input type="datetime-local"
                           name="expected_in"
                           class="form-control"
                           value="<?= htmlspecialchars($old['expected_in'] ?? '') ?>">
                </div>

                <!-- EXPECTED OUT -->
                <div class="mb-3">
                    <label class="form-label">Expected Check-Out</label>
                    <input type="datetime-local"
                           name="expected_out"
                           class="form-control"
                           value="<?= htmlspecialchars($old['expected_out'] ?? '') ?>">
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-primary">
                        Create Visit
                    </button>
                </div>

            </form>

        </div>
    </div>

</div>

<!-- AUTO SELECT DEPARTMENT SCRIPT -->
<script nonce="<?= csp_nonce() ?>">
document.addEventListener('DOMContentLoaded', function () {

    const hostSelect = document.getElementById('hostSelect');
    const deptSelect = document.getElementById('departmentSelect');

    function syncDepartment() {
        const selected = hostSelect.options[hostSelect.selectedIndex];
        if (!selected) return;

        const departmentId = selected.getAttribute('data-department');

        if (departmentId && deptSelect) {
            deptSelect.value = departmentId;
        }
    }

    // On host change
    hostSelect.addEventListener('change', syncDepartment);

    // On page load (important for validation reload)
    if (hostSelect.value) {
        syncDepartment();
    }

});

// Contractor-specific fields (contract reference, escort required) are
// most relevant for Contractor visits — highlight them by default
// visibility (always usable for any type) but visually de-emphasize
// them unless Contractor is selected, so the common case (a regular
// Business/Personal visit) doesn't have to look at fields it rarely
// needs.
function toggleContractorFields() {
    const select = document.getElementById('visitTypeSelect');
    const option = select.options[select.selectedIndex];
    const isContractor = option && option.getAttribute('data-name') === 'contractor';

    const refField    = document.getElementById('contractReferenceField');
    const escortField = document.getElementById('escortRequiredField');

    [refField, escortField].forEach(function (field) {
        if (!field) return;
        field.style.opacity = isContractor ? '1' : '0.6';
    });
}
document.addEventListener('DOMContentLoaded', function () {
    toggleContractorFields();
    // FIX: was solely relying on an inline onchange="" attribute on
    // the select, which this app's CSP silently blocks — the fields
    // never actually updated when you changed the visit type after
    // page load, only matched the very first initial render.
    document.getElementById('visitTypeSelect')?.addEventListener('change', toggleContractorFields);
});
</script>