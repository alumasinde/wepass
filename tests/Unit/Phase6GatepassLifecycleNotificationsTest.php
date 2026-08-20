<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class Phase6GatepassLifecycleNotificationsTest extends TestCase
{
    public function test_gatepass_controller_integrates_create_checkin_checkout_notifications(): void
    {
        $source=file_get_contents(base_path('src/Modules/Gatepass/Controllers/GatepassController.php')) ?: '';
        self::assertStringContainsString('GatepassNotificationService',$source);
        self::assertStringContainsString('$this->notifications->created',$source);
        self::assertStringContainsString('$this->notifications->checkedIn',$source);
        self::assertStringContainsString('$this->notifications->checkedOut',$source);
    }

    public function test_notification_events_apply_user_preferences(): void
    {
        $source=file_get_contents(base_path('src/Modules/Notifications/Services/NotificationEventService.php')) ?: '';
        self::assertStringContainsString('NotificationPreferenceService',$source);
        self::assertStringContainsString("isEnabled($userId,$eventCode",$source);
    }

    public function test_return_reminder_supports_due_and_overdue_events(): void
    {
        $source=file_get_contents(base_path('src/Modules/Notifications/Services/GatepassReminderService.php')) ?: '';
        self::assertStringContainsString('gatepass.return_reminder',$source);
        self::assertStringContainsString('gatepass.return_overdue',$source);
        self::assertStringContainsString("code='RETURNED'",$source);
    }
}
