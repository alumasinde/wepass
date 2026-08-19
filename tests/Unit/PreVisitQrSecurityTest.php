<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PreVisitQrSecurityTest extends TestCase
{
    public function test_previsit_qr_schema_stores_only_hash_and_expiry_metadata(): void
    {
        $sql = file_get_contents(base_path('database/018_phase3_previsit_qr.sql')) ?: '';

        self::assertStringContainsString('previsit_qr_token_hash', $sql);
        self::assertStringContainsString('previsit_qr_issued_at', $sql);
        self::assertStringContainsString('previsit_qr_expires_at', $sql);
        self::assertStringContainsString('previsit_qr_revoked_at', $sql);
        self::assertStringNotContainsString('previsit_qr_token varchar', strtolower($sql));
    }

    public function test_previsit_service_hashes_random_credentials_and_rejects_blacklisted_visitors(): void
    {
        $source = file_get_contents(base_path('src/Modules/Visits/Services/PreVisitQrService.php')) ?: '';

        self::assertStringContainsString('bin2hex(random_bytes(32))', $source);
        self::assertStringContainsString("hash('sha256', $token)", $source);
        self::assertStringContainsString('vis.is_blacklisted = 0', $source);
        self::assertStringContainsString('previsit_qr_revoked_at IS NULL', $source);
    }

    public function test_gate_validation_uses_approved_device_and_enforces_time_window(): void
    {
        $source = file_get_contents(base_path('src/Modules/Visits/Controllers/PreVisitQrController.php')) ?: '';

        self::assertStringContainsString('authenticateDevice', $source);
        self::assertStringContainsString('previsit_scan_window_seconds', $source);
        self::assertStringContainsString('PREVISIT_TOO_EARLY', $source);
        self::assertStringContainsString('PREVISIT_EXPIRED', $source);
    }
}
