<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class Phase4MigrationTest extends TestCase
{
    public function test_state_machine_migration_creates_history_and_expired_status(): void
    {
        $sql = file_get_contents(base_path('database/013_phase4_state_machine.sql')) ?: '';

        self::assertStringContainsString('gatepass_state_history', $sql);
        self::assertStringContainsString('transition_code', $sql);
        self::assertStringContainsString("('Expired','expired')", $sql);
        self::assertStringContainsString('fk_gsh_gatepass', $sql);
    }

    public function test_return_history_migration_is_append_only_with_restrictive_foreign_keys(): void
    {
        $sql = file_get_contents(base_path('database/014_returnable_item_history.sql')) ?: '';

        self::assertStringContainsString('gatepass_item_returns', $sql);
        self::assertStringContainsString('quantity_returned', $sql);
        self::assertStringContainsString('fk_gpir_item', $sql);
        self::assertStringContainsString('ON DELETE RESTRICT', $sql);
    }
}
