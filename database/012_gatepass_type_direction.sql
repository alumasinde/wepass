-- ============================================================
-- Glee GPMS  |  012_gatepass_type_direction.sql
-- Incremental migration — run via: php database/migrate_tenants.php
-- (added to the INCREMENTAL_MIGRATIONS allowlist in that script)
--
-- Adds a `direction` field to gatepass_types: 'outbound' (default —
-- something leaves first, optionally returns later — the only model
-- that existed before this) or 'inbound' (something arrives first —
-- a contractor's own tools, a visitor's personal laptop — and
-- leaves again later).
--
-- The check-in/check-out SEQUENCING for inbound types is fixed, not
-- configurable through Settings -> Gatepass Rules the way outbound
-- eligibility is — Check-In (arrival) becomes available once
-- Approved, Check-Out (departure) becomes available once Checked-In.
-- Deliberately not routed through the same configurable rules engine
-- as outbound: that engine exists to let a tenant adjust an already
-- fairly open-ended set of status transitions, whereas the inbound
-- case only ever has one sensible shape.
--
-- Defaults every existing type to 'outbound' — zero behavior change
-- for anything already configured; this is purely additive.
--
-- MySQL 8.0+  |  utf8mb4_unicode_ci
-- Safe to re-run: guarded the same way as every migration since 005.
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE `gatepass_types`
    ADD COLUMN `direction` enum('outbound','inbound') NOT NULL DEFAULT 'outbound' AFTER `requires_approval`;

SET FOREIGN_KEY_CHECKS = 1;
