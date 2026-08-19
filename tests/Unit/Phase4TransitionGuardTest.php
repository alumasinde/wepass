<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Gatepass\Services\GatepassTransitionGuard;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class Phase4TransitionGuardTest extends TestCase
{
    public function test_valid_transition_is_allowed(): void
    {
        GatepassTransitionGuard::assert('submitted', 'approved', 'APPROVE');
        self::assertTrue(true);
    }

    public function test_invalid_transition_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        GatepassTransitionGuard::assert('pending', 'checked_in', 'CHECKIN');
    }

    public function test_terminal_state_cannot_transition(): void
    {
        $this->expectException(RuntimeException::class);
        GatepassTransitionGuard::assert('cancelled', 'approved', 'OVERRIDE');
    }

    public function test_transition_guard_is_fail_closed_for_unknown_states(): void
    {
        $this->expectException(RuntimeException::class);
        GatepassTransitionGuard::assert('unknown', 'approved', 'APPROVE');
    }
}
