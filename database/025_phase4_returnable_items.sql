-- Phase 4: returnable-item lifecycle and immutable return history
-- MySQL 8.0+

CREATE TABLE IF NOT EXISTS gatepass_item_returns (
    id bigint unsigned NOT NULL AUTO_INCREMENT,
    gatepass_item_id bigint unsigned NOT NULL,
    gatepass_id bigint unsigned NOT NULL,
    quantity_returned int unsigned NOT NULL,
    actor_user_id bigint unsigned DEFAULT NULL,
    return_reference varchar(120) DEFAULT NULL,
    notes varchar(500) DEFAULT NULL,
    returned_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_gpir_item_date (gatepass_item_id, returned_at),
    KEY idx_gpir_gatepass_date (gatepass_id, returned_at),
    CONSTRAINT fk_gpir_item FOREIGN KEY (gatepass_item_id) REFERENCES gatepass_items(id) ON DELETE RESTRICT,
    CONSTRAINT fk_gpir_gatepass FOREIGN KEY (gatepass_id) REFERENCES gatepasses(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_gatepass_items_returnable_state
    ON gatepass_items (gatepass_id, is_returnable, returned_quantity);
