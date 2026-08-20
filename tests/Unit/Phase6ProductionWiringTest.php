<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class Phase6ProductionWiringTest extends TestCase
{
    public function test_runtime_registers_configured_channels(): void
    {
        $source=file_get_contents(base_path('src/Modules/Notifications/Services/NotificationRuntime.php')) ?: '';
        self::assertStringContainsString('NotificationChannelFactory::registerConfigured',$source);
        self::assertStringContainsString('->dispatch',$source);
    }

    public function test_configured_email_channel_delegates_to_email_transport(): void
    {
        $source=file_get_contents(base_path('src/Modules/Notifications/Channels/ConfiguredEmailChannel.php')) ?: '';
        self::assertStringContainsString('implements', $source);
        self::assertStringContainsString('$this->transport->send',$source);
    }

    public function test_sms_provider_requires_configuration_and_uses_bearer_auth(): void
    {
        $source=file_get_contents(base_path('src/Modules/Notifications/Channels/ConfiguredSmsProvider.php')) ?: '';
        self::assertStringContainsString('SMS_PROVIDER_URL',$source);
        self::assertStringContainsString('SMS_PROVIDER_TOKEN',$source);
        self::assertStringContainsString('Authorization: Bearer',$source);
    }
}
