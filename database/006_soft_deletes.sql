-- ============================================================
-- Glee GPMS  |  006_soft_deletes.sql
-- Incremental migration — run this on EVERY tenant DB, same way
-- as 005_production_readiness.sql:
--   mysql -u youruser -p your_tenant_db < database/006_soft_deletes.sql
--
-- Why: gatepass_workflow_instances and gatepass_approvals are both
-- ON DELETE CASCADE from gatepasses. GatepassRepository::delete()
-- used to run a real DELETE FROM gatepasses — which meant deleting
-- a gatepass permanently destroyed its entire approval history and
-- audit trail along with it. For a system whose whole point is an
-- audit trail, that's a compliance problem, not just a data-loss
-- one.
--
-- This adds a deleted_at column. GatepassRepository::delete() now
-- sets it instead of issuing a real DELETE, and every read query
-- (findById, findByNumber, findAll, findAllByDepartment) excludes
-- soft-deleted rows. The underlying row — and its approval history
-- — stays in the database and in `audit_logs`, it's just hidden
-- from normal use. Nothing here changes the FK/CASCADE behavior
-- itself; a hard DELETE issued directly against the DB (outside the
-- app) would still cascade exactly as before. That's expected —
-- this migration protects the app's own delete path, not the schema
-- itself.
--
-- MySQL 8.0+  |  utf8mb4_unicode_ci
-- Safe to re-run: on re-run this throws "Duplicate column", already
-- caught and skipped by every runner (same convention as
-- 005_production_readiness.sql).
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE `gatepasses` ADD COLUMN `deleted_at` datetime DEFAULT NULL AFTER `needs_approval`;

-- Speeds up every read query's new `deleted_at IS NULL` filter,
-- and is useful on its own for an admin "trash" / restore view later.
ALTER TABLE `gatepasses` ADD KEY `idx_gatepasses_deleted_at` (`deleted_at`);

SET FOREIGN_KEY_CHECKS = 1;
