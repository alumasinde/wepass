<?php

namespace App\Modules\Settings\Services;

use App\Core\Audit;
use App\Modules\Settings\Repositories\DepartmentRepository;
use RuntimeException;

class DepartmentService
{
    public function __construct(
        private DepartmentRepository $repo
    ) {}

    public function all(): array
    {
        return $this->repo->all();
    }

    public function find(int $id)
    {
        $department = $this->repo->find($id);

        if (! $department) {
            throw new RuntimeException('Department not found.');
        }

        return $department;
    }

    public function create(string $name, string $code)
    {
        $this->validateName($name);
        $this->validateCode($code);

        try {
            $department = $this->repo->create($name, $code);

            if (! $department) {
                throw new RuntimeException('Department creation failed.');
            }

            Audit::log(
                action: 'department.created',
                entityType: 'department',
                entityId: $department->id,
                metadata: [
                    'name' => $department->name,
                    'code' => $code,
                ]
            );

            return $department;

        } catch (\Throwable $e) {
            throw new RuntimeException(
                'Department creation failed: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    public function update(int $id, string $name)
    {
        $this->validateName($name);
        $existing = $this->repo->find($id);

        if (! $existing) {
            throw new RuntimeException('Department not found.');
        }

        try {
            $updated = $this->repo->update($id, $name);

            if (! $updated) {
                throw new RuntimeException('Department update failed.');
            }

            Audit::log(
                action: 'department.updated',
                entityType: 'department',
                entityId: $id,
                metadata: [
                    'before' => [
                        'name' => $existing->name,
                    ],
                    'after' => [
                        'name' => $name,
                    ],
                ]
            );

            return $this->repo->find($id);

        } catch (\Throwable $e) {
            throw new RuntimeException(
                'Department update failed: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    public function delete(int $id): bool
    {
        $existing = $this->repo->find($id);

        if (! $existing) {
            throw new RuntimeException('Department not found.');
        }

        try {
            $deleted = $this->repo->delete($id);

            if (! $deleted) {
                throw new RuntimeException('Department deletion failed.');
            }

            Audit::log(
                action: 'department.deleted',
                entityType: 'department',
                entityId: $id,
                metadata: [
                    'name' => $existing->name,
                ]
            );

            return true;

        } catch (\Throwable $e) {
            throw new RuntimeException(
                'Department deletion failed: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    public function toggle(int $id)
    {
        $department = $this->repo->find($id);

        if (! $department) {
            throw new RuntimeException('Department not found.');
        }

        try {
            $newStatus = ! (bool) $department->isActive;

            $updated = $this->repo->updateStatus($id, $newStatus);

            if (! $updated) {
                throw new RuntimeException('Department status update failed.');
            }

            Audit::log(
                action: 'department.toggled',
                entityType: 'department',
                entityId: $id,
                metadata: [
                    'previous' => (bool) $department->isActive,
                    'new' => $newStatus,
                ]
            );

            return $this->repo->find($id);

        } catch (\Throwable $e) {
            throw new RuntimeException(
                'Department status toggle failed: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    private function validateName(string $name): void
    {
        $name = trim($name);

        if ($name === '') {
            throw new RuntimeException('Department name is required.');
        }

        if (strlen($name) < 2) {
            throw new RuntimeException('Department name must be at least 2 characters.');
        }

        if (strlen($name) > 150) {
            throw new RuntimeException('Department name is too long.');
        }
    }

    private function validateCode(string $code): void
    {
        $code = trim($code);

        if ($code === '') {
            throw new RuntimeException('Department code is required.');
        }

        if (strlen($code) < 2) {
            throw new RuntimeException('Department code must be at least 2 characters.');
        }

        if (strlen($code) > 20) {
            throw new RuntimeException('Department code is too long.');
        }
    }
}