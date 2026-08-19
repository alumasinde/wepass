<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\TenantContext;
use App\Core\Response;

final class Permission
{
    public static function userHasPermission(int $userId, string $permission, ?int $tenantId = null): bool
    {
        $tenantId ??= TenantContext::id();
        if ($tenantId === null || $userId <= 0 || trim($permission) === '') {
            return false;
        }

        [$module, $action] = array_pad(explode('.', strtolower(trim($permission)), 2), 2, '');
        if ($module === '' || $action === '') {
            return false;
        }

        $db = DB::tenant();
        $stmt = $db->prepare(
            'SELECT 1
             FROM user_roles ur
             INNER JOIN role_permissions rp ON rp.role_id = ur.role_id
             INNER JOIN permissions p ON p.id = rp.permission_id
             INNER JOIN modules m ON m.id = p.module_id
             INNER JOIN actions a ON a.id = p.action_id
             WHERE ur.user_id = ?
               AND LOWER(m.name) = ?
               AND LOWER(a.name) = ?
             LIMIT 1'
        );
        $stmt->execute([$userId, $module, $action]);

        return (bool) $stmt->fetchColumn();
    }

    public static function userHasAnyPermission(int $userId, array $permissions, ?int $tenantId = null): bool
    {
        foreach ($permissions as $permission) {
            if (self::userHasPermission($userId, (string) $permission, $tenantId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Require at least one permission. The fallback list is temporary
     * compatibility for installations upgrading to the granular Phase 2
     * permission model; it can be removed once roles are migrated.
     */
    public static function requireAny(int $userId, array $permissions, array $fallbackPermissions = []): void
    {
        if (self::userHasAnyPermission($userId, $permissions)) {
            return;
        }

        if ($fallbackPermissions !== [] && self::userHasAnyPermission($userId, $fallbackPermissions)) {
            return;
        }

        Response::abort(403);
    }
}
