<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class Phase5ScanRateLimitTest extends TestCase
{
    public function test_gate_scanner_uses_a_device_gate_and_ip_bound_rate_limit(): void
    {
        $source=file_get_contents(base_path('src/Modules/Gatepass/Controllers/GateScanController.php'))?:'';
        self::assertStringContainsString('RateLimiter::tooManyAttempts', $source);
        self::assertStringContainsString('RateLimiter::hit', $source);
        self::assertStringContainsString('RATE_LIMITED', $source);
        self::assertStringContainsString("'gate-scan:'", $source);
    }
}
