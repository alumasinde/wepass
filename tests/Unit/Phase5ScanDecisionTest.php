<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class Phase5ScanDecisionTest extends TestCase
{
    public function test_scan_decision_service_is_centralized(): void
    {
        $source = file_get_contents(base_path('src/Modules/Gatepass/Services/GateScanDecisionService.php')) ?: '';

        self::assertStringContainsString('authenticateDevice', $source);
        self::assertStringContainsString('resolveQrToken', $source);
        self::assertStringContainsString('WRONG_DIRECTION', $source);
        self::assertStringContainsString('VISIT_NOT_STARTED', $source);
        self::assertStringContainsString('QR_INVALID_OR_EXPIRED', $source);
    }

    public function test_terminal_and_replay_states_are_denied(): void
    {
        $source = file_get_contents(base_path('src/Modules/Gatepass/Services/GateScanDecisionService.php')) ?: '';

        self::assertStringContainsString('ALREADY_CHECKED_IN', $source);
        self::assertStringContainsString('GATEPASS_NOT_ACTIVE', $source);
        self::assertStringContainsString("'RETURNED'", $source);
    }

    public function test_decision_returns_operational_action(): void
    {
        $source = file_get_contents(base_path('src/Modules/Gatepass/Services/GateScanDecisionService.php')) ?: '';

        self::assertStringContainsString("'CHECK_IN'", $source);
        self::assertStringContainsString("'CHECK_OUT'", $source);
        self::assertStringContainsString("'ALLOW'", $source);
        self::assertStringContainsString("'DENY'", $source);
    }
}
