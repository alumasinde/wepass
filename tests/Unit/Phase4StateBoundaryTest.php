<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class Phase4StateBoundaryTest extends TestCase
{
    public function test_state_service_invokes_transition_guard(): void
    {
        $source = file_get_contents(base_path('src/Modules/Gatepass/Services/GatepassStateService.php')) ?: '';

        self::assertStringContainsString('GatepassTransitionGuard::assert', $source);
        self::assertStringContainsString('gatepass_state_history', $source);
        self::assertStringContainsString('status_id=:to_status', $source);
    }

    public function test_transition_guard_remains_fail_closed_for_terminal_states(): void
    {
        $source = file_get_contents(base_path('src/Modules/Gatepass/Services/GatepassTransitionGuard.php')) ?: '';

        self::assertStringContainsString("'rejected', 'cancelled', 'expired'", $source);
        self::assertStringContainsString("'checked_out' => ['checked_in']", $source);
        self::assertStringContainsString("'checked_in' => ['checked_out']", $source);
    }
}
