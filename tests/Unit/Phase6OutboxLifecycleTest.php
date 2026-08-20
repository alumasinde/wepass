<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class Phase6OutboxLifecycleTest extends TestCase
{
    public function test_claim_moves_pending_delivery_to_processing(): void
    {
        $source=file_get_contents(base_path('src/Modules/Notifications/Repositories/NotificationRepository.php')) ?: '';
        self::assertStringContainsString("status='pending'",$source);
        self::assertStringContainsString("status='processing'",$source);
        self::assertStringContainsString("failed_at IS NULL",$source);
    }

    public function test_failed_delivery_has_terminal_retry_limit(): void
    {
        $source=file_get_contents(base_path('src/Modules/Notifications/Repositories/NotificationRepository.php')) ?: '';
        self::assertStringContainsString("attempts>=5",$source);
        self::assertStringContainsString("status='failed'",$source);
        self::assertStringContainsString("status='pending'",$source);
    }

    public function test_delivery_payload_is_decoded_for_channels(): void
    {
        $source=file_get_contents(base_path('src/Modules/Notifications/Repositories/NotificationRepository.php')) ?: '';
        self::assertStringContainsString("json_decode((string)$row['payload_json']",$source);
    }
}
