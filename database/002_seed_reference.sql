-- ============================================================
-- Glee GPMS  |  002_seed_reference.sql
-- Reference/lookup data ONLY — safe to run against ANY new
-- tenant database, including real client installs.
--
-- This is 002_seed.sql with two things deliberately removed:
--   1. The `users` / `user_roles` insert — it hardcoded a real
--      personal account (email + live bcrypt hash). Every new
--      tenant needs its OWN first admin, created explicitly
--      (see TenantService::provisionTenant(), or pass
--      --admin-email/--admin-name to database/Seeder.php for a
--      CLI-provisioned dev tenant).
--   2. The `visitor_companies` / `visitors` demo rows — sample
--      data with plausible-looking personal details that has no
--      place in a live client database.
--
-- Run this after 001_schema.sql. Used by both:
--   - TenantService::provisionTenant() (the "create tenant via
--     UI" flow, run by a logged-in super admin)
--   - database/Seeder.php (CLI tenant provisioning for local dev)
--
-- MySQL 8.0+  |  utf8mb4_unicode_ci
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

-- ── Actions ───────────────────────────────
INSERT IGNORE INTO actions (id, name) VALUES
(1,'view'),(2,'create'),(3,'update'),(4,'delete'),
(5,'approve'),(6,'reject'),
(7,'checkin'),(8,'checkout'),
(9,'export'),(10,'access'),
(18,'print'),
(22,'blacklist'),(26,'disable'),(28,'assign');

-- ── Modules ───────────────────────────────
INSERT IGNORE INTO modules (id, name) VALUES
(1,'gatepasses'),(2,'visits'),(3,'visitors'),(4,'users'),
(5,'departments'),(6,'workflows'),(7,'approval'),
(8,'reports'),(9,'tenant'),(10,'audit_logs'),
(11,'dashboard'),(12,'gatepass'),
(15,'roles'),(16,'settings'),(17,'audit');

-- ── Permissions ───────────────────────────
INSERT IGNORE INTO permissions (id, action_id, module_id) VALUES
(1,1,7),(2,3,7),(3,6,7),(4,9,7),(5,4,7),(6,2,7),(7,8,7),(8,7,7),(9,5,7),
(10,1,10),(11,3,10),(12,6,10),(13,9,10),(14,4,10),(15,2,10),(16,8,10),(17,7,10),(18,5,10),
(19,1,5),(20,3,5),(21,6,5),(22,9,5),(23,4,5),(24,2,5),(25,8,5),(26,7,5),(27,5,5),
(28,1,1),(29,3,1),(30,6,1),(31,9,1),(32,4,1),(33,2,1),(34,8,1),(35,7,1),(36,5,1),
(37,1,8),(38,3,8),(39,6,8),(40,9,8),(41,4,8),(42,2,8),(43,8,8),(44,7,8),(45,5,8),
(46,1,9),(47,3,9),(48,6,9),(49,9,9),(50,4,9),(51,2,9),(52,8,9),(53,7,9),(54,5,9),
(55,1,4),(56,3,4),(57,6,4),(58,9,4),(59,4,4),(60,2,4),(61,8,4),(62,7,4),(63,5,4),
(64,1,3),(65,3,3),(66,6,3),(67,9,3),(68,4,3),(69,2,3),(70,8,3),(71,7,3),(72,5,3),
(73,1,2),(74,3,2),(75,6,2),(76,9,2),(77,4,2),(78,2,2),(79,8,2),(80,7,2),(81,5,2),
(82,1,6),(83,3,6),(84,6,6),(85,9,6),(86,4,6),(87,2,6),(88,8,6),(89,7,6),(90,5,6),
(128,10,11),
(129,2,12),(130,1,12),(131,3,12),(132,4,12),(133,5,12),
(134,7,12),(135,8,12),(136,18,12),
(140,22,3),(144,26,4),
(145,2,15),(146,28,15),(147,3,15),
(148,3,16),
(149,1,17);

-- ── Roles ────────────────────────────────
INSERT IGNORE INTO roles (id, name) VALUES
(1,'admin'),
(2,'Security Manager'),
(3,'Receptionist'),
(4,'Department Head'),
(5,'General Manager'),
(6,'IT Manager'),
(7,'Finance Manager'),
(8,'HR Manager');

-- ── Role Permissions (admin = full access) ──
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions;

-- ── Departments (generic defaults — rename/add per client) ──
INSERT IGNORE INTO departments (id, name, code) VALUES
(1,'Reception','REC'),
(2,'IT','IT'),
(3,'Finance','FIN'),
(4,'HR','HR'),
(5,'Security','SEC');

-- ── Identification Types ────────────────
INSERT IGNORE INTO identification_types (id, name) VALUES
(1,'National ID'),(2,'Passport');

-- ── Visit Statuses ──────────────────────
INSERT IGNORE INTO visit_statuses (id,name,code) VALUES
(1,'Scheduled','scheduled'),
(2,'Checked In','checked_in'),
(3,'Checked Out','checked_out');

-- ── Visit Types ─────────────────────────
INSERT IGNORE INTO visit_types (id,name) VALUES
(1,'Business'),(2,'Personal'),(3,'Contractor');

-- ── Gatepass Statuses ───────────────────
INSERT IGNORE INTO gatepass_statuses (id,name,code) VALUES
(1,'Pending','pending'),
(2,'Submitted','submitted'),
(3,'Approved','approved'),
(4,'Rejected','rejected'),
(5,'Checked Out','checked_out'),
(6,'Checked In','checked_in'),
(7,'Cancelled','cancelled');

-- ── Workflows (MUST COME BEFORE TYPES) ──
INSERT IGNORE INTO workflows (id, name, description, is_active, created_at) VALUES
(1,'Standard Workflow','Standard gatepass workflow',1, NOW()),
(2,'Temporary Workflow','Workflow for temporary gatepasses',1, NOW());

-- ── Workflow Steps ──────────────────────
INSERT IGNORE INTO workflow_steps
(id, workflow_id, name, role_id, step_order, is_mandatory, department_id) VALUES
(1,1,'Department Head Approval',4,1,1,NULL),
(2,1,'Security Approval',2,2,1,NULL),
(3,1,'General Manager Approval',5,3,1,NULL),
(4,2,'Security Manager Approval',2,1,1,NULL);

-- ── Gatepass Types ───────────────────────
INSERT IGNORE INTO gatepass_types
(id,name,type_code,description,is_active,workflow_id,allowed_actions,created_at) VALUES
(1,'Standard','REG','Standard gatepass',1,1,'{\"checkin\":true,\"checkout\":true}', NOW()),
(2,'Gatepass In','IN','Gatepass In',1,1,'{\"checkin\":true,\"checkout\":false}', NOW()),
(3,'Gatepass Out','OUT','Gatepass Out',1,1,'{\"checkin\":false,\"checkout\":true}', NOW());

SET FOREIGN_KEY_CHECKS = 1;
