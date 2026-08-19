-- ============================================================
-- Glee GPMS  |  database/fixups/glee_unstick_gatepass_2.sql
-- ============================================================
-- ONE-OFF, TENANT-SPECIFIC. Run this by hand, once, directly
-- against Glee's own tenant database — NOT part of the numbered
-- migration chain, NOT run by migrate_tenants.php, and NOT safe to
-- run against any other tenant (it references specific row IDs from
-- Glee's data). Requires 007_explicit_approvers.sql to have already
-- been applied (adds the columns/table this script writes to).
--
-- What this fixes:
--   Gatepass GLEE-GP-2026-0001 (id 2) was approved at step 1
--   (Department Head — Lincoln) and advanced to step 2 (Security
--   Approval), but step 2's department_id was NULL and the OLD
--   logic silently required the approver to be in the SAME
--   department as the request (IT). George Okoth, the actual
--   Security Manager, is in the Security department — so zero
--   approvals were ever created for step 2, and the workflow has
--   been silently stalled ever since (workflow_instance id 2,
--   current_step_order = 2, status = 'in_progress', with no
--   pending gatepass_approvals row at all).
--
-- What this script does:
--   1. Re-points workflow_steps id 2 ("Security Approval", workflow
--      1) and id 4 ("Security Manager Approval", workflow 2) to
--      assignment_type = 'explicit', and tags George Okoth (user id
--      3) as the approver for both — matching what you'd get by
--      using the new Settings → Workflows → Steps → Assign
--      Approvers page for these two steps.
--   2. Directly inserts the missing gatepass_approvals row for the
--      already-stalled instance (id 2) so George sees it immediately
--      under My Approvals, without needing to restart the whole
--      gatepass.
--
-- What this does NOT fix:
--   workflow_steps id 3 ("General Manager Approval", workflow 1)
--   will stall the exact same way once step 2 is cleared — nobody
--   currently holds the General Manager role in user_roles at all.
--   Once you have a real GM user, either assign them role_id 5 (role
--   name 'General Manager') for the existing role_department
--   behavior, OR switch step id 3 to assignment_type = 'explicit'
--   and tag them via the new admin page — recommended, since a GM
--   role holder could legitimately sit in any department (Finance,
--   Executive Office, etc.), which is exactly the scenario
--   'explicit' exists for.
-- ============================================================

-- 1. Re-point the two Security-role steps to explicit assignment.
UPDATE workflow_steps
SET assignment_type = 'explicit'
WHERE id IN (2, 4);

-- 2. Tag George Okoth (user id 3) as the approver for both.
--    INSERT IGNORE — safe to re-run, the (workflow_step_id, user_id)
--    pair is unique-keyed.
INSERT IGNORE INTO workflow_step_approvers (workflow_step_id, user_id) VALUES (2, 3);
INSERT IGNORE INTO workflow_step_approvers (workflow_step_id, user_id) VALUES (4, 3);

-- 3. Create the missing pending approval for the already-stalled
--    instance (id 2, currently sitting at step_order 2 with zero
--    approval rows). INSERT IGNORE — safe to re-run.
INSERT IGNORE INTO gatepass_approvals (workflow_instance_id, workflow_step_id, approver_user_id, status)
VALUES (2, 2, 3, 'pending');
