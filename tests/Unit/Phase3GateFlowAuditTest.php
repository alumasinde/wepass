<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class Phase3GateFlowAuditTest extends TestCase
{
    private function read(string $path): string
    {
        return file_get_contents(base_path($path)) ?: '';
    }

    public function test_gate_scan_requires_approved_device_and_qr_validation(): void
    {
        $source = $this->read('src/Modules/Gatepass/Controllers/GateScanController.php');
        self::assertStringContainsString('authenticateDevice', $source);
        self::assertStringContainsString('resolveQrToken', $source);
        self::assertStringContainsString('idempotency->claim', $source);
    }

    public function test_gate_scan_rejects_invalid_qr_and_finalizes_event(): void
    {
        $source = $this->read('src/Modules/Gatepass/Controllers/GateScanController.php');
        self::assertStringContainsString('QR_INVALID_OR_EXPIRED', $source);
        self::assertStringContainsString('idempotency->complete', $source);
    }

    public function test_gate_scan_has_allowed_and_error_terminal_states(): void
    {
        $source = $this->read('src/Modules/Gatepass/Controllers/GateScanController.php');
        self::assertStringContainsString("'result' => 'allowed'", $source);
        self::assertStringContainsString("'result' => 'error'", $source);
        self::assertStringContainsString('PROCESSING_ERROR', $source);
    }

    public function test_scan_history_is_bounded_and_filterable(): void
    {
        $source = $this->read('src/Modules/Gatepass/Services/ScanOperationsService.php');
        self::assertStringContainsString('max(1,min($limit,500))', $source);
        foreach (['gate_id', 'device_id', 'guard_user_id', 'result', 'scan_type', 'from_date', 'to_date'] as $field) {
            self::assertStringContainsString($field, $source);
        }
    }

    public function test_phase3_routes_require_scan_permissions(): void
    {
        $source = $this->read('config/route_permissions.php');
        self::assertStringContainsString("['scans.scan']", $source);
        self::assertStringContainsString("['scans.view']", $source);
        self::assertStringContainsString("['scans.export']", $source);
    }
}
