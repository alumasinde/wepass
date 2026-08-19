<?php

declare(strict_types=1);

namespace App\Modules\Auth\Repositories;

use App\Core\DB;
use DateTimeImmutable;
use PDO;
use RuntimeException;

class UserRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DB::connect();

        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    }

    // ── HELPERS ──────────────────────────────────────────────

    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    private function now(): string
    {
        return (new DateTimeImmutable())->format('Y-m-d H:i:s');
    }

    // ── FIND ─────────────────────────────────────────────────

    public function findActiveByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                u.id,
                u.first_name,
                u.last_name,
                u.email,
                u.password_hash,
                u.department_id,
                u.auth_version,
                r.id   AS role_id,
                r.name AS role
            FROM users u
            LEFT JOIN user_roles ur ON ur.user_id = u.id
            LEFT JOIN roles r       ON r.id = ur.role_id
            WHERE u.email = :email
              AND u.is_active = 1
            LIMIT 1
        ");

        $stmt->execute([
            ':email' => $this->normalizeEmail($email)
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Return only the fields required to validate an existing session.
     * This deliberately avoids loading the full user record on every
     * authenticated request.
     */
    public function getSessionSecurityState(int $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT is_active, auth_version
            FROM users
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $userId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return [
            'is_active'    => (bool) $row['is_active'],
            'auth_version' => (int) $row['auth_version'],
        ];
    }

    /**
     * Increment the user's authentication version. Any session that
     * contains the previous version becomes invalid on its next request.
     */
    public function bumpAuthVersion(int $userId): void
    {
        $stmt = $this->db->prepare("
            UPDATE users
            SET auth_version = auth_version + 1,
                updated_at   = :now
            WHERE id = :id
        ");
        $stmt->execute([
            ':id'  => $userId,
            ':now' => $this->now(),
        ]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Failed to invalidate user sessions.');
        }
    }

    // ── PASSWORD RESET ───────────────────────────────────────

    public function createPasswordResetToken(int $userId, DateTimeImmutable $expiresAt): string
    {
        $this->db->beginTransaction();

        try {
            $rawToken    = bin2hex(random_bytes(32));
            $hashedToken = hash('sha256', $rawToken);

            $stmt = $this->db->prepare("
                UPDATE users
                SET reset_token   = :token,
                    reset_expires = :expires,
                    updated_at    = :now
                WHERE id = :id
                  AND is_active = 1
            ");

            $stmt->execute([
                ':token'   => $hashedToken,
                ':expires' => $expiresAt->format('Y-m-d H:i:s'),
                ':now'     => $this->now(),
                ':id'      => $userId,
            ]);

            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Failed to store reset token.');
            }

            $this->db->commit();
            return $rawToken;

        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function findByValidResetToken(string $rawToken): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, email, reset_expires
            FROM users
            WHERE reset_token   = :token
              AND reset_expires > :now
              AND is_active     = 1
            LIMIT 1
        ");

        $stmt->execute([
            ':token' => hash('sha256', $rawToken),
            ':now'   => $this->now(),
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function resetPasswordWithToken(
        int $userId,
        string $rawToken,
        string $newPasswordHash
    ): void {
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare("
                UPDATE users
                SET password_hash = :password,
                    reset_token   = NULL,
                    reset_expires = NULL,
                    auth_version  = auth_version + 1,
                    updated_at    = :now
                WHERE id = :id
                  AND reset_token = :token
                  AND reset_expires > :now
                  AND is_active = 1
            ");

            $stmt->execute([
                ':password' => $newPasswordHash,
                ':id'       => $userId,
                ':token'    => hash('sha256', $rawToken),
                ':now'      => $this->now(),
            ]);

            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Invalid or expired reset token.');
            }

            $this->db->commit();

        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function updatePassword(int $userId, string $passwordHash): void
    {
        $stmt = $this->db->prepare("
            UPDATE users
            SET password_hash = :password,
                reset_token   = NULL,
                reset_expires = NULL,
                updated_at    = :now
            WHERE id = :id
              AND is_active = 1
        ");

        $stmt->execute([
            ':password' => $passwordHash,
            ':id'       => $userId,
            ':now'      => $this->now(),
        ]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Password update failed.');
        }
    }
}
