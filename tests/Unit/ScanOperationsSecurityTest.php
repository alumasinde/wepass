<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ScanOperationsSecurityTest extends TestCase
{
    public function test_scan_recovery_is_bounded_and_only_recovers_processing_rows(): void
    {
        $sql=file_get_contents(base_path('src/Modules/Gatepass/Services/ScanOperationsService.php'))?:'';
        self::assertStringContainsString("result='processing'",$sql);
        self::assertStringContainsString("reason_code='PROCESSING_TIMEOUT'",$sql);
        self::assertStringContainsString('max(30,min($timeoutSeconds,3600))',$sql);
    }

    public function test_scan_history_supports_security_filters_and_caps_page_size(): void
    {
        $sql=file_get_contents(base_path('src/Modules/Gatepass/Services/ScanOperationsService.php'))?:'';
        self::assertStringContainsString("e.gate_id=:gate_id",$sql);
        self::assertStringContainsString("e.device_id=:device_id",$sql);
        self::assertStringContainsString("e.guard_user_id=:guard_user_id",$sql);
        self::assertStringContainsString("e.result=:result",$sql);
        self::assertStringContainsString('min($limit,500)',$sql);
    }

    public function test_scan_events_record_completion_timestamp(): void
    {
        $sql=file_get_contents(base_path('src/Modules/Gatepass/Services/ScanIdempotencyService.php'))?:'';
        self::assertStringContainsString('completed_at=NOW()',$sql);
        self::assertStringContainsString("WHERE id=:id AND result='processing'",$sql);
    }
}
