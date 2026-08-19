<?php

declare(strict_types=1);

namespace App\Modules\Gatepass\Services;

use App\Core\Audit;
use App\Core\DB;
use PDO;
use RuntimeException;

final class GateSecurityService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DB::connect();
    }

    public function authenticateDevice(string $deviceUuid, string $deviceSecret, int $gateId, ?int $guardUserId = null): ?array
    {
        $deviceUuid = trim($deviceUuid);
        $deviceSecret = trim($deviceSecret);
        if ($deviceUuid === '' || $deviceSecret === '' || $gateId < 1) return null;

        $stmt = $this->db->prepare("
            SELECT d.id AS device_id, d.device_uuid, d.device_name,
                   d.device_secret_hash, d.is_active AS device_active, d.revoked_at,
                   a.id AS assignment_id, a.gate_id, a.guard_user_id,
                   a.starts_at, a.ends_at, g.name AS gate_name, g.code AS gate_code
            FROM approved_devices d
            INNER JOIN gate_device_assignments a ON a.device_id = d.id
            INNER JOIN gates g ON g.id = a.gate_id
            WHERE d.device_uuid = :device_uuid AND a.gate_id = :gate_id
              AND d.is_active = 1 AND d.revoked_at IS NULL
              AND g.is_active = 1 AND a.is_active = 1
              AND a.starts_at <= NOW() AND (a.ends_at IS NULL OR a.ends_at >= NOW())
            ORDER BY a.starts_at DESC LIMIT 1
        ");
        $stmt->execute([':device_uuid' => $deviceUuid, ':gate_id' => $gateId]);
        $device = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$device) return null;

        $presentedHash = hash('sha256', $deviceSecret);
        if (!hash_equals((string) $device['device_secret_hash'], $presentedHash)) return null;

        if ($guardUserId !== null && $device['guard_user_id'] !== null
            && (int) $device['guard_user_id'] !== $guardUserId) return null;

        $this->db->prepare('UPDATE approved_devices SET last_seen_at = NOW() WHERE id = :id AND is_active = 1 AND revoked_at IS NULL')
            ->execute([':id' => (int) $device['device_id']]);
        return $device;
    }

    public function generateDeviceSecret(): string { return bin2hex(random_bytes(32)); }

    public function hashDeviceSecret(string $secret): string
    {
        if ($secret === '') throw new RuntimeException('Device secret cannot be empty.');
        return hash('sha256', $secret);
    }

    public function generateQrToken(): string { return bin2hex(random_bytes(32)); }

    public function hashQrToken(string $token): string
    {
        if ($token === '') throw new RuntimeException('QR token cannot be empty.');
        return hash('sha256', $token);
    }

    public function resolveQrToken(string $token): ?array
    {
        if ($token === '') return null;
        $hash = $this->hashQrToken($token);
        $stmt = $this->db->prepare("SELECT g.* FROM gatepasses g
            WHERE g.qr_token_hash = :hash AND g.deleted_at IS NULL
              AND g.qr_revoked_at IS NULL
              AND (g.qr_expires_at IS NULL OR g.qr_expires_at > NOW()) LIMIT 1");
        $stmt->execute([':hash' => $hash]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function recordScan(array $data): bool
    {
        $stmt = $this->db->prepare("INSERT INTO gate_scan_events
            (gate_id, device_id, guard_user_id, gatepass_id, visit_id, scan_type,
             result, reason_code, request_id, qr_token_hash, scanned_at, client_ip,
             user_agent, metadata_json)
            VALUES (:gate_id, :device_id, :guard_user_id, :gatepass_id, :visit_id,
                    :scan_type, :result, :reason_code, :request_id, :qr_token_hash,
                    NOW(), :client_ip, :user_agent, :metadata_json)
            ON DUPLICATE KEY UPDATE id = id");
        $ok = $stmt->execute([
            ':gate_id' => (int) $data['gate_id'], ':device_id' => (int) $data['device_id'],
            ':guard_user_id' => $data['guard_user_id'] ?? null, ':gatepass_id' => $data['gatepass_id'] ?? null,
            ':visit_id' => $data['visit_id'] ?? null, ':scan_type' => (string) $data['scan_type'],
            ':result' => (string) $data['result'], ':reason_code' => $data['reason_code'] ?? null,
            ':request_id' => (string) $data['request_id'], ':qr_token_hash' => $data['qr_token_hash'] ?? null,
            ':client_ip' => $data['client_ip'] ?? null, ':user_agent' => $data['user_agent'] ?? null,
            ':metadata_json' => isset($data['metadata']) ? json_encode($data['metadata'], JSON_UNESCAPED_SLASHES) : null,
        ]);
        if ($ok && !empty($data['gatepass_id'])) {
            Audit::log('gatepass.gate_scan', 'gatepass', (int) $data['gatepass_id'], [
                'gate_id' => (int) $data['gate_id'], 'device_id' => (int) $data['device_id'],
                'result' => (string) $data['result'], 'reason_code' => $data['reason_code'] ?? null,
            ]);
        }
        return $ok;
    }
}
