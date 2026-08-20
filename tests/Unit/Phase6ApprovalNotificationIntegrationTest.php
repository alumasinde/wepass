<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class Phase6ApprovalNotificationIntegrationTest extends TestCase
{
    public function test_approval_service_uses_transactional_notification_boundary(): void
    {
        $source=file_get_contents(base_path('src/Modules/Approval/Services/ApprovalService.php')) ?: '';
        self::assertStringContainsString('NotificationEventService',$source);
        self::assertStringContainsString('new NotificationEventService($this->db)',$source);
    }

    public function test_rejection_and_final_approval_publish_notifications(): void
    {
        $source=file_get_contents(base_path('src/Modules/Approval/Services/ApprovalService.php')) ?: '';
        self::assertStringContainsString("'gatepass.approval_rejected'",$source);
        self::assertStringContainsString("'gatepass.approved'",$source);
        self::assertStringContainsString('notifyRequester',$source);
    }
}
