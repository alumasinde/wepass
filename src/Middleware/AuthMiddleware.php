<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\TenantContext;
use App\Modules\Auth\Repositories\UserRepository;

class AuthMiddleware
{
    public function handle(Request $request): void
    {
        if (!Auth::check()) {
            Response::redirect('/login');
            exit;
        }

        if (!$this->sessionIsCurrent()) {
            Auth::logout();
            Response::redirect('/login');
            exit;
        }

        $this->guardTenantBinding();
    }

    /**
     * Server-side session revocation.
     *
     * The session stores the user's auth_version at login. Password
     * resets and role/security changes can increment the database
     * value, invalidating every older session without requiring us
     * to maintain a server-side session list.
     */
    private function sessionIsCurrent(): bool
    {
        $userId = Auth::id();
        $sessionVersion = $_SESSION['auth_version'] ?? null;

        if ($userId === null || !is_int($sessionVersion) && !ctype_digit((string) $sessionVersion)) {
            return false;
        }

        try {
            $state = (new UserRepository())->getSessionSecurityState($userId);
        } catch (\Throwable $e) {
            // Fail closed. A security-state lookup failure must not turn
            // into an implicit authentication bypass.
            error_log('[AuthMiddleware] Session security lookup failed: ' . $e->getMessage());
            return false;
        }

        if ($state === null || !$state['is_active']) {
            return false;
        }

        return (int) $sessionVersion === (int) $state['auth_version'];
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
     * would previously have been accepted as authenticated there too.
     * This forces a fresh login whenever the two don't match.
     */
    private function guardTenantBinding(): void
    {
        $sessionTenantCode = $_SESSION['tenant']['code'] ?? null;

        $currentTenantCode = TenantContext::hasTenant()
            ? (TenantContext::tenant()['code'] ?? null)
            : ((string) config('tenant.code', '') !== '' ? config('tenant.code') : null);

        // Legacy static single-tenant deployments (no [platform]
        // base_domain configured) have no per-request dynamic tenant
        // to compare against.
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
