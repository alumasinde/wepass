-- ============================================================
-- WePass / GPMS | 016_phase3_gate_security.sql
-- Phase 3 foundation: physical gates, approved guard devices,
-- guard-device assignments, QR credentials and scan events.
--
-- Security principles:
--   * QR payloads are opaque random credentials; never expose DB ids.
--   * Only active, non-revoked devices may submit gate scans.
--   * Devices are assigned to specific gates and optionally guards.
--   * Raw device secrets are NEVER stored; only SHA-256 hashes are.
--   * Scan events are append-only application records for audit.
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `gates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `code` varchar(50) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_gate_code` (`code`),
  KEY `idx_gates_active` (`is_active`),
  CONSTRAINT `fk_gates_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `approved_devices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `device_uuid` varchar(191) NOT NULL,
  `device_name` varchar(150) NOT NULL,
  `platform` enum('android','ios','web') NOT NULL DEFAULT 'android',
  `app_version` varchar(50) DEFAULT NULL,
  `device_secret_hash` char(64) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_seen_at` datetime DEFAULT NULL,
  `approved_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `approved_by` bigint unsigned DEFAULT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `revoked_by` bigint unsigned DEFAULT NULL,
  `revoke_reason` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_device_uuid` (`device_uuid`),
  UNIQUE KEY `uk_device_secret_hash` (`device_secret_hash`),
  KEY `idx_devices_active` (`is_active`, `revoked_at`),
  KEY `idx_devices_last_seen` (`last_seen_at`),
  CONSTRAINT `fk_devices_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_devices_revoked_by` FOREIGN KEY (`revoked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `gate_device_assignments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `gate_id` bigint unsigned NOT NULL,
  `device_id` bigint unsigned NOT NULL,
  `guard_user_id` bigint unsigned DEFAULT NULL,
  `starts_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ends_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_gda_gate_active` (`gate_id`, `is_active`, `starts_at`, `ends_at`),
  KEY `idx_gda_device_active` (`device_id`, `is_active`, `starts_at`, `ends_at`),
  KEY `idx_gda_guard_active` (`guard_user_id`, `is_active`, `starts_at`, `ends_at`),
  CONSTRAINT `fk_gda_gate` FOREIGN KEY (`gate_id`) REFERENCES `gates` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gda_device` FOREIGN KEY (`device_id`) REFERENCES `approved_devices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gda_guard` FOREIGN KEY (`guard_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_gda_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `gate_scan_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `gate_id` bigint unsigned NOT NULL,
  `device_id` bigint unsigned NOT NULL,
  `guard_user_id` bigint unsigned DEFAULT NULL,
  `gatepass_id` bigint unsigned DEFAULT NULL,
  `visit_id` bigint unsigned DEFAULT NULL,
  `scan_type` enum('checkin','checkout','validation','denied') NOT NULL,
  `result` enum('allowed','denied','error') NOT NULL,
  `reason_code` varchar(80) DEFAULT NULL,
  `request_id` varchar(100) NOT NULL,
  `qr_token_hash` char(64) DEFAULT NULL,
  `scanned_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `client_ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `metadata_json` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_scan_request` (`request_id`),
  KEY `idx_scan_gate_time` (`gate_id`, `scanned_at`),
  KEY `idx_scan_device_time` (`device_id`, `scanned_at`),
  KEY `idx_scan_guard_time` (`guard_user_id`, `scanned_at`),
  KEY `idx_scan_gatepass_time` (`gatepass_id`, `scanned_at`),
  KEY `idx_scan_result_time` (`result`, `scanned_at`),
  CONSTRAINT `fk_scan_gate` FOREIGN KEY (`gate_id`) REFERENCES `gates` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_scan_device` FOREIGN KEY (`device_id`) REFERENCES `approved_devices` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_scan_guard` FOREIGN KEY (`guard_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_scan_gatepass` FOREIGN KEY (`gatepass_id`) REFERENCES `gatepasses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_scan_visit` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- QR credentials are random opaque values. The database stores only
-- the SHA-256 digest, so a DB read cannot be used to recreate a QR.
ALTER TABLE `gatepasses`
  ADD COLUMN `qr_token_hash` char(64) DEFAULT NULL AFTER `gatepass_number`,
  ADD COLUMN `qr_expires_at` datetime DEFAULT NULL AFTER `qr_token_hash`,
  ADD COLUMN `qr_issued_at` datetime DEFAULT NULL AFTER `qr_expires_at`,
  ADD COLUMN `qr_revoked_at` datetime DEFAULT NULL AFTER `qr_issued_at`;

CREATE UNIQUE INDEX `uk_gatepass_qr_token_hash`
  ON `gatepasses` (`qr_token_hash`);

CREATE INDEX `idx_gatepass_qr_validity`
  ON `gatepasses` (`qr_expires_at`, `qr_revoked_at`);

SET FOREIGN_KEY_CHECKS = 1;
