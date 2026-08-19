<?php

use App\Core\App;
use App\Core\Container;
use App\Core\DB;
use App\Core\Request;
use App\Core\Router;
use App\Core\Response;
use App\Core\TenantContext;

// ── Base path helper ─────────────────────────────────────────
function base_path(string $path = ''): string
{
    return dirname(__DIR__) . ($path ? '/' . $path : '');
}

require base_path('vendor/autoload.php');

// ── Global helper functions (e, csrf_field, old, flash, asset, can, ...) ──
require base_path('bootstrap/helpers.php');

// ── Load config.ini + setup.ini ──────────────────────────────
$globalIni = base_path('config/config.ini');
$localIni  = base_path('config/setup.ini');

if (!file_exists($globalIni)) {
    die('Missing config/config.ini');
}

$ini = parse_ini_file($globalIni, true, INI_SCANNER_TYPED);
if ($ini === false) {
    die('Malformed config/config.ini');
}

// Merge setup.ini (override)
if (file_exists($localIni)) {
    $setup = parse_ini_file($localIni, true, INI_SCANNER_TYPED);
    if ($setup === false) {
        die('Malformed config/setup.ini');
    }

    foreach ($setup as $section => $values) {
        if (isset($ini[$section]) && is_array($ini[$section])) {
            $ini[$section] = array_merge($ini[$section], $values);
        } else {
            $ini[$section] = $values;
        }
    }
}

// ── Runtime config (DB overrides) ────────────────────────────
$GLOBALS['runtime_config'] = [];

// ── Config helper ────────────────────────────────────────────
function config(string $key, mixed $default = null): mixed
{
    static $cache = null;
    global $ini;

    if ($cache === null) {
        $cache = $ini ?? [];
    }

    $parts = explode('.', $key);

    // 1. Runtime override (DB wins)
    $value = $GLOBALS['runtime_config'] ?? [];
    foreach ($parts as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            $value = null;
            break;
        }
        $value = $value[$part];
    }
    if ($value !== null) {
        return $value;
    }

    // 2. INI fallback
    $section = $parts[0];
    $leaf    = $parts[1] ?? null;

    if ($leaf !== null) {
        return $cache[$section][$leaf] ?? $default;
    }

    return $cache[$section] ?? $default;
}

// ── env() shim ───────────────────────────────────────────────
function env(string $key, mixed $default = null): mixed
{
    return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
}

// ── Debug ────────────────────────────────────────────────────
if (config('app.debug', false)) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

date_default_timezone_set(config('app.timezone', 'UTC'));

// ── Security headers — every request, not just login ──────────
//

$cspNonce = bin2hex(random_bytes(16));
$GLOBALS['csp_nonce'] = $cspNonce;

if (!headers_sent()) {
    header("X-Frame-Options: SAMEORIGIN");
    header("X-Content-Type-Options: nosniff");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header(
		"Content-Security-Policy: " .
        "default-src 'self'; " .
        "script-src 'self' 'nonce-{$cspNonce}' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; " .
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; " .
        "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; " .
        "img-src 'self' data:; " .
        "connect-src 'self' https://cdn.jsdelivr.net; " .
        "object-src 'none'; " .
        "base-uri 'self'; " .
        "frame-ancestors 'self';"
    );
}

// ── SESSION ──────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_name(config('session.name', 'glee_session'));

    session_set_cookie_params([
        'lifetime' => (int)  config('session.lifetime', 7200),
        'httponly' => (bool) config('session.httponly', true),
        'secure'   => (bool) config('session.secure', false),
        'samesite' => config('session.samesite', 'Strict'),
    ]);

    session_start();
}

// ── DATABASE BOOTSTRAP (MASTER → TENANT) ─────────────────────
//
// Two modes, chosen automatically:
//
//   Dynamic domain mode — config.ini [platform] base_domain is set.
//   Tenant identity comes from the Host header on every request:
//     - "{code}.{base_domain}"        -> lookup by code (subdomain)
//     - the admin subdomain           -> platform/tenant-management panel
//     - the bare base domain / www.   -> reserved for the public site
//     - anything else                 -> lookup by custom_domain
//   One deployment serves every client; no per-tenant config.ini.
//
//   Legacy static mode — [platform] base_domain is blank (or absent).
//   Tenant identity comes from [tenant] code in THIS deployment's own
//   config.ini, exactly as before. A blank code means this
//   deployment IS the admin panel. Existing single-tenant
//   deployments keep working unchanged — this is purely additive.
try {
    $masterPdo = DB::master();

    $baseDomain = mb_strtolower(trim((string) config('platform.base_domain', '')));

    if ($baseDomain !== '') {
        // ── Dynamic domain mode ──────────────────────────────
        $host = mb_strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]);
        $adminSubdomain = mb_strtolower(trim((string) config('platform.admin_subdomain', 'manage')));
        $adminHost = $adminSubdomain . '.' . $baseDomain;

        if ($host === $baseDomain || $host === 'www.' . $baseDomain) {
            TenantContext::setPublicHost();
        } elseif ($host === $adminHost) {
            TenantContext::setAdminHost();
        } else {
            $stmt = $masterPdo->prepare("
                SELECT * FROM tenants
                WHERE (code = :code_via_subdomain OR custom_domain = :host)
                  AND is_active = 1
                LIMIT 1
            ");

            $codeViaSubdomain = str_ends_with($host, '.' . $baseDomain) && !str_contains(
                $sub = substr($host, 0, -(strlen($baseDomain) + 1)),
                '.'
            ) ? $sub : null;

            $stmt->execute([':code_via_subdomain' => $codeViaSubdomain, ':host' => $host]);
            $tenant = $stmt->fetch();

            if ($tenant) {
                $GLOBALS['runtime_config']['tenant'] = [
                    'id'    => $tenant['id'],
                    'code'  => $tenant['code'],
                    'name'  => $tenant['name'],
                    'email' => $tenant['email'],
                    'plan'  => $tenant['plan'],
                    'logo'  => $tenant['logo'],
                ];
                $GLOBALS['runtime_config']['database']['name'] = $tenant['db_name'];

                // Per-tenant MySQL username (DirectAdmin-provisioned
                // tenants only) — falls back to the single shared
                // [database] username when absent, exactly as before
                // this existed. The password is never per-tenant; it
                // stays the one shared [database] password either way.
                if (!empty($tenant['db_username'])) {
                    $GLOBALS['runtime_config']['database']['username'] = $tenant['db_username'];
                }

                TenantContext::setTenant($tenant);
            }
            // No match -> TenantContext stays unresolved; handled
            // right after the DI container is set up below.
        }
    } else {
        // ── Legacy static mode (unchanged behavior) ──────────
        $tenantCode = config('tenant.code');

        if ($tenantCode) {
            $stmt = $masterPdo->prepare("
                SELECT * FROM tenants
                WHERE code = :code AND is_active = 1
                LIMIT 1
            ");
            $stmt->execute([':code' => $tenantCode]);
            $tenant = $stmt->fetch();

            if ($tenant) {
                $GLOBALS['runtime_config']['tenant'] = [
                    'id'    => $tenant['id'],
                    'code'  => $tenant['code'],
                    'name'  => $tenant['name'],
                    'email' => $tenant['email'],
                    'plan'  => $tenant['plan'],
                    'logo'  => $tenant['logo'],
                ];
                $GLOBALS['runtime_config']['database']['name'] = $tenant['db_name'];

                if (!empty($tenant['db_username'])) {
                    $GLOBALS['runtime_config']['database']['username'] = $tenant['db_username'];
                }

                TenantContext::setTenant($tenant);
            }
        } else {
            // Blank [tenant] code, legacy mode -> this deployment
            // IS the admin panel (same convention as before).
            TenantContext::setAdminHost();
        }
    }
} catch (\Throwable $e) {
    if (config('app.debug')) {
        die("Bootstrap DB error: " . $e->getMessage());
    }
}

// ── DI Container ─────────────────────────────────────────────
$container = new Container();
App::setContainer($container);

$container->singleton(DB::class, fn() => DB::connect());
$container->bind(Request::class, fn() => new Request());
$container->singleton(Router::class, fn() => new Router());

// ── Exception handler ────────────────────────────────────────
set_exception_handler(function (\Throwable $e) {
    $code = $e->getCode();
    if (!is_int($code) || $code < 400 || $code > 599) {
        $code = 500;
    }

    if (config('app.debug', false)) {
        http_response_code($code);
        echo '<pre><strong>Error ' . $code . ':</strong> '
            . htmlspecialchars($e->getMessage()) . "\n\n"
            . htmlspecialchars($e->getTraceAsString())
            . '</pre>';
        exit;
    }

    Response::abort($code, 'Server Error');
});

// ── Public/unresolved host short-circuit (dynamic domain mode) ──
// Runs after the container so the exception handler above is
// already in place, but before the router ever sees the request —
// neither case has a tenant DB to route into.
if (TenantContext::isPublicHost()) {
    http_response_code(200);
    echo '<!DOCTYPE html><html><head><title>' . htmlspecialchars(config('app.name', 'GPMS')) . '</title>'
        . '<meta name="viewport" content="width=device-width, initial-scale=1.0"></head>'
        . '<body style="font-family:system-ui,sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;background:#0f1115;color:#e6e6e6;text-align:center;">'
        . '<div><h1 style="margin-bottom:8px;">' . htmlspecialchars(config('app.name', 'GPMS')) . '</h1>'
        . '<p style="color:#9aa0a6;">Site coming soon.</p></div></body></html>';
    exit;
}

// ── Admin host: only /master/* routes make sense here ──────────
// The admin host has no tenant database connected at all (by
// design — it's not tied to any one client). Before this guard,
// an ordinary tenant-facing route (like the regular /login) was
// still reachable here and would run all the way into a real DB
// query with no database selected, surfacing as a confusing
// "No database selected" SQL error instead of a clear redirect to
// where login on this domain actually belongs.
if (TenantContext::isAdminHost()) {
    $adminRequestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

    if (!str_starts_with($adminRequestPath, '/master')) {
        header('Location: /master/login');
        exit;
    }
}

if (!TenantContext::isResolved()) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><title>Not Found</title>'
        . '<meta name="viewport" content="width=device-width, initial-scale=1.0"></head>'
        . '<body style="font-family:system-ui,sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;background:#0f1115;color:#e6e6e6;text-align:center;">'
        . '<div><h1>404</h1><p style="color:#9aa0a6;">This address isn\'t connected to an account.</p></div></body></html>';
    exit;
}

return $container;