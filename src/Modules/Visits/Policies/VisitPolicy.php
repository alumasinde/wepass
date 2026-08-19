<?php

declare(strict_types=1);

namespace App\Modules\Visits\Policies;

use App\Core\Permission;

final class VisitPolicy
{
    public function __construct(private readonly Permission $permission) {}

    public function canViewAll(): bool
    {
        return $this->permission->can('visits.view_all');
    }

    public function view(array $user, array $visit): bool
    {
        if (!$this->permission->can('visits.view')) {
            return false;
        }

        if ($this->canViewAll()) {
            return true;
        }

        return $this->ownsOrScopes($user, $visit);
    }

    public function checkIn(array $user, array $visit): bool
    {
        return $this->permission->can('visits.checkin')
            && ($this->canViewAll() || $this->ownsOrScopes($user, $visit));
    }

    public function checkOut(array $user, array $visit): bool
    {
        return $this->permission->can('visits.checkout')
            && ($this->canViewAll() || $this->ownsOrScopes($user, $visit));
    }

    private function ownsOrScopes(array $user, array $visit): bool
    {
        $userId = (int) ($user['id'] ?? 0);
        $departmentId = $user['department_id'] ?? null;

        return $userId > 0 && (
            (int) ($visit['created_by'] ?? 0) === $userId
            || (int) ($visit['host_user_id'] ?? 0) === $userId
            || ($departmentId !== null && (int) $visit['department_id'] === (int) $departmentId)
        );
    }
}
