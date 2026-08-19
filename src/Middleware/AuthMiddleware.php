<?php

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\TenantContext;

class AuthMiddleware
{
    public function handle(Request $request): void
    {
        if (!Auth::check()) {
            Response::redirect('/login');
            exit;
        }

        $this->guardTenantBinding();
    }

    /**
     * Confirms the session's authenticated user actually belongs to
     * the tenant THIS request resolved to.
     *
     * Session storage is shared across every tenant in this
     * deployment, but DB::connect() picks a different physical
     * database purely from the current request's Host header
     * (dynamic-domain mode, see bootstrap/app.php). Before this
     * check, nothing tied a session to the tenant database it was
     * actually created against — Auth::check() only verified
     * "is somebody logged in", never "logged in to THIS tenant".
     *
     * A session cookie issued after logging in on one tenant's
     * subdomain, replayed against a different tenant's subdomain
     * (trivial outside a real browser — just set the Host header on
     * the request; no same-origin protection applies to that), would
     * previously have been accepted as authenticated there too,
     * using the cached user id/permissions from the wrong tenant
     * against the new tenant's actual database. This closes that gap
     * by forcing a fresh login whenever the two don't match.
     */
    private function guardTenantBinding(): void
    {
        $sessionTenantCode = $_SESSION['tenant']['code'] ?? null;

        $currentTenantCode = TenantContext::hasTenant()
            ? (TenantContext::tenant()['code'] ?? null)
            : ((string) config('tenant.code', '') !== '' ? config('tenant.code') : null);

        // Legacy static single-tenant deployments (no [platform]
        // base_domain configured) have no per-request dynamic tenant
        // to compare against — there's nothing to bind here, and the
        // cross-tenant replay this guards against isn't possible in
        // that mode (one deployment = one tenant DB, fixed at
        // config.ini level, not resolved per request).
        if ($currentTenantCode === null) {
            return;
        }

        if ($sessionTenantCode === null || $sessionTenantCode !== $currentTenantCode) {
            Auth::logout();
            Response::redirect('/login');
            exit;
        }
    }
}
