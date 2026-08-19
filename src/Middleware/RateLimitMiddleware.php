<?php

namespace App\Middleware;

use App\Core\Request;
use App\Core\RateLimiter;
use App\Core\Response;

/**
 * RateLimitMiddleware — attach to any route to cap how often it can
 * be hit by the same client within a time window.
 *
 * Route usage (array form lets you pass params after the class):
 *
 *   $router->post('/login', [LoginController::class, 'store'],
 *       [[RateLimitMiddleware::class, 'login', 10, 300]] // 10 hits / 5 min
 *   );
 *
 *   $router->post('/gatepasses/scan', [GateScanController::class, 'process'],
 *       [AuthMiddleware::class, [RateLimitMiddleware::class, 'gate-scan', 30, 60]]
 *   );
 *
 * The limiter key is namespaced by both the route's $bucket name and
 * the caller's identity (logged-in user id if available, else IP),
 * so one flooding client can't exhaust another client's quota.
 */
class RateLimitMiddleware
{
    public function handle(Request $request, string $bucket, int $maxAttempts = 60, int $decaySeconds = 60): void
    {
        $key = $bucket . ':' . $this->clientIdentity();

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = max(1, RateLimiter::availableIn($key));

            if (!headers_sent()) {
                header('Retry-After: ' . $retryAfter);
            }

            Response::abort(429, "Too many requests. Please try again in {$retryAfter} seconds.");
        }

        RateLimiter::hit($key, $decaySeconds);
    }

    private function clientIdentity(): string
    {
        if (!empty($_SESSION['user']['id'])) {
            return 'user:' . $_SESSION['user']['id'];
        }

        return 'ip:' . $this->clientIp();
    }

    /**
     * Only trusts X-Forwarded-For / X-Client-IP when the request's
     * REMOTE_ADDR (the actual TCP peer — this cannot be spoofed by
     * the client itself) matches an entry in config.ini
     * [security] trusted_proxies. Left blank by default, which means
     * "trust nothing, always use REMOTE_ADDR" — the safe default for
     * a deployment that isn't behind a known reverse proxy/load
     * balancer/CDN. Without this check, ANY client can set their own
     * X-Forwarded-For header to spoof another user's IP (exhausting
     * that IP's quota) or rotate a fake one on every request to
     * dodge their own rate limit entirely.
     *
     * Set trusted_proxies to your actual proxy's IP(s) once you know
     * your hosting setup, e.g.:
     *   trusted_proxies = "127.0.0.1,10.0.0.5"
     * or a CIDR range:
     *   trusted_proxies = "10.0.0.0/8"
     */
    private function clientIp(): string
    {
        $remoteAddr = trim((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'));

        if (!$this->isTrustedProxy($remoteAddr)) {
            return $remoteAddr;
        }

        foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP'] as $header) {
            if (!empty($_SERVER[$header])) {
                // Leftmost entry is the original client by convention;
                // everything after it was appended by proxies in the chain.
                $ip = trim(explode(',', $_SERVER[$header])[0]);
                if ($ip !== '') {
                    return $ip;
                }
            }
        }

        return $remoteAddr;
    }

    private function isTrustedProxy(string $ip): bool
    {
        $configured = (string) config('security.trusted_proxies', '');
        if (trim($configured) === '') {
            return false;
        }

        $trusted = array_filter(array_map('trim', explode(',', $configured)));

        foreach ($trusted as $entry) {
            if ($entry === $ip) {
                return true;
            }
            if (str_contains($entry, '/') && $this->ipInCidr($ip, $entry)) {
                return true;
            }
        }

        return false;
    }

    /**
     * IPv4 CIDR matching only. Plain-IP entries in trusted_proxies
     * still work for IPv6 (exact match, handled above) — this just
     * doesn't do IPv6 range matching.
     */
    private function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = array_pad(explode('/', $cidr, 2), 2, '32');
        $bits = (int) $bits;

        $ipLong     = ip2long($ip);
        $subnetLong = ip2long($subnet);

        if ($ipLong === false || $subnetLong === false || $bits < 0 || $bits > 32) {
            return false;
        }

        $mask = $bits === 0 ? 0 : (~0 << (32 - $bits)) & 0xFFFFFFFF;

        return ($ipLong & $mask) === ($subnetLong & $mask);
    }
}
