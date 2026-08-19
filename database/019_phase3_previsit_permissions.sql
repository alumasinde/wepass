-- ============================================================
-- 019_phase3_previsit_permissions.sql
-- Granular permissions for issuing/revoking pre-visit QR credentials.
-- ============================================================

INSERT INTO modules (name)
VALUES ('visits')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO actions (name)
VALUES ('previsit_qr_issue'), ('previsit_qr_revoke')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT IGNORE INTO permissions (action_id, module_id)
SELECT a.id, m.id
FROM actions a
CROSS JOIN modules m
WHERE m.name = 'visits'
  AND a.name IN ('previsit_qr_issue', 'previsit_qr_revoke');
