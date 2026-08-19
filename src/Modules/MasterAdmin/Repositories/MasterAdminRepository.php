<?php

namespace App\Modules\MasterAdmin\Repositories;

use App\Core\DB;
use PDO;

class MasterAdminRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DB::master();
    }

    public function findActiveByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, email, password_hash, first_name, last_name
            FROM master_admins
            WHERE email = :email AND is_active = 1
            LIMIT 1
        ");

        $stmt->execute([':email' => mb_strtolower(trim($email))]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function updatePassword(int $id, string $passwordHash): void
    {
        $this->db->prepare("
            UPDATE master_admins SET password_hash = :hash WHERE id = :id
        ")->execute([':hash' => $passwordHash, ':id' => $id]);
    }

    public function touchLastLogin(int $id): void
    {
        $this->db->prepare("
            UPDATE master_admins SET last_login_at = NOW() WHERE id = :id
        ")->execute([':id' => $id]);
    }
}
