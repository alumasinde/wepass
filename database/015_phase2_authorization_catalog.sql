-- ============================================================
-- 015_phase2_authorization_catalog.sql
-- Complete the granular authorization vocabulary used by the
-- Phase 2 route and object-level enforcement.
--
-- Additive only. This migration does not grant permissions to
-- existing roles automatically.
-- ============================================================

INSERT INTO modules (name)
VALUES ('users'), ('visitors')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO actions (name)
VALUES ('view'), ('view_all'), ('create'), ('update'), ('update_all'),
       ('delete'), ('disable'), ('blacklist'), ('issue_badge')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT IGNORE INTO permissions (action_id, module_id)
SELECT a.id, m.id
FROM actions a
CROSS JOIN modules m
WHERE
    (m.name = 'users' AND a.name IN ('view', 'create', 'update', 'disable'))
    OR
    (m.name = 'visitors' AND a.name IN ('view', 'view_all', 'create', 'update', 'update_all', 'delete', 'blacklist', 'issue_badge'));

-- Complete existing module vocabulary where the application already
-- distinguishes read-all or management operations.
INSERT IGNORE INTO permissions (action_id, module_id)
SELECT a.id, m.id
FROM actions a
CROSS JOIN modules m
WHERE
    (m.name = 'roles' AND a.name IN ('view', 'create', 'update', 'assign'))
    OR (m.name = 'settings' AND a.name IN ('view', 'update'))
    OR (m.name = 'reports' AND a.name IN ('view', 'export'))
    OR (m.name = 'audit' AND a.name IN ('view', 'export'))
    OR (m.name = 'delegation' AND a.name IN ('view', 'manage'));
