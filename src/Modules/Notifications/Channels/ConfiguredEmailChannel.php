<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Channels;

use RuntimeException;

final class ConfiguredEmailChannel implements \App\Modules\Notifications\Services\NotificationChannel
{
    private EmailChannel $transport;

    public function __construct(?string $fromAddress=null, ?string $fromName=null)
    {
        $this->transport=new EmailChannel($fromAddress,$fromName);
    }

    public static function fromEnvironment(): self
    {
        $from=trim((string)(getenv('MAIL_FROM_ADDRESS')?:''));
        $name=trim((string)(getenv('MAIL_FROM_NAME')?:'WePass'));
        return new self($from!==''?$from:null,$name);
    }

    public function send(array $delivery): void
    {
        $this->transport->send($delivery);
    }
}
