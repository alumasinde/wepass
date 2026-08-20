<?php

declare(strict_types=1);

namespace App\Modules\Gatepass\Services;

use App\Core\DB;
use PDO;
use RuntimeException;

/** Executes an already-authorized scan through the Phase 4 state boundary. */
final class GateScanExecutionService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? DB::connect();
    }

    public function execute(array $decision, int $actorUserId, string $timestamp): bool
    {
        if (($decision['decision'] ?? null) !== 'ALLOW') {
            throw new RuntimeException('Only an allowed scan can be executed.');
        }

        $gatepassId = (int)($decision['gatepass_id'] ?? 0);
        $action = strtoupper((string)($decision['action'] ?? ''));
        if ($gatepassId < 1 || $actorUserId < 1 || !in_array($action, ['CHECK_IN', 'CHECK_OUT'], true)) {
            throw new RuntimeException('Invalid scan execution request.');
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'SELECT g.id, s.code status_code
                 FROM gatepasses g
                 INNER JOIN gatepass_statuses s ON s.id=g.status_id
                 WHERE g.id=:id AND g.deleted_at IS NULL
                 FOR UPDATE'
            );
            $stmt->execute([':id' => $gatepassId]);
            $gatepass = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$gatepass) throw new RuntimeException('Gatepass not found.');

            $from = strtolower((string)$gatepass['status_code']);
            $to = $action === 'CHECK_IN' ? 'checked_in' : 'checked_out';
            $transitionCode = $action === 'CHECK_IN' ? 'CHECKIN' : 'CHECKOUT';

            $state = new GatepassStateService($this->db);
            $state->transition(
                $gatepassId,
                $from,
                $to,
                $transitionCode,
                $actorUserId,
                'QR_GATE_SCAN',
                ['scan_timestamp' => $timestamp]
            );

            $field = $action === 'CHECK_IN' ? 'actual_in' : 'actual_out';
            $actorField = $action === 'CHECK_IN' ? 'checked_in_by' : 'checked_out_by';
            $update = $this->db->prepare(
                "UPDATE gatepasses SET {$field}=:timestamp, {$actorField}=:actor WHERE id=:id AND deleted_at IS NULL"
            );
            $update->execute([':timestamp' => $timestamp, ':actor' => $actorUserId, ':id' => $gatepassId]);
            if ($update->rowCount() !== 1) throw new RuntimeException('Scan action could not be recorded.');

            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }
}
