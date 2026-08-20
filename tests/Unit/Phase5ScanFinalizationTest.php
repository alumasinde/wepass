<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class Phase5ScanFinalizationTest extends TestCase
{
    public function test_allowed_scan_executes_before_idempotency_completion(): void
    {
        $source=file_get_contents(base_path('src/Modules/Gatepass/Controllers/GateScanController.php')) ?: '';
        $executionPos=strpos($source,'execution->execute');
        $completePos=strpos($source,'idempotency->complete');
        self::assertNotFalse($executionPos);
        self::assertNotFalse($completePos);
        self::assertGreaterThan($executionPos,$completePos);
    }

    public function test_denied_scan_is_finalized_without_execution(): void
    {
        $source=file_get_contents(base_path('src/Modules/Gatepass/Controllers/GateScanController.php')) ?: '';
        self::assertStringContainsString("if($decision['decision']!=='ALLOW')",$source);
        self::assertStringContainsString("'result'=>'denied'",$source);
    }
}
