-- ============================================================
-- WePass / GPMS | 020_phase3_scan_idempotency.sql
-- Prevent concurrent scanner requests from processing the same
-- request_id more than once.
-- ============================================================

ALTER TABLE `gate_scan_events`
  MODIFY COLUMN `result` enum('processing','allowed','denied','error') NOT NULL,
  ADD COLUMN `claimed_at` datetime DEFAULT NULL AFTER `scanned_at`;

CREATE INDEX `idx_scan_processing`
  ON `gate_scan_events` (`result`, `claimed_at`);
