<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Gatepass\Services\GatepassTransitionGuard;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class Phase4ReturnTransitionTest extends TestCase
{
    public function test_checked_in_can_be_completed_as_returned(): void
    {
        GatepassTransitionGuard::assert('checked_in', 'returned', 'RETURN_COMPLETE');
        self::assertTrue(true);
    }

    public function test_returned_is_terminal(): void
    {
        $this->expectException(RuntimeException::class);
        GatepassTransitionGuard::assert('returned', 'checked_out', 'CHECKOUT');
    }

    public function test_workflow_transition_service_exposes_return_completion(): void
    {
        $source = file_get_contents(base_path('src/Modules/Gatepass/Services/GatepassWorkflowTransitionService.php')) ?: '';

        self::assertStringContainsString('completeReturn', $source);
        self::assertStringContainsString('RETURN_COMPLETE', $source);
    }
}
