-- ============================================================
-- WEPASS | 013_phase4_state_machine.sql
-- Phase 4: authoritative gatepass state history.
--
-- Adds the persistence boundary required by GatepassStateService.
-- Safe to re-run: CREATE TABLE/INDEX operations are guarded.
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `gatepass_state_history` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `gatepass_id` bigint unsigned NOT NULL,
  `from_status_id` bigint unsigned DEFAULT NULL,
  `to_status_id` bigint unsigned NOT NULL,
  `transition_code` varchar(80) NOT NULL,
  `actor_user_id` bigint unsigned DEFAULT NULL,
  `reason` varchar(1000) DEFAULT NULL,
  `metadata_json` json DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_gsh_gatepass_time` (`gatepass_id`,`created_at`),
  KEY `idx_gsh_transition_time` (`transition_code`,`created_at`),
  KEY `idx_gsh_to_status_time` (`to_status_id`,`created_at`),
  CONSTRAINT `fk_gsh_gatepass` FOREIGN KEY (`gatepass_id`) REFERENCES `gatepasses` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_gsh_from_status` FOREIGN KEY (`from_status_id`) REFERENCES `gatepass_statuses` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_gsh_to_status` FOREIGN KEY (`to_status_id`) REFERENCES `gatepass_statuses` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_gsh_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `gatepass_statuses` (`name`,`code`) VALUES
  ('Expired','expired');

SET FOREIGN_KEY_CHECKS = 1;
