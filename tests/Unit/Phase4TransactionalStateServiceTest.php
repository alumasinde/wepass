<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class Phase4TransactionalStateServiceTest extends TestCase
{
    public function test_state_service_can_share_an_existing_transaction_connection(): void
    {
        $source = file_get_contents(base_path('src/Modules/Gatepass/Services/GatepassStateService.php')) ?: '';

        self::assertStringContainsString('public function __construct(?PDO $db = null)', $source);
        self::assertStringContainsString('$this->ownsTransaction = $db === null;', $source);
        self::assertStringContainsString('GatepassTransitionGuard::assert', $source);
    }

    public function test_state_history_is_written_before_an_owned_transaction_commits(): void
    {
        $source = file_get_contents(base_path('src/Modules/Gatepass/Services/GatepassStateService.php')) ?: '';

        self::assertStringContainsString("INSERT INTO gatepass_state_history", $source);
        self::assertStringContainsString('if ($this->ownsTransaction) {', $source);
        self::assertStringContainsString('$this->db->commit();', $source);
    }
}
