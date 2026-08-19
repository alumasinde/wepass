<?php

namespace App\Modules\Roles\Services;

use App\Core\DB;
use PDO;
use RuntimeException;

class UserRoleService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DB::connect();
    }

    // FIX: was `int int $userId`
    public function getUserRoles(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT r.id, r.name
            FROM user_roles ur
            INNER JOIN roles r ON ur.role_id = r.id
            WHERE ur.user_id = ?
            ORDER BY r.name ASC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // FIX: was `int int $userId`
    public function getUserRoleIds(int $userId): array
    {
        $stmt = $this->db->prepare("SELECT role_id FROM user_roles WHERE user_id = ?");
        $stmt->execute([$userId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    // FIX: was `int int $userId`; INSERT had 2 cols but 3 placeholders
    public function assignRoles(int $userId, array $roleIds): bool
    {
        $this->db->beginTransaction();

        try {
            // Validate user exists
            $userCheck = $this->db->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
            $userCheck->execute([$userId]);
            if (!$userCheck->fetchColumn()) {
                throw new RuntimeException("Invalid user.");
            }

            $this->db->prepare("DELETE FROM user_roles WHERE user_id = ?")->execute([$userId]);

            if (empty($roleIds)) {
                $this->db->commit();
                return true;
            }

            $roleIds = array_unique(array_filter(array_map('intval', $roleIds)));

            // Validate roles exist
            $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
            $roleCheck    = $this->db->prepare("SELECT id FROM roles WHERE id IN ({$placeholders})");
            $roleCheck->execute($roleIds);
            $validRoleIds = array_map('intval', $roleCheck->fetchAll(PDO::FETCH_COLUMN));

            if (empty($validRoleIds)) {
                $this->db->commit();
                return true;
            }

            // FIX: INSERT has 2 columns (user_id, role_id), so 2 placeholders
            $insert = $this->db->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");

            foreach ($validRoleIds as $rid) {
                $insert->execute([$userId, $rid]);
            }

            $this->db->commit();
            return true;

        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
