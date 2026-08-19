<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class Phase4StateIntegrationTest extends TestCase
{
    public function test_gatepass_repository_records_checkin_and_checkout_as_state_transitions(): void
    {
        $php = file_get_contents(base_path('src/Modules/Gatepass/Repositories/GatepassRepository.php')) ?: '';

        self::assertStringContainsString('GatepassTransitionGuard::assert', $php);
        self::assertStringContainsString("transition_code'=>'CHECKIN'", $php);
        self::assertStringContainsString("transition_code'=>'CHECKOUT'", $php);
        self::assertStringContainsString('gatepass_state_history', $php);
        self::assertStringContainsString('FOR UPDATE', $php);
    }

    public function test_tenant_migration_allowlist_contains_phase4_migrations(): void
    {
        $php = file_get_contents(base_path('database/migrate_tenants.php')) ?: '';

        self::assertStringContainsString("'013_phase4_state_machine.sql'", $php);
        self::assertStringContainsString("'014_returnable_item_history.sql'", $php);
    }
}
