-- ============================================================
-- Glee GPMS  |  011_visitor_notes.sql
-- Incremental migration — run via: php database/migrate_tenants.php
-- (added to the INCREMENTAL_MIGRATIONS allowlist in that script)
--
-- VisitorRepository::create()/update() already INSERT/UPDATE a
-- `notes` column on `visitors`, and VisitorDTO already carries a
-- $notes property end-to-end — but the column was never actually
-- added to the schema. Every visitor creation/update would throw a
-- SQL error ("Unknown column 'notes'") the moment it reached the
-- database, regardless of whether notes was actually filled in
-- (naming a nonexistent column in an INSERT/UPDATE fails outright,
-- independent of the value). This finishes what was clearly already
-- half-built rather than ripping the half out.
--
-- MySQL 8.0+  |  utf8mb4_unicode_ci
-- Safe to re-run: guarded the same way as every migration since 005.
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE `visitors` ADD COLUMN `notes` text DEFAULT NULL AFTER `company_id`;

SET FOREIGN_KEY_CHECKS = 1;
