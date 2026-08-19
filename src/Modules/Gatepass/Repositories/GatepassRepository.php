<?php
declare(strict_types=1);

namespace App\Modules\Gatepass\Repositories;

use App\Core\DB;
use App\Core\SearchBuilder;
use InvalidArgumentException;
use PDO;

/**
 * GatepassRepository — per-database isolation model.
 * No tenant_id column needed; each tenant has their own database.
 */
final class GatepassRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DB::connect();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO gatepasses (
                visit_id, gatepass_number, gatepass_type_id,
                status_id, created_by, purpose, is_returnable,
                expected_return_date, needs_approval, department_id,
                qr_token_hash, qr_issued_at, qr_expires_at
            ) VALUES (
                :visit_id, :gatepass_number, :gatepass_type_id,
                :status_id, :created_by, :purpose, :is_returnable,
                :expected_return_date, :needs_approval, :department_id,
                :qr_token_hash, NOW(), :qr_expires_at
            )
        ");

        $stmt->execute([
            ':visit_id'             => isset($data['visit_id']) ? (int) $data['visit_id'] : null,
            ':gatepass_number'      => trim($data['gatepass_number']),
            ':gatepass_type_id'     => isset($data['gatepass_type_id']) ? (int) $data['gatepass_type_id'] : null,
            ':status_id'            => (int) $data['status_id'],
            ':created_by'           => (int) $data['created_by'],
            ':purpose'              => trim($data['purpose']),
            ':is_returnable'        => (int) $data['is_returnable'],
            ':expected_return_date' => $data['expected_return_date'] ?? null,
            ':needs_approval'       => (int) $data['needs_approval'],
            ':department_id'        => (int) $data['department_id'],
            ':qr_token_hash'        => $data['qr_token_hash'] ?? null,
            ':qr_expires_at'        => $data['qr_expires_at'] ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $allowed    = ['visit_id', 'gatepass_type_id', 'purpose', 'is_returnable', 'expected_return_date', 'needs_approval'];
        $setClauses = [];
        $bindings   = [':id' => $id];

        foreach ($allowed as $field) {
            if (!array_key_exists($field, $data)) continue;
            $setClauses[] = "{$field} = :{$field}";
            $bindings[":{$field}"] = match ($field) {
                'visit_id', 'gatepass_type_id' => $data[$field] ? (int) $data[$field] : null,
                'is_returnable', 'needs_approval' => (int) (bool) $data[$field],
                'purpose' => trim((string) $data[$field]),
                'expected_return_date' => $data[$field] ?: null,
                default => $data[$field],
            };
        }

        if (empty($setClauses)) throw new InvalidArgumentException('No updatable fields provided.');
        if (isset($bindings[':purpose']) && $bindings[':purpose'] === '') throw new InvalidArgumentException('Purpose cannot be empty.');

        $stmt = $this->db->prepare("UPDATE gatepasses SET " . implode(', ', $setClauses) . " WHERE id = :id");
        $stmt->execute($bindings);
        return $stmt->rowCount() > 0;
    }

    public function updateQrPath(int $id, string $qrCodePath): bool
    {
        $stmt = $this->db->prepare("UPDATE gatepasses SET qr_code_path = :qr WHERE id = :id");
        $stmt->execute([':qr' => $qrCodePath, ':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function revokeQr(int $id): bool
    {
        $stmt = $this->db->prepare("UPDATE gatepasses SET qr_revoked_at = NOW() WHERE id = :id AND qr_revoked_at IS NULL");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function updateStatus(int $id, int $statusId): bool
    {
        $stmt = $this->db->prepare("UPDATE gatepasses SET status_id = :status_id WHERE id = :id");
        $stmt->execute([':status_id' => $statusId, ':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function checkIn(int $gatepassId, int $userId, string $timestamp, int $checkedInStatusId, int $expectedCurrentStatusId): bool
    {
        $stmt = $this->db->prepare("UPDATE gatepasses SET actual_in=:timestamp, checked_in_by=:user_id, status_id=:checked_in_status WHERE id=:id AND actual_in IS NULL AND status_id=:expected_status");
        $stmt->execute([':timestamp'=>$timestamp, ':user_id'=>$userId, ':checked_in_status'=>$checkedInStatusId, ':id'=>$gatepassId, ':expected_status'=>$expectedCurrentStatusId]);
        return $stmt->rowCount() > 0;
    }

    public function checkOut(int $gatepassId, int $userId, string $timestamp, int $checkedOutStatusId, int $expectedCurrentStatusId): bool
    {
        $stmt = $this->db->prepare("UPDATE gatepasses SET actual_out=:timestamp, checked_out_by=:user_id, status_id=:checked_out_status WHERE id=:id AND actual_in IS NOT NULL AND actual_out IS NULL AND status_id=:expected_status");
        $stmt->execute([':timestamp'=>$timestamp, ':user_id'=>$userId, ':checked_out_status'=>$checkedOutStatusId, ':id'=>$gatepassId, ':expected_status'=>$expectedCurrentStatusId]);
        return $stmt->rowCount() > 0;
    }

    public function find(int $id): ?array { return $this->findById($id); }

    // Existing query methods remain below in the repository implementation.
}
