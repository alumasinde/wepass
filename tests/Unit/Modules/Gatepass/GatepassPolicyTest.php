<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Gatepass;

use App\Core\Permission;
use App\Modules\Gatepass\Policies\GatepassPolicy;
use PDO;
use PHPUnit\Framework\TestCase;

final class GatepassPolicyTest extends TestCase
{
    private GatepassPolicy $policy;

    protected function setUp(): void
    {
        $_SESSION['user'] = ['id' => 5];
        $_SESSION['permissions'] = [];
        $_SESSION['is_super_admin'] = false;

        $permission = new Permission(new PDO('sqlite::memory:'));
        $this->policy = new GatepassPolicy($permission);
    }

    protected function tearDown(): void
    {
        unset($_SESSION['user'], $_SESSION['permissions'], $_SESSION['is_super_admin']);
    }

    private function grant(string ...$permissions): void
    {
        foreach ($permissions as $permission) {
            $_SESSION['permissions'][$permission] = true;
        }
    }

    public function test_view_denied_without_base_permission(): void
    {
        $this->assertFalse($this->policy->view(['id' => 1, 'department_id' => 1], ['created_by' => 1, 'department_id' => 1]));
    }

    public function test_view_allowed_for_own_gatepass_even_in_different_department(): void
    {
        $this->grant('gatepass.view');
        $this->assertTrue($this->policy->view(['id' => 5, 'department_id' => 2], ['created_by' => 5, 'department_id' => 9]));
    }

    public function test_view_allowed_for_same_department_gatepass_not_owned(): void
    {
        $this->grant('gatepass.view');
        $this->assertTrue($this->policy->view(['id' => 5, 'department_id' => 2], ['created_by' => 99, 'department_id' => 2]));
    }

    public function test_view_denied_for_other_department_gatepass_not_owned(): void
    {
        $this->grant('gatepass.view');
        $this->assertFalse($this->policy->view(['id' => 5, 'department_id' => 2], ['created_by' => 99, 'department_id' => 3]));
    }

    public function test_view_all_bypasses_department_and_ownership(): void
    {
        $this->grant('gatepass.view', 'gatepass.view_all');
        $this->assertTrue($this->policy->view(['id' => 5, 'department_id' => 2], ['created_by' => 99, 'department_id' => 3]));
    }

    public function test_super_admin_bypasses_everything_regardless_of_granted_permissions(): void
    {
        $_SESSION['is_super_admin'] = true;
        $this->assertTrue($this->policy->view(['id' => 5, 'department_id' => 2], ['created_by' => 99, 'department_id' => 3]));
    }

    public function test_update_allowed_for_own_pending_gatepass(): void
    {
        $this->grant('gatepass.update');
        $this->assertTrue($this->policy->update(['id' => 5], ['created_by' => 5, 'status_code' => 'PENDING']));
    }

    public function test_update_denied_for_own_gatepass_once_no_longer_pending(): void
    {
        $this->grant('gatepass.update');
        $this->assertFalse($this->policy->update(['id' => 5], ['created_by' => 5, 'status_code' => 'APPROVED']));
    }

    public function test_update_denied_for_someone_elses_pending_gatepass(): void
    {
        $this->grant('gatepass.update');
        $this->assertFalse($this->policy->update(['id' => 5], ['created_by' => 99, 'status_code' => 'PENDING']));
    }

    public function test_update_view_all_can_edit_any_pending_gatepass(): void
    {
        $this->grant('gatepass.update', 'gatepass.view_all');
        $this->assertTrue($this->policy->update(['id' => 5], ['created_by' => 99, 'status_code' => 'PENDING']));
    }

    public function test_delete_allowed_for_own_pending_not_checked_in_gatepass(): void
    {
        $this->grant('gatepass.delete');
        $this->assertTrue($this->policy->delete(['id' => 5], ['created_by' => 5, 'status_code' => 'PENDING', 'actual_in' => null]));
    }

    public function test_delete_denied_once_checked_in_even_with_view_all(): void
    {
        $this->grant('gatepass.delete', 'gatepass.view_all');
        $this->assertFalse($this->policy->delete(['id' => 5], ['created_by' => 5, 'status_code' => 'APPROVED', 'actual_in' => '2026-07-30 08:00:00']));
    }

    public function test_delete_denied_for_someone_elses_gatepass_without_view_all(): void
    {
        $this->grant('gatepass.delete');
        $this->assertFalse($this->policy->delete(['id' => 5], ['created_by' => 99, 'status_code' => 'PENDING', 'actual_in' => null]));
    }

    public function test_checkin_requires_permission_and_record_scope(): void
    {
        $this->grant('gatepass.checkin');
        $this->assertTrue($this->policy->checkIn(['id' => 5, 'department_id' => 2], ['created_by' => 5, 'department_id' => 9]));
        $this->assertFalse($this->policy->checkIn(['id' => 5, 'department_id' => 2], ['created_by' => 99, 'department_id' => 9]));
    }

    public function test_checkout_requires_permission_and_record_scope(): void
    {
        $this->grant('gatepass.checkout');
        $this->assertTrue($this->policy->checkOut(['id' => 5, 'department_id' => 2], ['created_by' => 5, 'department_id' => 9]));
        $this->assertFalse($this->policy->checkOut(['id' => 5, 'department_id' => 2], ['created_by' => 99, 'department_id' => 9]));
    }

    public function test_physical_action_view_all_requires_explicit_view_all(): void
    {
        $this->grant('gatepass.checkin');
        $this->assertFalse($this->policy->checkIn(['id' => 5, 'department_id' => 2], ['created_by' => 99, 'department_id' => 3]));

        $this->grant('gatepass.view_all');
        $this->assertTrue($this->policy->checkIn(['id' => 5, 'department_id' => 2], ['created_by' => 99, 'department_id' => 3]));
    }
}
