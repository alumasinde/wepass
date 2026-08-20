<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use App\Modules\Notifications\Repositories\NotificationRepository;

final class NotificationService
{
    public function __construct(private readonly NotificationRepository $repository = new NotificationRepository())
    {
    }

    public function notifyInApp(?int $userId, string $eventCode, string $title, string $body, array $data = []): int
    {
        return $this->repository->createInApp($userId, $eventCode, $title, $body, $data);
    }

    public function queueChannel(
        string $idempotencyKey,
        string $eventCode,
        string $channel,
        string $recipient,
        string $body,
        ?int $notificationId = null,
        ?string $subject = null,
        array $payload = []
    ): bool {
        return $this->repository->queueDelivery($idempotencyKey, $eventCode, $channel, $recipient, $body, $notificationId, $subject, $payload);
    }
}
