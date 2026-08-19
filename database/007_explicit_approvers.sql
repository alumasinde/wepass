-- ============================================================
-- Glee GPMS  |  007_explicit_approvers.sql
-- Incremental migration — run via:
--   php database/migrate_tenants.php
-- (added to the INCREMENTAL_MIGRATIONS allowlist in that script)
--
-- Fixes the root cause behind a workflow silently stalling with no
-- approval ever created: `workflow_steps.department_id` was
-- overloaded — NULL meant "fall back to the gatepass's own
-- department", with no way to express "any department" (needed for
-- company-wide roles like a GM who might sit in Finance or the
-- Executive Office, not the department that filed the request).
--
-- Adds:
--   1. workflow_steps.assignment_type  — 'role_department' (today's
--      dynamic role+department matching, kept as the default for
--      every existing step so nothing changes behavior on upgrade)
--      or 'explicit' (new — eligible approvers are an explicit,
--      admin-picked list of users, department-agnostic).
--   2. workflow_steps.department_scope — only meaningful when
--      assignment_type = 'role_department'. 'same_as_request' (the
--      ORIGINAL fallback behavior, now explicit instead of implicit
--      — safe default, preserves existing steps' intent exactly),
--      'fixed' (use the step's own department_id, e.g. a dedicated
--      desk that clears every department's requests), or 'any' (role
--      match only, no department filter at all).
--   3. workflow_steps.approval_rule — 'all' (unanimous — every
--      eligible approver at the step must act, today's only
--      behavior, kept as the default) or 'any' (first eligible
--      approver to act resolves the step; the rest are marked
--      'skipped' instead of sitting pending forever).
--   4. workflow_step_approvers — the explicit approver list table.
--   5. gatepass_approvals.status gains a 'skipped' value, used when
--      an 'any'-rule step is resolved by one approver (the others'
--      pending rows are closed out) and when a step is rejected
--      (any other pending approvers at that point no longer have
--      anything to act on).
--
-- This migration ONLY changes schema/defaults — it does not touch
-- any tenant's existing workflow_steps DATA. Every existing step
-- keeps assignment_type='role_department', department_scope=
-- 'same_as_request', approval_rule='all', which reproduces its
-- current real-world behavior exactly (just via an explicit column
-- now, not a silent code fallback). Re-pointing a specific step to
-- 'explicit' and tagging its approvers is a deliberate admin action
-- via Settings → Workflows → Steps → Assign Approvers, done per
-- tenant, not by this migration.
--
-- MySQL 8.0+  |  utf8mb4_unicode_ci
-- Safe to re-run: every ADD COLUMN/TABLE is guarded the same way as
-- 005/006 (a "Duplicate"/"exists" error is caught and skipped by
-- every runner, including migrate_tenants.php).
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ── 1. New columns on workflow_steps ────────────────────────

ALTER TABLE `workflow_steps` ADD COLUMN `assignment_type` ENUM('role_department','explicit') NOT NULL DEFAULT 'role_department' AFTER `department_id`;
ALTER TABLE `workflow_steps` ADD COLUMN `department_scope` ENUM('same_as_request','fixed','any') NOT NULL DEFAULT 'same_as_request' AFTER `assignment_type`;
ALTER TABLE `workflow_steps` ADD COLUMN `approval_rule` ENUM('all','any') NOT NULL DEFAULT 'all' AFTER `department_scope`;

-- ── 2. Explicit approver assignment table ───────────────────

CREATE TABLE IF NOT EXISTS `workflow_step_approvers` (
  `id`                bigint unsigned NOT NULL AUTO_INCREMENT,
  `workflow_step_id`  bigint unsigned NOT NULL,
  `user_id`           bigint unsigned NOT NULL,
  `created_at`        datetime        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_step_user` (`workflow_step_id`, `user_id`),
  KEY `idx_wsa_user` (`user_id`),
  CONSTRAINT `fk_wsa_step` FOREIGN KEY (`workflow_step_id`) REFERENCES `workflow_steps` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_wsa_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 3. 'skipped' status for approvals resolved by someone else's ──
--       action at the same step (any-rule resolution, or rejection)

ALTER TABLE `gatepass_approvals` MODIFY COLUMN `status` ENUM('pending','approved','rejected','skipped') NOT NULL DEFAULT 'pending';

SET FOREIGN_KEY_CHECKS = 1;
