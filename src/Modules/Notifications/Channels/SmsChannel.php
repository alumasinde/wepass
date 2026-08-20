<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Channels;

use App\Modules\Notifications\Services\NotificationChannel;
use RuntimeException;

interface SmsProvider
{
    public function send(string $recipient, string $message, array $payload = []): void;
}

final class SmsChannel implements NotificationChannel
{
    public function __construct(private readonly SmsProvider $provider)
    {
    }

    public function send(array $delivery): void
    {
        $recipient = trim((string)($delivery['recipient'] ?? ''));
        $body = trim((string)($delivery['body'] ?? ''));
        if ($recipient === '') throw new RuntimeException('SMS recipient is required.');
        if ($body === '') throw new RuntimeException('SMS body cannot be empty.');
        $payload = is_array($delivery['payload'] ?? null) ? $delivery['payload'] : [];
        $this->provider->send($recipient, $body, $payload);
    }
}
