-- ============================================================
-- Glee GPMS  |  009_type_requires_approval.sql
-- Incremental migration — run via: php database/migrate_tenants.php
-- (added to the INCREMENTAL_MIGRATIONS allowlist in that script)
--
-- Closes a real self-approval bypass: the gatepass create/edit forms
-- had a plain "Needs Approval" checkbox, unguarded by any permission
-- check, that the server trusted directly — GatepassService::create()
-- set the initial status straight to APPROVED and never started a
-- workflow at all if that checkbox was unticked. Any user creating a
-- gatepass (an employee taking equipment out, for instance — exactly
-- the scenario this system exists to control) could self-approve
-- their own request with one click.
--
-- Moves this decision to where it belongs: the gatepass TYPE's
-- configuration (Settings → Gatepass Types, admin-only, gated behind
-- settings.update), not user input on an individual gatepass.
-- GatepassService now looks this up itself and ignores whatever (if
-- anything) a client sends for it.
--
-- Defaults to 1 (requires approval) for every existing type — the
-- safe default, and matches what every currently-seeded type already
-- behaves like today (their linked workflows already run in
-- practice; this migration doesn't change existing behavior for
-- types that were always actually going through approval, only
-- closes the loophole that let a USER skip it per-gatepass).
--
-- MySQL 8.0+  |  utf8mb4_unicode_ci
-- Safe to re-run: guarded the same way as every migration since 005.
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE `gatepass_types` ADD COLUMN `requires_approval` tinyint(1) NOT NULL DEFAULT 1 AFTER `workflow_id`;

SET FOREIGN_KEY_CHECKS = 1;
