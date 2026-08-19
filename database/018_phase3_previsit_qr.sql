-- ============================================================
-- 018_phase3_previsit_qr.sql
-- Pre-visit visitor QR credentials for gate-side validation.
-- Raw tokens are never stored.
-- ============================================================

ALTER TABLE `visits`
  ADD COLUMN `previsit_qr_token_hash` char(64) DEFAULT NULL AFTER `visit_status_id`,
  ADD COLUMN `previsit_qr_issued_at` datetime DEFAULT NULL AFTER `previsit_qr_token_hash`,
  ADD COLUMN `previsit_qr_expires_at` datetime DEFAULT NULL AFTER `previsit_qr_issued_at`,
  ADD COLUMN `previsit_qr_revoked_at` datetime DEFAULT NULL AFTER `previsit_qr_expires_at`;

CREATE UNIQUE INDEX `uk_visit_previsit_qr_token_hash`
  ON `visits` (`previsit_qr_token_hash`);

CREATE INDEX `idx_visit_previsit_qr_validity`
  ON `visits` (`previsit_qr_expires_at`, `previsit_qr_revoked_at`);
