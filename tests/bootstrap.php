<?php

declare(strict_types=1);

/**
 * tests/bootstrap.php — PHPUnit entry point.
 *
 * Deliberately minimal: just Composer autoloading plus a session,
 * NOT the full bootstrap/app.php. That file resolves a tenant
 * database from the request's Host header (see TenantContext in
 * bootstrap/app.php) and sends real HTTP headers — neither makes
 * sense under a CLI test run with no actual request, and trying to
 * reuse it here would mean either mocking an HTTP request just to
 * satisfy tenant resolution, or accidentally depending on a real
 * MySQL connection for tests that shouldn't need one at all.
 *
 * The unit tests in this suite are chosen specifically to not need
 * a live database connection (see the docblocks in
 * GatepassPolicyTest/ApprovalPolicyTest for how). If a future test
 * genuinely needs the DB layer, prefer giving it its own explicit
 * PDO connection in that test rather than pulling in the whole
 * app bootstrap here.
 */

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        return dirname(__DIR__) . ($path ? '/' . $path : '');
    }
}

require base_path('vendor/autoload.php');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
