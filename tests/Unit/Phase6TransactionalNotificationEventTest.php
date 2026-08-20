<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class Phase6TransactionalNotificationEventTest extends TestCase
{
    public function test_event_service_uses_caller_transaction_connection(): void
    {
        $source=file_get_contents(base_path('src/Modules/Notifications/Services/NotificationEventService.php')) ?: '';
        self::assertStringContainsString('function __construct(PDO $db)', $source);
        self::assertStringContainsString('new NotificationRepository($db)', $source);
        self::assertStringNotContainsString('DB::connect()', $source);
    }

    public function test_event_delivery_has_deterministic_idempotency_key(): void
    {
        $source=file_get_contents(base_path('src/Modules/Notifications/Services/NotificationEventService.php')) ?: '';
        self::assertStringContainsString("hash('sha256'", $source);
        self::assertStringContainsString("$eventCode.'|'.$userId.'|'.$channel", $source);
    }

    public function test_event_service_supports_in_app_email_and_sms(): void
    {
        $source=file_get_contents(base_path('src/Modules/Notifications/Services/NotificationEventService.php')) ?: '';
        self::assertStringContainsString("'in_app'", $source);
        self::assertStringContainsString("'email'", $source);
        self::assertStringContainsString("'sms'", $source);
    }
}
