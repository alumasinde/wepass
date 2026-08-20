<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use App\Modules\Notifications\Repositories\NotificationRepository;
use PDO;

/**
 * Publishes notifications using the caller's PDO connection.
 * When called inside a business transaction, notification writes commit/rollback
 * with the business operation instead of using a separate connection.
 */
final class NotificationEventService
{
    private NotificationRepository $repository;

    public function __construct(PDO $db)
    {
        $this->repository = new NotificationRepository($db);
    }

    public function publishToUser(
        int $userId,
        string $eventCode,
        string $title,
        string $body,
        ?string $email = null,
        ?string $phone = null,
        array $data = [],
        array $channels = ['in_app', 'email']
    ): void {
        $notificationId = null;
        if (in_array('in_app', $channels, true)) {
            $notificationId = $this->repository->createInApp($userId, $eventCode, $title, $body, $data);
        }

        if (in_array('email', $channels, true) && $email !== null && trim($email) !== '') {
            $this->repository->queueDelivery(
                $this->key($eventCode, $userId, 'email', $data),
                $eventCode,
                'email',
                trim($email),
                $body,
                $notificationId,
                $title,
                $data
            );
        }

        if (in_array('sms', $channels, true) && $phone !== null && trim($phone) !== '') {
            $this->repository->queueDelivery(
                $this->key($eventCode, $userId, 'sms', $data),
                $eventCode,
                'sms',
                trim($phone),
                $body,
                $notificationId,
                null,
                $data
            );
        }
    }

    private function key(string $eventCode, int $userId, string $channel, array $data): string
    {
        return hash('sha256', $eventCode.'|'.$userId.'|'.$channel.'|'.json_encode($data, JSON_UNESCAPED_SLASHES));
    }
}
