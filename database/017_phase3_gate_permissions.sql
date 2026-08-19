-- ============================================================
-- 017_phase3_gate_permissions.sql
-- Phase 3 authorization vocabulary. Additive only.
-- Permissions are NOT granted to existing non-admin roles here.
-- ============================================================

INSERT INTO modules (name)
VALUES ('gates'), ('devices'), ('scans')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO actions (name)
VALUES ('view'), ('create'), ('update'), ('disable'),
       ('approve'), ('revoke'), ('assign'), ('scan'), ('export')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT IGNORE INTO permissions (action_id, module_id)
SELECT a.id, m.id
FROM actions a
CROSS JOIN modules m
WHERE
    (m.name = 'gates' AND a.name IN ('view', 'create', 'update', 'disable'))
    OR
    (m.name = 'devices' AND a.name IN ('view', 'approve', 'revoke', 'assign'))
    OR
    (m.name = 'scans' AND a.name IN ('view', 'scan', 'export'));
