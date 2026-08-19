<?php

declare(strict_types=1);

namespace App\Modules\Visits\Services;

use App\Core\Audit;
use App\Core\DB;
use App\Modules\Visits\Repositories\VisitRepository;
use App\Modules\Visits\DTOs\VisitDTO;
use App\Modules\Badges\Repositories\BadgeRepository;
use App\Modules\Visitors\Repositories\VisitorRepository;
use PDO;
use RuntimeException;
use Throwable;

final class VisitService
{
    private const STATUS_PENDING = 1;
    private const STATUS_CHECKED_IN = 2;
    private const STATUS_COMPLETED = 3;

    private VisitRepository $visitRepo;
    private BadgeRepository $badgeRepo;
    private VisitorRepository $visitorRepo;
    private PDO $db;

    public function __construct()
    {
        $this->visitRepo = new VisitRepository();
        $this->badgeRepo = new BadgeRepository();
        $this->visitorRepo = new VisitorRepository();
        $this->db = DB::connect();
    }

    public function find(int $visitId): ?array
    {
        return $this->visitRepo->find($visitId);
    }

    public function create(VisitDTO $dto): int
    {
        $visitor = $this->visitorRepo->find($dto->visitor_id);
        if (!$visitor) {
            throw new RuntimeException('Visitor not found.');
        }

        $activeVisit = $this->visitRepo->findActiveByVisitor($dto->visitor_id);
        if ($activeVisit) {
            throw new RuntimeException('Visitor already has an active visit.');
        }

        $this->db->beginTransaction();
        try {
            $visitId = $this->visitRepo->create([
                'visitor_id' => $dto->visitor_id,
                'department_id' => $dto->department_id,
                'host_user_id' => $dto->host_user_id,
                'visit_type_id' => $dto->visit_type_id,
                'visit_status_id' => self::STATUS_PENDING,
                'purpose' => $dto->purpose,
                'contract_reference' => $dto->contract_reference,
                'escort_required' => $dto->escort_required,
                'expected_in' => $dto->expected_in,
                'expected_out' => $dto->expected_out,
                'created_by' => $dto->created_by,
            ]);
            Audit::log('visit.created', 'visit', $visitId);
            $this->db->commit();
            return $visitId;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function checkIn(int $visitId): void
    {
        $visit = $this->visitRepo->find($visitId);
        if (!$visit) throw new RuntimeException('Visit not found.');
        if ($visit['checkin_time']) throw new RuntimeException('Visitor already checked in.');
        if ($visit['checkout_time']) throw new RuntimeException('Visit already completed.');

        $visitor = $this->visitorRepo->find((int) $visit['visitor_id']);
        if (!$visitor) throw new RuntimeException('Visitor not found.');
        if ((int) $visitor['is_blacklisted'] === 1) throw new RuntimeException('Blacklisted visitors cannot check in.');

        $this->db->beginTransaction();
        try {
            $this->visitRepo->checkIn($visitId, self::STATUS_CHECKED_IN);
            Audit::log('visit.checkin', 'visit', $visitId);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function checkOut(int $visitId): void
    {
        $visit = $this->visitRepo->find($visitId);
        if (!$visit) throw new RuntimeException('Visit not found.');
        if (!$visit['checkin_time']) throw new RuntimeException('Visitor not checked in.');
        if ($visit['checkout_time']) throw new RuntimeException('Visitor already checked out.');
        if ($this->badgeRepo->hasActiveBadge($visitId)) throw new RuntimeException('Badge must be returned before checkout.');

        $this->db->beginTransaction();
        try {
            $this->visitRepo->checkOut($visitId, self::STATUS_COMPLETED);
            $this->badgeRepo->returnActiveBadge($visitId);
            Audit::log('visit.checkout', 'visit', $visitId);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function getVisitor(int $visitorId): ?array { return $this->visitorRepo->find($visitorId); }
    public function activeVisits(): array { return $this->visitRepo->getActiveVisits(); }
    public function getDepartments(): array { return $this->visitRepo->getDepartments(); }
    public function getHosts(): array { return $this->visitRepo->getHosts(); }
    public function getVisitTypes(): array { return $this->visitRepo->getVisitTypes(); }
}
