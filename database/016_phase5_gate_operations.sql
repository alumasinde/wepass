-- WEPASS | Phase 5: Gate Operations + QR Security
-- MySQL 8.0+
-- Additive migration. Existing gate/device tables are retained.

ALTER TABLE approved_devices
    ADD COLUMN approved_at datetime DEFAULT NULL AFTER is_active,
    ADD COLUMN approved_by bigint unsigned DEFAULT NULL AFTER approved_at;

ALTER TABLE approved_devices
    ADD KEY idx_device_approval(approved_at, approved_by);

ALTER TABLE gate_device_assignments
    ADD KEY idx_gda_active_window(gate_id, device_id, is_active, starts_at, ends_at);

ALTER TABLE gate_scan_events
    ADD KEY idx_scan_request_time(request_id, scanned_at),
    ADD KEY idx_scan_qr_time(qr_token_hash, scanned_at);
