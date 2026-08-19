-- ============================================================
-- WEPASS | 014_returnable_item_history.sql
-- Phase 4: append-only return events for gatepass items.
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `gatepass_item_returns` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `gatepass_item_id` bigint unsigned NOT NULL,
  `gatepass_id` bigint unsigned NOT NULL,
  `quantity_returned` int unsigned NOT NULL,
  `actor_user_id` bigint unsigned DEFAULT NULL,
  `return_reference` varchar(150) DEFAULT NULL,
  `notes` varchar(1000) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_gpir_item_time` (`gatepass_item_id`,`created_at`),
  KEY `idx_gpir_gatepass_time` (`gatepass_id`,`created_at`),
  KEY `idx_gpir_actor_time` (`actor_user_id`,`created_at`),
  CONSTRAINT `fk_gpir_item` FOREIGN KEY (`gatepass_item_id`) REFERENCES `gatepass_items` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_gpir_gatepass` FOREIGN KEY (`gatepass_id`) REFERENCES `gatepasses` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_gpir_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
