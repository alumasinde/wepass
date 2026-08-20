<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use App\Modules\Notifications\Repositories\NotificationRepository;
use RuntimeException;

interface NotificationChannel
{
    public function send(array $delivery): void;
}

final class NotificationDispatcher
{
    /** @var array<string,NotificationChannel> */
    private array $channels = [];

    public function __construct(private readonly NotificationRepository $repository = new NotificationRepository())
    {
    }

    public function register(string $channel, NotificationChannel $handler): void
    {
        $this->channels[strtolower(trim($channel))] = $handler;
    }

    public function dispatch(int $limit = 50): array
    {
        $processed = 0;
        $sent = 0;
        $failed = 0;

        foreach ($this->repository->claimPendingDeliveries(max(1, min(100, $limit))) as $delivery) {
            $processed++;
            $channel = strtolower((string)$delivery['channel']);
            try {
                if (!isset($this->channels[$channel])) {
                    throw new RuntimeException("No notification channel registered: {$channel}");
                }
                $this->channels[$channel]->send($delivery);
                $this->repository->markDeliverySent((int)$delivery['id']);
                $sent++;
            } catch (\Throwable $e) {
                $this->repository->markDeliveryFailed((int)$delivery['id'], $e->getMessage());
                $failed++;
            }
        }

        return compact('processed', 'sent', 'failed');
    }
}
