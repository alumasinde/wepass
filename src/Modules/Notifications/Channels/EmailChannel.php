<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Channels;

use App\Modules\Notifications\Services\NotificationChannel;
use RuntimeException;

final class EmailChannel implements NotificationChannel
{
    public function __construct(private readonly ?string $fromAddress = null, private readonly ?string $fromName = null)
    {
    }

    public function send(array $delivery): void
    {
        $to = trim((string)($delivery['recipient'] ?? ''));
        $subject = trim((string)($delivery['subject'] ?? 'Notification'));
        $body = (string)($delivery['body'] ?? '');
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Invalid email recipient.');
        if ($body === '') throw new RuntimeException('Notification body cannot be empty.');

        $headers = ['MIME-Version: 1.0', 'Content-Type: text/plain; charset=UTF-8'];
        if ($this->fromAddress && filter_var($this->fromAddress, FILTER_VALIDATE_EMAIL)) {
            $name = $this->fromName ? sprintf('"%s" ', addslashes($this->fromName)) : '';
            $headers[] = 'From: '.$name.'<'.$this->fromAddress.'>';
        }

        $encodedSubject = function_exists('mb_encode_mimeheader')
            ? mb_encode_mimeheader($subject, 'UTF-8')
            : $subject;

        if (!mail($to, $encodedSubject, $body, implode("\r\n", $headers))) {
            throw new RuntimeException('Email transport rejected the notification.');
        }
    }
}
