<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Permission;
use App\Core\Request;
use App\Core\Response;
use App\Core\TenantContext;

final class RouteAuthorizationMiddleware
{
    /** @var array<string, array<string, array<int, string>>> */
    private array $rules;

    public function __construct()
    {
        $this->rules = require __DIR__ . '/../../config/route_permissions.php';
    }

    public function handle(Request $request): void
    {
        $method = strtoupper($request->method());
        $uri = rtrim(parse_url($request->uri(), PHP_URL_PATH) ?: '/', '/');
        $uri = $uri === '' ? '/' : $uri;

        $permission = $this->resolvePermission($method, $uri);
        if ($permission === null) {
            return;
        }

        $userId = Auth::id();
        if ($userId === null) {
            Response::abort(401);
        }

        if (!TenantContext::hasTenant()) {
            Response::abort(403, 'Tenant context unavailable.');
        }

        $tenant = TenantContext::tenant();
        $tenantId = isset($tenant['id']) && is_numeric($tenant['id']) ? (int) $tenant['id'] : null;
        if ($tenantId === null || $tenantId <= 0) {
            Response::abort(403, 'Tenant context unavailable.');
        }

        if (!Permission::userHasAnyPermission($userId, $permission, $tenantId)) {
            Response::abort(403);
        }
    }

    /** @return array<int, string>|null */
    private function resolvePermission(string $method, string $uri): ?array
    {
        foreach ($this->rules[$method] ?? [] as $pattern => $permissions) {
            if (preg_match($pattern, $uri) === 1) {
                return $permissions;
            }
        }

        return null;
    }
}
