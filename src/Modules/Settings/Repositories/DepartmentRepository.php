<?php

namespace App\Modules\Settings\Repositories;

use App\Core\DB;
use App\Modules\Settings\DTOs\DepartmentDTO;

/**
 * DepartmentRepository — per-database isolation model.
 * No tenant_id filtering needed.
 */
class DepartmentRepository
{
    public function all(): array
    {
        $rows = DB::query("
            SELECT id, name, code, is_active, created_at
            FROM   departments
            ORDER BY name ASC
        ")->fetchAll();

        return array_map([$this, 'map'], $rows);
    }

    public function find(int $id): ?DepartmentDTO
    {
        $row = DB::query("
            SELECT id, name, code, is_active, created_at
            FROM   departments
            WHERE  id = ?
            LIMIT 1
        ", [$id])->fetch();

        return $row ? $this->map($row) : null;
    }

    public function create(string $name, string $code): DepartmentDTO
    {
        DB::query("
            INSERT INTO departments (name, code, is_active)
            VALUES (?, ?, 1)
        ", [$name, $code]);

        return $this->find((int) DB::lastInsertId());
    }

    public function update(int $id, string $name): ?DepartmentDTO
    {
        $stmt = DB::query("
            UPDATE departments SET name = ? WHERE id = ?
        ", [$name, $id]);

        return $stmt->rowCount() > 0 ? $this->find($id) : null;
    }

    public function updateStatus(int $id, bool $isActive): ?DepartmentDTO
    {
        $stmt = DB::query("
            UPDATE departments SET is_active = ? WHERE id = ?
        ", [$isActive ? 1 : 0, $id]);

        return $stmt->rowCount() > 0 ? $this->find($id) : null;
    }

    public function delete(int $id): bool
    {
        $stmt = DB::query("DELETE FROM departments WHERE id = ?", [$id]);
        return $stmt->rowCount() > 0;
    }

    private function map(array $row): DepartmentDTO
    {
        return new DepartmentDTO(
            id:        (int)  $row['id'],
            tenantId:  0,   // no longer meaningful
            name:      $row['name'],
            code:      $row['code'],
            isActive:  (bool) $row['is_active'],
            createdAt: $row['created_at'] ?? null
        );
    }
}
