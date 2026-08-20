<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

final class NotificationRuntime
{
    public static function dispatcher(): NotificationDispatcher
    {
        $dispatcher=new NotificationDispatcher();
        NotificationChannelFactory::registerConfigured($dispatcher);
        return $dispatcher;
    }

    public static function dispatch(int $limit=50): array
    {
        return self::dispatcher()->dispatch($limit);
    }
}
