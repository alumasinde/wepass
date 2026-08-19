<?php

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;

/**
 * DB — PDO wrapper for the active tenant database.
 *
 * Connection parameters come from config.ini [database] section.
 * Each tenant has its own isolated database; no tenant_id column
 * filtering is needed here — isolation is at the database level.
 */
class DB
{
    private static ?PDO $pdo = null;
    private static ?PDO $masterPdo = null;

    // ── Connection ───────────────────────────────────────────

    public static function connect(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        // Tenant-aware path: every real request (dynamic-domain mode
        // or legacy static mode, both) resolves a tenant via
        // TenantContext before routing ever happens — see
        // bootstrap/app.php. When one's resolved, the
        // TenantConnectionManager is the actual source of truth for
        // how to reach that tenant's database, whether it's on this
        // same server (the only case that exists today) or on
        // infrastructure the client hosts themselves. No repository
        // or service anywhere needed to change for this — they all
        // still just call DB::connect().
        if (TenantContext::hasTenant()) {
            self::$pdo = TenantConnectionManager::connectionFor(TenantContext::tenant());
            return self::$pdo;
        }

        // Fallback path — no tenant resolved via TenantContext at
        // all (shouldn't happen on a normal tenant-scoped request,
        // but kept as a safety net rather than a hard requirement,
        // e.g. for tooling that calls DB::connect() outside a normal
        // request lifecycle).
        $host    = config('database.host',     '127.0.0.1');
        $port    = config('database.port',     '3306');
        $name    = config('database.name',     'glee_tenant_default');
        $charset = config('database.charset',  'utf8mb4');
        $user    = config('database.username', '');
        $pass    = config('database.password', '');

        // Never fall back to a hardcoded credential. Missing config
        // is a deployment error and must fail loudly, not connect
        // silently with a baked-in username/password.
        if ($user === '') {
            if (config('app.debug', false)) {
                die('Database connection failed: [database] username is not set in config.ini.');
            }
            die('Database connection failed. Check config/config.ini [database].');
        }

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";

        try {
            self::$pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_PERSISTENT         => (bool) config('database.persistent', false),
            ]);
        } catch (PDOException $e) {
            if (config('app.debug', false)) {
                die('Database connection failed: ' . $e->getMessage());
            }
            die('Database connection failed. Check config/config.ini [database].');
        }

        return self::$pdo;
    }

    public static function connection(): PDO
    {
        return self::connect();
    }

    /**
     * Always connects to glee_master ([master_db] section), regardless
     * of which tenant DB this install's regular connect() targets.
     *
     * Every deployment — a tenant install or the platform/admin
     * install used to manage clients — can reach the tenant registry
     * through this, independent of the active [database] connection.
     * Used by TenantRepository (tenants table lives only in
     * glee_master, never in a tenant DB) and by the master-admin
     * login/tenant-provisioning flow.
     */
    public static function master(): PDO
    {
        if (self::$masterPdo !== null) {
            return self::$masterPdo;
        }

        $host = self::firstNonEmpty(config('master_db.host'), config('mysql.host'), '127.0.0.1');
        $port = self::firstNonEmpty(config('master_db.port'), config('mysql.port'), '3306');
        $name = self::firstNonEmpty(config('master_db.name'), null, 'glee_master');
        $user = self::firstNonEmpty(config('master_db.username'), config('mysql.username'), '');
        $pass = self::firstNonEmpty(config('master_db.password'), config('mysql.password'), '');

        if ($user === '') {
            if (config('app.debug', false)) {
                die('Master DB connection failed: [master_db] username is not set in config.ini.');
            }
            die('Master DB connection failed. Check config/config.ini [master_db].');
        }

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

        try {
            self::$masterPdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            if (config('app.debug', false)) {
                die('Master DB connection failed: ' . $e->getMessage());
            }
            die('Master DB connection failed. Check config/config.ini [master_db].');
        }

        return self::$masterPdo;
    }

    /**
     * Ad-hoc connection using the MySQL admin/root credentials from
     * config.ini [mysql] — the same account Seeder.php already uses
     * to CREATE DATABASE. Needed only when provisioning a brand new
     * tenant database that doesn't exist yet (so DB::connect()/
     * DB::master(), which both target a specific existing database,
     * can't be used). Pass null to connect server-wide (for CREATE
     * DATABASE); pass a name to connect into a specific database
     * (for running schema/seed SQL against a freshly created one).
     *
     * Deliberately NOT cached/singleton — this is only ever used
     * during the low-frequency "create a new tenant" flow.
     */
    public static function adminConnection(?string $dbname = null): PDO
    {
        $host = self::firstNonEmpty(config('mysql.host'), config('database.host'), '127.0.0.1');
        $port = self::firstNonEmpty(config('mysql.port'), config('database.port'), '3306');
        $user = (string) config('mysql.username', '');
        $pass = (string) config('mysql.password', '');

        if ($user === '') {
            throw new \RuntimeException('config.ini [mysql] username is not set — required to provision new tenant databases.');
        }

        $dsn = "mysql:host={$host};port={$port}" . ($dbname !== null ? ";dbname={$dbname}" : '') . ';charset=utf8mb4';

        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    /**
     * Returns the first non-empty-string value among $a, $b, then
     * $default. config()'s own $default parameter only kicks in when
     * a key is entirely ABSENT from config.ini — an explicit empty
     * value ("" left blank in an ini section) is still "present" and
     * short-circuits it. This is for the common case of wanting
     * [master_db]/[database] to fall back to [mysql] when left blank.
     */
    private static function firstNonEmpty(mixed ...$values): string
    {
        foreach ($values as $value) {
            if ($value !== null && $value !== '') {
                return (string) $value;
            }
        }

        return '';
    }

    // ── Query helpers ────────────────────────────────────────

    public static function query(string $sql, array $bindings = []): PDOStatement
    {
        $stmt = self::connect()->prepare($sql);
        $stmt->execute($bindings);
        return $stmt;
    }

    public static function select(string $sql, array $bindings = []): array
    {
        return self::query($sql, $bindings)->fetchAll();
    }

    public static function selectOne(string $sql, array $bindings = []): ?array
    {
        $result = self::query($sql, $bindings)->fetch();
        return $result ?: null;
    }

    public static function statement(string $sql, array $bindings = []): int
    {
        return self::query($sql, $bindings)->rowCount();
    }

    public static function insert(string $sql, array $bindings = []): string|false
    {
        self::query($sql, $bindings);
        return self::connect()->lastInsertId();
    }

    public static function lastInsertId(): string
    {
        return self::connect()->lastInsertId();
    }

    public static function table(string $table): QueryBuilder
    {
        return new QueryBuilder($table);
    }

    // ── Transactions ─────────────────────────────────────────

    public static function transaction(callable $callback): mixed
    {
        $pdo = self::connect();

        try {
            $started = false;
            if (!$pdo->inTransaction()) {
                $pdo->beginTransaction();
                $started = true;
            }

            $result = $callback($pdo);

            if ($started) {
                $pdo->commit();
            }

            return $result;

        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function beginTransaction(): bool
    {
        return self::connect()->beginTransaction();
    }

    public static function commit(): bool
    {
        return self::connect()->commit();
    }

    public static function rollBack(): bool
    {
        return self::connect()->rollBack();
    }
}
