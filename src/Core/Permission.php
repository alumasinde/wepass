<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\TenantContext;

final class Permission
{
    public static function userHasPermission(int $userId, string $permission, ?int $tenantId = null): bool
    {
        $tenantId ??= TenantContext::id();
        if ($tenantId === null) {
            return false;
        }

        $db = DB::tenant();
        $stmt = $db->prepare(
            'SELECT 1
             FROM user_roles ur
             INNER JOIN role_permissions rp ON rp.role_id = ur.role_id
             INNER JOIN permissions p ON p.id = rp.permission_id
             WHERE ur.user_id = ? AND p.name = ? AND p.is_active = 1
             LIMIT 1'
        );
        $stmt->execute([$userId, $permission]);

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
}
