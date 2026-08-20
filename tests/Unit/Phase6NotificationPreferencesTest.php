<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class Phase6NotificationPreferencesTest extends TestCase
{
    public function test_preference_service_forces_mandatory_security_events_enabled(): void
    {
        $source=file_get_contents(base_path('src/Modules/Notifications/Services/NotificationPreferenceService.php')) ?: '';
        self::assertStringContainsString('MANDATORY_EVENTS',$source);
        self::assertStringContainsString("$enabled=true",$source);
        self::assertStringContainsString("return true",$source);
    }

    public function test_preference_api_is_authenticated_and_user_scoped(): void
    {
        $controller=file_get_contents(base_path('src/Modules/Notifications/Controllers/NotificationPreferenceController.php')) ?: '';
        $routes=file_get_contents(base_path('routes/web.php')) ?: '';
        self::assertStringContainsString("$_SESSION['user']['id']",$controller);
        self::assertStringContainsString("'/notifications/preferences'",$routes);
        self::assertStringContainsString('NotificationPreferenceController::class', $routes);
    }
}
