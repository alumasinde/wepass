<?php

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

/**
 * CSRFMiddleware — verifies the CSRF token on every state-changing
 * request (anything other than GET/HEAD/OPTIONS).
 *
 * This is applied automatically to EVERY route by Router::dispatch()
 * — no route has to opt in, and no controller needs its own copy of
 * this check. That's deliberate: the previous version of this class
 * existed but was never attached anywhere, so most POST endpoints in
 * the app had no CSRF protection at all even though many forms
 * rendered a token. Making it a router-level guard means a new route
 * can't accidentally ship without protection.
 *
 * Uses the SAME session key and field name as csrf_token()/
 * csrf_field() in bootstrap/helpers.php ('csrf_token' — NOT the old
 * '_token'), so it actually validates what the forms send. A handful
 * of controllers (Auth, Approval, Tenant, MasterAdmin) already run
 * their own equivalent check inline — that's redundant now but
 * harmless, since it reads the same session value rather than
 * consuming/rotating it.
 */
class CSRFMiddleware
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function handle(Request $request): void
    {
        $method = strtoupper($request->method());

        if (in_array($method, self::SAFE_METHODS, true)) {
            return;
        }

        $submitted = (string) $request->input('csrf_token', '');
        $expected  = (string) ($_SESSION['csrf_token'] ?? '');

        if ($expected === '' || $submitted === '' || !hash_equals($expected, $submitted)) {
            Response::abort(419, 'Your session has expired. Please refresh the page and try again.');
        }
    }
}
