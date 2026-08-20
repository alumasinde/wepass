<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class Phase6NotificationControllerTest extends TestCase
{
    public function test_notification_controller_exposes_inbox_operations(): void
    {
        $source=file_get_contents(base_path('src/Modules/Notifications/Controllers/NotificationController.php')) ?: '';
        self::assertStringContainsString('public function index(', $source);
        self::assertStringContainsString('public function unreadCount(', $source);
        self::assertStringContainsString('public function markRead(', $source);
        self::assertStringContainsString('public function markAllRead(', $source);
    }

    public function test_notification_inbox_is_user_scoped(): void
    {
        $source=file_get_contents(base_path('src/Modules/Notifications/Controllers/NotificationController.php')) ?: '';
        self::assertStringContainsString('$userId=(int)($_SESSION[\'user\'][\'id\']??0)', $source);
        self::assertStringContainsString('listForUser($userId', $source);
        self::assertStringContainsString('markRead($id,$userId)', $source);
    }
}
