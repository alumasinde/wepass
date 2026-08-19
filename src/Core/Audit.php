<?php

declare(strict_types=1);

namespace App\Core;

use App\Modules\Audit\Repositories\AuditLogRepository;
use App\Modules\Audit\Services\AuditService;
use Throwable;

class Audit
{
    public static function log(
        string  $action,
        ?string $entityType = null,
        ?int    $entityId   = null,
        ?array  $metadata   = null
    ): void {
        try {
            if (empty($_SESSION['user'])) {
                return;
            }

            $user      = $_SESSION['user'];
            $actorName = self::resolveActorName($user);

            self::service()->log(
                (int) $user['id'],
                $actorName,
                $action,
                $entityType,
                $entityId,
                self::withRequestContext($metadata),
                self::clientIp()
            );
        } catch (Throwable $e) {
            error_log('[Audit] log() failed: ' . $e->getMessage());
        }
    }

    public static function system(
        string  $action,
        ?string $entityType = null,
        ?int    $entityId   = null,
        ?array  $metadata   = null
    ): void {
        try {
            self::service()->log(
                null,
                'System',
                $action,
                $entityType,
                $entityId,
                self::withRequestContext($metadata),
                self::clientIp()
            );
        } catch (Throwable $e) {
            error_log('[Audit] system() failed: ' . $e->getMessage());
        }
    }

    private static function service(): AuditService
    {
        $db   = DB::connect();
        $repo = new AuditLogRepository($db);

        return new AuditService($repo);
    }

    private static function resolveActorName(array $user): string
    {
        if (!empty($user['name'])) {
            return $user['name'];
        }

        $parts = array_filter([
            $user['first_name'] ?? '',
            $user['last_name']  ?? '',
        ]);

        if ($parts) {
            return implode(' ', $parts);
        }

        return $user['email'] ?? ('User #' . ($user['id'] ?? '?'));
    }

    /**
     * Add safe request context to an audit event.
     *
     * Query strings are deliberately excluded. Reset links and other
     * security-sensitive URLs may contain bearer-style tokens; storing
     * those values in an audit table would turn an audit log into a
     * credential leak.
     */
    private static function withRequestContext(?array $metadata): ?array
    {
        $uri = $_SERVER['REQUEST_URI'] ?? null;
        $path = null;

        if (is_string($uri) && $uri !== '') {
            $parsed = parse_url($uri, PHP_URL_PATH);
            $path   = is_string($parsed) && $parsed !== '' ? $parsed : '/';
        }

        $context = array_filter([
            'method' => $_SERVER['REQUEST_METHOD'] ?? null,
            'path'   => $path,
        ]);

        if (empty($metadata) && empty($context)) {
            return null;
        }

        return array_merge($metadata ?? [], ['_request' => $context ?: null]);
    }

    private static function clientIp(): ?string
    {
        // Do not blindly trust arbitrary forwarding headers. The web
        // server should only expose these headers when it is itself a
        // trusted reverse proxy. Fall back to REMOTE_ADDR otherwise.
        $remote = $_SERVER['REMOTE_ADDR'] ?? null;
        $trustedProxy = (string) config('security.trusted_proxy', '');

        if ($trustedProxy !== '' && $remote === $trustedProxy) {
            foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR'] as $key) {
                if (!empty($_SERVER[$key])) {
                    $candidate = trim(explode(',', (string) $_SERVER[$key])[0]);
                    if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                        return $candidate;
                    }
                }
            }
        }

        return is_string($remote) && filter_var($remote, FILTER_VALIDATE_IP)
            ? $remote
            : null;
    }
}
