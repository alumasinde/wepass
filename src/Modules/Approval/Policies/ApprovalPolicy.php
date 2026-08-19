<?php

namespace App\Modules\Approval\Policies;

use App\Core\Permission;

class ApprovalPolicy
{
     public function __construct(
        private Permission $permission
    ) {}

    /* =========================================================
     * VIEW ANY (See approvals dashboard)
     * ========================================================= */

    public function viewAny(): bool
    {
        //permission key: approval.view
return $this->permission->can('approval.view');        
}
    

    /* =========================================================
     * APPROVE / REJECT
     * ========================================================= */

    public function approve(): bool
     {
      return $this->permission->can('approval.approve');
        }

    public function reject(): bool
    {
        return $this->permission->can('approval.reject');
    }

    /* =========================================================
     * VIEW SINGLE APPROVAL
     * ========================================================= */

public function view(array $user, array $approval): bool
{
    // Ownership is already enforced at the query level —
    // ApprovalService::findApproval() scopes by
    // WHERE approver_user_id = :user_id, so $approval will be null/
    // empty for anything not assigned to this user before it ever
    // reaches here. This is the permission-level check on top of that.
    return $this->permission->can('approval.view');
}

}