-- Phase 3 operational scan hardening.
-- Adds recovery metadata without changing the public scan contract.
ALTER TABLE `gate_scan_events`
  ADD COLUMN `processing_started_at` datetime DEFAULT NULL AFTER `scanned_at`,
  ADD COLUMN `completed_at` datetime DEFAULT NULL AFTER `processing_started_at`;

CREATE INDEX `idx_scan_processing` ON `gate_scan_events` (`result`, `processing_started_at`);
