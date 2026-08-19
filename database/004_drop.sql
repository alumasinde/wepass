-- ============================================================
-- Glee GPMS  |  004_drop.sql
-- Drop every table — full reset.
-- Use this when you need to re-run 001_schema.sql from scratch.
-- WARNING: all data will be permanently lost.
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `gatepass_approvals`;
DROP TABLE IF EXISTS `gatepass_workflow_instances`;
DROP TABLE IF EXISTS `workflow_gatepass_type`;
DROP TABLE IF EXISTS `gatepass_items`;
DROP TABLE IF EXISTS `gatepasses`;
DROP TABLE IF EXISTS `gatepass_types`;
DROP TABLE IF EXISTS `gatepass_statuses`;
DROP TABLE IF EXISTS `workflow_steps`;
DROP TABLE IF EXISTS `workflows`;
DROP TABLE IF EXISTS `visit_badges`;
DROP TABLE IF EXISTS `visits`;
DROP TABLE IF EXISTS `visit_types`;
DROP TABLE IF EXISTS `visit_statuses`;
DROP TABLE IF EXISTS `visitor_watchlist`;
DROP TABLE IF EXISTS `visitors`;
DROP TABLE IF EXISTS `visitor_companies`;
DROP TABLE IF EXISTS `identification_types`;
DROP TABLE IF EXISTS `user_otps`;
DROP TABLE IF EXISTS `user_roles`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `departments`;
DROP TABLE IF EXISTS `role_permissions`;
DROP TABLE IF EXISTS `roles`;
DROP TABLE IF EXISTS `tenant_settings`;
DROP TABLE IF EXISTS `tenants`;
DROP TABLE IF EXISTS `permissions`;
DROP TABLE IF EXISTS `modules`;
DROP TABLE IF EXISTS `actions`;

SET FOREIGN_KEY_CHECKS = 1;
