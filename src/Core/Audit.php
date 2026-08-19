<?php

namespace App\Core;

use App\Core\DB;
use App\Modules\Audit\Repositories\AuditLogRepository;
use App\Modules\Audit\Services\AuditService;
use Throwable;

class Audit
{
    /**
     * Log an action performed by the currently authenticated user.
     *
     * Per-database isolation: no tenant_id column needed.
     * The connection itself is already scoped to the tenant database.
     *
     * @param string      $action
     * @param string|null $entityType
     * @param int|null    $entityId
     * @param array|null  $metadata   Optional extra context.
     *                                Use ['target_label' => '…'] to fill the {target}
     *                                placeholder in the human-readable message.
     */
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

    /**
     * Log a system-initiated action (no human actor, e.g. scheduled jobs).
     *
     * Per-database isolation: tenant_id removed. The DB connection is already
     * scoped to the correct tenant database — no runtime tenant filtering needed.
     *
     * @param string      $action
     * @param string|null $entityType
     * @param int|null    $entityId
     * @param array|null  $metadata
     */
    public static function system(
        string  $action,
        ?string $entityType = null,
        ?int    $entityId   = null,
        ?array  $metadata   = null
    ): void {
        try {
            self::service()->log(
                null,        // no user ID
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

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private static function service(): AuditService
    {
        $db   = DB::connect();
        $repo = new AuditLogRepository($db);

        return new AuditService($repo);
    }

    /**
     * Build a readable actor name from the session user array.
     * Falls back gracefully if name fields are absent.
     */
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

    private static function withRequestContext(?array $metadata): ?array
    {
        $context = array_filter([
            'method' => $_SERVER['REQUEST_METHOD'] ?? null,
            'url'    => $_SERVER['REQUEST_URI']    ?? null,
        ]);

        if (empty($metadata) && empty($context)) {
            return null;
        }

        return array_merge($metadata ?? [], ['_request' => $context ?: null]);
    }

    private static function clientIp(): ?string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
            if (!empty($_SERVER[$key])) {
                return explode(',', $_SERVER[$key])[0];
            }
        }

        return null;
    }
}
