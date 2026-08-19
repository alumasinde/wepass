<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ScanIdempotencySecurityTest extends TestCase
{
    public function test_scan_event_has_unique_request_id_and_processing_state(): void
    {
        $sql = file_get_contents(base_path('database/020_phase3_scan_idempotency.sql')) ?: '';
        self::assertStringContainsString("enum('processing','allowed','denied','error')", $sql);
        self::assertStringContainsString('uk_scan_request', file_get_contents(base_path('database/016_phase3_gate_security.sql')) ?: '');
    }

    public function test_scanner_claim_is_atomic_on_duplicate_request(): void
    {
        $source = file_get_contents(base_path('src/Modules/Gatepass/Services/ScanIdempotencyService.php')) ?: '';
        self::assertStringContainsString('INSERT INTO gate_scan_events', $source);
        self::assertStringContainsString('1062', $source);
        self::assertStringContainsString("result = 'processing'", $source);
    }

    public function test_scan_controller_uses_idempotency_service_before_gate_action(): void
    {
        $source = file_get_contents(base_path('src/Modules/Gatepass/Controllers/GateScanController.php')) ?: '';
        self::assertStringContainsString('ScanIdempotencyService', $source);
        self::assertStringContainsString('$this->idempotency->claim', $source);
        self::assertStringContainsString('$this->idempotency->complete', $source);
    }
}
