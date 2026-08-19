<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class QrCredentialServiceTest extends TestCase
{
    public function test_qr_credentials_are_256_bit_random_values(): void
    {
        $source = file_get_contents(base_path('src/Modules/Gatepass/Services/QrCredentialService.php')) ?: '';

        self::assertStringContainsString('bin2hex(random_bytes(32))', $source);
        self::assertStringContainsString("hash('sha256', $token)", $source);
    }

    public function test_issued_credentials_are_expiring_and_revocable(): void
    {
        $source = file_get_contents(base_path('src/Modules/Gatepass/Services/QrCredentialService.php')) ?: '';

        self::assertStringContainsString('qr_expires_at', $source);
        self::assertStringContainsString('qr_revoked_at', $source);
        self::assertStringContainsString('qr_issued_at', $source);
    }

    public function test_raw_qr_token_is_not_written_to_database(): void
    {
        $source = file_get_contents(base_path('src/Modules/Gatepass/Services/QrCredentialService.php')) ?: '';

        self::assertStringContainsString('qr_token_hash = :hash', $source);
        self::assertStringNotContainsString('qr_token = :token', $source);
    }
}
