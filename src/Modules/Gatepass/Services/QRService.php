<?php

namespace App\Modules\Gatepass\Services;

class QRService
{
    private const CACHE_DIR = '/uploads/qrcodes';
    private const MAX_ATTEMPTS = 3;

    /** Generate/cache a QR. $payload defaults to the gatepass number for legacy callers. */
    public function getOrCreate(string $gatepassNumber, ?string $payload = null): ?string
    {
        $safeName = $this->safeFileName($gatepassNumber);
        $relative = self::CACHE_DIR . "/{$safeName}.png";
        $absolute = $this->publicPath() . $relative;
        if (is_file($absolute) && filesize($absolute) > 0) return $relative;
        $bytes = $this->fetchWithRetry($payload ?? $gatepassNumber);
        if ($bytes === null) return null;
        $dir = dirname($absolute);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) return null;
        if (@file_put_contents($absolute, $bytes) === false) return null;
        return $relative;
    }

    /** Force regeneration and encode the supplied payload. */
    public function regenerate(string $gatepassNumber, ?string $payload = null): ?string
    {
        $safeName = $this->safeFileName($gatepassNumber);
        $absolute = $this->publicPath() . self::CACHE_DIR . "/{$safeName}.png";
        if (is_file($absolute)) @unlink($absolute);
        return $this->generate($gatepassNumber, $payload ?? $gatepassNumber);
    }

    private function generate(string $gatepassNumber, string $payload): ?string
    {
        $safeName = $this->safeFileName($gatepassNumber);
        $relative = self::CACHE_DIR . "/{$safeName}.png";
        $absolute = $this->publicPath() . $relative;
        $bytes = $this->fetchWithRetry($payload);
        if ($bytes === null) return null;
        $dir = dirname($absolute);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) return null;
        if (@file_put_contents($absolute, $bytes) === false) return null;
        return $relative;
    }

    private function fetchWithRetry(string $payload): ?string
    {
        $base = rtrim((string) config('qr.service_url', ''), '/');
        if ($base === '') { error_log('QRService: qr.service_url is not configured.'); return null; }
        $url = $base . '/generate?code=' . urlencode($payload);
        $timeout = max(1, (int) config('qr.timeout_seconds', 5));
        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $result = $this->fetch($url, $timeout);
            if ($result !== null) return $result;
            if ($attempt < self::MAX_ATTEMPTS) usleep(200_000 * $attempt);
        }
        error_log("QRService: failed to generate QR after " . self::MAX_ATTEMPTS . ' attempts.');
        return null;
    }

    private function fetch(string $url, int $timeout): ?string
    {
        if (!function_exists('curl_init')) return $this->fetchViaStream($url, $timeout);
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>$timeout,CURLOPT_TIMEOUT=>$timeout,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTP|CURLPROTO_HTTPS]);
        $body=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_HTTP_CODE);$error=curl_error($ch);curl_close($ch);
        return ($body!==false&&$error===''&&$status===200&&$body!=='')?$body:null;
    }

    private function fetchViaStream(string $url, int $timeout): ?string
    {
        $context=stream_context_create(['http'=>['timeout'=>$timeout,'ignore_errors'=>true]]);
        $body=@file_get_contents($url,false,$context);
        return ($body!==false&&$body!=='')?$body:null;
    }

    private function safeFileName(string $value): string { return preg_replace('/[^A-Za-z0-9_-]/','_',$value); }
    private function publicPath(): string { return base_path('public'); }
}
