<?php

namespace App\Modules\Roles\Services;

use App\Core\DB;
use PDO;
use RuntimeException;

class RoleService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DB::connect();
    }

    // ── ROLES ────────────────────────────────────────────────

    // FIX: removed int $tenantId param — per-database isolation, not needed
    public function all(): array
    {
        return $this->db->query("SELECT * FROM roles ORDER BY name ASC")
                        ->fetchAll(PDO::FETCH_ASSOC);
    }

    // FIX: was `int int $roleId` — removed duplicate type keyword
    public function find(int $roleId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM roles WHERE id = ? LIMIT 1");
        $stmt->execute([$roleId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // FIX: was `int string $name` — corrected to `string $name`; INSERT had 1 col but 2 placeholders
    public function create(string $name): int
    {
        $stmt = $this->db->prepare("INSERT INTO roles (name) VALUES (?)");
        $stmt->execute([trim($name)]);
        return (int) $this->db->lastInsertId();
    }

    // FIX: was `int int $roleId` — removed duplicate type keyword
    public function update(int $roleId, string $name): bool
    {
        $stmt = $this->db->prepare("UPDATE roles SET name = ? WHERE id = ?");
        return $stmt->execute([trim($name), $roleId]);
    }

    // FIX: was `int int $roleId` — removed duplicate type keyword
    public function delete(int $roleId): bool
    {
        $this->db->beginTransaction();

        try {
            $this->db->prepare("DELETE FROM role_permissions WHERE role_id = ?")->execute([$roleId]);
            $this->db->prepare("DELETE FROM roles WHERE id = ?")->execute([$roleId]);
            $this->db->commit();
            return true;

        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // ── PERMISSIONS ──────────────────────────────────────────

    public function allPermissions(): array
    {
        return $this->db->query("
            SELECT p.id, a.name AS action, m.name AS module
            FROM permissions p
            INNER JOIN actions a ON p.action_id = a.id
            INNER JOIN modules m ON p.module_id  = m.id
            ORDER BY m.name, a.name
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    // FIX: was `int int $roleId`
    public function getRolePermissions(int $roleId): array
    {
        $stmt = $this->db->prepare("
            SELECT p.id, a.name AS action, m.name AS module
            FROM role_permissions rp
            INNER JOIN permissions p ON rp.permission_id = p.id
            INNER JOIN actions a     ON p.action_id = a.id
            INNER JOIN modules m     ON p.module_id  = m.id
            WHERE rp.role_id = ?
            ORDER BY m.name, a.name
        ");
        $stmt->execute([$roleId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // FIX: was `int int $roleId`
    public function getRolePermissionIds(int $roleId): array
    {
        $stmt = $this->db->prepare("SELECT permission_id FROM role_permissions WHERE role_id = ?");
        $stmt->execute([$roleId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    // FIX: was `int int $roleId`; INSERT had 2 cols but 3 placeholders
    public function assignPermissions(int $roleId, array $permissionIds): bool
    {
        $this->db->beginTransaction();

        try {
            $role = $this->find($roleId);
            if (!$role) {
                throw new RuntimeException("Invalid role.");
            }

            $this->db->prepare("DELETE FROM role_permissions WHERE role_id = ?")->execute([$roleId]);

            if (empty($permissionIds)) {
                $this->db->commit();
                return true;
            }

            $permissionIds = array_unique(array_filter(array_map('intval', $permissionIds)));

            // FIX: INSERT has 2 columns (role_id, permission_id), so 2 placeholders
            $insert = $this->db->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");

            foreach ($permissionIds as $pid) {
                $insert->execute([$roleId, $pid]);
            }

            $this->db->commit();
            return true;

        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
