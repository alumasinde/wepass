<?php
declare(strict_types=1);

namespace App\Modules\Visitors\Repositories;

use App\Core\DB;
use App\Core\SearchBuilder;
use PDO;

/**
 * VisitorRepository — per-database isolation model.
 */
final class VisitorRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DB::connect();
    }

	public function find(int $id): ?array
{
    $stmt = $this->db->prepare("
        SELECT v.*, it.name AS id_type_name, vc.name AS company_name
        FROM visitors v
        LEFT JOIN identification_types it ON it.id = v.id_type_id
        LEFT JOIN visitor_companies    vc ON vc.id = v.company_id
        WHERE v.id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

    public function findByIdNumber(string $idNumber): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM visitors
            WHERE UPPER(TRIM(id_number)) = UPPER(TRIM(:id_number))
            LIMIT 1
        ");
        $stmt->execute([':id_number' => $idNumber]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO visitors (
                first_name, last_name, id_type_id, id_number,
                phone, email, company_id, notes, is_blacklisted, risk_score,
                created_by, created_at
            ) VALUES (
                :first_name, :last_name, :id_type_id, :id_number,
                :phone, :email, :company_id, :notes, :is_blacklisted, :risk_score,
                :created_by, NOW()
            )
        ");

        $stmt->execute([
            ':first_name'    => trim($data['first_name']),
            ':last_name'     => trim($data['last_name']),
            ':id_type_id'    => $data['id_type_id'] ? (int) $data['id_type_id'] : null,
            ':id_number'     => trim($data['id_number'] ?? ''),
            ':phone'         => trim($data['phone'] ?? ''),
            ':email'         => trim($data['email'] ?? ''),
            ':company_id'    => $data['company_id'] ? (int) $data['company_id'] : null,
            ':notes'         => $data['notes'] ?? null,
            ':is_blacklisted'=> 0,
            ':risk_score'    => (int) ($data['risk_score'] ?? 0),
            ':created_by'    => $data['created_by'] ? (int) $data['created_by'] : 0,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE visitors
            SET first_name = :first_name,
                last_name  = :last_name,
                id_type_id = :id_type_id,
                id_number  = :id_number,
                phone      = :phone,
                email      = :email,
                company_id = :company_id,
                notes      = :notes
            WHERE id = :id
        ");

        $stmt->execute([
            ':first_name' => trim($data['first_name']),
            ':last_name'  => trim($data['last_name']),
            ':id_type_id' => $data['id_type_id'] ? (int) $data['id_type_id'] : null,
            ':id_number'  => trim($data['id_number'] ?? ''),
            ':phone'      => trim($data['phone'] ?? ''),
            ':email'      => trim($data['email'] ?? ''),
            ':company_id' => $data['company_id'] ? (int) $data['company_id'] : null,
            ':notes'      => $data['notes'] ?? null,
            ':id'         => $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function updateBlacklist(int $id, bool $blacklisted): bool
    {
        $stmt = $this->db->prepare("
            UPDATE visitors SET is_blacklisted = :flag WHERE id = :id
        ");
        $stmt->execute([':flag' => $blacklisted ? 1 : 0, ':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function updateRiskScore(int $id, int $score): bool
    {
        $stmt = $this->db->prepare("
            UPDATE visitors SET risk_score = :score WHERE id = :id
        ");
        $stmt->execute([':score' => $score, ':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function findAll(): array
    {
        $sql = "
            SELECT
                v.id, v.first_name, v.last_name, v.id_number,
                v.phone, v.email, v.is_blacklisted, v.risk_score,
                it.name  AS id_type_name,
                vc.name  AS company_name,
                COUNT(DISTINCT vis.id) AS total_visits
            FROM visitors v
            LEFT JOIN identification_types          it  ON it.id  = v.id_type_id
            LEFT JOIN visitor_companies vc  ON vc.id  = v.company_id
            LEFT JOIN visits            vis ON vis.visitor_id = v.id
            WHERE 1=1
        ";

        $bindings = [];
        $sql = SearchBuilder::apply($sql, [
            'v.first_name', 'v.last_name', 'v.id_number', 'v.phone', 'vc.name',
        ], $bindings);

        $sql .= ' GROUP BY v.id ORDER BY v.created_at DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($bindings);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findWithVisits(int $id): ?array
    {
        $visitor = $this->find($id);
        if (!$visitor) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT
                vis.id,
                d.name   AS department_name,
                s.name   AS status_name,
                vis.checkin_time,
                vis.checkout_time
            FROM visits vis
            LEFT JOIN departments  d ON d.id = vis.department_id
            LEFT JOIN visit_statuses s ON s.id = vis.visit_status_id
            WHERE vis.visitor_id = :visitor_id
            ORDER BY vis.checkin_time DESC
        ");

        $stmt->execute([':visitor_id' => $id]);
        $visitor['visits'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $visitor;
    }

    public function getIdTypes(): array
    {
        return DB::select("SELECT id, name FROM identification_types ORDER BY name ASC");
    }

    public function getCompanies(): array
    {
        return DB::select("SELECT id, name FROM visitor_companies ORDER BY name ASC");
    }

    /**
     * Resolves the "or enter a new company name" field on the
     * visitor create/edit forms — that field was rendered but never
     * actually read anywhere, so typing a new company name and
     * submitting silently did nothing. `name` is unique, so this is
     * safe to call even if two people happen to type the same new
     * company name around the same time — the second call just
     * finds the first one's row instead of erroring.
     */
    public function getOrCreateCompany(string $name): int
    {
        $name = trim($name);

        $stmt = $this->db->prepare("SELECT id FROM visitor_companies WHERE name = :name LIMIT 1");
        $stmt->execute([':name' => $name]);
        $existing = $stmt->fetchColumn();

        if ($existing !== false) {
            return (int) $existing;
        }

        $stmt = $this->db->prepare("
            INSERT INTO visitor_companies (name, created_at) VALUES (:name, NOW())
            ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)
        ");
        $stmt->execute([':name' => $name]);

        return (int) $this->db->lastInsertId();
    }
}
