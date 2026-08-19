-- ============================================================
-- Glee GPMS  |  008_delegation.sql
-- Incremental migration — run via: php database/migrate_tenants.php
-- (added to the INCREMENTAL_MIGRATIONS allowlist in that script)
--
-- Adds approver delegation: any user can name a backup ("while I'm
-- away, X acts for me") for a date range. When
-- ApprovalService::createApprovalsForStep() builds the eligible-
-- approver list for a step (either assignment mode — role_department
-- or explicit), anyone with an active delegate right now is swapped
-- out for their delegate instead of being left eligible themselves —
-- so a tagged Security Manager or GM on leave doesn't silently stall
-- the workflow the same way an unassigned role did before.
--
-- One row per user (unique on user_id) — a user has at most one
-- active/scheduled delegate at a time; saving a new one replaces the
-- old. Self-service: every user manages their own row, gated by
-- session identity in DelegationController, not by a permission —
-- there's nothing to seed here.
--
-- MySQL 8.0+  |  utf8mb4_unicode_ci
-- Safe to re-run: guarded the same way as every migration since 005.
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `user_delegates` (
  `id`                bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id`           bigint unsigned NOT NULL,
  `delegate_user_id`  bigint unsigned NOT NULL,
  `starts_at`         datetime        NOT NULL,
  `ends_at`           datetime        NOT NULL,
  `created_at`        datetime        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_delegate` (`user_id`),
  KEY `idx_ud_delegate` (`delegate_user_id`),
  KEY `idx_ud_active_window` (`user_id`, `starts_at`, `ends_at`),
  CONSTRAINT `fk_ud_user`     FOREIGN KEY (`user_id`)          REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ud_delegate` FOREIGN KEY (`delegate_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
