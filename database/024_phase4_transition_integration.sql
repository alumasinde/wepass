-- Phase 4: workflow transition integration support
-- MySQL 8.0+

ALTER TABLE gatepass_state_history
    ADD INDEX idx_gpsh_gatepass_created (gatepass_id, created_at),
    ADD INDEX idx_gpsh_transition_created (transition_code, created_at);

-- Keep workflow-instance status transitions queryable and auditable.
ALTER TABLE gatepass_workflow_instances
    ADD INDEX idx_gwi_gatepass_status (gatepass_id, status),
    ADD INDEX idx_gwi_status_step (status, current_step_order);

-- Explicit cancellation/override metadata belongs to the transition
-- history rather than replacing the current state with opaque flags.
