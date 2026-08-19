-- Phase 4: explicit terminal RETURNED gatepass state.
-- Idempotent for existing tenant databases.

INSERT IGNORE INTO gatepass_statuses (name, code)
VALUES ('Returned', 'returned');
