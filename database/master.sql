-- NOTE: no CREATE DATABASE / USE statement here on purpose.
-- The database name comes from config.ini [master_db] name, and
-- both database/migrate.php and database/Seeder.php already
-- create that database (ensureDb()) and connect INTO it directly
-- via the PDO DSN's dbname= parameter before running this file.
-- Hardcoding a database name here would silently override that —
-- which is exactly the bug that sent tables to `glee_master`
-- instead of the real configured name.

-- Tenant registry
CREATE TABLE IF NOT EXISTS `tenants` (
  `id`         bigint unsigned NOT NULL AUTO_INCREMENT,
  `name`       varchar(255)   NOT NULL,
  `code`       varchar(120)   NOT NULL COMMENT 'Unique short code — matches setup.ini [tenant] code',
  `db_name`    varchar(120)   NOT NULL COMMENT 'The tenant-specific database name on this server',
  `plan`       varchar(50)    NOT NULL DEFAULT 'starter',
  `logo`       varchar(255)   DEFAULT NULL,
  `email`      varchar(255)   NOT NULL DEFAULT '',
  `phone`      varchar(50)    DEFAULT NULL,
  `country`    varchar(100)   DEFAULT NULL,
  `is_active`  tinyint(1)     NOT NULL DEFAULT 1,
  `created_at` datetime       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime       DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_code` (`code`),
  UNIQUE KEY `uk_tenant_db`   (`db_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dynamic-domain mode (config.ini [platform] base_domain set): a
-- tenant is reached either at "{code}.{base_domain}" (no column
-- needed — `code` already covers it) or at an entirely separate
-- domain the client owns, mapped here explicitly.
--
-- Plain ALTER TABLE, not a conditional PREPARE/EXECUTE — on
-- re-run this throws "Duplicate column"/"Duplicate key", which
-- every runner (migrate.php, Seeder.php, TenantService) already
-- catches and skips. Simpler and avoids a real PDO/mysqlnd quirk:
-- running PREPARE/EXECUTE/DEALLOCATE as separate exec() calls can
-- fail with "Cannot execute queries while other unbuffered
-- queries are active" on some MySQL/MariaDB + PDO combinations.
ALTER TABLE `tenants` ADD COLUMN `custom_domain` varchar(255) DEFAULT NULL AFTER `code`;
ALTER TABLE `tenants` ADD UNIQUE KEY `uk_tenant_custom_domain` (`custom_domain`);

-- Per-tenant MySQL username (DirectAdmin provisioning only — see
-- TenantService::provisionTenant()). NULL for any tenant provisioned
-- before this existed, or via the direct-CREATE-DATABASE path on a
-- host that allows it (e.g. a VPS) — those keep using the single
-- shared [database] username from config.ini exactly as before.
-- The password is deliberately NOT stored per-tenant here — it
-- stays the one shared [database] password. Storing a username
-- alone isn't a usable credential without it, so this column carries
-- no secret and needs no encryption-at-rest handling.
ALTER TABLE `tenants` ADD COLUMN `db_username` varchar(64) DEFAULT NULL AFTER `db_name`;

-- Full encrypted connection details (host, port, database, username,
-- password, SSL settings, optional failover host) for a tenant whose
-- database lives on its own separate infrastructure — see
-- App\Core\TenantConnectionManager and App\Core\ConnectionCrypto.
-- Encrypted with AES-256-GCM using a key stored OUTSIDE this
-- database entirely (storage/keys/tenant_connection.key, outside the
-- web root) — see bin/generate-tenant-key.php for why that
-- separation is the whole point. NULL for every tenant that doesn't
-- need this (which is every tenant provisioned so far — they're all
-- still on this one server, resolved via db_name/db_username/the
-- shared [database] credential instead). `text`, not `varchar`, since
-- the encrypted+base64'd payload is longer than a raw password.
ALTER TABLE `tenants` ADD COLUMN `connection_string` text DEFAULT NULL AFTER `db_username`;

-- Company Settings self-service (Settings → Company) — the form has
-- always had phone/country fields, but nothing backed them until now;
-- submitting the form was a documented no-op ("managed via
-- config.ini") even after name/email became genuinely per-tenant.
ALTER TABLE `tenants` ADD COLUMN `phone`   varchar(50)  DEFAULT NULL AFTER `email`;
ALTER TABLE `tenants` ADD COLUMN `country` varchar(100) DEFAULT NULL AFTER `phone`;

-- Rate limiting (App\Core\RateLimiter) — lives here, not in a
-- tenant database, because it's used on BOTH tenant-host routes
-- (login, password reset, gate scanning) AND admin-host routes
-- (/master/login), which has no tenant database at all. The
-- master DB is the one connection guaranteed to exist regardless
-- of which host resolved the request.
CREATE TABLE IF NOT EXISTS `rate_limits` (
  `id`           bigint unsigned NOT NULL AUTO_INCREMENT,
  `rl_key`       varchar(190)   NOT NULL,
  `attempts`     int unsigned   NOT NULL DEFAULT 0,
  `reset_at`     datetime       NOT NULL,
  `updated_at`   datetime       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_rl_key` (`rl_key`),
  KEY `idx_rl_reset_at` (`reset_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Platform-level super admin accounts. Deliberately separate from
-- any tenant's `users` table — a super admin isn't a member of any
-- one client's staff, they manage the tenant registry itself
-- (create new clients, view the tenant list). Authenticated via
-- App\Modules\MasterAdmin, reachable from any deployment through
-- App\Core\DB::master().
CREATE TABLE IF NOT EXISTS `master_admins` (
  `id`             bigint unsigned NOT NULL AUTO_INCREMENT,
  `email`          varchar(255)   NOT NULL,
  `password_hash`  varchar(255)   NOT NULL,
  `first_name`     varchar(120)   NOT NULL,
  `last_name`      varchar(120)   NOT NULL DEFAULT '',
  `is_active`      tinyint(1)     NOT NULL DEFAULT 1,
  `last_login_at`  datetime       DEFAULT NULL,
  `created_at`     datetime       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     datetime       DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_master_admin_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;