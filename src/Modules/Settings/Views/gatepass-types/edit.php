<?php
$workflows = $workflows ?? [];
$type = $type ?? null;

if (!$type) {
    return;
}
?>

<div id="editGatepassModal" class="modal-overlay">
    <div class="modal">

        <h3>Edit Gatepass Type</h3>

        <form id="editGatepassForm">

            <input type="hidden" id="gp_id" value="<?= (int)$type->id ?>">

            <div class="form-group">
                <label>Name</label>
                <input type="text" id="gp_name_edit" class="form-control"
                       value="<?= htmlspecialchars($type->name) ?>" required>
            </div>

            <div class="form-group">
                <label>Code</label>
                <input type="text" id="gp_code_edit" class="form-control"
                       value="<?= htmlspecialchars($type->code ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="gp_direction_edit">Direction</label>
                <select id="gp_direction_edit" class="form-control">
                    <option value="outbound" <?= ($type->direction ?? 'outbound') === 'outbound' ? 'selected' : '' ?>>
                        Outbound — something leaves first, optionally returns later
                    </option>
                    <option value="inbound" <?= ($type->direction ?? 'outbound') === 'inbound' ? 'selected' : '' ?>>
                        Inbound — something arrives first, leaves again later
                    </option>
                </select>
                <small class="text-muted">
                    Outbound example: equipment loaned out. Inbound example: a contractor's own tools,
                    or a visitor's personal laptop, brought on-site and taken away again.
                </small>
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" id="gp_checkin_edit"
                        <?= !empty($type->allowedActions['checkin']) ? 'checked' : '' ?>>
                    Allow Check-in
                </label>
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" id="gp_checkout_edit"
                        <?= !empty($type->allowedActions['checkout']) ? 'checked' : '' ?>>
                    Allow Check-out
                </label>
            </div>

            <div class="form-group">
                <label>Workflow</label>
                <select id="gp_workflow_edit" class="form-control">
                    <option value="">-- None --</option>

                    <?php foreach ($workflows as $wf): ?>
                        <option value="<?= (int)$wf['id'] ?>"
                            <?= ($type->workflowId == $wf['id']) ? 'selected' : '' ?>>
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
                    <input type="checkbox" id="gp_requires_approval_edit"
                        <?= !empty($type->requiresApproval) ? 'checked' : '' ?>>
                    Requires Approval
                </label>
                <small class="form-text text-muted">
                    Whether a gatepass of this type needs to go through the approval workflow before it's
                    valid — set here, per type, not chosen by whoever creates an individual gatepass.
                </small>
            </div>

            <div id="gp_rule_summary" class="alert alert-info" style="font-size:0.875rem;"></div>

            <div class="form-group">
                <label>
                    <input type="checkbox" id="gp_active_edit"
                        <?= $type->isActive ? 'checked' : '' ?>>
                    Active
                </label>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-secondary" id="gp_cancel_edit_btn">Cancel</button>
                <button type="submit" class="btn btn-primary" id="gp_update_btn">Update</button>
            </div>

        </form>

    </div>
</div>

<script nonce="<?= csp_nonce() ?>">
    const GP_CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;

    // The tenant's current Gatepass Rules (Settings -> Gatepass Rules),
    // used only to render the live summary below — never sent back to
    // the server from here, this form doesn't edit these rules.
    const GP_WORKFLOW_RULES = <?= json_encode($workflowRules ?? \App\Modules\Gatepass\Services\GatepassWorkflow::DEFAULT_RULES) ?>;

    // Phase 1 of simplifying Types/Workflows/Rules: every active
    // workflow's actual steps, keyed by workflow_id, so the summary
    // shows the real approval chain instead of just a name.
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

    document.addEventListener('DOMContentLoaded', function () {
        // FIX 1: Show the modal immediately on page load
        document.getElementById('editGatepassModal').style.display = 'flex';

        // FIX: these were previously bound via inline onsubmit/onclick
        // attributes, which the app's CSP silently blocks (nonces only
        // authorize <script> tags, not inline event attributes) — this
        // form could never actually submit via JS at all. Binding
        // properly here is what actually makes Save/Cancel work.
        document.getElementById('editGatepassForm')?.addEventListener('submit', submitEditGatepass);
        document.getElementById('gp_cancel_edit_btn')?.addEventListener('click', closeEditGatepass);

        document.getElementById('gp_checkin_edit')?.addEventListener('change', updateSummary);
        document.getElementById('gp_checkout_edit')?.addEventListener('change', updateSummary);
        document.getElementById('gp_direction_edit')?.addEventListener('change', updateSummary);
        document.getElementById('gp_workflow_edit')?.addEventListener('change', updateSummary);
        document.getElementById('gp_requires_approval_edit')?.addEventListener('change', updateSummary);

        function updateSummary() {
            gpUpdateRuleSummary('gp_checkin_edit', 'gp_checkout_edit', 'gp_rule_summary', 'gp_direction_edit', 'gp_workflow_edit', 'gp_requires_approval_edit');
        }
        updateSummary();
    });

    function closeEditGatepass() {
        // FIX 2: redirect on close instead of just hiding,
        // since this is a dedicated page not an overlay on top of another page
        window.location.href = '/settings/gatepass-types';
    }

    async function submitEditGatepass(event) {
        event.preventDefault();

        const btn = document.getElementById('gp_update_btn');
        btn.disabled = true;
        btn.innerText = 'Saving...';

        const id = document.getElementById('gp_id').value;
        const name = document.getElementById('gp_name_edit').value.trim();
        const code = document.getElementById('gp_code_edit').value.trim();
        const checkin = document.getElementById('gp_checkin_edit').checked;
        const checkout = document.getElementById('gp_checkout_edit').checked;
        const workflowId = document.getElementById('gp_workflow_edit').value || null;
        const requiresApproval = document.getElementById('gp_requires_approval_edit').checked;
        const isActive = document.getElementById('gp_active_edit').checked;
        const direction = document.getElementById('gp_direction_edit').value;

        try {
            const response = await fetch(`/settings/gatepass-types/${id}/update`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    csrf_token: GP_CSRF_TOKEN,
                    name, code, checkin, checkout,
                    workflow_id: workflowId,
                    requires_approval: requiresApproval,
                    is_active: isActive,
                    direction: direction
                })
            });

            const contentType = response.headers.get('content-type') || '';

            if (!contentType.includes('application/json')) {
                const text = await response.text();
                console.error(text);
                throw new Error('Invalid server response');
            }

            // FIX 3: parse JSON before checking ok, so error messages from the
            // server are available even on non-2xx responses
            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || data.error || 'Update failed');
            }

            location.reload();

        } catch (error) {
            alert(error.message);
        } finally {
            // FIX 4: always re-enable button (was missing in original)
            btn.disabled = false;
            btn.innerText = 'Update';
        }
    }
</script>