<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Permission;
use App\Core\Response;
use App\Core\TenantContext;

final class PermissionMiddleware
{
    public function __construct(private string $permission)
    {
    }

    public function handle(callable $next, ...$args): mixed
    {
        $userId = Auth::userId();
        if ($userId === null) {
            return Response::json(['error' => 'Unauthenticated'], 401);
        }

        $tenantId = TenantContext::id();
        if ($tenantId === null) {
            return Response::json(['error' => 'Tenant context unavailable'], 403);
        }

        if (!Permission::userHasPermission($userId, $this->permission, $tenantId)) {
            return Response::json(['error' => 'Forbidden'], 403);
        }

        return $next(...$args);
    }
}
