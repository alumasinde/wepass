<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Approval;

use App\Core\Permission;
use App\Modules\Approval\Policies\ApprovalPolicy;
use PDO;
use PHPUnit\Framework\TestCase;

final class ApprovalPolicyTest extends TestCase
{
    private ApprovalPolicy $policy;

    protected function setUp(): void
    {
        $_SESSION['permissions']    = [];
        $_SESSION['is_super_admin'] = false;

        $permission   = new Permission(new PDO('sqlite::memory:'));
        $this->policy = new ApprovalPolicy($permission);
    }

    protected function tearDown(): void
    {
        unset($_SESSION['permissions'], $_SESSION['is_super_admin']);
    }

    public function test_view_any_false_without_permission(): void
    {
        $this->assertFalse($this->policy->viewAny());
    }

    public function test_view_any_true_with_permission(): void
    {
        $_SESSION['permissions']['approval.view'] = true;
        $this->assertTrue($this->policy->viewAny());
    }

    public function test_approve_requires_its_own_permission_key_not_view(): void
    {
        $_SESSION['permissions']['approval.view'] = true;

        // Holding approval.view does not imply approval.approve —
        // these are deliberately separate keys.
        $this->assertFalse($this->policy->approve());

        $_SESSION['permissions']['approval.approve'] = true;
        $this->assertTrue($this->policy->approve());
    }

    public function test_reject_requires_its_own_permission_key(): void
    {
        $_SESSION['permissions']['approval.approve'] = true;

        $this->assertFalse($this->policy->reject());

        $_SESSION['permissions']['approval.reject'] = true;
        $this->assertTrue($this->policy->reject());
    }

    public function test_view_single_approval_gated_by_approval_view(): void
    {
        // Object-level ownership is enforced separately, at the
        // repository query level (see the class docblock in
        // ApprovalPolicy::view()) — this only checks the permission
        // key itself.
        $approval = ['id' => 1, 'approver_user_id' => 5];
        $user     = ['id' => 5];

        $this->assertFalse($this->policy->view($user, $approval));

        $_SESSION['permissions']['approval.view'] = true;
        $this->assertTrue($this->policy->view($user, $approval));
    }

    public function test_super_admin_bypasses_every_check(): void
    {
        $_SESSION['is_super_admin'] = true;

        $this->assertTrue($this->policy->viewAny());
        $this->assertTrue($this->policy->approve());
        $this->assertTrue($this->policy->reject());
    }
}
