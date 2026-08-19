<?php

declare(strict_types=1);

namespace App\Modules\Gatepass\Services;

use App\Core\DB;
use PDO;
use RuntimeException;

/** Administrative lifecycle for physical gates and approved guard devices. */
final class GateDeviceService
{
    private PDO $db;
    private GateSecurityService $security;

    public function __construct()
    {
        $this->db = DB::connect();
        $this->security = new GateSecurityService();
    }

    public function createGate(string $name, string $code, ?string $location, int $createdBy): int
    {
        $name = trim($name); $code = trim($code);
        if ($name === '' || $code === '' || $createdBy < 1) throw new RuntimeException('Gate name, code and creator are required.');
        $stmt = $this->db->prepare('INSERT INTO gates (name, code, location, created_by) VALUES (:name, :code, :location, :created_by)');
        $stmt->execute([':name'=>$name, ':code'=>$code, ':location'=>$location ?: null, ':created_by'=>$createdBy]);
        return (int) $this->db->lastInsertId();
    }

    /** Returns the raw secret exactly once for secure device provisioning. */
    public function approveDevice(string $uuid, string $name, string $platform, int $approvedBy, ?string $appVersion = null): array
    {
        $uuid = trim($uuid); $name = trim($name);
        if ($uuid === '' || $name === '' || $approvedBy < 1) throw new RuntimeException('Device identity and approver are required.');
        if (!in_array($platform, ['android','ios','web'], true)) throw new RuntimeException('Unsupported device platform.');

        $secret = $this->security->generateDeviceSecret();
        $hash = $this->security->hashDeviceSecret($secret);
        $stmt = $this->db->prepare('INSERT INTO approved_devices (device_uuid, device_name, platform, app_version, device_secret_hash, approved_by) VALUES (:uuid, :name, :platform, :version, :hash, :approved_by)');
        $stmt->execute([':uuid'=>$uuid, ':name'=>$name, ':platform'=>$platform, ':version'=>$appVersion ?: null, ':hash'=>$hash, ':approved_by'=>$approvedBy]);

        return ['device_id'=>(int)$this->db->lastInsertId(), 'device_uuid'=>$uuid, 'device_secret'=>$secret];
    }

    public function revokeDevice(int $deviceId, int $revokedBy, string $reason): bool
    {
        if ($deviceId < 1 || $revokedBy < 1) return false;
        $stmt = $this->db->prepare('UPDATE approved_devices SET is_active = 0, revoked_at = NOW(), revoked_by = :user, revoke_reason = :reason WHERE id = :id AND revoked_at IS NULL');
        $stmt->execute([':user'=>$revokedBy, ':reason'=>trim($reason) ?: 'Revoked by administrator', ':id'=>$deviceId]);
        return $stmt->rowCount() === 1;
    }

    public function assignDevice(int $gateId, int $deviceId, ?int $guardUserId, int $createdBy, ?string $startsAt = null, ?string $endsAt = null): int
    {
        if ($gateId < 1 || $deviceId < 1 || $createdBy < 1) throw new RuntimeException('Gate, device and creator are required.');
        $startsAt ??= date('Y-m-d H:i:s');
        if ($endsAt !== null && strtotime($endsAt) < strtotime($startsAt)) throw new RuntimeException('Assignment end cannot precede its start.');

        $stmt = $this->db->prepare('INSERT INTO gate_device_assignments (gate_id, device_id, guard_user_id, starts_at, ends_at, created_by) VALUES (:gate, :device, :guard, :starts, :ends, :creator)');
        $stmt->execute([':gate'=>$gateId, ':device'=>$deviceId, ':guard'=>$guardUserId, ':starts'=>$startsAt, ':ends'=>$endsAt, ':creator'=>$createdBy]);
        return (int) $this->db->lastInsertId();
    }
}
