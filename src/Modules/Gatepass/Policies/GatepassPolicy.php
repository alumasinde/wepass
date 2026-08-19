<?php

namespace App\Modules\Gatepass\Policies;

use App\Core\Permission;

/**
 * Object-level authorization for gatepass records.
 * Coarse permissions decide whether an operation exists; this policy
 * decides whether the authenticated user may perform it on this record.
 */
class GatepassPolicy
{
    public function __construct(private Permission $permission) {}

    public function canViewAll(): bool
    {
        return $this->permission->can('gatepass.view_all');
    }

    public function create(): bool
    {
        return $this->permission->can('gatepass.create');
    }

    public function approve(): bool
    {
        return $this->permission->can('gatepass.approve');
    }

    public function view(array $user, array $gatepass): bool
    {
        if (!$this->permission->can('gatepass.view')) {
            return false;
        }

        if ($this->canViewAll()) {
            return true;
        }

        if ((int) ($gatepass['created_by'] ?? 0) === (int) ($user['id'] ?? 0)) {
            return true;
        }

        return isset($user['department_id'], $gatepass['department_id'])
            && (int) $user['department_id'] === (int) $gatepass['department_id'];
    }

    public function update(array $user, array $gatepass): bool
    {
        if (!$this->permission->can('gatepass.update')) {
            return false;
        }

        if ($this->canViewAll()) {
            return true;
        }

        return (int) ($gatepass['created_by'] ?? 0) === (int) ($user['id'] ?? 0)
            && strtoupper((string) ($gatepass['status_code'] ?? '')) === 'PENDING';
    }

    public function delete(array $user, array $gatepass): bool
    {
        if (!$this->permission->can('gatepass.delete')) {
            return false;
        }

        if (!empty($gatepass['actual_in'])) {
            return false;
        }

        if ($this->canViewAll()) {
            return true;
        }

        return (int) ($gatepass['created_by'] ?? 0) === (int) ($user['id'] ?? 0)
            && strtoupper((string) ($gatepass['status_code'] ?? '')) === 'PENDING';
    }

    /**
     * Physical gate actions require both the dedicated operation
     * permission and record visibility. Cross-department operators
     * need gatepass.view_all in addition to the action permission.
     */
    public function checkIn(array $user, array $gatepass): bool
    {
        return $this->canPhysicalAction($user, $gatepass, 'gatepass.checkin');
    }

    public function checkOut(array $user, array $gatepass): bool
    {
        return $this->canPhysicalAction($user, $gatepass, 'gatepass.checkout');
    }

    private function canPhysicalAction(array $user, array $gatepass, string $permission): bool
    {
        if (!$this->permission->can($permission)) {
            return false;
        }

        if ($this->canViewAll()) {
            return true;
        }

        if ((int) ($gatepass['created_by'] ?? 0) === (int) ($user['id'] ?? 0)) {
            return true;
        }

        return isset($user['department_id'], $gatepass['department_id'])
            && (int) $user['department_id'] === (int) $gatepass['department_id'];
    }
}
