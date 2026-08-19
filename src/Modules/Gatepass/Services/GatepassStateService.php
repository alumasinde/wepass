<?php

declare(strict_types=1);

namespace App\Modules\Gatepass\Services;

use App\Core\Audit;
use App\Core\DB;
use PDO;
use RuntimeException;

/**
 * Phase 4 state-transition boundary.
 * Every controlled status change is conditional on the expected
 * current state and produces an immutable transition record.
 */
final class GatepassStateService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DB::connect();
    }

    public function transition(
        int $gatepassId,
        string $fromStatusCode,
        string $toStatusCode,
        string $transitionCode,
        ?int $actorUserId = null,
        ?string $reason = null,
        array $metadata = []
    ): bool {
        if ($gatepassId < 1 || $fromStatusCode === '' || $toStatusCode === '' || $transitionCode === '') {
            throw new RuntimeException('Invalid gatepass transition.');
        }
        if (strcasecmp($fromStatusCode, $toStatusCode) === 0) {
            throw new RuntimeException('Gatepass cannot transition to the same state.');
        }

        GatepassTransitionGuard::assert($fromStatusCode, $toStatusCode, $transitionCode);

        $this->db->beginTransaction();
        try {
            $status = $this->db->prepare(
                'SELECT id, code FROM gatepass_statuses WHERE code IN (:from_code, :to_code)'
            );
            $status->execute([
                ':from_code' => strtoupper($fromStatusCode),
                ':to_code' => strtoupper($toStatusCode),
            ]);

            $ids = [];
            foreach ($status->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $ids[strtoupper((string) $row['code'])] = (int) $row['id'];
            }
            $fromId = $ids[strtoupper($fromStatusCode)] ?? null;
            $toId = $ids[strtoupper($toStatusCode)] ?? null;
            if (!$fromId || !$toId) {
                throw new RuntimeException('Unknown gatepass status.');
            }

            $update = $this->db->prepare(
                'UPDATE gatepasses SET status_id=:to_status WHERE id=:id AND status_id=:from_status AND deleted_at IS NULL'
            );
            $update->execute([
                ':to_status' => $toId,
                ':id' => $gatepassId,
                ':from_status' => $fromId,
            ]);

            if ($update->rowCount() !== 1) {
                throw new RuntimeException('Gatepass state changed concurrently or is no longer available.');
            }

            $history = $this->db->prepare(
                'INSERT INTO gatepass_state_history
                    (gatepass_id, from_status_id, to_status_id, transition_code, actor_user_id, reason, metadata_json)
                 VALUES (:gatepass_id, :from_status, :to_status, :transition_code, :actor, :reason, :metadata)'
            );
            $history->execute([
                ':gatepass_id' => $gatepassId,
                ':from_status' => $fromId,
                ':to_status' => $toId,
                ':transition_code' => $transitionCode,
                ':actor' => $actorUserId,
                ':reason' => $reason,
                ':metadata' => $metadata ? json_encode($metadata, JSON_UNESCAPED_SLASHES) : null,
            ]);

            $this->db->commit();
            Audit::log('gatepass.state_transition', 'gatepass', $gatepassId, [
                'from' => strtoupper($fromStatusCode),
                'to' => strtoupper($toStatusCode),
                'transition' => $transitionCode,
                'actor_user_id' => $actorUserId,
            ]);
            return true;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function history(int $gatepassId, int $limit = 100): array
    {
        $limit = max(1, min($limit, 500));
        $stmt = $this->db->prepare(
            'SELECT h.id, h.gatepass_id, fs.code from_status, ts.code to_status,
                    h.transition_code, h.actor_user_id, h.reason, h.metadata_json, h.created_at
             FROM gatepass_state_history h
             LEFT JOIN gatepass_statuses fs ON fs.id=h.from_status_id
             INNER JOIN gatepass_statuses ts ON ts.id=h.to_status_id
             WHERE h.gatepass_id=:gatepass_id
             ORDER BY h.id DESC LIMIT ' . $limit
        );
        $stmt->execute([':gatepass_id' => $gatepassId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Mark only active, unexpired gatepasses as expired. */
    public function expireDueGatepasses(int $batchSize = 200): int
    {
        $batchSize = max(1, min($batchSize, 1000));
        $stmt = $this->db->prepare(
            'SELECT g.id, s.code status_code
             FROM gatepasses g
             INNER JOIN gatepass_statuses s ON s.id=g.status_id
             WHERE g.deleted_at IS NULL
               AND g.expires_at IS NOT NULL
               AND g.expires_at <= NOW()
               AND s.code IN (\'PENDING\', \'APPROVED\')
             ORDER BY g.id
             LIMIT ' . $batchSize
        );
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $count = 0;
        foreach ($rows as $row) {
            try {
                if ($this->transition((int)$row['id'], (string)$row['status_code'], 'EXPIRED', 'EXPIRE_SYSTEM')) {
                    $count++;
                }
            } catch (RuntimeException $e) {
                // A concurrent state change is expected under a worker race.
            }
        }
        return $count;
    }
}
