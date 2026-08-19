<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class Phase4ApprovalStateBoundaryTest extends TestCase
{
    public function test_approval_service_uses_authoritative_state_boundary(): void
    {
        $source = file_get_contents(base_path('src/Modules/Approval/Services/ApprovalService.php')) ?: '';

        self::assertStringContainsString('GatepassStateService', $source);
        self::assertStringContainsString('transitionGatepass', $source);
        self::assertStringContainsString("'rejected','APPROVAL_REJECTED'", str_replace(' ', '', $source));
        self::assertStringContainsString("'approved','WORKFLOW_APPROVED'", str_replace(' ', '', $source));
        self::assertStringNotContainsString('UPDATE gatepasses SET status_id = ?', $source);
    }

    public function test_approval_transitions_are_recorded_with_actor_context(): void
    {
        $source = file_get_contents(base_path('src/Modules/Approval/Services/ApprovalService.php')) ?: '';

        self::assertStringContainsString('APPROVAL_REJECTED', $source);
        self::assertStringContainsString('WORKFLOW_APPROVED', $source);
        self::assertStringContainsString('$userId', $source);
    }
}
