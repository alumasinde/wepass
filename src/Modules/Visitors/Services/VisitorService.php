<?php

declare(strict_types=1);

namespace App\Modules\Visitors\Services;

use App\Core\Audit;
use App\Core\DB;
use App\Modules\Visitors\Repositories\VisitorRepository;
use App\Modules\Visitors\Services\RiskEngine;
use App\Modules\Visitors\DTOs\VisitorDTO;
use PDO;
use Exception;

final class VisitorService
{
    private VisitorRepository $repo;
    private RiskEngine        $riskEngine;
    private PDO               $db;

    public function __construct()
    {
        $this->repo       = new VisitorRepository();
        $this->db         = DB::connect();
        $this->riskEngine = new RiskEngine();
    }


    // ── CREATE ───────────────────────────────────────────────

    public function create(VisitorDTO $dto): int
    {
        if ($dto->id_number) {
            // FIX: was $dto->$dto->id_number — removed erroneous self-dereference
            $existing = $this->repo->findByIdNumber($dto->id_number);

            if ($existing) {
                throw new Exception('A visitor with this ID number already exists.');
            }
        }

        $this->db->beginTransaction();

        try {
            $riskScore = $this->riskEngine->calculate(
                $dto->id_number,
                $dto->phone,
                false,
                0
            );

            $visitorId = $this->repo->create([
                'first_name'     => $dto->first_name,
                'last_name'      => $dto->last_name,
                'id_type_id'     => $dto->id_type_id,
                'id_number'      => $dto->id_number,
                'phone'          => $dto->phone,
                'email'          => $dto->email,
                'company_id'     => $dto->company_id,
                'notes'          => $dto->notes,
                'created_by'     => $dto->created_by,
                'risk_score'     => $riskScore,
                'is_blacklisted' => 0,
            ]);

            Audit::log('visitor.created', 'visitor', $visitorId, [
                'name' => trim($dto->first_name . ' ' . $dto->last_name),
            ]);

            $this->db->commit();
            return $visitorId;

        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // ── UPDATE ───────────────────────────────────────────────

    // FIX: was `int int $visitorId`
    public function update(int $visitorId, VisitorDTO $dto): void
    {
        $this->db->beginTransaction();

        try {
            $existing = $this->repo->find($visitorId);

            if (!$existing) {
                throw new Exception('Visitor not found.');
            }

            if ($dto->id_number) {
                $existingById = $this->repo->findByIdNumber($dto->id_number);

                if ($existingById && (int) $existingById['id'] !== $visitorId) {
                    throw new Exception('Another visitor already uses this ID number.');
                }
            }

            $riskScore = $this->riskEngine->calculate(
                $dto->id_number,
                $dto->phone,
                (bool) $existing['is_blacklisted'],
                0
            );

            $this->repo->update($visitorId, [
                'first_name' => $dto->first_name,
                'last_name'  => $dto->last_name,
                'id_type_id' => $dto->id_type_id,
                'id_number'  => $dto->id_number,
                'phone'      => $dto->phone,
                'email'      => $dto->email,
                'company_id' => $dto->company_id,
                'notes'      => $dto->notes,
                'risk_score' => $riskScore,
            ]);

            Audit::log('visitor.updated', 'visitor', $visitorId);

            $this->db->commit();

        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // ── BLACKLIST ────────────────────────────────────────────

    // FIX: was `int int $visitorId`
    public function blacklist(int $visitorId, int $riskScore = 100): void
    {
        if (!$this->repo->find($visitorId)) {
            throw new Exception('Visitor not found.');
        }

        $this->repo->updateBlacklist($visitorId, true);
        $this->repo->updateRiskScore($visitorId, $riskScore);

        Audit::log('visitor.blacklisted', 'visitor', $visitorId);
    }

    // FIX: was `int int $visitorId`
    public function unblacklist(int $visitorId): void
    {
        if (!$this->repo->find($visitorId)) {
            throw new Exception('Visitor not found.');
        }

        $this->repo->updateBlacklist($visitorId, false);

        Audit::log('visitor.unblacklisted', 'visitor', $visitorId);
    }

    // ── FIND / LIST ──────────────────────────────────────────

    // FIX: was `int int $visitorId`
    public function find(int $visitorId): ?array
    {
        return $this->repo->find($visitorId);
    }

    // FIX: removed int $tenantId param — no longer needed
    public function list(): array
    {
        return $this->repo->findAll();
    }

    // FIX: was `int int $visitorId`
    public function findWithVisits(int $visitorId): ?array
    {
        return $this->repo->findWithVisits($visitorId);
    }

    // FIX: removed int $tenantId params
    public function getIdentificationTypes(): array
    {
        return $this->repo->getIdTypes();
    }

    // FIX: removed int $tenantId params
    public function getCompanies(): array
    {
        return $this->repo->getCompanies();
    }

    public function getOrCreateCompany(string $name): int
    {
        return $this->repo->getOrCreateCompany($name);
    }
}
