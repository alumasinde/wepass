<?php

/**
 * test-directadmin.php — run once, by hand, to isolate exactly where
 * tenant provisioning is failing. Tests the FULL chain
 * provisionTenant() uses, one step at a time, so a failure tells you
 * exactly which link broke instead of a single opaque error:
 *
 *   1. DirectAdmin API: create a throwaway database + grant runtime_db_user
 *   2. Direct MySQL PDO connect to it, AS runtime_db_user
 *   3. (if step 2 fails) retry once after a short delay, to check
 *      whether this is a grant-propagation timing issue
 *   4. DirectAdmin API: delete the throwaway database (cleanup)
 *
 * Usage: php test-directadmin.php
 * Delete this file from the server once you're done with it.
 */

function base_path(string $path = ''): string
{
    return __DIR__ . ($path ? '/' . $path : '');
}

require base_path('vendor/autoload.php');

$globalIni = base_path('config/config.ini');
if (!file_exists($globalIni)) {
    fwrite(STDERR, "Missing config/config.ini\n");
    exit(1);
}
$ini = parse_ini_file($globalIni, true, INI_SCANNER_TYPED);
if (file_exists(base_path('config/setup.ini'))) {
    $setup = parse_ini_file(base_path('config/setup.ini'), true, INI_SCANNER_TYPED);
    if ($setup !== false) {
        foreach ($setup as $section => $values) {
            $ini[$section] = isset($ini[$section]) && is_array($ini[$section])
                ? array_merge($ini[$section], $values) : $values;
        }
    }
}
$GLOBALS['runtime_config'] = [];

function config(string $key, mixed $default = null): mixed
{
    static $cache = null;
    global $ini;
    if ($cache === null) $cache = $ini ?? [];
    $parts = explode('.', $key);
    $value = $GLOBALS['runtime_config'] ?? [];
    foreach ($parts as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) { $value = null; break; }
        $value = $value[$part];
    }
    if ($value !== null) return $value;
    $section = $parts[0];
    $leaf = $parts[1] ?? null;
    return $leaf !== null ? ($cache[$section][$leaf] ?? $default) : ($cache[$section] ?? $default);
}

function out(string $line): void
{
    echo (PHP_SAPI === 'cli') ? $line . "\n" : htmlspecialchars($line) . "<br>\n";
}

function tryConnect(string $host, string $port, string $dbName, string $user, string $pass): array
{
    try {
        $pdo = new PDO(
            "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4",
            $user, $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
        );
        $pdo->query('SELECT 1');
        return [true, null];
    } catch (\Throwable $e) {
        return [false, $e->getMessage()];
    }
}

out('== Full provisioning-chain test ==');
out('');

$host          = (string) config('directadmin.host', '');
$daUsername    = (string) config('directadmin.username', '');
$runtimeDbUser = (string) config('directadmin.runtime_db_user', '');
$dbPassword    = (string) config('database.password', '');
$mysqlHost     = (string) config('database.host', 'localhost');
$mysqlPort     = (string) config('database.port', '3306');

out("directadmin.host:            " . ($host ?: '(NOT SET)'));
out("directadmin.username:        " . ($daUsername ?: '(NOT SET)'));
out("directadmin.runtime_db_user: " . ($runtimeDbUser ?: '(NOT SET)'));
out("database.password:           " . ($dbPassword !== '' ? '(set, ' . strlen($dbPassword) . ' chars)' : '(EMPTY)'));
out("database.host used for PDO:  {$mysqlHost}:{$mysqlPort}");
out('');

if ($host === '' || $daUsername === '' || $runtimeDbUser === '') {
    out('Missing [directadmin] config — fill it in first.');
    exit(1);
}

try {
    $client = new \App\Core\DirectAdminClient();
    $testSuffix = 'gpms_da_test_' . substr(md5((string) time()), 0, 6);

    out("STEP 1: Creating test database via DirectAdmin API (suffix: {$testSuffix})...");
    $fullName = $client->createDatabase($testSuffix, $runtimeDbUser, $dbPassword);
    out("  -> SUCCESS: {$fullName}");
    out('');

    out("STEP 2: Connecting via PDO as '{$runtimeDbUser}' immediately...");
    [$ok, $err] = tryConnect($mysqlHost, $mysqlPort, $fullName, $runtimeDbUser, $dbPassword);

    if ($ok) {
        out('  -> SUCCESS — connected and queried immediately. The chain works end to end.');
    } else {
        out("  -> FAILED: {$err}");
        out('');
        out('STEP 3: Retrying after a 3-second delay (checking for a grant-propagation timing issue)...');
        sleep(3);
        [$ok2, $err2] = tryConnect($mysqlHost, $mysqlPort, $fullName, $runtimeDbUser, $dbPassword);
        if ($ok2) {
            out('  -> SUCCESS on retry. This IS a timing issue — the grant takes a moment to become');
            out('     usable after DirectAdmin creates it. Provisioning needs a short delay/retry added.');
        } else {
            out("  -> STILL FAILED: {$err2}");
            out('  -> This is NOT a timing issue. The credentials themselves are wrong somehow —');
            out("     most likely: MySQL's actual password for '{$runtimeDbUser}' does not match");
            out("     the current [database] password in config.ini (e.g. it was rotated after");
            out("     '{$runtimeDbUser}' was first created, and DirectAdmin's password-reset-on-reuse");
            out('     behavior did not apply the way expected).');
        }
    }
    out('');

    out('STEP 4: Deleting test database via DirectAdmin API (cleanup)...');
    $client->deleteDatabase($fullName);
    out('  -> Done.');

} catch (\Throwable $e) {
    out('FAILED at the DirectAdmin API step itself: ' . $e->getMessage());
    exit(1);
}
