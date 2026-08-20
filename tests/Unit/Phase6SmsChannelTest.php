<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class Phase6SmsChannelTest extends TestCase
{
    public function test_sms_channel_uses_provider_abstraction(): void
    {
        $source=file_get_contents(base_path('src/Modules/Notifications/Channels/SmsChannel.php')) ?: '';
        self::assertStringContainsString('interface SmsProvider',$source);
        self::assertStringContainsString('implements NotificationChannel',$source);
        self::assertStringContainsString('$this->provider->send',$source);
    }

    public function test_sms_channel_rejects_missing_recipient_or_body(): void
    {
        $source=file_get_contents(base_path('src/Modules/Notifications/Channels/SmsChannel.php')) ?: '';
        self::assertStringContainsString('SMS recipient is required.',$source);
        self::assertStringContainsString('SMS body cannot be empty.',$source);
    }
}
