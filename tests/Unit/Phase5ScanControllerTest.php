<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class Phase5ScanControllerTest extends TestCase
{
    public function test_controller_uses_centralized_decision_and_execution_services(): void
    {
        $source = file_get_contents(base_path('src/Modules/Gatepass/Controllers/GateScanController.php')) ?: '';

        self::assertStringContainsString('GateScanDecisionService', $source);
        self::assertStringContainsString('GateScanExecutionService', $source);
        self::assertStringContainsString('$this->decisions->decide', $source);
        self::assertStringContainsString('$this->execution->execute', $source);
    }

    public function test_controller_records_denied_and_allowed_scans_with_request_id(): void
    {
        $source = file_get_contents(base_path('src/Modules/Gatepass/Controllers/GateScanController.php')) ?: '';

        self::assertStringContainsString('recordScan', $source);
        self::assertStringContainsString('request_id', $source);
        self::assertStringContainsString("'DENY'", $source);
        self::assertStringContainsString("'ALLOW'", $source);
    }
}
