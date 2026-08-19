<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Seeder must be run from CLI.\n");
    exit(1);
}

/**
 * ============================================================
 * 1. LOAD + MERGE CONFIG
 * ============================================================
 */
$root = dirname(__DIR__);

$globalIni = $root . '/config/config.ini';
$localIni  = $root . '/config/setup.ini';

if (!file_exists($globalIni)) {
    die("Missing config/config.ini\n");
}
if (!file_exists($localIni)) {
    die("Missing config/setup.ini\n");
}

$config = parse_ini_file($globalIni, true, INI_SCANNER_TYPED);
$setup  = parse_ini_file($localIni,  true, INI_SCANNER_TYPED);

foreach ($setup as $section => $values) {
    $config[$section] = isset($config[$section])
        ? array_merge($config[$section], $values)
        : $values;
}

/**
 * ============================================================
 * 2. EXTRACT CONFIG
 * ============================================================
 */

$mysqlHost = $config['mysql']['host'] ?? '127.0.0.1';
$mysqlPort = (int)($config['mysql']['port'] ?? 3306);
$mysqlUser = $config['mysql']['username'] ?? 'root';
$mysqlPass = (string)($config['mysql']['password'] ?? ''); // FIXED

$masterName = $config['master_db']['name'] ?? 'glee_master';
$tenantDb   = $config['database']['name'] ?? null;

$tenantCode  = $config['tenant']['code']  ?? null;
$tenantName  = $config['tenant']['name']  ?? 'Tenant';
$tenantPlan  = $config['tenant']['plan']  ?? 'starter';
$tenantLogo  = $config['tenant']['logo']  ?? '';
$tenantEmail = $config['tenant']['email'] ?? '';

if (!$tenantDb || !$tenantCode) {
    die("Missing tenant config (database.name / tenant.code)\n");
}

function fail(string $msg): never {
    fwrite(STDERR, "[FAIL] {$msg}\n");
    exit(1);
}

function generatePassword(int $length = 16): string
{
    $bytes = random_bytes($length);
    return substr(str_replace(['+', '/', '='], '', base64_encode($bytes)), 0, $length);
}

function dbRoot(string $host, int $port, string $user, string $pass): PDO
{
    return new PDO(
        "mysql:host={$host};port={$port};charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
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

function ensureDb(PDO $pdo, string $name): void {
    $exists = $pdo->query("
        SELECT SCHEMA_NAME FROM information_schema.SCHEMATA
        WHERE SCHEMA_NAME = " . $pdo->quote($name)
    )->fetchColumn();

    if (!$exists) {
        $pdo->exec("
            CREATE DATABASE `{$name}`
            CHARACTER SET utf8mb4
            COLLATE utf8mb4_unicode_ci
        ");
        echo "Created DB {$name}\n";
    }
}

function runSql(PDO $pdo, string $file): void {
    if (!file_exists($file)) fail("Missing SQL: {$file}");

    $sql = file_get_contents($file);

    // strip comments
    $sql = preg_replace('/--.*(\n|$)/', '', $sql);
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

    $stmts = array_filter(array_map('trim', explode(";\n", $sql)));

    foreach ($stmts as $stmt) {
        try {
            $pdo->exec($stmt);
        } catch (PDOException $e) {
            $msg = $e->getMessage();

            if (
                str_contains($msg, 'exists') ||
                str_contains($msg, 'Duplicate')
            ) continue;

            fail($msg . "\n{$stmt}");
        }
    }
}

function seedTenantSettings(PDO $tenant): void
{
    // Same shape TenantSettingService/GatepassService/BadgeService
    // actually read: config_json IS the settings object directly.
    $settings = [
        'gatepass_numbering' => [
            'prefix'        => 'GP',
            'include_year'  => true,
            'include_month' => false,
            'padding'       => 5,
            'reset_yearly'  => true,
            'current_year'  => (int) date('Y'),
            'sequence'      => 1,
        ],
        'badge_numbering' => [
            'prefix'       => 'BDG',
            'mode'         => 'sequential',
            'include_year' => false,
            'padding'      => 5,
            'reset_yearly' => false,
            'current_year' => (int) date('Y'),
            'sequence'     => 1,
        ],
    ];

    $stmt = $tenant->prepare("
        INSERT INTO tenant_settings (setting_key, config_json, updated_at)
        VALUES (:key, :config, NOW())
        ON DUPLICATE KEY UPDATE config_json = VALUES(config_json), updated_at = NOW()
    ");

    foreach ($settings as $key => $config) {
        $stmt->execute([
            ':key'    => $key,
            ':config' => json_encode($config),
        ]);
    }

    echo "Tenant settings seeded\n";
}

/**
 * ============================================================
 * 4. CONNECT MYSQL
 * ============================================================
 */

echo "\n== GLEE SEEDER ==\n";

$rootDb = dbRoot($mysqlHost, $mysqlPort, $mysqlUser, $mysqlPass);

/**
 * ============================================================
 * 5. MASTER DB + TENANT REGISTRATION
 * ============================================================
 */

ensureDb($rootDb, $masterName);
$master = db($mysqlHost, $mysqlPort, $mysqlUser, $mysqlPass, $masterName);

// create schema
runSql($master, __DIR__ . '/master.sql');

$master->beginTransaction();

try {
    $stmt = $master->prepare("SELECT id FROM tenants WHERE code = ?");
    $stmt->execute([$tenantCode]);
    $tenantId = $stmt->fetchColumn();

    if (!$tenantId) {
        $master->prepare("
            INSERT INTO tenants (name, code, db_name, plan, logo, email)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([$tenantName, $tenantCode, $tenantDb, $tenantPlan, $tenantLogo, $tenantEmail]);

        $tenantId = $master->lastInsertId();
        echo "Tenant created\n";
    } else {
        $master->prepare("
            UPDATE tenants
            SET name=?, db_name=?, plan=?, logo=?, email=?
            WHERE code=?
        ")->execute([$tenantName, $tenantDb, $tenantPlan, $tenantLogo, $tenantEmail, $tenantCode]);

        echo "Tenant updated\n";
    }

    $master->commit();

} catch (Throwable $e) {
    $master->rollBack();
    fail($e->getMessage());
}

ensureDb($rootDb, $tenantDb);
$tenant = db($mysqlHost, $mysqlPort, $mysqlUser, $mysqlPass, $tenantDb);

runSql($tenant, __DIR__ . '/001_schema.sql');
runSql($tenant, __DIR__ . '/002_seed_reference.sql');

seedTenantSettings($tenant);

/**
 * ============================================================
 * 6. TENANT ADMIN (from [tenant_admin] in config.ini/setup.ini)
 * ============================================================
 * 002_seed_reference.sql deliberately contains NO users — every
 * tenant needs its own real admin, never a shared/reused account.
 * Configure [tenant_admin] email/first_name/last_name in
 * setup.ini for local dev; omit password and one is generated
 * and printed once.
 */
$adminEmail = $config['tenant_admin']['email']      ?? null;
$adminFirst = $config['tenant_admin']['first_name'] ?? 'Admin';
$adminLast  = $config['tenant_admin']['last_name']  ?? '';
$adminPass  = (string) ($config['tenant_admin']['password'] ?? '');

if ($adminEmail) {
    $stmt = $tenant->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$adminEmail]);

    if (!$stmt->fetchColumn()) {
        $generated = false;
        if ($adminPass === '') {
            $adminPass = generatePassword();
            $generated = true;
        }

        $username = preg_replace('/[^a-z0-9]/', '', explode('@', $adminEmail)[0]) ?: 'admin';

        $tenant->prepare("
            INSERT INTO users (email, password_hash, first_name, last_name, username)
            VALUES (?, ?, ?, ?, ?)
        ")->execute([$adminEmail, password_hash($adminPass, PASSWORD_DEFAULT), $adminFirst, $adminLast, $username]);

        $tenant->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (?, 1)')
            ->execute([(int) $tenant->lastInsertId()]);

        echo "Tenant admin created: {$adminEmail}\n";
        if ($generated) {
            echo "  Generated password: {$adminPass}\n";
            echo "  ⚠ Save this now — it will not be shown again.\n";
        }
    } else {
        echo "Tenant admin '{$adminEmail}' already exists — skipped.\n";
    }
} else {
    echo "No [tenant_admin] email configured — skipping admin creation.\n";
    echo "This tenant has NO users yet. Add one manually or set [tenant_admin] in setup.ini and re-run.\n";
}

echo "\n✔ Tenant '{$tenantCode}' ready\n";
echo "Run: php -S localhost:8000 -t public/\n\n";