-- Phase 3 scan operations permissions.
-- Additive only: existing roles are not implicitly elevated.
INSERT INTO permissions (module_id, action_id, name, description)
SELECT m.id, a.id, x.name, x.description
FROM (SELECT 'scans' module_name, 'view' action_name, 'scans.view' name, 'View gate scan history' description
      UNION ALL SELECT 'scans','export','scans.export','Export gate scan history') x
JOIN modules m ON LOWER(m.name)=x.module_name
JOIN actions a ON LOWER(a.name)=x.action_name
WHERE NOT EXISTS (SELECT 1 FROM permissions p WHERE LOWER(p.name)=x.name);