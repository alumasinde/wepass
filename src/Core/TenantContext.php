<?php

namespace App\Core;

/**
 * TenantContext — what kind of request is this, resolved once
 * during bootstrap from the Host header (dynamic-domain mode) or
 * from static config.ini (legacy per-deployment mode).
 *
 * Three mutually exclusive states per request:
 *   - Public host   — the bare platform domain (gpms.co.ke) or www.
 *                      Reserved for the future marketing site.
 *   - Admin host     — the platform's admin subdomain (or, in legacy
 *                      mode, a deployment with a blank [tenant] code).
 *                      Only /master/* routes make sense here.
 *   - Tenant resolved — a specific client's subdomain or custom
 *                      domain matched an active row in
 *                      glee_master.tenants. Normal app routes apply.
 *
 * If none of the three are set, the host didn't match anything —
 * bootstrap renders a 404 before the router ever runs.
 */
final class TenantContext
{
    private static bool $isAdminHost = false;
    private static bool $isPublicHost = false;
    private static ?array $tenant = null;

    public static function setAdminHost(): void
    {
        self::$isAdminHost = true;
    }

    public static function setPublicHost(): void
    {
        self::$isPublicHost = true;
    }

    public static function setTenant(array $tenant): void
    {
        self::$tenant = $tenant;
    }

    public static function isAdminHost(): bool
    {
        return self::$isAdminHost;
    }

    public static function isPublicHost(): bool
    {
        return self::$isPublicHost;
    }

    public static function hasTenant(): bool
    {
        return self::$tenant !== null;
    }

    public static function tenant(): ?array
    {
        return self::$tenant;
    }

    public static function isResolved(): bool
    {
        return self::$isAdminHost || self::$isPublicHost || self::$tenant !== null;
    }
}
