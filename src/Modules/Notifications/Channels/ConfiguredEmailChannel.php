<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Channels;

use RuntimeException;

final class ConfiguredEmailChannel extends EmailChannel
{
    public static function fromEnvironment(): self
    {
        $from=trim((string)(getenv('MAIL_FROM_ADDRESS')?:''));
        $name=trim((string)(getenv('MAIL_FROM_NAME')?:'WePass'));
        return new self($from!==''?$from:null,$name);
    }
}
