<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class Phase4MigrationRunnerTest extends TestCase
{
    public function test_returned_status_migration_is_allowlisted(): void
    {
        $source = file_get_contents(base_path('database/migrate_tenants.php')) ?: '';

        self::assertStringContainsString("'015_phase4_returned_status.sql'", $source);
    }
}
