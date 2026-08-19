<?php

namespace App\Middleware;

use App\Core\Response;

/**
 * TenantMiddleware — per-database isolation model.
 *
 * With per-database isolation there is no runtime tenant_id to resolve
 * from the URL. Tenant identity is loaded at login from glee_master
 * and stored in the session. This middleware simply ensures the user
 * has an authenticated session (AuthMiddleware handles the actual auth
 * check; this is kept for BC compatibility with routes that reference it).
 */
class TenantMiddleware
{
    public function handle(): void
    {
        // Per-database model: no subdomain/tenant_id resolution needed.
        // AuthMiddleware already guards authenticated routes.
        // This middleware is a no-op but kept for route compatibility.
    }
}
