<?php

declare(strict_types=1);

namespace App\Modules\Gatepass\Services;

use App\Core\DB;
use PDO;
use RuntimeException;

/**
 * Issues and revokes opaque QR credentials for gatepasses.
 * The raw token exists only in the issuing request and is never persisted.
 */
final class QrCredentialService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DB::connect();
    }

    /**
     * Issue a fresh credential. The returned token must be delivered to the
     * QR presentation layer immediately; it cannot be recovered from MySQL.
     */
    public function issue(int $gatepassId, ?int $ttlSeconds = null): string
    {
        if ($gatepassId < 1) throw new RuntimeException('Invalid gatepass.');

        $ttlSeconds ??= (int) config('security.qr_ttl_seconds', 86400);
        if ($ttlSeconds < 60 || $ttlSeconds > 2592000) {
            throw new RuntimeException('QR credential lifetime is outside the allowed range.');
        }

        $token = bin2hex(random_bytes(32));
        $hash  = hash('sha256', $token);
        $expires = date('Y-m-d H:i:s', time() + $ttlSeconds);

        $stmt = $this->db->prepare(
            'UPDATE gatepasses
             SET qr_token_hash = :hash,
                 qr_issued_at = NOW(),
                 qr_expires_at = :expires,
                 qr_revoked_at = NULL
             WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute([':hash' => $hash, ':expires' => $expires, ':id' => $gatepassId]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Gatepass not found or QR credential was not issued.');
        }

        return $token;
    }

    public function revoke(int $gatepassId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE gatepasses SET qr_revoked_at = NOW() WHERE id = :id AND qr_token_hash IS NOT NULL AND qr_revoked_at IS NULL'
        );
        $stmt->execute([':id' => $gatepassId]);
        return $stmt->rowCount() === 1;
    }

    public function isValid(string $token): bool
    {
        if ($token === '') return false;
        $hash = hash('sha256', $token);
        $stmt = $this->db->prepare(
            'SELECT 1 FROM gatepasses
             WHERE qr_token_hash = :hash AND deleted_at IS NULL
               AND qr_revoked_at IS NULL
               AND (qr_expires_at IS NULL OR qr_expires_at > NOW())
             LIMIT 1'
        );
        $stmt->execute([':hash' => $hash]);
        return (bool) $stmt->fetchColumn();
    }
}
