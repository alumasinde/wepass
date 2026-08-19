<?php

namespace App\Core;

use PDO;

class Permission
{
    private array $permissions = [];

    public function __construct(private PDO $db)
    {
    }

    /**
     * Load permissions into session
     */
    public function loadForUser(int $userId): void
{
    $stmt = $this->db->prepare("
        SELECT m.name AS module, a.name AS action
        FROM user_roles ur
        JOIN role_permissions rp ON rp.role_id = ur.role_id
        JOIN permissions p ON p.id = rp.permission_id
        JOIN modules m ON m.id = p.module_id
        JOIN actions a ON a.id = p.action_id
        WHERE ur.user_id = ?
    ");

    $stmt->execute([$userId]);

    $perms = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $key = strtolower($row['module']) . '.' . strtolower($row['action']);
        $perms[$key] = true;
    }

    $_SESSION['permissions'] = $perms;
    $this->permissions = $perms;
}

    /**
     * Check permission
     */
    public function can(string $permission): bool
{
    // SUPER ADMIN shortcut
    if (!empty($_SESSION['is_super_admin'])) {
        return true;
    }

    return isset($_SESSION['permissions'][$permission]);
}


    /**
     * Debug helper
     */
    public function all(): array
    {
        return $_SESSION['permissions'] ?? [];
    }
}
