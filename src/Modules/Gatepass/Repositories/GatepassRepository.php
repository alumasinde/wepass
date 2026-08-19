<?php
declare(strict_types=1);

namespace App\Modules\Gatepass\Repositories;

use App\Core\DB;
use App\Core\SearchBuilder;
use InvalidArgumentException;
use PDO;

/**
 * GatepassRepository — per-database isolation model.
 * No tenant_id column needed; each tenant has their own database.
 */
final class GatepassRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DB::connect();
    }

    // ── CREATE ───────────────────────────────────────────────

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO gatepasses (
                visit_id, gatepass_number, gatepass_type_id,
                status_id, created_by, purpose, is_returnable,
                expected_return_date, needs_approval, department_id
            ) VALUES (
                :visit_id, :gatepass_number, :gatepass_type_id,
                :status_id, :created_by, :purpose, :is_returnable,
                :expected_return_date, :needs_approval, :department_id
            )
        ");

        $stmt->execute([
            ':visit_id'             => isset($data['visit_id']) ? (int) $data['visit_id'] : null,
            ':gatepass_number'      => trim($data['gatepass_number']),
            ':gatepass_type_id'     => isset($data['gatepass_type_id']) ? (int) $data['gatepass_type_id'] : null,
            ':status_id'            => (int) $data['status_id'],
            ':created_by'           => (int) $data['created_by'],
            ':purpose'              => trim($data['purpose']),
            ':is_returnable'        => (int) $data['is_returnable'],
            ':expected_return_date' => $data['expected_return_date'] ?? null,
            ':needs_approval'       => (int) $data['needs_approval'],
            ':department_id'        => (int) $data['department_id'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    // ── UPDATE ───────────────────────────────────────────────

    public function update(int $id, array $data): bool
    {
        $allowed    = ['visit_id', 'gatepass_type_id', 'purpose', 'is_returnable', 'expected_return_date', 'needs_approval'];
        $setClauses = [];
        $bindings   = [':id' => $id];

        foreach ($allowed as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $setClauses[] = "{$field} = :{$field}";
            $bindings[":{$field}"] = match ($field) {
                'visit_id', 'gatepass_type_id' => $data[$field] ? (int) $data[$field] : null,
                'is_returnable', 'needs_approval' => (int) (bool) $data[$field],
                'purpose' => trim((string) $data[$field]),
                'expected_return_date' => $data[$field] ?: null,
                default => $data[$field],
            };
        }

        if (empty($setClauses)) {
            throw new InvalidArgumentException('No updatable fields provided.');
        }

        if (isset($bindings[':purpose']) && $bindings[':purpose'] === '') {
            throw new InvalidArgumentException('Purpose cannot be empty.');
        }

        $stmt = $this->db->prepare("
            UPDATE gatepasses
            SET " . implode(', ', $setClauses) . "
            WHERE id = :id
        ");

        $stmt->execute($bindings);
        return $stmt->rowCount() > 0;
    }

    // ── QR CODE ──────────────────────────────────────────────

    /**
     * Persist the path to the cached QR image (see QRService).
     * Kept as its own method rather than added to update()'s
     * whitelist since it's written by the system, not user input.
     */
    public function updateQrPath(int $id, string $qrCodePath): bool
    {
        $stmt = $this->db->prepare("
            UPDATE gatepasses SET qr_code_path = :qr WHERE id = :id
        ");

        $stmt->execute([':qr' => $qrCodePath, ':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    // ── UPDATE STATUS ────────────────────────────────────────

    public function updateStatus(int $id, int $statusId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE gatepasses SET status_id = :status_id WHERE id = :id
        ");
        $stmt->execute([':status_id' => $statusId, ':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    // ── CHECK IN / OUT ───────────────────────────────────────

    public function checkIn(
        int    $gatepassId,
        int    $userId,
        string $timestamp,
        int    $checkedInStatusId,
        int    $expectedCurrentStatusId
    ): bool {
        // FIX: previously hardcoded `status_id IN (checked_out, approved)`
        // here — a second, independent copy of "which statuses are
        // acceptable" baked into raw SQL, separate from (and now out of
        // sync with) GatepassWorkflow's configurable eligibility rules.
        // The service already decided this status is eligible before
        // ever calling this method; the repository's only job is real
        // optimistic concurrency — confirm the row is still in the
        // exact status it was in when the service read it a moment
        // ago, not re-litigate which statuses are "allowed" with its
        // own separate, stale list.
        $stmt = $this->db->prepare("
            UPDATE gatepasses
            SET actual_in     = :timestamp,
                checked_in_by = :user_id,
                status_id     = :checked_in_status
            WHERE id        = :id
              AND actual_in IS NULL
              AND status_id = :expected_status
        ");

        $stmt->execute([
            ':timestamp'        => $timestamp,
            ':user_id'          => $userId,
            ':checked_in_status'=> $checkedInStatusId,
            ':id'               => $gatepassId,
            ':expected_status'  => $expectedCurrentStatusId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function checkOut(
        int    $gatepassId,
        int    $userId,
        string $timestamp,
        int    $checkedOutStatusId,
        int    $expectedCurrentStatusId
    ): bool {
        // FIX: previously had NO status check at all here — asymmetric
        // with checkIn(), and meant checkOut() had no real concurrency
        // protection whatsoever. Same fix as checkIn(): confirm the
        // row is still in the exact status the service already
        // validated as eligible, rather than trusting a gap between
        // the read and this write to never matter.
        $stmt = $this->db->prepare("
            UPDATE gatepasses
            SET actual_out     = :timestamp,
                checked_out_by = :user_id,
                status_id      = :status_id
            WHERE id          = :id
              AND actual_out IS NULL
              AND status_id   = :expected_status
        ");

        $stmt->execute([
            ':timestamp'       => $timestamp,
            ':user_id'         => $userId,
            ':status_id'       => $checkedOutStatusId,
            ':id'              => $gatepassId,
            ':expected_status' => $expectedCurrentStatusId,
        ]);

        return $stmt->rowCount() > 0;
    }

    // ── FIND ─────────────────────────────────────────────────

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                g.id, g.gatepass_number, g.visit_id,
                g.gatepass_type_id, g.status_id, g.department_id,
                g.checked_in_by, g.checked_out_by,
                g.actual_in, g.actual_out,
                g.created_by, g.created_at, g.purpose,
                g.is_returnable, g.expected_return_date,
                g.actual_return_date, g.is_fully_returned, g.needs_approval,
                s.name  AS status_name,
                s.code  AS status_code,
                gt.name AS gatepass_type_name,
                gt.type_code,
                gt.allowed_actions,
                gt.direction,
                u.first_name AS created_by_first_name,
                u.last_name  AS created_by_last_name
            FROM gatepasses g
            INNER JOIN gatepass_statuses s  ON s.id  = g.status_id
            INNER JOIN gatepass_types    gt ON gt.id = g.gatepass_type_id
            LEFT  JOIN users             u  ON u.id  = g.created_by
            WHERE g.id = :id
              AND g.deleted_at IS NULL
            LIMIT 1
        ");

        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $row['status_code'] = strtoupper($row['status_code'] ?? '');
        $row['type_code']   = strtoupper($row['type_code']   ?? '');

        return $row;
    }

    public function findByNumber(string $number): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                g.*,
                s.name     AS status_name,
                s.code     AS status_code,
                gt.name    AS gatepass_type_name,
                gt.type_code,
                u.first_name,
                u.last_name
            FROM gatepasses g
            INNER JOIN gatepass_statuses s  ON s.id  = g.status_id
            INNER JOIN gatepass_types    gt ON gt.id = g.gatepass_type_id
            LEFT  JOIN users             u  ON u.id  = g.created_by
            WHERE g.gatepass_number = :number
              AND g.deleted_at IS NULL
            LIMIT 1
        ");

        $stmt->execute([':number' => $number]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $row['status_code'] = strtoupper($row['status_code'] ?? '');
        $row['type_code']   = strtoupper($row['type_code']   ?? '');

        return $row;
    }

    // ── FIND ALL ─────────────────────────────────────────────

    public function findAll(): array
    {
        $sql = "
            SELECT
                g.id, g.gatepass_number, g.actual_in, g.actual_out,
                g.is_returnable, g.needs_approval, g.purpose, g.created_at,
                gs.name AS status_name,
                gs.code AS status_code,
                gt.name AS gatepass_type_name,
                gt.type_code,
                gt.allowed_actions,
                gt.direction,
                d.name  AS department_name,
                CONCAT(v.first_name, ' ', v.last_name) AS visitor_name,
                vc.name AS company,
                CONCAT(u.first_name, ' ', u.last_name) AS requested_by
            FROM gatepasses g
            LEFT JOIN visits             vi ON vi.id = g.visit_id
            LEFT JOIN visitors           v  ON v.id  = vi.visitor_id
            LEFT JOIN visitor_companies  vc ON vc.id = v.company_id
            LEFT JOIN gatepass_statuses  gs ON gs.id = g.status_id
            LEFT JOIN gatepass_types     gt ON gt.id = g.gatepass_type_id
            LEFT JOIN departments        d  ON d.id  = g.department_id
            LEFT JOIN users              u  ON u.id  = g.created_by
            WHERE g.deleted_at IS NULL
        ";

        $bindings = [];
        $sql = SearchBuilder::apply($sql, [
            'g.gatepass_number', 'g.purpose', 'd.name',
            'v.first_name', 'v.last_name', 'vc.name',
            'u.first_name', 'u.last_name',
        ], $bindings);

        $sql .= ' ORDER BY g.created_at DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($bindings);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findAllByDepartment(int $departmentId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                g.id, g.gatepass_number, g.actual_in, g.actual_out,
                g.is_returnable, g.needs_approval, g.purpose, g.created_at,
                gs.name AS status_name, gs.code AS status_code,
                gt.name AS gatepass_type_name, gt.type_code,
                gt.allowed_actions,
                gt.direction,
                d.name  AS department_name,
                CONCAT(v.first_name, ' ', v.last_name) AS visitor_name,
                vc.name AS company,
                CONCAT(u.first_name, ' ', u.last_name) AS requested_by
            FROM gatepasses g
            LEFT JOIN visits             vi ON vi.id = g.visit_id
            LEFT JOIN visitors           v  ON v.id  = vi.visitor_id
            LEFT JOIN visitor_companies  vc ON vc.id = v.company_id
            LEFT JOIN gatepass_statuses  gs ON gs.id = g.status_id
            LEFT JOIN gatepass_types     gt ON gt.id = g.gatepass_type_id
            LEFT JOIN departments        d  ON d.id  = g.department_id
            LEFT JOIN users              u  ON u.id  = g.created_by
            WHERE g.department_id = :department_id
              AND g.deleted_at IS NULL
            ORDER BY g.created_at DESC
        ");

        $stmt->execute([':department_id' => $departmentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── WORKFLOW ─────────────────────────────────────────────

  public function getWorkflowIdFromType(int $gatepassTypeId): ?int
{
    $stmt = $this->db->prepare("
        SELECT gt.workflow_id
        FROM   gatepass_types gt
        JOIN   workflows w ON w.id = gt.workflow_id AND w.is_active = 1
        WHERE  gt.id = :gatepass_type_id
        AND    gt.is_active = 1
        LIMIT 1
    ");

    $stmt->execute([':gatepass_type_id' => $gatepassTypeId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? (int) $row['workflow_id'] : null;
}

/**
 * The authoritative source for whether a gatepass needs to go
 * through the approval workflow — set on the gatepass TYPE
 * (Settings → Gatepass Types, admin-only), never trusted from
 * whatever a client sends when creating an individual gatepass.
 * Defaults to true (safe) if the type can't be found at all — an
 * unresolvable type should never result in an auto-approved
 * gatepass.
 */
public function typeRequiresApproval(int $gatepassTypeId): bool
{
    $stmt = $this->db->prepare("
        SELECT requires_approval FROM gatepass_types WHERE id = :id AND is_active = 1 LIMIT 1
    ");
    $stmt->execute([':id' => $gatepassTypeId]);
    $value = $stmt->fetchColumn();

    return $value === false ? true : (bool) $value;
}

    // ── DELETE ───────────────────────────────────────────────

    public function delete(int $id): bool
    {
        // Soft delete — NOT a real DELETE. gatepass_workflow_instances
        // and gatepass_approvals are ON DELETE CASCADE from this
        // table, so a hard delete used to silently wipe out the
        // entire approval/audit history for the record along with it.
        // Setting deleted_at hides the row from every normal read
        // path (see findById/findByNumber/findAll/findAllByDepartment)
        // while keeping it — and its history — intact in the database.
        $stmt = $this->db->prepare("
            UPDATE gatepasses
            SET deleted_at = NOW()
            WHERE id = :id AND deleted_at IS NULL
        ");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }
}
