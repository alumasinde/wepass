<?php

namespace App\Modules\Visitors\Policies;

use App\Core\Permission;

class VisitorPolicy
{
     public function __construct(
        private Permission $permission
    ) {}

    public function view(): bool
    {
        return $this->permission->can(  'visitors.view');
    }

    public function create(): bool
    {
        return $this->permission->can(  permission: 'visitors.create');
    }

    public function update(): bool
    {
        return $this->permission->can(   'visitors.update');
    }

   public function delete(): bool
    {
        return $this->permission->can(   'visitors.delete');
    }

    public function issueBadge(): bool
    {
        return $this->permission->can(   'visitors.issue_badge');
    }

}