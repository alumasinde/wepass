<?php

declare(strict_types=1);

namespace App\Modules\Gatepass\Services;

use App\Core\DB;
use PDO;
use RuntimeException;

/**
 * Atomic claim/finalization for scanner requests.
 * A request_id can be processed by exactly one concurrent request.
 */
final class ScanIdempotencyService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DB::connect();
    }

    public function claim(string $requestId, array $data): array
    {
        $requestId = trim($requestId);
        if ($requestId === '') {
            throw new RuntimeException('Scanner request ID is required.');
        }

        $sql = "INSERT INTO gate_scan_events
            (gate_id, device_id, guard_user_id, gatepass_id, visit_id,
             scan_type, result, reason_code, request_id, qr_token_hash,
             scanned_at, claimed_at, client_ip, user_agent, metadata_json)
            VALUES
            (:gate_id, :device_id, :guard_user_id, NULL, NULL,
             'validation', 'processing', NULL, :request_id, :qr_token_hash,
             NOW(), NOW(), :client_ip, :user_agent, NULL)";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':gate_id' => (int) $data['gate_id'],
                ':device_id' => (int) $data['device_id'],
                ':guard_user_id' => $data['guard_user_id'] ?? null,
                ':request_id' => $requestId,
                ':qr_token_hash' => $data['qr_token_hash'] ?? null,
                ':client_ip' => $data['client_ip'] ?? null,
                ':user_agent' => $data['user_agent'] ?? null,
            ]);

            return ['claimed' => true, 'event_id' => (int) $this->db->lastInsertId()];
        } catch (\PDOException $e) {
            if ((int) $e->errorInfo[1] !== 1062) {
                throw $e;
            }

            $existing = $this->db->prepare(
                'SELECT id, result, scan_type, reason_code FROM gate_scan_events WHERE request_id = :request_id LIMIT 1'
            );
            $existing->execute([':request_id' => $requestId]);
            $row = $existing->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                throw $e;
            }

            return ['claimed' => false, 'event' => $row];
        }
    }

    public function complete(int $eventId, array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE gate_scan_events
             SET gatepass_id = :gatepass_id,
                 visit_id = :visit_id,
                 scan_type = :scan_type,
                 result = :result,
                 reason_code = :reason_code,
                 metadata_json = :metadata_json
             WHERE id = :id AND result = \'processing\''
        );
        $stmt->execute([
            ':gatepass_id' => $data['gatepass_id'] ?? null,
            ':visit_id' => $data['visit_id'] ?? null,
            ':scan_type' => (string) $data['scan_type'],
            ':result' => (string) $data['result'],
            ':reason_code' => $data['reason_code'] ?? null,
            ':metadata_json' => isset($data['metadata'])
                ? json_encode($data['metadata'], JSON_UNESCAPED_SLASHES)
                : null,
            ':id' => $eventId,
        ]);
    }
}
