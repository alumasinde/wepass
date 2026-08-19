-- Phase 4: explicit terminal/administrative states and transition permissions.
INSERT IGNORE INTO gatepass_statuses (id, name, code) VALUES
(8, 'Expired', 'expired');

-- Additive permission vocabulary. Existing role grants are intentionally untouched.
INSERT IGNORE INTO actions (id, name) VALUES
(31, 'cancel'),
(32, 'override');

INSERT IGNORE INTO modules (id, name) VALUES
(18, 'gatepass_workflow');

INSERT IGNORE INTO permissions (action_id, module_id)
SELECT a.id, m.id
FROM actions a
CROSS JOIN modules m
WHERE m.name='gatepass_workflow'
  AND a.name IN ('cancel','override');
