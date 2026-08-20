<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class Phase6EmailChannelTest extends TestCase
{
    public function test_email_channel_uses_notification_channel_contract(): void
    {
        $source=file_get_contents(base_path('src/Modules/Notifications/Channels/EmailChannel.php')) ?: '';
        self::assertStringContainsString('implements NotificationChannel',$source);
        self::assertStringContainsString('FILTER_VALIDATE_EMAIL',$source);
        self::assertStringContainsString('mail(',$source);
    }

    public function test_dispatcher_has_provider_independent_channel_registry(): void
    {
        $source=file_get_contents(base_path('src/Modules/Notifications/Services/NotificationDispatcher.php')) ?: '';
        self::assertStringContainsString('NotificationChannel',$source);
        self::assertStringContainsString('register(',$source);
        self::assertStringContainsString('markDeliveryFailed',$source);
    }
}
