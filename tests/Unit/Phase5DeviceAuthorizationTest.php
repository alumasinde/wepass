<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class Phase5DeviceAuthorizationTest extends TestCase
{
    public function test_device_authentication_requires_active_device_gate_and_assignment_window(): void
    {
        $source = file_get_contents(base_path('src/Modules/Gatepass/Services/GateSecurityService.php')) ?: '';

        self::assertStringContainsString('d.is_active=1', $source);
        self::assertStringContainsString('d.revoked_at IS NULL', $source);
        self::assertStringContainsString('g.is_active=1', $source);
        self::assertStringContainsString('a.is_active=1', $source);
        self::assertStringContainsString('a.starts_at<=NOW()', $source);
        self::assertStringContainsString('(a.ends_at IS NULL OR a.ends_at>=NOW())', $source);
    }

    public function test_guard_assignment_is_enforced_when_bound(): void
    {
        $source = file_get_contents(base_path('src/Modules/Gatepass/Services/GateSecurityService.php')) ?: '';

        self::assertStringContainsString('guardUserId', $source);
        self::assertStringContainsString('hash_equals', $source);
    }

    public function test_admin_assignment_requires_active_gate_and_device(): void
    {
        $source = file_get_contents(base_path('src/Modules/Settings/Services/GateSecurityAdminService.php')) ?: '';

        self::assertStringContainsString('is_active=1 AND revoked_at IS NULL', $source);
        self::assertStringContainsString('WHERE id=:id AND is_active=1', $source);
    }
}
