<?php

namespace App\Modules\Tenant\Repositories;

use App\Core\DB;
use PDO;

/**
 * TenantRepository — the tenant *registry*, stored in glee_master.
 *
 * Always goes through DB::master(), never DB::connect(). The
 * `tenants` table lives only in glee_master; a tenant's own
 * database (reached via DB::connect() on a tenant install) never
 * has this table. Querying it through DB::connect() (the previous
 * behavior) would fail on any real tenant deployment.
 */
class TenantRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DB::master();
    }

    public function findById(int $tenantId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, name, code, db_name, db_username, connection_string, plan, logo, email, phone, country, is_active, custom_domain
            FROM tenants
            WHERE id = :tenant_id
              AND is_active = 1
            LIMIT 1
        ");

        $stmt->execute([':tenant_id' => $tenantId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function updateLogo(int $tenantId, string $logo): bool
    {
        $stmt = $this->db->prepare("
            UPDATE tenants SET logo = :logo WHERE id = :id
        ");

        return $stmt->execute([':logo' => $logo, ':id' => $tenantId]);
    }

    /**
     * Settings → Company self-service update. Deliberately does NOT
     * accept `code` — that's the tenant's actual subdomain
     * (code.albatechsolutions.co.ke); letting a tenant admin casually
     * rename it here would break their own login URL and any
     * bookmarks the moment they saved the form. Changing it (if ever
     * genuinely needed) stays a master-admin action, not self-service.
     */
    public function updateCompanyDetails(int $tenantId, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE tenants
            SET name = :name, email = :email, phone = :phone, country = :country
            WHERE id = :id
        ");

        return $stmt->execute([
            ':name'    => $data['name'],
            ':email'   => $data['email'] ?? '',
            ':phone'   => $data['phone'] ?: null,
            ':country' => $data['country'] ?: null,
            ':id'      => $tenantId,
        ]);
    }

    /**
     * Overwrites a tenant's stored connection string. $encrypted is
     * already-encrypted (ConnectionCrypto::encrypt() output) — this
     * method never sees or handles plaintext credentials itself, it
     * just stores whatever opaque string it's given. Pass null to
     * clear it (reverts that tenant to the legacy db_name/db_username
     * + shared [database] credential resolution).
     */
    public function updateConnectionString(int $tenantId, ?string $encrypted): bool
    {
        $stmt = $this->db->prepare("
            UPDATE tenants SET connection_string = :cs WHERE id = :id
        ");

        return $stmt->execute([':cs' => $encrypted, ':id' => $tenantId]);
    }

    public function findActiveByCode(string $code): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, name, code, db_name, db_username, connection_string, plan, logo, email, phone, country, is_active, custom_domain
            FROM tenants
            WHERE code = :code
              AND is_active = 1
            LIMIT 1
        ");

        $stmt->execute([':code' => $code]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findActiveByCustomDomain(string $domain): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, name, code, db_name, db_username, connection_string, plan, logo, email, phone, country, is_active, custom_domain
            FROM tenants
            WHERE custom_domain = :domain
              AND is_active = 1
            LIMIT 1
        ");

        $stmt->execute([':domain' => $domain]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Resolves a tenant from the request Host header, for
     * dynamic-domain mode (config.ini [platform] base_domain set).
     *
     *   - "{code}.{$baseDomain}"  -> lookup by code (subdomain)
     *   - anything else           -> lookup by custom_domain (exact)
     *
     * $host and $baseDomain must already be lowercased by the caller.
     */
    public function findForHost(string $host, string $baseDomain): ?array
    {
        $suffix = '.' . $baseDomain;

        if ($baseDomain !== '' && str_ends_with($host, $suffix)) {
            $code = substr($host, 0, -strlen($suffix));

            // Reject anything but a single-label subdomain
            // (client1.gpms.co.ke, not a.b.gpms.co.ke) — keeps the
            // code -> lookup mapping unambiguous.
            if ($code === '' || str_contains($code, '.')) {
                return null;
            }

            return $this->findActiveByCode($code);
        }

        return $this->findActiveByCustomDomain($host);
    }

    public function codeExists(string $code): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM tenants WHERE code = :code LIMIT 1");
        $stmt->execute([':code' => $code]);

        return (bool) $stmt->fetchColumn();
    }

    public function customDomainExists(string $domain): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM tenants WHERE custom_domain = :domain LIMIT 1");
        $stmt->execute([':domain' => $domain]);

        return (bool) $stmt->fetchColumn();
    }

    /* Fetch logo/name/email to display in the login page */
    public function findTenantLogo(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT logo AS tenant_logo, name AS tenant_name, email FROM tenants WHERE id = :id"
        );

        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    /**
     * All tenants, newest first — for the super-admin tenant list.
     */
    public function all(): array
    {
        return $this->db->query("
            SELECT id, name, code, db_name, db_username, connection_string, plan, logo, email, phone, country, is_active, custom_domain, created_at
            FROM tenants
            ORDER BY created_at DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Register a newly-provisioned tenant in the master registry.
     * Called AFTER the tenant's own database has already been
     * created and seeded (see TenantService::provisionTenant()) —
     * this only writes the registry row.
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO tenants (name, code, db_name, db_username, connection_string, plan, email, custom_domain, is_active)
            VALUES (:name, :code, :db_name, :db_username, :connection_string, :plan, :email, :custom_domain, 1)
        ");

        $stmt->execute([
            ':name'              => $data['name'],
            ':code'              => $data['code'],
            ':db_name'           => $data['db_name'],
            ':db_username'       => $data['db_username'] ?? null,
            ':connection_string' => $data['connection_string'] ?? null,
            ':plan'              => $data['plan'] ?? 'starter',
            ':email'             => $data['email'] ?? '',
            ':custom_domain'     => $data['custom_domain'] ?: null,
        ]);

        return (int) $this->db->lastInsertId();
    }
}