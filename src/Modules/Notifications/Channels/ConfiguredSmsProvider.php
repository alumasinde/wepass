<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Channels;

use RuntimeException;

final class ConfiguredSmsProvider implements SmsProvider
{
    public function __construct(private readonly string $endpoint, private readonly string $token){}

    public static function fromEnvironment(): self
    {
        $endpoint=trim((string)(getenv('SMS_PROVIDER_URL')?:''));
        $token=trim((string)(getenv('SMS_PROVIDER_TOKEN')?:''));
        if($endpoint===''||$token==='') throw new RuntimeException('SMS provider is not configured.');
        if(!filter_var($endpoint,FILTER_VALIDATE_URL)) throw new RuntimeException('SMS provider URL is invalid.');
        return new self($endpoint,$token);
    }

    public function send(string $recipient,string $message,array $payload=[]): void
    {
        if(trim($recipient)===''||trim($message)==='') throw new RuntimeException('SMS recipient and message are required.');
        $body=json_encode(['to'=>$recipient,'message'=>$message,'metadata'=>$payload],JSON_THROW_ON_ERROR);
        $ch=curl_init($this->endpoint);
        curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$this->token,'Content-Type: application/json'],CURLOPT_POSTFIELDS=>$body]);
        $response=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$error=curl_error($ch);curl_close($ch);
        if($response===false||$error!==''||$status<200||$status>=300) throw new RuntimeException('SMS provider request failed.'.($error!==''?' '.$error:'').' HTTP '.$status);
    }
}
