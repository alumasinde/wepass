<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Gatepass\Services\GatepassTransitionGuard;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class Phase4WorkflowTransitionIntegrationTest extends TestCase
{
    public function test_workflow_outcomes_have_explicit_transition_codes(): void
    {
        $source = file_get_contents(base_path('src/Modules/Gatepass/Services/GatepassWorkflowTransitionService.php')) ?: '';

        self::assertStringContainsString('APPROVE_WORKFLOW', $source);
        self::assertStringContainsString('REJECT_WORKFLOW', $source);
        self::assertStringContainsString('CANCEL_WORKFLOW', $source);
    }

    public function test_rejection_and_cancellation_require_reasons(): void
    {
        $source = file_get_contents(base_path('src/Modules/Gatepass/Services/GatepassWorkflowTransitionService.php')) ?: '';

        self::assertStringContainsString("A rejection reason is required.", $source);
        self::assertStringContainsString("A cancellation reason is required.", $source);
    }

    public function test_approval_transition_respects_state_machine(): void
    {
        GatepassTransitionGuard::assert('submitted', 'approved', 'APPROVE_WORKFLOW');

        $this->expectException(RuntimeException::class);
        GatepassTransitionGuard::assert('cancelled', 'approved', 'APPROVE_WORKFLOW');
    }
}
