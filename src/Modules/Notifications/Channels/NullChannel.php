<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Channels;

use App\Modules\Notifications\Services\NotificationChannel;

final class NullChannel implements NotificationChannel
{
    public function __construct(private array &$deliveries)
    {
    }

    public function send(array $delivery): void
    {
        $this->deliveries[] = $delivery;
    }
}
