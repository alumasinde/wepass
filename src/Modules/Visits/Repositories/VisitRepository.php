<?php

declare(strict_types=1);

namespace App\Modules\Visits\Repositories;

use App\Core\DB;
use PDO;
use App\Core\SearchBuilder;
use RuntimeException;

final class VisitRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DB::connect();
    }

    // ── BASE SELECT ──────────────────────────────────────────

    private function baseSelect(): string
    {
        return "
            SELECT
                v.*,
                CONCAT(vis.first_name, ' ', vis.last_name) AS visitor_name,
                CONCAT(u.first_name,   ' ', u.last_name)   AS host_name,
                d.name   AS department_name,
                vc.name  AS visitor_company,
                s.name   AS status_name,
                vb.badge_code,
                vb.printed_at  AS badge_issued_at,
                vb.returned_at AS badge_returned_at,
                vb.is_active   AS badge_active
            FROM visits v
            INNER JOIN visitors        vis ON vis.id = v.visitor_id
            LEFT  JOIN users           u   ON u.id   = v.host_user_id
            LEFT  JOIN departments     d   ON d.id   = v.department_id
            LEFT  JOIN visitor_companies vc ON vc.id = vis.company_id
            LEFT  JOIN visit_statuses  s   ON s.id   = v.visit_status_id
            LEFT  JOIN visit_badges    vb  ON vb.id  = (
                SELECT id FROM visit_badges
                WHERE visit_id = v.id
                ORDER BY printed_at DESC
                LIMIT 1
            )
        ";
    }

    // FIX: was `int int $visitId`
    public function find(int $visitId): ?array
    {
        $sql  = $this->baseSelect() . " WHERE v.id = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $visitId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // FIX: removed int $tenantId param — no longer needed
    public function all(): array
    {
        $sql  = $this->baseSelect() . " ORDER BY v.created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── CREATE ───────────────────────────────────────────────

    public function create(array $data): int
    {
        // FIX: SQL had ::department_id (double colon) instead of :department_id
        // FIX: removed :tenant_id binding — column dropped in per-database schema
        $sql = "
            INSERT INTO visits (
                department_id, visitor_id, host_user_id, visit_type_id,
                visit_status_id, purpose, contract_reference, escort_required,
                expected_in, expected_out,
                created_by, created_at, updated_at
            ) VALUES (
                :department_id, :visitor_id, :host_user_id, :visit_type_id,
                :visit_status_id, :purpose, :contract_reference, :escort_required,
                :expected_in, :expected_out,
                :created_by, NOW(), NOW()
            )
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':department_id',   $data['department_id'],   PDO::PARAM_INT);
        $stmt->bindValue(':visitor_id',      $data['visitor_id'],      PDO::PARAM_INT);
        $stmt->bindValue(':host_user_id',    $data['host_user_id'],    PDO::PARAM_INT);
        $stmt->bindValue(':visit_type_id',   $data['visit_type_id'],   PDO::PARAM_INT);
        $stmt->bindValue(':visit_status_id', $data['visit_status_id'], PDO::PARAM_INT);
        $stmt->bindValue(':purpose',         $data['purpose']);
        $stmt->bindValue(':contract_reference', $data['contract_reference'] ?? null);
        $stmt->bindValue(':escort_required', !empty($data['escort_required']) ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':expected_in',     $data['expected_in']);
        $stmt->bindValue(':expected_out',    $data['expected_out']);
        $stmt->bindValue(':created_by',      $data['created_by'],      PDO::PARAM_INT);
        $stmt->execute();

        return (int) $this->db->lastInsertId();
    }

    // FIX: was `int int $visitId`
    public function checkIn(int $visitId, int $checkedInStatusId): void
    {
        $sql  = "
            UPDATE visits
            SET checkin_time    = NOW(),
                visit_status_id = :status_id,
                updated_at      = NOW()
            WHERE id          = :visit_id
              AND checkin_time IS NULL
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':visit_id',  $visitId,           PDO::PARAM_INT);
        $stmt->bindValue(':status_id', $checkedInStatusId, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('Visit cannot be checked in.');
        }
    }

    // FIX: was `int int $visitId`
    public function checkOut(int $visitId, int $checkedOutStatusId): void
    {
        $sql  = "
            UPDATE visits
            SET checkout_time   = NOW(),
                visit_status_id = :status_id,
                updated_at      = NOW()
            WHERE id            = :visit_id
              AND checkin_time  IS NOT NULL
              AND checkout_time IS NULL
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':visit_id',  $visitId,            PDO::PARAM_INT);
        $stmt->bindValue(':status_id', $checkedOutStatusId, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('Visit cannot be checked out.');
        }
    }

    // FIX: removed int $tenantId param
    /*public function getActiveVisits(): array
    {
        $sql  = $this->baseSelect() . " WHERE v.checkout_time IS NULL ORDER BY v.created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
        */

    public function getActiveVisits(): array
{
    $sql = $this->baseSelect() . "
        WHERE v.checkout_time IS NULL
    ";

    $bindings = [];

    // Apply search across relevant visit fields
    $sql = SearchBuilder::apply($sql, [
        'vis.first_name',
        'vis.last_name',
        'u.first_name',
        'u.last_name',
        'vc.name',
        'd.name',
        'v.purpose'
    ], $bindings);

    $sql .= " ORDER BY v.created_at DESC";

    $stmt = $this->db->prepare($sql);
    $stmt->execute($bindings);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

    // FIX: removed int $tenantId param
    public function getDepartments(): array
    {
        return $this->db->query("
            SELECT id, name, code FROM departments WHERE is_active = 1 ORDER BY name ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    // FIX: removed int $tenantId param
    public function getHosts(): array
    {
        return $this->db->query("
            SELECT id, first_name, last_name, department_id
            FROM users WHERE is_active = 1 ORDER BY first_name ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    // FIX: removed int $tenantId param
    public function getVisitTypes(): array
    {
        return $this->db->query("SELECT id, name FROM visit_types ORDER BY name ASC")
                        ->fetchAll(PDO::FETCH_ASSOC);
    }

    // FIX: was `int int $visitorId`
    public function findActiveByVisitor(int $visitorId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM visits
            WHERE visitor_id    = :visitor_id
              AND checkin_time  IS NOT NULL
              AND checkout_time IS NULL
            LIMIT 1
        ");
        $stmt->bindValue(':visitor_id', $visitorId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
