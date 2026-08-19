-- ============================================================
-- Glee GPMS  |  001_schema.sql
-- Per-tenant database schema — run this on each tenant's DB.
-- Tenant identity is implicit: each tenant has their own DB.
-- The master_db (separate) holds the tenants table and routes
-- connections to the correct tenant database.
--
-- MySQL 8.0+  |  utf8mb4_unicode_ci
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE            = 'NO_AUTO_VALUE_ON_ZERO';

-- ── 1. Lookup tables (no foreign keys) ──────────────────────

CREATE TABLE IF NOT EXISTS `actions` (
  `id`   bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120)   NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_action_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `modules` (
  `id`   bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120)   NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_module_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `permissions` (
  `id`        bigint unsigned NOT NULL AUTO_INCREMENT,
  `action_id` bigint unsigned NOT NULL,
  `module_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_perm_action_module` (`action_id`, `module_id`),
  KEY `idx_perm_module` (`module_id`),
  CONSTRAINT `fk_perm_action` FOREIGN KEY (`action_id`) REFERENCES `actions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_perm_module` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 2. Tenant settings ───────────────────────────────────────
-- tenant_id lives in master_db.tenants; settings are stored
-- per-DB so no tenant_id column is needed here.

CREATE TABLE IF NOT EXISTS `tenant_settings` (
  `id`            bigint unsigned NOT NULL AUTO_INCREMENT,
  `setting_key`   varchar(150)   NOT NULL,
  `config_json`   json           NOT NULL,
  `setting_value` json           DEFAULT NULL,
  `updated_at`    timestamp      NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ts_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 3. Roles & permissions ───────────────────────────────────

CREATE TABLE IF NOT EXISTS `roles` (
  `id`   bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120)   NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_role_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `role_permissions` (
  `role_id`       bigint unsigned NOT NULL,
  `permission_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`role_id`, `permission_id`),
  KEY `idx_rp_permission` (`permission_id`),
  CONSTRAINT `fk_rp_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rp_role`       FOREIGN KEY (`role_id`)       REFERENCES `roles`       (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 4. Departments ───────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `departments` (
  `id`         bigint unsigned NOT NULL AUTO_INCREMENT,
  `name`       varchar(150)   NOT NULL,
  `code`       varchar(50)    NOT NULL,
  `is_active`  tinyint(1)     NOT NULL DEFAULT 1,
  `created_at` datetime       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_dept_code`   (`code`),
  KEY `idx_depts_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 5. Users ─────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `users` (
  `id`            bigint unsigned NOT NULL AUTO_INCREMENT,
  `email`         varchar(255)   NOT NULL,
  `password_hash` varchar(255)   NOT NULL,
  `first_name`    varchar(120)   NOT NULL,
  `last_name`     varchar(120)   NOT NULL,
  `username`      varchar(120)   NOT NULL,
  `department_id` bigint unsigned DEFAULT NULL,
  `is_active`     tinyint(1)     NOT NULL DEFAULT 1,
  `is_admin`      tinyint(1)     NOT NULL DEFAULT 0,
  `reset_token`   varchar(255)   DEFAULT NULL,
  `reset_expires` datetime       DEFAULT NULL,
  `created_at`    datetime       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    datetime       DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_email`    (`email`),
  UNIQUE KEY `uk_user_username` (`username`),
  KEY `idx_users_active`       (`is_active`),
  KEY `idx_users_dept`         (`department_id`),
  KEY `idx_users_reset_token`  (`reset_token`),
  KEY `idx_users_reset_expires`(`reset_expires`),
  CONSTRAINT `fk_users_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_roles` (
  `user_id` bigint unsigned NOT NULL,
  `role_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`user_id`, `role_id`),
  KEY `idx_ur_role` (`role_id`),
  CONSTRAINT `fk_ur_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ur_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_otps` (
  `id`         bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id`    bigint unsigned NOT NULL,
  `otp_code`   varchar(10)    NOT NULL,
  `purpose`    varchar(50)    NOT NULL DEFAULT 'login',
  `is_used`    tinyint(1)     NOT NULL DEFAULT 0,
  `expires_at` datetime       NOT NULL,
  `used_at`    datetime       DEFAULT NULL,
  `created_at` datetime       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_otp_lookup` (`user_id`, `purpose`, `is_used`, `expires_at`),
  CONSTRAINT `fk_otp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 6. Identification & visitor companies ────────────────────

CREATE TABLE IF NOT EXISTS `identification_types` (
  `id`   bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120)   NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_idtype_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `visitor_companies` (
  `id`         bigint unsigned NOT NULL AUTO_INCREMENT,
  `name`       varchar(255)   NOT NULL,
  `created_at` datetime       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime       DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_vc_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 7. Visitors ──────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `visitors` (
  `id`             bigint unsigned NOT NULL AUTO_INCREMENT,
  `first_name`     varchar(120)   NOT NULL,
  `last_name`      varchar(120)   NOT NULL,
  `id_type_id`     bigint unsigned DEFAULT NULL,
  `id_number`      varchar(100)   DEFAULT NULL,
  `phone`          varchar(50)    DEFAULT NULL,
  `email`          varchar(255)   DEFAULT NULL,
  `company_id`     bigint unsigned DEFAULT NULL,
  `notes`          text           DEFAULT NULL,
  `risk_score`     int            NOT NULL DEFAULT 0,
  `is_blacklisted` tinyint(1)     NOT NULL DEFAULT 0,
  `created_at`     datetime       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by`     bigint unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_visitor_phone`     (`phone`),
  KEY `idx_visitors_name`     (`last_name`, `first_name`),
  KEY `idx_visitor_id_lookup` (`id_type_id`, `id_number`),
  KEY `idx_visitor_blacklist` (`is_blacklisted`),
  KEY `idx_visitor_company`   (`company_id`),
  CONSTRAINT `fk_visitors_id_type` FOREIGN KEY (`id_type_id`) REFERENCES `identification_types` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_visitors_company` FOREIGN KEY (`company_id`) REFERENCES `visitor_companies`    (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `visitor_watchlist` (
  `id`         bigint unsigned NOT NULL AUTO_INCREMENT,
  `visitor_id` bigint unsigned NOT NULL,
  `severity`   varchar(50)    DEFAULT NULL,
  `reason`     varchar(500)   DEFAULT NULL,
  `created_at` datetime       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_vw_visitor`  (`visitor_id`),
  KEY `idx_vw_severity` (`severity`),
  CONSTRAINT `fk_vw_visitor` FOREIGN KEY (`visitor_id`) REFERENCES `visitors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 8. Visit statuses & types ────────────────────────────────

CREATE TABLE IF NOT EXISTS `visit_statuses` (
  `id`   bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120)   NOT NULL,
  `code` varchar(120)   NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_vs_name` (`name`),
  UNIQUE KEY `uk_vs_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `visit_types` (
  `id`   bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120)   NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_vt_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 9. Visits & badges ───────────────────────────────────────

CREATE TABLE IF NOT EXISTS `visits` (
  `id`              bigint unsigned NOT NULL AUTO_INCREMENT,
  `department_id`   bigint unsigned NOT NULL,
  `visitor_id`      bigint unsigned NOT NULL,
  `host_user_id`    bigint unsigned DEFAULT NULL,
  `visit_type_id`   bigint unsigned DEFAULT NULL,
  `visit_status_id` bigint unsigned DEFAULT NULL,
  `purpose`         text           DEFAULT NULL,
  `contract_reference` varchar(150) DEFAULT NULL,
  `escort_required` tinyint(1)     NOT NULL DEFAULT 0,
  `expected_in`     datetime       DEFAULT NULL,
  `expected_out`    datetime       DEFAULT NULL,
  `checkin_time`    datetime       DEFAULT NULL,
  `checkout_time`   datetime       DEFAULT NULL,
  `created_by`      bigint unsigned DEFAULT NULL,
  `created_at`      datetime       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      datetime       NOT NULL DEFAULT (now()),
  PRIMARY KEY (`id`),
  KEY `idx_visit_visitor`  (`visitor_id`),
  KEY `idx_visits_dept`    (`department_id`),
  KEY `idx_visits_active`  (`visit_status_id`, `checkin_time`),
  KEY `idx_visits_time`    (`created_at` DESC),
  KEY `idx_visits_host`    (`host_user_id`),
  KEY `idx_visits_type`    (`visit_type_id`),
  KEY `idx_visits_created_by` (`created_by`),
  CONSTRAINT `fk_visits_dept`       FOREIGN KEY (`department_id`)   REFERENCES `departments`   (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_visits_visitor`    FOREIGN KEY (`visitor_id`)      REFERENCES `visitors`      (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_visits_host_user`  FOREIGN KEY (`host_user_id`)    REFERENCES `users`         (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_visits_type`       FOREIGN KEY (`visit_type_id`)   REFERENCES `visit_types`   (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_visits_status`     FOREIGN KEY (`visit_status_id`) REFERENCES `visit_statuses`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_visits_created_by` FOREIGN KEY (`created_by`)      REFERENCES `users`         (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `visit_badges` (
  `id`          bigint unsigned NOT NULL AUTO_INCREMENT,
  `visit_id`    bigint unsigned NOT NULL,
  `badge_code`  varchar(120)   NOT NULL,
  `is_active`   tinyint(1)     NOT NULL DEFAULT 1,
  `printed_at`  datetime       DEFAULT NULL,
  `returned_at` datetime       DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_vb_badge_code`  (`badge_code`),
  KEY `idx_vb_visit`        (`visit_id`),
  KEY `idx_vb_active`       (`is_active`),
  CONSTRAINT `fk_vb_visit` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 10. Workflows ────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `workflows` (
  `id`          bigint unsigned NOT NULL AUTO_INCREMENT,
  `name`        varchar(150)   NOT NULL,
  `description` varchar(250)   NOT NULL DEFAULT '',
  `is_active`   tinyint(1)     NOT NULL DEFAULT 1,
  `created_at`  datetime       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_wf_name`   (`name`),
  KEY `idx_wf_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `workflow_steps` (
  `id`               bigint unsigned NOT NULL AUTO_INCREMENT,
  `workflow_id`      bigint unsigned NOT NULL,
  `name`             varchar(150)   NOT NULL DEFAULT '',
  `role_id`          bigint unsigned NOT NULL,
  `step_order`       int            NOT NULL,
  `is_mandatory`     tinyint(1)     NOT NULL DEFAULT 1,
  `department_id`    bigint unsigned DEFAULT NULL,
  `assignment_type`  ENUM('role_department','explicit') NOT NULL DEFAULT 'role_department',
  `department_scope` ENUM('same_as_request','fixed','any') NOT NULL DEFAULT 'same_as_request',
  `approval_rule`    ENUM('all','any') NOT NULL DEFAULT 'all',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ws_wf_order` (`workflow_id`, `step_order`),
  KEY `idx_ws_role`     (`role_id`),
  KEY `idx_ws_dept`     (`department_id`),
  KEY `idx_ws_workflow` (`workflow_id`),
  CONSTRAINT `fk_ws_workflow` FOREIGN KEY (`workflow_id`)   REFERENCES `workflows`   (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ws_role`     FOREIGN KEY (`role_id`)       REFERENCES `roles`       (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_ws_dept`     FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `workflow_step_approvers` (
  `id`               bigint unsigned NOT NULL AUTO_INCREMENT,
  `workflow_step_id` bigint unsigned NOT NULL,
  `user_id`          bigint unsigned NOT NULL,
  `created_at`       datetime        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_step_user` (`workflow_step_id`, `user_id`),
  KEY `idx_wsa_user` (`user_id`),
  CONSTRAINT `fk_wsa_step` FOREIGN KEY (`workflow_step_id`) REFERENCES `workflow_steps` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_wsa_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_delegates` (
  `id`               bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id`          bigint unsigned NOT NULL,
  `delegate_user_id` bigint unsigned NOT NULL,
  `starts_at`        datetime        NOT NULL,
  `ends_at`          datetime        NOT NULL,
  `created_at`       datetime        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_delegate` (`user_id`),
  KEY `idx_ud_delegate` (`delegate_user_id`),
  KEY `idx_ud_active_window` (`user_id`, `starts_at`, `ends_at`),
  CONSTRAINT `fk_ud_user`     FOREIGN KEY (`user_id`)          REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ud_delegate` FOREIGN KEY (`delegate_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 11. Gatepass statuses & types ────────────────────────────

CREATE TABLE IF NOT EXISTS `gatepass_statuses` (
  `id`   bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120)   NOT NULL,
  `code` varchar(120)   NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_gs_name` (`name`),
  UNIQUE KEY `uk_gs_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `gatepass_types` (
  `id`                bigint unsigned NOT NULL AUTO_INCREMENT,
  `name`              varchar(120)   NOT NULL,
  `type_code`         varchar(20)    NOT NULL,
  `description`       varchar(255)   DEFAULT NULL,
  `is_active`         tinyint(1)     NOT NULL DEFAULT 1,
  `created_at`        datetime       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `workflow_id`       bigint unsigned DEFAULT NULL,
  `requires_approval` tinyint(1)     NOT NULL DEFAULT 1,
  `direction`         enum('outbound','inbound') NOT NULL DEFAULT 'outbound',
  `allowed_actions`   json           DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_gpt_name`      (`name`),
  UNIQUE KEY `uk_gpt_type_code` (`type_code`),
  KEY `idx_gpt_active`   (`is_active`),
  KEY `idx_gpt_workflow` (`workflow_id`),
  CONSTRAINT `fk_gpt_workflow` FOREIGN KEY (`workflow_id`) REFERENCES `workflows` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 12. Gatepasses ───────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `gatepasses` (
  `id`                   bigint unsigned NOT NULL AUTO_INCREMENT,
  `visit_id`             bigint unsigned DEFAULT NULL,
  `gatepass_type_id`     bigint unsigned DEFAULT NULL,
  `gatepass_number`      varchar(100)   NOT NULL,
  `status_id`            bigint unsigned DEFAULT NULL,
  `department_id`        bigint unsigned DEFAULT NULL,
  `checked_in_by`        bigint unsigned DEFAULT NULL,
  `checked_out_by`       bigint unsigned DEFAULT NULL,
  `actual_in`            datetime       DEFAULT NULL,
  `actual_out`           datetime       DEFAULT NULL,
  `created_by`           bigint unsigned DEFAULT NULL,
  `created_at`           datetime       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `purpose`              varchar(250)   DEFAULT NULL,
  `is_returnable`        tinyint(1)     NOT NULL DEFAULT 0,
  `expected_return_date` datetime       DEFAULT NULL,
  `actual_return_date`   datetime       DEFAULT NULL,
  `is_fully_returned`    tinyint(1)     NOT NULL DEFAULT 0,
  `needs_approval`       tinyint(1)     NOT NULL DEFAULT 1,
  `deleted_at`           datetime       DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_gatepass_number` (`gatepass_number`),
  KEY `idx_gatepass_status`      (`status_id`),
  KEY `idx_gatepass_type`        (`gatepass_type_id`),
  KEY `idx_gatepass_department`  (`department_id`),
  KEY `idx_gatepass_created_by`  (`created_by`),
  KEY `idx_gatepass_list`        (`status_id`, `created_at` DESC),
  KEY `idx_gatepass_returnable`  (`is_returnable`, `is_fully_returned`),
  KEY `idx_gatepass_visit`       (`visit_id`),
  KEY `idx_gatepass_checked_in`  (`checked_in_by`),
  KEY `idx_gatepass_checked_out` (`checked_out_by`),
  KEY `idx_gatepasses_deleted_at` (`deleted_at`),
  CONSTRAINT `fk_gatepass_visit`          FOREIGN KEY (`visit_id`)         REFERENCES `visits`           (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_gatepass_type`           FOREIGN KEY (`gatepass_type_id`) REFERENCES `gatepass_types`   (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_gatepass_status`         FOREIGN KEY (`status_id`)        REFERENCES `gatepass_statuses`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_gatepass_department`     FOREIGN KEY (`department_id`)    REFERENCES `departments`      (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_gatepass_created_by`     FOREIGN KEY (`created_by`)       REFERENCES `users`            (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_gatepass_checked_in_by`  FOREIGN KEY (`checked_in_by`)    REFERENCES `users`            (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_gatepass_checked_out_by` FOREIGN KEY (`checked_out_by`)   REFERENCES `users`            (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `gatepass_items` (
  `id`                bigint unsigned NOT NULL AUTO_INCREMENT,
  `gatepass_id`       bigint unsigned NOT NULL,
  `item_name`         varchar(255)   NOT NULL,
  `description`       text           DEFAULT NULL,
  `quantity`          int            NOT NULL DEFAULT 1,
  `serial_number`     varchar(255)   DEFAULT NULL,
  `is_returnable`     tinyint(1)     NOT NULL DEFAULT 0,
  `returned_quantity` int            DEFAULT 0,
  `created_at`        timestamp      NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_gi_gatepass` (`gatepass_id`),
  KEY `idx_gi_serial`   (`serial_number`),
  CONSTRAINT `fk_gi_gatepass` FOREIGN KEY (`gatepass_id`) REFERENCES `gatepasses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 13. Approval workflow ────────────────────────────────────

CREATE TABLE IF NOT EXISTS `gatepass_workflow_instances` (
  `id`                 bigint unsigned NOT NULL AUTO_INCREMENT,
  `gatepass_id`        bigint unsigned NOT NULL,
  `workflow_id`        bigint unsigned NOT NULL,
  `current_step_order` int            NOT NULL DEFAULT 1,
  `status`             enum('in_progress','approved','rejected') NOT NULL DEFAULT 'in_progress',
  `started_at`         datetime       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at`       datetime       DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_gwi_gatepass` (`gatepass_id`),
  KEY `idx_gwi_status`   (`status`),
  KEY `idx_gwi_workflow` (`workflow_id`),
  CONSTRAINT `fk_gwi_gatepass` FOREIGN KEY (`gatepass_id`) REFERENCES `gatepasses`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gwi_workflow` FOREIGN KEY (`workflow_id`) REFERENCES `workflows` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `workflow_gatepass_type` (
  `id`               bigint unsigned NOT NULL AUTO_INCREMENT,
  `workflow_id`      bigint unsigned NOT NULL,
  `gatepass_type_id` bigint unsigned NOT NULL,
  `created_at`       datetime       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_workflow_type` (`workflow_id`, `gatepass_type_id`),
  KEY `idx_wgt_workflow`      (`workflow_id`),
  KEY `idx_wgt_gatepass_type` (`gatepass_type_id`),
  CONSTRAINT `fk_wgt_workflow`      FOREIGN KEY (`workflow_id`)      REFERENCES `workflows`     (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_wgt_gatepass_type` FOREIGN KEY (`gatepass_type_id`) REFERENCES `gatepass_types`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `gatepass_approvals` (
  `id`                   bigint unsigned NOT NULL AUTO_INCREMENT,
  `workflow_instance_id` bigint unsigned NOT NULL,
  `workflow_step_id`     bigint unsigned NOT NULL,
  `approver_user_id`     bigint unsigned NOT NULL,
  `status`               enum('pending','approved','rejected','skipped') NOT NULL DEFAULT 'pending',
  `comments`             text           DEFAULT NULL,
  `acted_at`             datetime       DEFAULT NULL,
  `created_at`           datetime       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ga_status`          (`status`),
  KEY `idx_ga_user_pending`    (`approver_user_id`, `status`),
  KEY `idx_ga_instance_status` (`workflow_instance_id`, `status`),
  KEY `idx_ga_step`            (`workflow_step_id`),
  CONSTRAINT `fk_ga_instance` FOREIGN KEY (`workflow_instance_id`) REFERENCES `gatepass_workflow_instances`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ga_step`     FOREIGN KEY (`workflow_step_id`)     REFERENCES `workflow_steps`             (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_ga_user`     FOREIGN KEY (`approver_user_id`)     REFERENCES `users`                      (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 14. Audit log ────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id`          bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id`     bigint unsigned DEFAULT NULL,
  `action`      varchar(120)   NOT NULL,
  `entity_type` varchar(50)    DEFAULT NULL,
  `entity_id`   bigint unsigned DEFAULT NULL,
  `metadata`    json           DEFAULT NULL,
  `ip_address`  varchar(50)    DEFAULT NULL,
  `created_at`  datetime       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `message`     varchar(255)   DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_audit_time`   (`created_at`),
  KEY `idx_audit_entity` (`entity_type`, `entity_id`),
  KEY `idx_audit_user`   (`user_id`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;