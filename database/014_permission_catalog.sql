-- ============================================================
-- 014_permission_catalog.sql
-- Expand the tenant permission vocabulary for Phase 2
-- authorization hardening.
--
-- This migration is additive and does not grant new privileges to
-- existing roles automatically. Existing role assignments remain
-- unchanged; administrators can explicitly assign the new permissions.
-- ============================================================

INSERT INTO modules (name)
VALUES
    ('visits'),
    ('badges'),
    ('reports'),
    ('delegation')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO actions (name)
VALUES
    ('view'),
    ('view_all'),
    ('create'),
    ('checkin'),
    ('checkout'),
    ('issue'),
    ('return'),
    ('export'),
    ('manage')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT IGNORE INTO permissions (action_id, module_id)
SELECT a.id, m.id
FROM actions a
CROSS JOIN modules m
WHERE
    (m.name = 'visits' AND a.name IN ('create', 'view', 'view_all', 'checkin', 'checkout'))
    OR (m.name = 'badges' AND a.name IN ('view', 'issue', 'return'))
    OR (m.name = 'reports' AND a.name IN ('view', 'export'))
    OR (m.name = 'delegation' AND a.name IN ('view', 'manage'));

-- Existing modules also receive the newly defined read/export actions
-- where applicable. INSERT IGNORE keeps this safe on already-seeded DBs.
INSERT IGNORE INTO permissions (action_id, module_id)
SELECT a.id, m.id
FROM actions a
CROSS JOIN modules m
WHERE
    (m.name = 'roles' AND a.name = 'view')
    OR (m.name = 'settings' AND a.name = 'view')
    OR (m.name = 'audit' AND a.name = 'export');
