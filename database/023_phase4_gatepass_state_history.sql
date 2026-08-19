-- Phase 4: immutable gatepass state-transition history and expiry metadata.
CREATE TABLE IF NOT EXISTS gatepass_state_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    gatepass_id BIGINT UNSIGNED NOT NULL,
    from_status_id BIGINT UNSIGNED NULL,
    to_status_id BIGINT UNSIGNED NOT NULL,
    transition_code VARCHAR(64) NOT NULL,
    actor_user_id BIGINT UNSIGNED NULL,
    reason VARCHAR(500) NULL,
    metadata_json JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_gph_gatepass_created (gatepass_id, created_at),
    INDEX idx_gph_transition_created (transition_code, created_at),
    CONSTRAINT fk_gph_gatepass FOREIGN KEY (gatepass_id) REFERENCES gatepasses(id),
    CONSTRAINT fk_gph_from_status FOREIGN KEY (from_status_id) REFERENCES gatepass_statuses(id),
    CONSTRAINT fk_gph_to_status FOREIGN KEY (to_status_id) REFERENCES gatepass_statuses(id),
    CONSTRAINT fk_gph_actor FOREIGN KEY (actor_user_id) REFERENCES users(id)
) ENGINE=InnoDB;

ALTER TABLE gatepasses
    ADD COLUMN IF NOT EXISTS expires_at DATETIME NULL AFTER expected_return_date,
    ADD INDEX IF NOT EXISTS idx_gatepasses_expires_status (expires_at, status_id);
