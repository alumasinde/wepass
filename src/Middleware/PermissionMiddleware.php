<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Permission;
use App\Core\Request;
use App\Core\Response;
use App\Core\TenantContext;

final class PermissionMiddleware
{
    /**
     * Router passes middleware parameters to handle(), not the constructor.
     * Keeping the middleware stateless also makes it safe for container use.
     */
    public function handle(Request $request, ...$params): void
    {
        $permission = $params[0] ?? null;
        if (!is_string($permission) || trim($permission) === '') {
            Response::abort(500, 'Permission middleware is misconfigured.');
        }

        $userId = Auth::id();
        if ($userId === null) {
            Response::abort(401);
        }

        if (!TenantContext::hasTenant()) {
            Response::abort(403, 'Tenant context unavailable.');
        }

        if (!Permission::userHasPermission($userId, $permission, TenantContext::id())) {
            Response::abort(403);
        }
    }
}
