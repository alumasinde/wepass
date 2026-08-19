<?php

declare(strict_types=1);

namespace App\Modules\Gatepass\Services;

use App\Core\DB;
use PDO;

/** Security-safe, bounded CSV export for gate scan history. */
final class ScanExportService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DB::connect();
    }

    public function streamCsv(array $filters = [], int $maxRows = 10000): void
    {
        $maxRows = max(1, min($maxRows, 10000));
        $where = [];
        $params = [];

        if (!empty($filters['gate_id'])) { $where[] = 'e.gate_id = :gate_id'; $params[':gate_id'] = (int)$filters['gate_id']; }
        if (!empty($filters['device_id'])) { $where[] = 'e.device_id = :device_id'; $params[':device_id'] = (int)$filters['device_id']; }
        if (!empty($filters['guard_user_id'])) { $where[] = 'e.guard_user_id = :guard_user_id'; $params[':guard_user_id'] = (int)$filters['guard_user_id']; }
        if (!empty($filters['result'])) { $where[] = 'e.result = :result'; $params[':result'] = (string)$filters['result']; }
        if (!empty($filters['scan_type'])) { $where[] = 'e.scan_type = :scan_type'; $params[':scan_type'] = (string)$filters['scan_type']; }
        if (!empty($filters['from'])) { $where[] = 'e.scanned_at >= :from_date'; $params[':from_date'] = $filters['from'].' 00:00:00'; }
        if (!empty($filters['to'])) { $where[] = 'e.scanned_at < DATE_ADD(:to_date, INTERVAL 1 DAY)'; $params[':to_date'] = $filters['to']; }

        $sql = "SELECT e.id,e.scan_type,e.result,e.reason_code,e.request_id,e.scanned_at,e.completed_at,
                       g.name AS gate_name,d.device_name,u.username,ga.gatepass_number
                FROM gate_scan_events e
                INNER JOIN gates g ON g.id=e.gate_id
                INNER JOIN approved_devices d ON d.id=e.device_id
                LEFT JOIN users u ON u.id=e.guard_user_id
                LEFT JOIN gatepasses ga ON ga.id=e.gatepass_id";
        if ($where) $sql .= ' WHERE '.implode(' AND ', $where);
        $sql .= ' ORDER BY e.scanned_at DESC LIMIT '.$maxRows;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        if (!headers_sent()) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="gate-scan-history-'.gmdate('Ymd-His').'.csv"');
            header('X-Content-Type-Options: nosniff');
        }

        $out = fopen('php://output', 'wb');
        fputcsv($out, ['ID','Scan Type','Result','Reason','Request ID','Scanned At','Completed At','Gate','Device','Guard','Gatepass']);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($out, [$row['id'],$row['scan_type'],$row['result'],$row['reason_code'],$row['request_id'],$row['scanned_at'],$row['completed_at'],$row['gate_name'],$row['device_name'],$row['username'],$row['gatepass_number']]);
        }
        fclose($out);
    }
}
