<?php

declare(strict_types=1);

namespace App\Modules\Visitors\Policies;

use App\Core\Permission;

final class VisitorPolicy
{
    public function __construct(private readonly Permission $permission)
    {
    }

    public function view(): bool
    {
        return $this->permission->can('visitors.view')
            || $this->permission->can('visitors.view_all')
            || $this->permission->can('gatepass.view')
            || $this->permission->can('gatepass.view_all');
    }

    public function create(): bool
    {
        return $this->permission->can('visitors.create')
            || $this->permission->can('gatepass.create');
    }

    public function update(): bool
    {
        return $this->permission->can('visitors.update')
            || $this->permission->can('visitors.update_all')
            || $this->permission->can('gatepass.update');
    }

    public function delete(): bool
    {
        return $this->permission->can('visitors.delete');
    }

    public function issueBadge(): bool
    {
        return $this->permission->can('visitors.issue_badge')
            || $this->permission->can('badges.issue');
    }

    public function canViewRecord(array $visitor): bool
    {
        if ($this->permission->can('visitors.view_all') || $this->permission->can('gatepass.view_all')) {
            return true;
        }

        return ($this->permission->can('visitors.view') || $this->permission->can('gatepass.view'))
            && $this->ownedByCurrentUser($visitor);
    }

    public function canUpdateRecord(array $visitor): bool
    {
        if ($this->permission->can('visitors.update_all')) {
            return true;
        }

        return ($this->permission->can('visitors.update') || $this->permission->can('gatepass.update'))
            && $this->ownedByCurrentUser($visitor);
    }

    public function canBlacklistRecord(array $visitor): bool
    {
        if ($this->permission->can('visitors.manage')) {
            return true;
        }

        return ($this->permission->can('visitors.blacklist') || $this->permission->can('gatepass.update'))
            && $this->ownedByCurrentUser($visitor);
    }

    private function ownedByCurrentUser(array $visitor): bool
    {
        $userId = (int) ($_SESSION['user']['id'] ?? 0);
        return $userId > 0 && (int) ($visitor['created_by'] ?? 0) === $userId;
    }
}
