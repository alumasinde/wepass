<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class Phase5ScanIdempotencyIntegrationTest extends TestCase
{
    public function test_scanner_uses_atomic_idempotency_claim_before_decision(): void
    {
        $source = file_get_contents(base_path('src/Modules/Gatepass/Controllers/GateScanController.php')) ?: '';
        $claimPos = strpos($source, 'idempotency->claim');
        $decisionPos = strpos($source, 'decisions->decide');

        self::assertNotFalse($claimPos);
        self::assertNotFalse($decisionPos);
        self::assertLessThan($decisionPos, $claimPos);
    }

    public function test_scanner_completes_claim_with_final_decision(): void
    {
        $source = file_get_contents(base_path('src/Modules/Gatepass/Controllers/GateScanController.php')) ?: '';

        self::assertStringContainsString('idempotency->complete', $source);
        self::assertStringContainsString('REQUEST_ALREADY_PROCESSED', $source);
    }
}
