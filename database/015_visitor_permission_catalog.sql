-- ============================================================
-- 015_visitor_permission_catalog.sql
-- Visitor authorization vocabulary for Phase 2 hardening.
-- Additive only: no existing role receives new privileges.
-- ============================================================

INSERT INTO modules (name)
VALUES ('visitors')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO actions (name)
VALUES ('view'), ('view_all'), ('create'), ('update'), ('update_all'),
       ('delete'), ('blacklist'), ('manage'), ('issue_badge')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT IGNORE INTO permissions (action_id, module_id)
SELECT a.id, m.id
FROM actions a
CROSS JOIN modules m
WHERE m.name = 'visitors'
  AND a.name IN (
      'view', 'view_all', 'create', 'update', 'update_all',
      'delete', 'blacklist', 'manage', 'issue_badge'
  );
