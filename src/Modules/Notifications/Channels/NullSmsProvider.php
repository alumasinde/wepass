<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Channels;

final class NullSmsProvider implements SmsProvider
{
    public function __construct(private array &$messages)
    {
    }

    public function send(string $recipient, string $message, array $payload = []): void
    {
        $this->messages[] = compact('recipient', 'message', 'payload');
    }
}
