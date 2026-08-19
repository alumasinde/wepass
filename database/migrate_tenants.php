<?php
declare(strict_types=1);

/**
 * ============================================================
 * migrate_tenants.php — take every tenant database from wherever
 * it currently is (brand new, or partially migrated) to fully
 * current, in one run.
 * ============================================================
 *
 * database/migrate.php ONLY touches glee_master (schema + super
 * admin bootstrap) — it explicitly does not touch tenant databases.
 * Until now, applying a new incremental migration (005, 006, ...)
 * to every tenant meant running `mysql ... < database/00X_*.sql`
 * by hand, once per tenant, and remembering which tenants you'd
 * already done. That doesn't scale past a couple of clients and
 * has no record of what's been applied where. This does both:
 * iterates every tenant registered in glee_master.tenants, tracks
 * which migrations have already run against each one in a new
 * `schema_migrations` table inside that tenant's own database, and
 * only applies what's actually pending.
 *
 * ── SAFETY: why this uses a hardcoded allowlist, not a glob ────
 * database/ also contains 002_seed_reference.sql (demo/reference
 * DATA — re-running it against a live tenant would duplicate rows),
 * 003_truncate.sql and 004_drop.sql (genuinely destructive — do
 * exactly what their names say). A glob over database/*.sql would
 * eventually run one of those against every live tenant database
 * in one command. That is the specific mistake this script is
 * built to make structurally impossible: it will NEVER execute any
 * file that isn't explicitly listed in INCREMENTAL_MIGRATIONS
 * below. Adding a new migration means adding its filename to that
 * array by hand — deliberately manual, deliberately reviewable.
 *
 * ── Why 001_schema.sql is now included, first ───────────────────
 * Every statement in it is CREATE TABLE IF NOT EXISTS — running it
 * against a tenant database that already has all its tables is a
 * genuine no-op (MySQL skips a CREATE TABLE entirely when the table
 * exists, including its column/constraint list — it does NOT
 * retroactively add new columns to an existing table). That's
 * exactly what makes it safe to put first in this chain: on a
 * brand-new, empty tenant database it creates the full base schema;
 * on an existing one it does nothing and moves straight on to
 * 005/006/007, which ARE built to retrofit existing tables (ALTER
 * ... ADD COLUMN). Together, running 001 → 005 → 006 → 007 in order
 * is what makes this one command correct for a tenant in any state
 * — freshly provisioned or years old.
 *
 * Usage:
 *   php database/migrate_tenants.php                    # all active tenants, all pending migrations
 *   php database/migrate_tenants.php --dry-run           # show what WOULD run, changes nothing
 *   php database/migrate_tenants.php --tenant=acme       # only the tenant with code "acme"
 *   php database/migrate_tenants.php --include-inactive  # also process is_active=0 tenants
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "migrate_tenants.php must be run from CLI.\n");
    exit(1);
}

// ── The allowlist — see the safety note above before touching this ──
//
// Order matters (applied in this order, tracked per-tenant). Every
// entry here must be a purely additive/idempotent schema change
// (CREATE TABLE IF NOT EXISTS, or an ALTER ... ADD COLUMN/KEY that's
// safe to attempt twice) — never seed data, never anything
// destructive. 001 is the full base schema (safe no-op on tables
// that already exist); everything after it is an incremental
// retrofit for tables that DO already exist.
const INCREMENTAL_MIGRATIONS = [
    '001_schema.sql',
    '005_production_readiness.sql',
    '006_soft_deletes.sql',
    '007_explicit_approvers.sql',
    '008_delegation.sql',
    '009_type_requires_approval.sql',
    '010_contractor_visits.sql',
    '011_visitor_notes.sql',
    '012_gatepass_type_direction.sql',
];

$root = dirname(__DIR__);

// ── 1. Load + merge config (same pattern as migrate.php/Seeder.php) ──

$globalIni = $root . '/config/config.ini';
$localIni  = $root . '/config/setup.ini';

if (!file_exists($globalIni)) {
    fwrite(STDERR, "Missing config/config.ini — copy config/config.ini.example first.\n");
    exit(1);
}

$config = parse_ini_file($globalIni, true, INI_SCANNER_TYPED);

if (file_exists($localIni)) {
    $setup = parse_ini_file($localIni, true, INI_SCANNER_TYPED);
    foreach ($setup as $section => $values) {
        $config[$section] = isset($config[$section])
            ? array_merge($config[$section], $values)
            : $values;
    }
}

$mysqlHost = $config['mysql']['host'] ?? $config['database']['host'] ?? '127.0.0.1';
$mysqlPort = (int) ($config['mysql']['port'] ?? $config['database']['port'] ?? 3306);
$mysqlUser = $config['mysql']['username'] ?? $config['database']['username'] ?? '';
$mysqlPass = (string) ($config['mysql']['password'] ?? $config['database']['password'] ?? '');

$masterName = $config['master_db']['name'] ?? 'glee_master';

if ($mysqlUser === '') {
    fwrite(STDERR, "Missing [mysql] username in config.ini — needed to reach every tenant database.\n");
    exit(1);
}

// ── 2. CLI args ────────────────────────────────────────────

$args = [];
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--')) {
        if (str_contains($arg, '=')) {
            [$key, $value] = explode('=', substr($arg, 2), 2);
            $args[$key] = $value;
        } else {
            $args[substr($arg, 2)] = true;
        }
    }
}

$dryRun           = isset($args['dry-run']);
$onlyTenantCode   = $args['tenant'] ?? null;
$includeInactive  = isset($args['include-inactive']);

function fail(string $msg): never
{
    fwrite(STDERR, "[FAIL] {$msg}\n");
    exit(1);
}

function db(string $host, int $port, string $user, string $pass, string $dbname): PDO
{
    return new PDO(
        "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
}

/**
 * Same statement-splitting approach as migrate.php's runSql() —
 * strips comments, splits on ";\n". Works for the plain
 * CREATE TABLE / ALTER TABLE style every migration in this project
 * uses. "Already exists"/"Duplicate" errors are swallowed (that's
 * what makes a migration safe to re-run); anything else aborts.
 */
function runSqlFile(PDO $pdo, string $file): void
{
    $sql = file_get_contents($file);
    $sql = preg_replace('/--.*(\n|$)/', '', $sql);
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

    $stmts = array_filter(array_map('trim', explode(";\n", $sql)));

    foreach ($stmts as $stmt) {
        if ($stmt === '') {
            continue;
        }
        try {
            $pdo->exec($stmt);
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'exists') || str_contains($msg, 'Duplicate')) {
                continue;
            }
            throw $e;
        }
    }
}

function ensureMigrationsTable(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `schema_migrations` (
            `id`          bigint unsigned NOT NULL AUTO_INCREMENT,
            `migration`   varchar(190)    NOT NULL,
            `applied_at`  datetime        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_migration` (`migration`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function appliedMigrations(PDO $pdo): array
{
    return $pdo->query('SELECT migration FROM schema_migrations')
        ->fetchAll(PDO::FETCH_COLUMN);
}

// ── 3. Load tenant list from glee_master ────────────────────

$master = db($mysqlHost, $mysqlPort, $mysqlUser, $mysqlPass, $masterName);

$where = $includeInactive ? '1=1' : 'is_active = 1';
$bindings = [];

if ($onlyTenantCode !== null) {
    $where .= ' AND code = :code';
    $bindings[':code'] = $onlyTenantCode;
}

$stmt = $master->prepare("SELECT id, code, name, db_name, is_active FROM tenants WHERE {$where} ORDER BY code");
$stmt->execute($bindings);
$tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$tenants) {
    echo $onlyTenantCode !== null
        ? "No tenant found with code \"{$onlyTenantCode}\".\n"
        : "No tenants to process (use --include-inactive to also check disabled tenants).\n";
    exit(0);
}

$migrationsDir = __DIR__;

echo "\n== TENANT MIGRATIONS" . ($dryRun ? " (dry run — nothing will be changed)" : '') . " ==\n";
echo count($tenants) . " tenant(s) to check: " . implode(', ', array_column($tenants, 'code')) . "\n";
echo "Allowlisted migrations: " . implode(', ', INCREMENTAL_MIGRATIONS) . "\n\n";

$failures = [];

foreach ($tenants as $tenant) {
    $label = "{$tenant['code']} ({$tenant['db_name']})" . ($tenant['is_active'] ? '' : ' [inactive]');
    echo "── {$label} ──────────────────────────────\n";

    try {
        $tenantDb = db($mysqlHost, $mysqlPort, $mysqlUser, $mysqlPass, $tenant['db_name']);
    } catch (PDOException $e) {
        echo "  ✗ Could not connect: {$e->getMessage()}\n\n";
        $failures[] = $tenant['code'] . ': connection failed';
        continue;
    }

    if (!$dryRun) {
        ensureMigrationsTable($tenantDb);
    }

    $applied = appliedMigrationsSafe($tenantDb, $dryRun);

    $ranAny = false;

    foreach (INCREMENTAL_MIGRATIONS as $migration) {
        if (in_array($migration, $applied, true)) {
            echo "  = {$migration} (already applied)\n";
            continue;
        }

        $file = $migrationsDir . '/' . $migration;

        if (!file_exists($file)) {
            echo "  ✗ {$migration} — file not found, skipping\n";
            $failures[] = "{$tenant['code']}: missing file {$migration}";
            continue;
        }

        if ($dryRun) {
            echo "  → {$migration} (would apply)\n";
            continue;
        }

        try {
            runSqlFile($tenantDb, $file);
            $tenantDb->prepare('INSERT INTO schema_migrations (migration) VALUES (:m)')
                ->execute([':m' => $migration]);
            echo "  ✓ {$migration} applied\n";
            $ranAny = true;
        } catch (Throwable $e) {
            echo "  ✗ {$migration} FAILED: {$e->getMessage()}\n";
            $failures[] = "{$tenant['code']}: {$migration} — {$e->getMessage()}";
            // Stop this tenant's migration chain on first failure —
            // later migrations may depend on this one — but keep
            // going to the next tenant.
            break;
        }
    }

    if (!$ranAny && !$dryRun) {
        echo "  (nothing to do)\n";
    }

    echo "\n";
}

// ── Small helpers used above, defined last for readability ───

function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t
    ");
    $stmt->execute([':t' => $table]);
    return (bool) $stmt->fetchColumn();
}

function appliedMigrationsSafe(PDO $pdo, bool $dryRun): array
{
    // In dry-run mode ensureMigrationsTable() is never called, so on
    // a tenant that's never had a migration applied, the tracking
    // table itself won't exist yet — that's not an error, it just
    // means everything is pending.
    if (!tableExists($pdo, 'schema_migrations')) {
        return [];
    }
    return appliedMigrations($pdo);
}

// ── Summary ────────────────────────────────────────────────

if ($failures) {
    echo "== DONE WITH ERRORS ==\n";
    foreach ($failures as $f) {
        echo "  - {$f}\n";
    }
    echo "\n";
    exit(1);
}

echo "== DONE" . ($dryRun ? " (dry run)" : '') . " — no errors ==\n\n";
