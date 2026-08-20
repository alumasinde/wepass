<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class Phase4RepositoryStatusMutationTest extends TestCase
{
    public function test_repository_has_no_unrestricted_update_status_method(): void
    {
        $source = file_get_contents(base_path('src/Modules/Gatepass/Repositories/GatepassRepository.php')) ?: '';

        self::assertStringNotContainsString('function updateStatus(', $source);
        self::assertStringContainsString('GatepassTransitionGuard::assert', $source);
        self::assertStringContainsString('gatepass_state_history', $source);
    }
}
