<?php

declare(strict_types=1);

namespace App\Modules\Gatepass\Services;

use App\Core\DB;
use PDO;
use RuntimeException;

/** Read-only scan operations plus safe recovery of abandoned processing requests. */
final class ScanOperationsService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DB::connect();
    }

    public function recent(array $filters = [], int $limit = 100): array
    {
        $limit = max(1, min($limit, 500));
        $where = [];
        $params = [];
        if (!empty($filters['gate_id'])) { $where[] = 'e.gate_id = :gate_id'; $params[':gate_id'] = (int)$filters['gate_id']; }
        if (!empty($filters['device_id'])) { $where[] = 'e.device_id = :device_id'; $params[':device_id'] = (int)$filters['device_id']; }
        if (!empty($filters['guard_user_id'])) { $where[] = 'e.guard_user_id = :guard_user_id'; $params[':guard_user_id'] = (int)$filters['guard_user_id']; }
        if (!empty($filters['result'])) { $where[] = 'e.result = :result'; $params[':result'] = $filters['result']; }
        if (!empty($filters['scan_type'])) { $where[] = 'e.scan_type = :scan_type'; $params[':scan_type'] = $filters['scan_type']; }
        if (!empty($filters['from'])) { $where[] = 'e.scanned_at >= :from_date'; $params[':from_date'] = $filters['from'].' 00:00:00'; }
        if (!empty($filters['to'])) { $where[] = 'e.scanned_at < DATE_ADD(:to_date, INTERVAL 1 DAY)'; $params[':to_date'] = $filters['to']; }
        $sql = "SELECT e.id,e.scan_type,e.result,e.reason_code,e.request_id,e.scanned_at,e.processing_started_at,e.completed_at,g.name gate_name,d.device_name,u.username,ga.gatepass_number FROM gate_scan_events e INNER JOIN gates g ON g.id=e.gate_id INNER JOIN approved_devices d ON d.id=e.device_id LEFT JOIN users u ON u.id=e.guard_user_id LEFT JOIN gatepasses ga ON ga.id=e.gatepass_id";
        if ($where) $sql .= ' WHERE '.implode(' AND ', $where);
        $sql .= " ORDER BY e.scanned_at DESC LIMIT {$limit}";
        $stmt = $this->db->prepare($sql); $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Mark abandoned processing rows as errors. Call from cron/maintenance. */
    public function recoverStuck(int $timeoutSeconds = 120): int
    {
        $timeoutSeconds = max(30, min($timeoutSeconds, 3600));
        $stmt = $this->db->prepare("UPDATE gate_scan_events SET result='error', reason_code='PROCESSING_TIMEOUT', completed_at=NOW() WHERE result='processing' AND processing_started_at IS NOT NULL AND processing_started_at < DATE_SUB(NOW(), INTERVAL :seconds SECOND)");
        // MySQL does not allow bound parameters for INTERVAL in every server mode;
        // interpolate only the validated integer range.
        $sql = "UPDATE gate_scan_events SET result='error', reason_code='PROCESSING_TIMEOUT', completed_at=NOW() WHERE result='processing' AND processing_started_at IS NOT NULL AND processing_started_at < DATE_SUB(NOW(), INTERVAL {$timeoutSeconds} SECOND)";
        return $this->db->exec($sql);
    }
}
