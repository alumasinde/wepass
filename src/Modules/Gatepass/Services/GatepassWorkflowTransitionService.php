<?php

declare(strict_types=1);

namespace App\Modules\Gatepass\Services;

use App\Core\DB;
use PDO;
use RuntimeException;

/**
 * Coordinates workflow outcomes with the authoritative gatepass state.
 * Approval/rejection/cancellation/return completion paths must use
 * this boundary instead of writing gatepasses.status_id directly.
 */
final class GatepassWorkflowTransitionService
{
    private PDO $db;
    private GatepassStateService $states;

    public function __construct(?GatepassStateService $states = null)
    {
        $this->db = DB::connect();
        $this->states = $states ?? new GatepassStateService();
    }

    public function approve(int $gatepassId, int $actorUserId, ?string $reason = null): bool
    {
        return $this->transitionFromCurrent($gatepassId, 'APPROVED', 'APPROVE_WORKFLOW', $actorUserId, $reason);
    }

    public function reject(int $gatepassId, int $actorUserId, string $reason): bool
    {
        if (trim($reason) === '') {
            throw new RuntimeException('A rejection reason is required.');
        }
        return $this->transitionFromCurrent($gatepassId, 'REJECTED', 'REJECT_WORKFLOW', $actorUserId, $reason);
    }

    public function cancel(int $gatepassId, int $actorUserId, string $reason): bool
    {
        if (trim($reason) === '') {
            throw new RuntimeException('A cancellation reason is required.');
        }
        return $this->transitionFromCurrent($gatepassId, 'CANCELLED', 'CANCEL_WORKFLOW', $actorUserId, $reason);
    }

    public function completeReturn(int $gatepassId, int $actorUserId, ?string $reason = null): bool
    {
        return $this->transitionFromCurrent($gatepassId, 'RETURNED', 'RETURN_COMPLETE', $actorUserId, $reason);
    }

    private function transitionFromCurrent(
        int $gatepassId,
        string $to,
        string $code,
        int $actorUserId,
        ?string $reason
    ): bool {
        $stmt = $this->db->prepare(
            'SELECT s.code FROM gatepasses g INNER JOIN gatepass_statuses s ON s.id=g.status_id WHERE g.id=:id AND g.deleted_at IS NULL'
        );
        $stmt->execute([':id' => $gatepassId]);
        $from = $stmt->fetchColumn();
        if (!$from) {
            throw new RuntimeException('Gatepass not found.');
        }

        GatepassTransitionGuard::assert((string)$from, $to, $code);

        return $this->states->transition(
            $gatepassId,
            (string)$from,
            $to,
            $code,
            $actorUserId,
            $reason
        );
    }
}
