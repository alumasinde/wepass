<?php

declare(strict_types=1);

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

    public function getUserRoleIds(int $userId): array
    {
        $stmt = $this->db->prepare("SELECT role_id FROM user_roles WHERE user_id = ?");
        $stmt->execute([$userId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Replace a user's roles atomically and revoke existing sessions.
     * The revocation happens in the same transaction as the role change,
     * so a successful role update can never leave the old session
     * authorization active.
     */
    public function assignRoles(int $userId, array $roleIds): bool
    {
        $this->db->beginTransaction();

        try {
            $userCheck = $this->db->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
            $userCheck->execute([$userId]);
            if (!$userCheck->fetchColumn()) {
                throw new RuntimeException("Invalid user.");
            }

            $this->db->prepare("DELETE FROM user_roles WHERE user_id = ?")->execute([$userId]);

            $validRoleIds = [];

            if (!empty($roleIds)) {
                $roleIds = array_unique(array_filter(array_map('intval', $roleIds)));

                if ($roleIds) {
                    $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
                    $roleCheck    = $this->db->prepare("SELECT id FROM roles WHERE id IN ({$placeholders})");
                    $roleCheck->execute($roleIds);
                    $validRoleIds = array_map('intval', $roleCheck->fetchAll(PDO::FETCH_COLUMN));
                }
            }

            $insert = $this->db->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
            foreach ($validRoleIds as $rid) {
                $insert->execute([$userId, $rid]);
            }

            // Any change to role membership changes effective authorization.
            // Incrementing auth_version revokes every existing session.
            $revoke = $this->db->prepare("
                UPDATE users
                SET auth_version = auth_version + 1,
                    updated_at   = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $revoke->execute([$userId]);

            if ($revoke->rowCount() !== 1) {
                throw new RuntimeException('Failed to invalidate user sessions.');
            }

            $this->db->commit();
            return true;

        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}
