<?php

namespace App\Modules\Gatepass\Repositories;

use App\Core\DB;
use PDO;
use RuntimeException;

class GatepassStatusRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DB::connect();
    }

    public function findAll(): array
{
    return DB::query("
        SELECT id, name, code 
        FROM gatepass_statuses 
        ORDER BY name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
}
    public function getIdByCode(string $code): ?int
    {
        $stmt = $this->db->prepare("
            SELECT id FROM gatepass_statuses WHERE code = :code LIMIT 1
        ");
        $stmt->execute([':code' => strtolower($code)]);
        $id = $stmt->fetchColumn();
        return $id !== false ? (int) $id : null;
    }

    public function requireIdByCode(string $code): int
    {
        $id = $this->getIdByCode($code);
        if ($id === null) {
            throw new RuntimeException("Gatepass status '{$code}' not found.");
        }
        return $id;
    }

    public function isApproved(int $statusId): bool
    {
        return $this->statusMatches($statusId, 'approved');
    }

    public function isRejected(int $statusId): bool
    {
        return $this->statusMatches($statusId, 'rejected');
    }

    private function statusMatches(int $statusId, string $code): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM gatepass_statuses WHERE id = :id AND code = :code LIMIT 1
        ");
        $stmt->execute([':id' => $statusId, ':code' => $code]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
