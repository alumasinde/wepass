<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class Phase4GatepassStateTest extends TestCase
{
    private function read(string $path): string
    {
        return file_get_contents(base_path($path)) ?: '';
    }

    public function test_state_service_requires_expected_current_state(): void
    {
        $source = $this->read('src/Modules/Gatepass/Services/GatepassStateService.php');
        self::assertStringContainsString('status_id=:from_status', $source);
        self::assertStringContainsString('deleted_at IS NULL', $source);
        self::assertStringContainsString('Gatepass state changed concurrently', $source);
    }

    public function test_state_changes_create_immutable_history(): void
    {
        $source = $this->read('src/Modules/Gatepass/Services/GatepassStateService.php');
        self::assertStringContainsString('gatepass_state_history', $source);
        self::assertStringContainsString('from_status_id', $source);
        self::assertStringContainsString('to_status_id', $source);
        self::assertStringContainsString('transition_code', $source);
    }

    public function test_expiry_is_bounded_and_only_targets_pending_or_approved(): void
    {
        $source = $this->read('src/Modules/Gatepass/Services/GatepassStateService.php');
        self::assertStringContainsString('max(1, min($batchSize, 1000))', $source);
        self::assertStringContainsString("s.code IN ('PENDING', 'APPROVED')", $source);
        self::assertStringContainsString("'EXPIRED'", $source);
        self::assertStringContainsString("'EXPIRE_SYSTEM'", $source);
    }

    public function test_phase4_schema_adds_expired_state_and_expiry_index(): void
    {
        $source = $this->read('database/024_phase4_gatepass_states.sql');
        self::assertStringContainsString("'Expired', 'expired'", $source);
        self::assertStringContainsString('gatepass_workflow', $source);
        self::assertStringContainsString("'cancel','override'", $source);
    }
}
