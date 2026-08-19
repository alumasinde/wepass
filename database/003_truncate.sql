-- ============================================================
-- Glee GPMS  |  003_truncate.sql
-- Wipe all data while keeping the schema intact.
-- Safe to run repeatedly in development.
-- WARNING: irreversible — do NOT run on production.
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE `audit_logs`;
TRUNCATE TABLE `gatepass_approvals`;
TRUNCATE TABLE `gatepass_workflow_instances`;
TRUNCATE TABLE `workflow_gatepass_type`;
TRUNCATE TABLE `gatepass_items`;
TRUNCATE TABLE `gatepasses`;
TRUNCATE TABLE `gatepass_types`;
TRUNCATE TABLE `gatepass_statuses`;
TRUNCATE TABLE `workflow_steps`;
TRUNCATE TABLE `workflows`;
TRUNCATE TABLE `visit_badges`;
TRUNCATE TABLE `visits`;
TRUNCATE TABLE `visit_types`;
TRUNCATE TABLE `visit_statuses`;
TRUNCATE TABLE `visitor_watchlist`;
TRUNCATE TABLE `visitors`;
TRUNCATE TABLE `visitor_companies`;
TRUNCATE TABLE `identification_types`;
TRUNCATE TABLE `user_otps`;
TRUNCATE TABLE `user_roles`;
TRUNCATE TABLE `users`;
TRUNCATE TABLE `departments`;
TRUNCATE TABLE `role_permissions`;
TRUNCATE TABLE `roles`;
TRUNCATE TABLE `tenant_settings`;
TRUNCATE TABLE `tenants`;
TRUNCATE TABLE `permissions`;
TRUNCATE TABLE `modules`;
TRUNCATE TABLE `actions`;

SET FOREIGN_KEY_CHECKS = 1;
