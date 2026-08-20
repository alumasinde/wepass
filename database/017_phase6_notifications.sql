-- WEPASS | Phase 6: Notifications & Integrations
-- MySQL 8.0+ | tenant database

CREATE TABLE IF NOT EXISTS notification_templates (
  id bigint unsigned NOT NULL AUTO_INCREMENT,
  event_code varchar(100) NOT NULL,
  channel varchar(30) NOT NULL,
  subject varchar(255) DEFAULT NULL,
  body_template text NOT NULL,
  is_active tinyint(1) NOT NULL DEFAULT 1,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at datetime DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_notification_template (event_code, channel),
  KEY idx_notification_template_active (is_active, event_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
  id bigint unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint unsigned DEFAULT NULL,
  event_code varchar(100) NOT NULL,
  title varchar(255) NOT NULL,
  body text NOT NULL,
  data_json json DEFAULT NULL,
  read_at datetime DEFAULT NULL,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notifications_user_unread (user_id, read_at, created_at),
  KEY idx_notifications_event (event_code, created_at),
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_outbox (
  id bigint unsigned NOT NULL AUTO_INCREMENT,
  idempotency_key varchar(190) NOT NULL,
  notification_id bigint unsigned DEFAULT NULL,
  event_code varchar(100) NOT NULL,
  channel varchar(30) NOT NULL,
  recipient varchar(255) NOT NULL,
  subject varchar(255) DEFAULT NULL,
  body text NOT NULL,
  payload_json json DEFAULT NULL,
  status varchar(30) NOT NULL DEFAULT 'pending',
  attempts int unsigned NOT NULL DEFAULT 0,
  available_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  locked_at datetime DEFAULT NULL,
  sent_at datetime DEFAULT NULL,
  failed_at datetime DEFAULT NULL,
  last_error text DEFAULT NULL,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at datetime DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_notification_outbox_idempotency (idempotency_key),
  KEY idx_notification_outbox_dispatch (status, available_at, id),
  KEY idx_notification_outbox_notification (notification_id),
  CONSTRAINT fk_notification_outbox_notification FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_preferences (
  id bigint unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint unsigned NOT NULL,
  event_code varchar(100) NOT NULL,
  channel varchar(30) NOT NULL,
  is_enabled tinyint(1) NOT NULL DEFAULT 1,
  updated_at datetime DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_notification_preference (user_id, event_code, channel),
  KEY idx_notification_preference_event (event_code, channel),
  CONSTRAINT fk_notification_preferences_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
