<?php

namespace App\Core;

/**
 * Tenant — per-database isolation model.
 *
 * With the new schema each tenant has their own database.
 * tenant_id is no longer a filter column in SQL queries.
 *
 * This class holds tenant identity metadata that is
 * loaded once at login from glee_master, then stored in
 * the session for the lifetime of the authenticated session.
 *
 * Repositories must NOT pass tenant_id to SQL — isolation
 * is guaranteed at the DB connection level via config.ini.
 */
class Tenant
{
    // ── Identity (loaded from session after login) ───────────

    public static function code(): ?string
    {
        return $_SESSION['tenant']['code'] ?? null;
    }

    public static function name(): ?string
    {
        return $_SESSION['tenant']['name'] ?? null;
    }

    public static function plan(): ?string
    {
        return $_SESSION['tenant']['plan'] ?? null;
    }

    public static function logo(): ?string
    {
        return $_SESSION['tenant']['logo'] ?? null;
    }

    /**
     * Hydrate tenant context into the session.
     * Called once at login.
     */
    public static function set(array $tenantData): void
    {
        $_SESSION['tenant'] = [
            'code' => $tenantData['code'] ?? null,
            'name' => $tenantData['name'] ?? null,
            'plan' => $tenantData['plan'] ?? null,
            'logo' => $tenantData['logo'] ?? null,
        ];
    }

    /**
     * Clear tenant from session (logout).
     */
    public static function clear(): void
    {
        unset($_SESSION['tenant']);
    }

    /**
     * BC shim — returns 1 always (legacy callers expected an int).
     * Remove usages of Tenant::id() and Tenant::require() as they
     * are no longer meaningful in the per-database model.
     *
     * @deprecated Use per-database isolation instead.
     */
    public static function id(): int
    {
        return 1;
    }

    /**
     * @deprecated Use per-database isolation instead.
     */
    public static function require(): int
    {
        return 1;
    }
}
