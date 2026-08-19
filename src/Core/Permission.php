<?php

declare(strict_types=1);

namespace App\Core;

final class Permission
{
    /**
     * Optional dependency retained for backwards compatibility with existing
     * controllers. Authorization is tenant-aware and does not use this value.
     */
    public function __construct(private readonly mixed $connection = null) {}

    public function can(string $permission, ?int $userId = null, ?int $tenantId = null): bool
    {
        $userId ??= isset($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : null;
        if ($userId === null || $userId <= 0 || trim($permission) === '') {
            return false;
        }

        if (!empty($_SESSION['is_super_admin'])) {
            return true;
        }

        return self::userHasPermission($userId, $permission, $tenantId);
    }

    public static function userHasPermission(int $userId, string $permission, ?int $tenantId = null): bool
    {
        if ($userId <= 0 || trim($permission) === '') {
            return false;
        }

        if (!empty($_SESSION['is_super_admin']) && (int) ($_SESSION['user']['id'] ?? $userId) === $userId) {
            return true;
        }

        // Login builds this cache from the tenant DB. Auth-version/session
        // invalidation makes role changes revoke stale sessions.
        if (isset($_SESSION['permissions']) && is_array($_SESSION['permissions'])) {
            return !empty($_SESSION['permissions'][$permission]);
        }

        $tenantId ??= self::tenantIdFromContext();
        if ($tenantId === null) {
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

    private static function tenantIdFromContext(): ?int
    {
        if (!TenantContext::hasTenant()) {
            return null;
        }

        $tenant = TenantContext::tenant();
        $id = $tenant['id'] ?? null;

        return is_numeric($id) && (int) $id > 0 ? (int) $id : null;
    }
}
