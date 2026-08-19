<?php

declare(strict_types=1);

namespace App\Modules\Settings\Services;

use App\Core\DB;
use App\Modules\Gatepass\Services\GateSecurityService;
use PDO;
use RuntimeException;

final class GateSecurityAdminService
{
    private PDO $db;
    private GateSecurityService $security;

    public function __construct()
    {
        $this->db = DB::connect();
        $this->security = new GateSecurityService();
    }

    public function gates(): array
    {
        return $this->db->query("SELECT id,name,code,location,is_active,created_at FROM gates ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function devices(): array
    {
        $sql = "SELECT d.id,d.device_uuid,d.device_name,d.is_active,d.revoked_at,d.last_seen_at,d.created_at,
                       GROUP_CONCAT(CONCAT(g.name,' / ',COALESCE(u.username,'Any guard')) ORDER BY a.starts_at DESC SEPARATOR ', ') assignments
                FROM approved_devices d
                LEFT JOIN gate_device_assignments a ON a.device_id=d.id AND a.is_active=1
                LEFT JOIN gates g ON g.id=a.gate_id
                LEFT JOIN users u ON u.id=a.guard_user_id
                GROUP BY d.id ORDER BY d.created_at DESC";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function users(): array
    {
        return $this->db->query("SELECT id,username,first_name,last_name FROM users WHERE is_active=1 ORDER BY first_name,last_name")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function assignments(): array
    {
        $sql = "SELECT a.id,a.gate_id,a.device_id,a.guard_user_id,a.starts_at,a.ends_at,a.is_active,
                       g.name gate_name,d.device_name,u.username
                FROM gate_device_assignments a
                INNER JOIN gates g ON g.id=a.gate_id
                INNER JOIN approved_devices d ON d.id=a.device_id
                LEFT JOIN users u ON u.id=a.guard_user_id
                ORDER BY a.is_active DESC,a.starts_at DESC";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function recentScans(int $limit = 50): array
    {
        $limit = max(1, min($limit, 200));
        $sql = "SELECT e.id,e.scan_type,e.result,e.reason_code,e.request_id,e.scanned_at,
                       g.name gate_name,d.device_name,u.username,ga.gatepass_number
                FROM gate_scan_events e
                INNER JOIN gates g ON g.id=e.gate_id
                INNER JOIN approved_devices d ON d.id=e.device_id
                LEFT JOIN users u ON u.id=e.guard_user_id
                LEFT JOIN gatepasses ga ON ga.id=e.gatepass_id
                ORDER BY e.scanned_at DESC LIMIT {$limit}";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createGate(string $name, string $code, ?string $location): int
    {
        $name=trim($name);$code=trim($code);$location=$location!==null?trim($location):null;
        if($name===''||$code==='') throw new RuntimeException('Gate name and code are required.');
        $stmt=$this->db->prepare("INSERT INTO gates(name,code,location) VALUES(:name,:code,:location)");
        $stmt->execute([':name'=>$name,':code'=>$code,':location'=>$location?:null]);
        return (int)$this->db->lastInsertId();
    }

    /** Returns the one-time plaintext secret. It is never stored. */
    public function createDevice(string $uuid,string $name): string
    {
        $uuid=trim($uuid);$name=trim($name);
        if($uuid===''||$name==='') throw new RuntimeException('Device UUID and device name are required.');
        $secret=$this->security->generateDeviceSecret();
        $stmt=$this->db->prepare("INSERT INTO approved_devices(device_uuid,device_name,device_secret_hash) VALUES(:uuid,:name,:hash)");
        $stmt->execute([':uuid'=>$uuid,':name'=>$name,':hash'=>$this->security->hashDeviceSecret($secret)]);
        return $secret;
    }

    public function assign(int $gateId,int $deviceId,?int $guardUserId,?string $startsAt,?string $endsAt): void
    {
        if($gateId<1||$deviceId<1) throw new RuntimeException('Gate and device are required.');
        $starts=$startsAt?date('Y-m-d H:i:s',strtotime($startsAt)):date('Y-m-d H:i:s');
        $ends=$endsAt?date('Y-m-d H:i:s',strtotime($endsAt)):null;
        if($ends!==null&&strtotime($ends)<=strtotime($starts)) throw new RuntimeException('End time must be after start time.');
        $this->db->beginTransaction();
        try{
            $this->db->prepare("UPDATE gate_device_assignments SET is_active=0,ends_at=COALESCE(ends_at,NOW()) WHERE device_id=:device AND gate_id=:gate AND is_active=1")->execute([':device'=>$deviceId,':gate'=>$gateId]);
            $stmt=$this->db->prepare("INSERT INTO gate_device_assignments(gate_id,device_id,guard_user_id,starts_at,ends_at) VALUES(:gate,:device,:guard,:starts,:ends)");
            $stmt->execute([':gate'=>$gateId,':device'=>$deviceId,':guard'=>$guardUserId?:null,':starts'=>$starts,':ends'=>$ends]);
            $this->db->commit();
        }catch(\Throwable $e){$this->db->rollBack();throw $e;}
    }

    public function revokeDevice(int $deviceId): void
    {
        $stmt=$this->db->prepare("UPDATE approved_devices SET is_active=0,revoked_at=NOW() WHERE id=:id AND revoked_at IS NULL");
        $stmt->execute([':id'=>$deviceId]);
        if($stmt->rowCount()===0) throw new RuntimeException('Device not found or already revoked.');
        $this->db->prepare("UPDATE gate_device_assignments SET is_active=0,ends_at=COALESCE(ends_at,NOW()) WHERE device_id=:id AND is_active=1")->execute([':id'=>$deviceId]);
    }

    public function deactivateAssignment(int $id): void
    {
        $stmt=$this->db->prepare("UPDATE gate_device_assignments SET is_active=0,ends_at=COALESCE(ends_at,NOW()) WHERE id=:id AND is_active=1");
        $stmt->execute([':id'=>$id]);
        if($stmt->rowCount()===0) throw new RuntimeException('Assignment not found or already inactive.');
    }
}
