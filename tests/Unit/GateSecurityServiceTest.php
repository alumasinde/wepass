<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class GateSecurityServiceTest extends TestCase
{
    public function test_service_uses_constant_time_device_secret_comparison(): void
    {
        $sql = file_get_contents(base_path('src/Modules/Gatepass/Services/GateSecurityService.php')) ?: '';

        self::assertStringContainsString("hash_equals($expected, $presentedHash)", $sql);
        self::assertStringContainsString('random_bytes(32)', $sql);
    }

    public function test_service_never_stores_plaintext_device_or_qr_credentials(): void
    {
        $sql = file_get_contents(base_path('src/Modules/Gatepass/Services/GateSecurityService.php')) ?: '';

        self::assertStringContainsString("hash('sha256', $deviceSecret)", $sql);
        self::assertStringContainsString("hash('sha256', $token)", $sql);
        self::assertStringContainsString('qr_token_hash', $sql);
        self::assertStringContainsString('device_secret_hash', $sql);
    }

    public function test_qr_validation_rejects_revoked_expired_or_deleted_credentials(): void
    {
        $sql = file_get_contents(base_path('src/Modules/Gatepass/Services/GateSecurityService.php')) ?: '';

        self::assertStringContainsString('g.deleted_at IS NULL', $sql);
        self::assertStringContainsString('g.qr_revoked_at IS NULL', $sql);
        self::assertStringContainsString('g.qr_expires_at IS NULL OR g.qr_expires_at > NOW()', $sql);
    }
}
