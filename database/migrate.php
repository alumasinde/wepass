<?php
declare(strict_types=1);

/**
 * ============================================================
 * migrate.php — master database + super admin bootstrap
 * ============================================================
 * Same pattern as database/Seeder.php (config.ini-driven, safe to
 * re-run), but scoped to the MASTER database only:
 *
 *   1. Create `glee_master` if it doesn't exist.
 *   2. Run master.sql (idempotent — CREATE TABLE IF NOT EXISTS).
 *   3. Ensure at least one active super admin account exists in
 *      master_admins.
 *
 * It deliberately does NOT create or touch any tenant database.
 * Once a super admin exists, log in at /master/login and create
 * tenants through the UI (Settings > Tenants > New Tenant) —
 * that flow provisions the tenant's database, seeds it from
 * 002_seed_reference.sql, and registers it in glee_master for you.
 *
 * Usage:
 *   php database/migrate.php
 *   php database/migrate.php --email=admin@albatech.co.ke --name="Albert Masinde"
 *   php database/migrate.php --email=admin@albatech.co.ke --name="Albert Masinde" --password="something-strong"
 *
 * If --password is omitted, a random one is generated and printed
 * ONCE. Nothing is ever hardcoded into source.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "migrate.php must be run from CLI.\n");
    exit(1);
}

$root = dirname(__DIR__);

// ── 1. Load + merge config (same pattern as Seeder.php) ──────

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
    fwrite(STDERR, "Missing [mysql] username in config.ini — needed to create databases.\n");
    exit(1);
}

// ── 2. CLI args ────────────────────────────────────────────

$args = [];
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--') && str_contains($arg, '=')) {
        [$key, $value] = explode('=', substr($arg, 2), 2);
        $args[$key] = $value;
    }
}

function fail(string $msg): never
{
    fwrite(STDERR, "[FAIL] {$msg}\n");
    exit(1);
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

function ensureDb(PDO $pdo, string $name): void
{
    $exists = $pdo->query("
        SELECT SCHEMA_NAME FROM information_schema.SCHEMATA
        WHERE SCHEMA_NAME = " . $pdo->quote($name)
    )->fetchColumn();

    if (!$exists) {
        $pdo->exec("CREATE DATABASE `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo "Created DB {$name}\n";
    }
}

function runSql(PDO $pdo, string $file): void
{
    if (!file_exists($file)) {
        fail("Missing SQL: {$file}");
    }

    $sql = file_get_contents($file);
    $sql = preg_replace('/--.*(\n|$)/', '', $sql);
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

    $stmts = array_filter(array_map('trim', explode(";\n", $sql)));

    foreach ($stmts as $stmt) {
        try {
            $pdo->exec($stmt);
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'exists') || str_contains($msg, 'Duplicate')) {
                continue;
            }
            fail($msg . "\n{$stmt}");
        }
    }
}

function generatePassword(int $length = 16): string
{
    $bytes = random_bytes($length);
    return substr(str_replace(['+', '/', '='], '', base64_encode($bytes)), 0, $length);
}

// ── 3. Master DB + schema ─────────────────────────────────────

echo "\n== GLEE MIGRATE (master) ==\n";

$rootDb = dbRoot($mysqlHost, $mysqlPort, $mysqlUser, $mysqlPass);

ensureDb($rootDb, $masterName);
$master = db($mysqlHost, $mysqlPort, $mysqlUser, $mysqlPass, $masterName);

runSql($master, __DIR__ . '/master.sql');
echo "Master schema up to date.\n";

// ── 4. Super admin ────────────────────────────────────────────

$existing = (int) $master->query("SELECT COUNT(*) FROM master_admins WHERE is_active = 1")->fetchColumn();

if ($existing > 0 && !isset($args['email'])) {
    echo "\n{$existing} active super admin account(s) already exist — nothing to do.\n";
    echo "Pass --email=... to add another.\n\n";
    exit(0);
}

$email = $args['email'] ?? null;

if ($email === null) {
    fwrite(STDOUT, "Super admin email: ");
    $email = trim(fgets(STDIN));
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fail('A valid email is required.');
}

$stmt = $master->prepare('SELECT id FROM master_admins WHERE email = :email');
$stmt->execute([':email' => mb_strtolower($email)]);

if ($stmt->fetchColumn()) {
    echo "A super admin with that email already exists — nothing to do.\n\n";
    exit(0);
}

$name = $args['name'] ?? null;
if ($name === null) {
    fwrite(STDOUT, "Full name: ");
    $name = trim(fgets(STDIN));
}
[$firstName, $lastName] = array_pad(explode(' ', trim($name), 2), 2, '');

$password = $args['password'] ?? null;
$generated = false;

if ($password === null || $password === '') {
    $password  = generatePassword();
    $generated = true;
}

if (strlen($password) < 12) {
    fail('Password must be at least 12 characters.');
}

$master->prepare('
    INSERT INTO master_admins (email, password_hash, first_name, last_name)
    VALUES (:email, :hash, :first, :last)
')->execute([
    ':email' => mb_strtolower($email),
    ':hash'  => password_hash($password, PASSWORD_DEFAULT),
    ':first' => $firstName,
    ':last'  => $lastName,
]);

echo "\n✔ Super admin created: {$email}\n";

if ($generated) {
    echo "  Generated password: {$password}\n";
    echo "  ⚠ Save this now — it will not be shown again. Change it after first login.\n";
}

echo "\nLog in at /master/login, then create tenants under Settings > Tenants.\n\n";
