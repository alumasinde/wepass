<?php

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

/**
 * TenantConnectionManager — the single place that turns a tenant
 * row into an actual PDO connection.
 *
 * Two sources of connection details, tried in this order:
 *   1. tenants.connection_string (encrypted) — the primary path
 *      going forward. Works identically whether that tenant's
 *      database happens to be on the same server as everything
 *      else, or on entirely separate infrastructure the client
 *      hosts themselves — the manager doesn't know or care which;
 *      it's just a host/port/credentials bundle either way.
 *   2. Legacy fallback — tenants provisioned before connection_string
 *      existed (or via the direct-CREATE-DATABASE path with no
 *      DirectAdmin involved) have no connection_string at all. These
 *      keep resolving exactly as before: shared [database] host/
 *      port/password from config.ini, combined with that tenant's
 *      own db_name and (if set) db_username. Nothing about how
 *      these tenants connect changes.
 *
 * Every repository/service in the app is completely unaware this
 * class exists — they all still just call DB::connect(), which
 * delegates here when a tenant is resolved. That's deliberate: it
 * means this entire mechanism could be introduced without touching
 * a single repository or service.
 */
final class TenantConnectionManager
{
    /** @var array<int, PDO> per-request cache, keyed by tenant id */
    private static array $connections = [];

    public static function connectionFor(array $tenant): PDO
    {
        $tenantId = (int) ($tenant['id'] ?? 0);

        if ($tenantId > 0 && isset(self::$connections[$tenantId])) {
            return self::$connections[$tenantId];
        }

        $details = self::resolveConnectionDetails($tenant);
        $pdo     = self::connectWithFailover($details, $tenant);

        if ($tenantId > 0) {
            self::$connections[$tenantId] = $pdo;
        }

        return $pdo;
    }

    /**
     * Decrypts connection_string when present; otherwise builds the
     * same legacy shape TenantService::provisionTenant() and
     * bootstrap/app.php already used before this class existed —
     * existing tenants (Glee, and anything provisioned before this)
     * are completely unaffected either way.
     */
    private static function resolveConnectionDetails(array $tenant): array
    {
        $encrypted = trim((string) ($tenant['connection_string'] ?? ''));

        if ($encrypted !== '') {
            try {
                $details = ConnectionCrypto::decrypt($encrypted);
            } catch (\Throwable $e) {
                self::log('error', $tenant, 'Failed to decrypt connection_string: ' . $e->getMessage());
                throw new RuntimeException(
                    "Could not decrypt connection details for tenant '{$tenant['code']}' — " . $e->getMessage()
                );
            }

            return array_merge([
                'host'          => '127.0.0.1',
                'port'          => 3306,
                'database'      => $tenant['db_name'] ?? '',
                'username'      => '',
                'password'      => '',
                'charset'       => 'utf8mb4',
                'ssl'           => false,
                'ssl_ca'        => null,
                'ssl_cert'      => null,
                'ssl_key'       => null,
                'ssl_verify'    => true,
                'persistent'    => false,
                'failover_host' => null,
                'failover_port' => null,
            ], $details);
        }

        // Legacy fallback — identical to what bootstrap/app.php and
        // DB::connect() did before per-tenant connection strings
        // existed.
        return [
            'host'          => (string) config('database.host', '127.0.0.1'),
            'port'          => (int) config('database.port', 3306),
            'database'      => (string) ($tenant['db_name'] ?? config('database.name', '')),
            'username'      => (string) (!empty($tenant['db_username']) ? $tenant['db_username'] : config('database.username', '')),
            'password'      => (string) config('database.password', ''),
            'charset'       => (string) config('database.charset', 'utf8mb4'),
            'ssl'           => false,
            'ssl_ca'        => null,
            'ssl_cert'      => null,
            'ssl_key'       => null,
            'ssl_verify'    => true,
            'persistent'    => (bool) config('database.persistent', false),
            'failover_host' => null,
            'failover_port' => null,
        ];
    }

    /**
     * Tries the primary host first (with a short retry, same
     * reasoning as TenantService's post-provisioning connect — a
     * freshly granted user isn't always immediately usable on every
     * host). If a failover_host is configured AND the primary is
     * unreachable after all retries, tries the failover once before
     * giving up entirely. failover_host is opt-in per tenant (set it
     * in that tenant's connection details) — most tenants, including
     * every one provisioned so far, simply won't have one set, and
     * this whole block is skipped for them.
     */
    private static function connectWithFailover(array $details, array $tenant): PDO
    {
        try {
            return self::attemptConnect($details['host'], $details['port'], $details, $tenant, primary: true);
        } catch (RuntimeException $primaryError) {
            if (empty($details['failover_host'])) {
                throw $primaryError;
            }

            self::log('warning', $tenant, "Primary host unreachable, trying failover_host: {$primaryError->getMessage()}");

            try {
                return self::attemptConnect(
                    $details['failover_host'],
                    $details['failover_port'] ?: $details['port'],
                    $details,
                    $tenant,
                    primary: false
                );
            } catch (RuntimeException $failoverError) {
                self::log('error', $tenant, "Failover host also unreachable: {$failoverError->getMessage()}");
                // Surface the ORIGINAL (primary) error — that's the
                // one describing the tenant's actual database, not
                // the backup path, and is more useful for diagnosing
                // what's actually down.
                throw $primaryError;
            }
        }
    }

    private static function attemptConnect(string $host, int|string $port, array $details, array $tenant, bool $primary): PDO
    {
        $attempts     = 3;
        $delaySeconds = [0, 1, 2];
        $lastError    = null;

        for ($i = 0; $i < $attempts; $i++) {
            if ($delaySeconds[$i] > 0) {
                sleep($delaySeconds[$i]);
            }

            try {
                $pdo = self::buildPdo($host, $port, $details);
                self::log('info', $tenant, ($primary ? 'Connected' : 'Connected via failover') . " to {$host}:{$port}");
                return $pdo;
            } catch (PDOException $e) {
                $lastError = $e;
            }
        }

        self::log('error', $tenant, "Failed to connect to {$host}:{$port} after {$attempts} attempts: " . $lastError->getMessage());
        throw new RuntimeException("Could not connect to {$host}:{$port}: " . $lastError->getMessage());
    }

    /**
     * Interactive "does this actually work" check for the admin UI —
     * a single attempt with a short timeout, deliberately NOT using
     * the retry/failover/per-request-cache machinery connectionFor()
     * uses for real traffic. An admin testing a connection wants a
     * fast, honest answer right now, not a 6-second retry sequence
     * before finding out it's wrong.
     *
     * Never touches connection_string or the tenants table at all —
     * takes the same raw details shape the Connection/New Tenant
     * forms already collect, connects, checks for a 'users' table
     * (the same schema-readiness check provisionHostedSeparately()
     * uses), and reports what it found. Nothing is saved here.
     */
    public static function testConnection(array $details): array
    {
        $host = trim((string) ($details['host'] ?? ''));
        $port = (int) ($details['port'] ?? 3306);
        $db   = trim((string) ($details['database'] ?? ''));
        $user = trim((string) ($details['username'] ?? ''));
        $pass = (string) ($details['password'] ?? '');

        if ($host === '' || $db === '' || $user === '') {
            return ['success' => false, 'message' => 'Host, database name, and username are required.'];
        }

        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 6, // short and deliberate — this is an interactive check, not real traffic
        ];

        if (!empty($details['ssl'])) {
            if (!empty($details['ssl_ca']))   $options[PDO::MYSQL_ATTR_SSL_CA]   = $details['ssl_ca'];
            if (!empty($details['ssl_cert'])) $options[PDO::MYSQL_ATTR_SSL_CERT] = $details['ssl_cert'];
            if (!empty($details['ssl_key']))  $options[PDO::MYSQL_ATTR_SSL_KEY]  = $details['ssl_key'];
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = (bool) ($details['ssl_verify'] ?? true);
        }

        try {
            $pdo = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => "Could not connect to {$host}:{$port}/{$db} — " . $e->getMessage(),
            ];
        }

        try {
            $hasUsersTable = (bool) $pdo->query("SHOW TABLES LIKE 'users'")->fetchColumn();
        } catch (\Throwable) {
            $hasUsersTable = false;
        }

        if (!$hasUsersTable) {
            return [
                'success' => true,
                'message' => "Connected to {$host}:{$port}/{$db} successfully, but no 'users' table was found — " .
                    "run database/001_schema.sql through 011_visitor_notes.sql against it before using this connection.",
                'schema_ready' => false,
            ];
        }

        return [
            'success'      => true,
            'message'      => "Connected to {$host}:{$port}/{$db} successfully — schema looks set up.",
            'schema_ready' => true,
        ];
    }

    private static function buildPdo(string $host, int|string $port, array $details): PDO
    {
        $dsn = "mysql:host={$host};port={$port};dbname={$details['database']};charset={$details['charset']}";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => (bool) ($details['persistent'] ?? false),
            // FIX: no timeout at all here previously — fine for a
            // connection on the same server, but a genuinely slow or
            // unreliable remote link (an SSH tunnel to someone's
            // laptop is exactly this) could hang for the OS-level
            // default TCP timeout instead of failing fast and
            // retrying. 8s per attempt, up to 3 attempts (see
            // attemptConnect()), bounds the worst case to well under
            // a minute instead of potentially much longer.
            PDO::ATTR_TIMEOUT             => 8,
        ];

        // SSL — only applied when a tenant's connection details
        // actually request it (ssl: true). Not exercised by any
        // tenant provisioned so far (everything's still on one
        // server), but a client on their own remote infrastructure
        // requiring an encrypted connection is exactly the case this
        // is for.
        if (!empty($details['ssl'])) {
            if (!empty($details['ssl_ca']))   $options[PDO::MYSQL_ATTR_SSL_CA]   = $details['ssl_ca'];
            if (!empty($details['ssl_cert'])) $options[PDO::MYSQL_ATTR_SSL_CERT] = $details['ssl_cert'];
            if (!empty($details['ssl_key']))  $options[PDO::MYSQL_ATTR_SSL_KEY]  = $details['ssl_key'];
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = (bool) ($details['ssl_verify'] ?? true);
        }

        return new PDO($dsn, (string) $details['username'], (string) $details['password'], $options);
    }

    /**
     * Append-only file log, deliberately separate from the app's
     * audit_logs table (that lives IN a tenant database — logging a
     * failure to connect to a tenant database there would be circular
     * on the exact requests where it matters most). One shared file
     * across all tenants, since this is infrastructure-level
     * logging, not tenant business data.
     */
    private static function log(string $level, array $tenant, string $message): void
    {
        $line = sprintf(
            "[%s] %s tenant=%s(%s) %s\n",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $tenant['code'] ?? '?',
            $tenant['id'] ?? '?',
            $message
        );

        $logPath = base_path('storage/logs/tenant_connections.log');
        $logDir  = dirname($logPath);

        if (!is_dir($logDir)) {
            @mkdir($logDir, 0700, true);
        }

        @file_put_contents($logPath, $line, FILE_APPEND | LOCK_EX);
    }
}