-- ============================================================
-- Glee GPMS  |  010_contractor_visits.sql
-- Incremental migration — run via: php database/migrate_tenants.php
-- (added to the INCREMENTAL_MIGRATIONS allowlist in that script)
--
-- Adds a distinct "Contractor" visit type (previously everything
-- non-personal was lumped into generic "Business"), plus two
-- contractor-relevant fields on visits:
--   - contract_reference — a PO/contract/work-order number, so
--     security or facilities can tie a visit back to an actual
--     engagement instead of just a free-text purpose field.
--   - escort_required     — flags that this contractor must be
--     accompanied on-site, for facilities/security to act on.
--
-- Both fields are optional and apply to any visit type, not just
-- Contractor — a "Business" visit occasionally needing an escort
-- isn't blocked from recording that just because it isn't a
-- contractor visit.
--
-- MySQL 8.0+  |  utf8mb4_unicode_ci
-- Safe to re-run: guarded the same way as every migration since 005.
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE `visits` ADD COLUMN `contract_reference` varchar(150) DEFAULT NULL AFTER `purpose`;
ALTER TABLE `visits` ADD COLUMN `escort_required` tinyint(1) NOT NULL DEFAULT 0 AFTER `contract_reference`;

INSERT IGNORE INTO `visit_types` (`name`) VALUES ('Contractor');

SET FOREIGN_KEY_CHECKS = 1;
