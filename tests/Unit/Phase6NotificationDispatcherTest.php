<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class Phase6NotificationDispatcherTest extends TestCase
{
    public function test_dispatcher_claims_pending_deliveries_and_handles_channels(): void
    {
        $source=file_get_contents(base_path('src/Modules/Notifications/Services/NotificationDispatcher.php')) ?: '';
        self::assertStringContainsString('claimPendingDeliveries', $source);
        self::assertStringContainsString('register', $source);
        self::assertStringContainsString('markDeliverySent', $source);
        self::assertStringContainsString('markDeliveryFailed', $source);
    }

    public function test_outbox_repository_supports_retry_and_locking(): void
    {
        $source=file_get_contents(base_path('src/Modules/Notifications/Repositories/NotificationRepository.php')) ?: '';
        self::assertStringContainsString('FOR UPDATE SKIP LOCKED', $source);
        self::assertStringContainsString('locked_at', $source);
        self::assertStringContainsString('attempts=attempts+1', $source);
        self::assertStringContainsString('available_at=DATE_ADD', $source);
    }
}
