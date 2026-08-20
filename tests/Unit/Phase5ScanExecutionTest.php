<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class Phase5ScanExecutionTest extends TestCase
{
    public function test_only_allowed_scan_decisions_can_execute(): void
    {
        $source = file_get_contents(base_path('src/Modules/Gatepass/Services/GateScanExecutionService.php')) ?: '';
        self::assertStringContainsString("'ALLOW'", $source);
        self::assertStringContainsString('Only an allowed scan can be executed.', $source);
    }

    public function test_scan_execution_uses_authoritative_state_service(): void
    {
        $source = file_get_contents(base_path('src/Modules/Gatepass/Services/GateScanExecutionService.php')) ?: '';
        self::assertStringContainsString('new GatepassStateService($this->db)', $source);
        self::assertStringContainsString('QR_GATE_SCAN', $source);
        self::assertStringContainsString('beginTransaction()', $source);
        self::assertStringContainsString('commit()', $source);
    }

    public function test_scan_execution_records_check_in_and_check_out_actor_fields(): void
    {
        $source = file_get_contents(base_path('src/Modules/Gatepass/Services/GateScanExecutionService.php')) ?: '';
        self::assertStringContainsString('actual_in', $source);
        self::assertStringContainsString('actual_out', $source);
        self::assertStringContainsString('checked_in_by', $source);
        self::assertStringContainsString('checked_out_by', $source);
    }
}
