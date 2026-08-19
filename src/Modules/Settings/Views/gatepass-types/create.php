<?php
$workflows     = $workflows ?? [];
$workflowRules = $workflowRules ?? \App\Modules\Gatepass\Services\GatepassWorkflow::DEFAULT_RULES;
?>

<div id="createGatepassModal" class="modal-overlay">
    <div class="modal">

        <h3>Create Gatepass Type</h3>

        <form id="createGatepassForm">

            <div class="form-group">
                <label>Name</label>
                <input type="text" id="gp_name" name="name" class="form-control" required>
            </div>

            <div class="form-group">
                <label>Code</label>
                <input type="text" id="gp_code" name="code" class="form-control">
            </div>

            <div class="form-group">
                <label for="gp_direction">Direction</label>
                <select id="gp_direction" name="direction" class="form-control">
                    <option value="outbound">Outbound — something leaves first, optionally returns later</option>
                    <option value="inbound">Inbound — something arrives first, leaves again later</option>
                </select>
                <small class="text-muted">
                    Outbound example: equipment loaned out. Inbound example: a contractor's own tools,
                    or a visitor's personal laptop, brought on-site and taken away again.
                </small>
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" id="gp_checkin" name="checkin"> Allow Check-in
                </label>
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" id="gp_checkout" name="checkout"> Allow Check-out
                </label>
            </div>

            <div class="form-group">
                <label for="gp_workflow">Workflow</label>

                <select id="gp_workflow" name="workflow_id" class="form-control">
                    <option value="">-- None --</option>

                    <?php foreach ($workflows as $wf): ?>
                        <option value="<?= (int)$wf['id'] ?>">
                            <?= htmlspecialchars($wf['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <?php if (empty($workflows)): ?>
                    <small class="text-muted">No workflows available</small>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" id="gp_requires_approval" name="requires_approval" checked>
                    Requires Approval
                </label>
                <small class="form-text text-muted">
                    Whether a gatepass of this type needs to go through the approval workflow before it's
                    valid. This is set here, per type — NOT chosen by whoever creates an individual gatepass.
                </small>
            </div>

            <div id="gp_rule_summary" class="alert alert-info" style="font-size:0.875rem;"></div>

            <div class="form-group">
                <label>
                    <input type="checkbox" id="gp_active" name="is_active" checked> Active
                </label>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-secondary" id="gp_cancel_create_btn">Cancel</button>
                <button type="submit" class="btn btn-primary" id="gp_submit_btn">Save</button>
            </div>

        </form>

    </div>
</div>

<script nonce="<?= csp_nonce() ?>">
const GP_CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;
const GP_WORKFLOW_RULES = <?= json_encode($workflowRules) ?>;

// Phase 1 of simplifying Types/Workflows/Rules: every active
// workflow's actual steps, keyed by workflow_id, embedded once here
// so the summary can show the real approval chain the instant a
// workflow is picked — no more "just a name in a dropdown."
const GP_WORKFLOW_STEPS_MAP = <?= json_encode($workflowStepsMap ?? []) ?>;

function gpFormatStatusList(codes) {
    if (!codes || codes.length === 0) return 'no statuses (nothing will trigger this)';
    return codes.map(c => c.replace(/_/g, ' ')).join(', ');
}

function gpFormatApprovalChain(workflowId) {
    if (!workflowId) return null;
    const steps = GP_WORKFLOW_STEPS_MAP[workflowId];
    if (!steps || steps.length === 0) {
        return '<em>This workflow has no steps configured yet — approval would have nothing to route to.</em>';
    }

    const scopeLabel = { same_as_request: 'same department as the request', fixed: 'a fixed department', any: 'any department' };

    let html = '<ol style="margin:4px 0 0 18px;padding:0;">';
    steps.forEach(function (step) {
        let who;
        if (step.assignment_type === 'explicit') {
            who = step.approver_names && step.approver_names.length
                ? step.approver_names.join(', ')
                : '<em>no approvers assigned yet — this step would block everything</em>';
        } else {
            who = (step.role_name || 'Unknown role') + ' (' + (scopeLabel[step.department_scope] || step.department_scope) + ')';
        }
        html += '<li>' + who + (step.approval_rule === 'any' ? ' — <em>any one</em> approver' : ' — <em>all</em> approvers') + '</li>';
    });
    html += '</ol>';
    return html;
}

function gpUpdateRuleSummary(checkinCheckboxId, checkoutCheckboxId, summaryElId, directionSelectId, workflowSelectId, requiresApprovalId) {
    const summaryEl = document.getElementById(summaryElId);
    if (!summaryEl) return;

    const checkinAllowed  = document.getElementById(checkinCheckboxId)?.checked;
    const checkoutAllowed = document.getElementById(checkoutCheckboxId)?.checked;
    const direction = document.getElementById(directionSelectId)?.value || 'outbound';
    const workflowId = document.getElementById(workflowSelectId)?.value || '';
    const requiresApproval = document.getElementById(requiresApprovalId)?.checked;

    let html = '<strong>What this means right now:</strong><ul style="margin:8px 0 0 18px;padding:0;">';

    if (direction === 'inbound') {
        html += '<li>Check-In (arrival): ' + (checkinAllowed
            ? 'available once the gatepass is <strong>Approved</strong>'
            : '<strong>disabled</strong> for this type') + '</li>';

        html += '<li>Check-Out (departure): ' + (checkoutAllowed
            ? 'available once it has been <strong>Checked-In</strong>'
            : '<strong>disabled</strong> for this type') + '</li>';

        html += '</ul><small class="text-muted">Inbound sequencing is fixed (arrive, then leave) — not affected by <a href="/settings/gatepass-rules">Settings &rarr; Gatepass Rules</a>, which only applies to Outbound types.</small>';
    } else {
        const checkoutStatuses = gpFormatStatusList(GP_WORKFLOW_RULES.checkout_statuses);
        const checkinStatuses  = gpFormatStatusList(GP_WORKFLOW_RULES.checkin_statuses);
        const requiresReturnable = GP_WORKFLOW_RULES.checkin_requires_returnable;

        html += '<li>Check-Out: ' + (checkoutAllowed
            ? 'available when status is <strong>' + checkoutStatuses + '</strong>'
            : '<strong>disabled</strong> for this type, regardless of status') + '</li>';

        html += '<li>Check-In: ' + (checkinAllowed
            ? 'available when status is <strong>' + checkinStatuses + '</strong>'
                + (requiresReturnable ? ' <em>and</em> the gatepass is marked returnable' : '')
            : '<strong>disabled</strong> for this type, regardless of status') + '</li>';

        html += '</ul><small class="text-muted">Check-in can never happen before check-out — that\'s always enforced. Change the status lists themselves under <a href="/settings/gatepass-rules">Settings &rarr; Gatepass Rules</a>.</small>';
    }

    // The actual approval chain — the core of Phase 1. Previously the
    // only way to know what a workflow does was to leave this screen
    // and check Settings -> Workflows separately.
    html += '<div style="margin-top:10px;padding-top:10px;border-top:1px solid rgba(0,0,0,0.08);">';
    if (!requiresApproval) {
        html += '<strong>Approval:</strong> not required for this type — a gatepass is usable immediately once created.';
    } else if (!workflowId) {
        html += '<strong>Approval:</strong> required, but <strong>no workflow is selected</strong> — this type would have nothing to route approval to. Pick a workflow above.';
    } else {
        html += '<strong>Approval chain:</strong>';
        html += gpFormatApprovalChain(workflowId);
    }
    html += '</div>';

    summaryEl.innerHTML = html;
}

function openCreateModal() {
    document.getElementById('createGatepassModal').hidden = false;
}

function closeCreateGatepass() {
    document.getElementById('createGatepassModal').hidden = true;
}

document.addEventListener('DOMContentLoaded', function () {
    // FIX: these were previously bound via inline onsubmit/onclick
    // attributes, silently blocked by this app's CSP (nonces only
    // authorize <script> tags, not inline event attributes) — this
    // form could never actually submit via JS. Binding here properly
    // is what actually makes Save/Cancel work.
    document.getElementById('createGatepassForm')?.addEventListener('submit', submitCreateGatepass);
    document.getElementById('gp_cancel_create_btn')?.addEventListener('click', closeCreateGatepass);

    document.getElementById('gp_checkin')?.addEventListener('change', updateSummary);
    document.getElementById('gp_checkout')?.addEventListener('change', updateSummary);
    document.getElementById('gp_direction')?.addEventListener('change', updateSummary);
    document.getElementById('gp_workflow')?.addEventListener('change', updateSummary);
    document.getElementById('gp_requires_approval')?.addEventListener('change', updateSummary);

    function updateSummary() {
        gpUpdateRuleSummary('gp_checkin', 'gp_checkout', 'gp_rule_summary', 'gp_direction', 'gp_workflow', 'gp_requires_approval');
    }
    updateSummary();
});

async function submitCreateGatepass(e) {
    e.preventDefault();

    const btn = document.getElementById('gp_submit_btn');
    btn.disabled = true;
    btn.innerText = "Saving...";

    const wfValue = document.getElementById('gp_workflow').value;

    const payload = {
        csrf_token: GP_CSRF_TOKEN,
        name: document.getElementById('gp_name').value.trim(),
        code: document.getElementById('gp_code').value.trim(),
        checkin: document.getElementById('gp_checkin').checked ? 1 : 0,
        checkout: document.getElementById('gp_checkout').checked ? 1 : 0,
        workflow_id: wfValue === '' ? null : parseInt(wfValue, 10),
        requires_approval: document.getElementById('gp_requires_approval').checked ? 1 : 0,
        is_active: document.getElementById('gp_active').checked ? 1 : 0,
        direction: document.getElementById('gp_direction').value
    };

    if (!payload.name) {
        alert('Name is required');
        btn.disabled = false;
        btn.innerText = "Save";
        return;
    }

    try {
        const res = await fetch('/settings/gatepass-types/store', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        const contentType = res.headers.get("content-type") || "";

        if (!contentType.includes("application/json")) {
            const text = await res.text();
            console.error(text);
            throw new Error("Invalid server response");
        }

        const data = await res.json();

        if (!res.ok) {
            throw new Error(data.message || 'Failed to create');
        }

        location.reload();

    } catch (err) {
        alert(err.message);
    } finally {
        btn.disabled = false;
        btn.innerText = "Save";
    }
}
</script>
