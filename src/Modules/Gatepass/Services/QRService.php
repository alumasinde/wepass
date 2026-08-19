<?php

namespace App\Modules\Gatepass\Services;

/**
 * QRService — generates and caches the QR image for a gatepass.
 *
 * Design goals over the previous stub:
 *   - cURL with a real timeout instead of file_get_contents() (which
 *     has no timeout by default and can hang the request thread).
 *   - Retries with backoff for a flaky microservice.
 *   - Once generated, the PNG is cached to
 *     public/uploads/qrcodes/{gatepass_number}.png. Every later
 *     read (showing the gatepass, printing it, the gate scanner UI)
 *     serves the cached file — so the external QR microservice only
 *     needs to be up at the moment a gatepass is CREATED, never
 *     while it's being scanned or viewed.
 *   - Never throws into the caller's request: on failure it logs
 *     and returns null so GatepassService can still complete the
 *     gatepass creation (the gatepass_number itself remains valid
 *     and typeable at the gate even without a QR image).
 */
class QRService
{
    private const CACHE_DIR = '/uploads/qrcodes';
    private const MAX_ATTEMPTS = 3;

    /**
     * Returns the public, web-relative path to the QR PNG
     * (e.g. "/uploads/qrcodes/GP-2026-00042.png"), generating and
     * caching it on first call. Returns null if generation fails
     * after retries — callers should degrade gracefully (show the
     * gatepass number as text instead of a QR image).
     */
    public function getOrCreate(string $gatepassNumber): ?string
    {
        $safeName = $this->safeFileName($gatepassNumber);
        $relative = self::CACHE_DIR . "/{$safeName}.png";
        $absolute = $this->publicPath() . $relative;

        if (is_file($absolute) && filesize($absolute) > 0) {
            return $relative;
        }

        $bytes = $this->fetchWithRetry($gatepassNumber);

        if ($bytes === null) {
            return null;
        }

        $dir = dirname($absolute);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            error_log("QRService: could not create cache dir {$dir}");
            return null;
        }

        if (@file_put_contents($absolute, $bytes) === false) {
            error_log("QRService: could not write {$absolute}");
            return null;
        }

        return $relative;
    }

    /**
     * Force regeneration even if a cached file exists (e.g. after a
     * gatepass number correction).
     */
    public function regenerate(string $gatepassNumber): ?string
    {
        $safeName = $this->safeFileName($gatepassNumber);
        $absolute = $this->publicPath() . self::CACHE_DIR . "/{$safeName}.png";

        if (is_file($absolute)) {
            @unlink($absolute);
        }

        return $this->getOrCreate($gatepassNumber);
    }

    // ── internal ─────────────────────────────────────────────

    private function fetchWithRetry(string $gatepassNumber): ?string
    {
        $base = rtrim((string) config('qr.service_url', ''), '/');

        if ($base === '') {
            error_log('QRService: qr.service_url is not configured.');
            return null;
        }

        $url     = $base . '/generate?code=' . urlencode($gatepassNumber);
        $timeout = (int) config('qr.timeout_seconds', 5);

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $result = $this->fetch($url, $timeout);

            if ($result !== null) {
                return $result;
            }

            if ($attempt < self::MAX_ATTEMPTS) {
                usleep(200_000 * $attempt); // 0.2s, 0.4s backoff
            }
        }

        error_log("QRService: failed to generate QR for '{$gatepassNumber}' after " . self::MAX_ATTEMPTS . ' attempts.');
        return null;
    }

    private function fetch(string $url, int $timeout): ?string
    {
        if (!function_exists('curl_init')) {
            return $this->fetchViaStream($url, $timeout);
        }

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        ]);

        $body       = curl_exec($ch);
        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error      = curl_error($ch);
        curl_close($ch);

        if ($body === false || $error !== '' || $httpStatus !== 200 || $body === '') {
            return null;
        }

        return $body;
    }

    private function fetchViaStream(string $url, int $timeout): ?string
    {
        $context = stream_context_create([
            'http' => ['timeout' => $timeout, 'ignore_errors' => true],
        ]);

        $body = @file_get_contents($url, false, $context);

        if ($body === false || $body === '') {
            return null;
        }

        return $body;
    }

    private function safeFileName(string $gatepassNumber): string
    {
        return preg_replace('/[^A-Za-z0-9_-]/', '_', $gatepassNumber);
    }

    private function publicPath(): string
    {
        return base_path('public');
    }
}
