-- ============================================================
-- 013_auth_session_version.sql
-- Enterprise identity hardening: per-user authentication version.
-- Incrementing auth_version invalidates every existing session for
-- that user. Run once against every tenant database.
-- ============================================================

ALTER TABLE `users`
    ADD COLUMN `auth_version` bigint unsigned NOT NULL DEFAULT 1
    COMMENT 'Increment to revoke all existing authenticated sessions'
    AFTER `is_active`;
