<?php

namespace App\Middleware;

use App\Core\Permission;
use App\Core\Request;
use App\Core\Response;

/**
 * PermissionMiddleware — guards a route behind a specific permission
 * key (e.g. "gatepasses.create", "settings.manage_users").
 *
 * Attach via the array form so the permission string is passed as a
 * parameter:
 *
 *   $router->get('/settings/users', [UserManagementController::class, 'index'],
 *       [AuthMiddleware::class, [PermissionMiddleware::class, 'settings.manage_users']]
 *   );
 */
class PermissionMiddleware
{
    public function handle(Request $request, string $permission): void
    {
        $perm = new Permission(\App\Core\DB::connect());

        if (!$perm->can($permission)) {
            Response::abort(403, "You don't have permission to do that ({$permission}).");
        }
    }
}
