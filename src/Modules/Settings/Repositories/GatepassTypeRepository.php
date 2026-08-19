<?php

namespace App\Modules\Settings\Repositories;

use App\Core\DB;
use App\Modules\Settings\DTOs\GatepassTypeDTO;
use PDO;
use PDOException;
use RuntimeException;

class GatepassTypeRepository
{
    public function all(): array
    {
        $rows = DB::query("
            SELECT 
                gt.id,
                gt.name,
                gt.allowed_actions,
                gt.is_active,
                gt.type_code,
                gt.workflow_id,
                gt.requires_approval,
                gt.direction,
                gt.created_at,
                w.name AS workflow_name
            FROM gatepass_types gt
            LEFT JOIN workflows w ON gt.workflow_id = w.id
            WHERE gt.is_active = 1
            ORDER BY gt.name ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'map'], $rows);
    }

    public function find(int $id): ?GatepassTypeDTO
    {
        $row = DB::query("
            SELECT 
                gt.*,
                w.name AS workflow_name
            FROM gatepass_types gt
            LEFT JOIN workflows w ON gt.workflow_id = w.id
            WHERE gt.id = ?
            LIMIT 1
        ", [$id])->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->map($row) : null;
    }

    public function create(
        string $name,
        ?string $code,
        array $allowedActions,
        ?int $workflowId,
        bool $requiresApproval = true,
        string $direction = 'outbound'
    ): int {
        try {
            DB::query("
                INSERT INTO gatepass_types 
                (name, type_code, allowed_actions, workflow_id, requires_approval, direction, is_active, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 1, NOW())
            ", [
                $name,
                $code,
                $this->encodeActions($allowedActions),
                $workflowId,
                $requiresApproval ? 1 : 0,
                $direction === 'inbound' ? 'inbound' : 'outbound',
            ]);

            return (int) DB::lastInsertId();

        } catch (PDOException $e) {
            $this->handlePDO($e);
                    throw $e; // ✅ safety

        }
    }

    public function update(
        int $id,
        string $name,
        ?string $code,
        array $allowedActions,
        ?int $workflowId,
        bool $isActive = true,
        bool $requiresApproval = true,
        string $direction = 'outbound'
    ): bool {
        try {
            $stmt = DB::query("
                UPDATE gatepass_types
                SET name = ?, 
                    type_code = ?, 
                    allowed_actions = ?, 
                    workflow_id = ?, 
                    is_active = ?,
                    requires_approval = ?,
                    direction = ?
                WHERE id = ?
            ", [
                $name,
                $code,
                $this->encodeActions($allowedActions),
                $workflowId,
                $isActive ? 1 : 0,
                $requiresApproval ? 1 : 0,
                $direction === 'inbound' ? 'inbound' : 'outbound',
                $id
            ]);

            return $stmt->rowCount() > 0;

        } catch (PDOException $e) {
            $this->handlePDO($e);
                    throw $e;

        }
    }

    public function updateActions(int $id, array $actions): bool
    {
        $stmt = DB::query("
            UPDATE gatepass_types 
            SET allowed_actions = ? 
            WHERE id = ?
        ", [
            $this->encodeActions($actions),
            $id
        ]);

        return $stmt->rowCount() > 0;
    }

    public function workflowExists(int $id): bool
    {
        return (bool) DB::query("
            SELECT 1 
            FROM workflows 
            WHERE id = ? 
            AND is_active = 1
            LIMIT 1
        ", [$id])->fetchColumn();
    }

    // ───────────────── PRIVATE HELPERS ─────────────────

  private function map(array $row): GatepassTypeDTO
{
    return GatepassTypeDTO::fromArray($row);
}

private function encodeActions(array $actions): string
{
    return json_encode($actions, JSON_THROW_ON_ERROR);
}


    private function handlePDO(PDOException $e): never
    {
        // Duplicate entry (UNIQUE constraint)
        if ($e->getCode() === '23000') {
            throw new RuntimeException('Name or code already exists');
        }

        throw new RuntimeException('Database error: ' . $e->getMessage());
    }
}
