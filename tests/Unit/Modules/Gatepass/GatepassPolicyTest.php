<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Gatepass;

use App\Core\Permission;
use App\Modules\Gatepass\Policies\GatepassPolicy;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Permission::can() only ever reads $_SESSION['permissions'] /
 * $_SESSION['is_super_admin'] — it never touches its PDO connection
 * for that method (only loadForUser() does, which these tests never
 * call). That means these tests can drive Permission's real
 * behavior directly through the session array instead of mocking
 * anything, using an in-memory SQLite handle purely to satisfy the
 * constructor's type — no MySQL, no tenant DB, no fixtures needed.
 */
final class GatepassPolicyTest extends TestCase
{
    private GatepassPolicy $policy;

    protected function setUp(): void
    {
        $_SESSION['permissions']    = [];
        $_SESSION['is_super_admin'] = false;

        $permission   = new Permission(new PDO('sqlite::memory:'));
        $this->policy = new GatepassPolicy($permission);
    }

    protected function tearDown(): void
    {
        unset($_SESSION['permissions'], $_SESSION['is_super_admin']);
    }

    private function grant(string ...$permissions): void
    {
        foreach ($permissions as $permission) {
            $_SESSION['permissions'][$permission] = true;
        }
    }

    // ── view() ───────────────────────────────────────────────

    public function test_view_denied_without_base_permission(): void
    {
        $user     = ['id' => 1, 'department_id' => 1];
        $gatepass = ['created_by' => 1, 'department_id' => 1];

        $this->assertFalse($this->policy->view($user, $gatepass));
    }

    public function test_view_allowed_for_own_gatepass_even_in_different_department(): void
    {
        $this->grant('gatepass.view');

        $user     = ['id' => 5, 'department_id' => 2];
        $gatepass = ['created_by' => 5, 'department_id' => 9];

        $this->assertTrue($this->policy->view($user, $gatepass));
    }

    public function test_view_allowed_for_same_department_gatepass_not_owned(): void
    {
        $this->grant('gatepass.view');

        $user     = ['id' => 5, 'department_id' => 2];
        $gatepass = ['created_by' => 99, 'department_id' => 2];

        $this->assertTrue($this->policy->view($user, $gatepass));
    }

    public function test_view_denied_for_other_department_gatepass_not_owned(): void
    {
        $this->grant('gatepass.view');

        $user     = ['id' => 5, 'department_id' => 2];
        $gatepass = ['created_by' => 99, 'department_id' => 3];

        $this->assertFalse($this->policy->view($user, $gatepass));
    }

    public function test_view_all_bypasses_department_and_ownership(): void
    {
        $this->grant('gatepass.view', 'gatepass.view_all');

        $user     = ['id' => 5, 'department_id' => 2];
        $gatepass = ['created_by' => 99, 'department_id' => 3];

        $this->assertTrue($this->policy->view($user, $gatepass));
    }

    public function test_super_admin_bypasses_everything_regardless_of_granted_permissions(): void
    {
        $_SESSION['is_super_admin'] = true;
        // Deliberately NOT granting gatepass.view at all.

        $user     = ['id' => 5, 'department_id' => 2];
        $gatepass = ['created_by' => 99, 'department_id' => 3];

        $this->assertTrue($this->policy->view($user, $gatepass));
    }

    // ── update() ─────────────────────────────────────────────

    public function test_update_allowed_for_own_pending_gatepass(): void
    {
        $this->grant('gatepass.update');

        $user     = ['id' => 5];
        $gatepass = ['created_by' => 5, 'status_code' => 'PENDING'];

        $this->assertTrue($this->policy->update($user, $gatepass));
    }

    public function test_update_denied_for_own_gatepass_once_no_longer_pending(): void
    {
        $this->grant('gatepass.update');

        $user     = ['id' => 5];
        $gatepass = ['created_by' => 5, 'status_code' => 'APPROVED'];

        $this->assertFalse($this->policy->update($user, $gatepass));
    }

    public function test_update_denied_for_someone_elses_pending_gatepass(): void
    {
        $this->grant('gatepass.update');

        $user     = ['id' => 5];
        $gatepass = ['created_by' => 99, 'status_code' => 'PENDING'];

        $this->assertFalse($this->policy->update($user, $gatepass));
    }

    public function test_update_view_all_can_edit_any_pending_gatepass_regardless_of_owner(): void
    {
        $this->grant('gatepass.update', 'gatepass.view_all');

        $user     = ['id' => 5];
        $gatepass = ['created_by' => 99, 'status_code' => 'PENDING'];

        $this->assertTrue($this->policy->update($user, $gatepass));
    }

    // ── delete() ─────────────────────────────────────────────

    public function test_delete_allowed_for_own_pending_not_checked_in_gatepass(): void
    {
        $this->grant('gatepass.delete');

        $user     = ['id' => 5];
        $gatepass = ['created_by' => 5, 'status_code' => 'PENDING', 'actual_in' => null];

        $this->assertTrue($this->policy->delete($user, $gatepass));
    }

    /**
     * The important one: this is the audit-trail protection — even
     * a view_all holder can't delete a gatepass that's already been
     * checked in, because that represents a real physical event.
     */
    public function test_delete_denied_once_checked_in_even_with_view_all(): void
    {
        $this->grant('gatepass.delete', 'gatepass.view_all');

        $user     = ['id' => 5];
        $gatepass = ['created_by' => 5, 'status_code' => 'APPROVED', 'actual_in' => '2026-07-30 08:00:00'];

        $this->assertFalse($this->policy->delete($user, $gatepass));
    }

    public function test_delete_denied_for_someone_elses_gatepass_without_view_all(): void
    {
        $this->grant('gatepass.delete');

        $user     = ['id' => 5];
        $gatepass = ['created_by' => 99, 'status_code' => 'PENDING', 'actual_in' => null];

        $this->assertFalse($this->policy->delete($user, $gatepass));
    }
}
