<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use App\Modules\Notifications\Channels\ConfiguredEmailChannel;
use App\Modules\Notifications\Channels\ConfiguredSmsProvider;
use App\Modules\Notifications\Channels\SmsChannel;
use RuntimeException;

final class NotificationChannelFactory
{
    public static function registerConfigured(NotificationDispatcher $dispatcher): void
    {
        if(trim((string)(getenv('MAIL_FROM_ADDRESS')?:''))!=='') $dispatcher->register('email',ConfiguredEmailChannel::fromEnvironment());
        if(trim((string)(getenv('SMS_PROVIDER_URL')?:''))!=='' && trim((string)(getenv('SMS_PROVIDER_TOKEN')?:''))!=='') $dispatcher->register('sms',new SmsChannel(ConfiguredSmsProvider::fromEnvironment()));
    }
}
