-- ============================================================
-- Glee GPMS  |  005_production_readiness.sql
-- Incremental migration — run this on each tenant's DB AFTER
-- 001_schema.sql (and 002_seed.sql if used).
--
-- Adds:
--   - rate_limits   : generic, reusable throttling store used by
--                     App\Core\RateLimiter (login, password reset,
--                     gate scanning, API).
--   - mail_log      : audit trail for every email the system
--                     attempts to send (App\Core\Mailer).
--   - gatepasses.qr_code_path : cached path to the generated QR
--                     image, so gate scanning never depends on
--                     the QR image service being reachable.
--
-- MySQL 8.0+  |  utf8mb4_unicode_ci
-- Safe to re-run: every statement is guarded (IF NOT EXISTS / a
-- checked ALTER), matching the style of 001_schema.sql.
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ── 1. Rate limiting ─────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `rate_limits` (
  `id`           bigint unsigned NOT NULL AUTO_INCREMENT,
  `rl_key`       varchar(190)   NOT NULL,
  `attempts`     int unsigned   NOT NULL DEFAULT 0,
  `reset_at`     datetime       NOT NULL,
  `updated_at`   datetime       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_rl_key` (`rl_key`),
  KEY `idx_rl_reset_at` (`reset_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 2. Mail audit log ────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `mail_log` (
  `id`          bigint unsigned NOT NULL AUTO_INCREMENT,
  `to_email`    varchar(255)   NOT NULL,
  `subject`     varchar(255)   NOT NULL,
  `status`      enum('sent','failed','logged') NOT NULL DEFAULT 'logged',
  `error`       text           DEFAULT NULL,
  `created_at`  datetime       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mail_log_to`      (`to_email`),
  KEY `idx_mail_log_status`  (`status`),
  KEY `idx_mail_log_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 3. Cached QR image path on gatepasses ────────────────────
-- Plain ALTER TABLE, not conditional PREPARE/EXECUTE — see the
-- note in master.sql for why (a real PDO/mysqlnd quirk with
-- running PREPARE/EXECUTE/DEALLOCATE as separate exec() calls).
-- On re-run this throws "Duplicate column", already caught and
-- skipped by every runner.
ALTER TABLE `gatepasses` ADD COLUMN `qr_code_path` varchar(255) DEFAULT NULL AFTER `gatepass_number`;

SET FOREIGN_KEY_CHECKS = 1;
