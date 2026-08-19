<?php

declare(strict_types=1);

namespace App\Modules\Gatepass\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Audit;
use App\Modules\Gatepass\Services\ScanExportService;

final class ScanExportController extends Controller
{
    public function __construct(private ScanExportService $service) {}

    public function export(Request $request): never
    {
        $filters = $request->all() ?? $_GET ?? [];
        Audit::log('gate_scan.export', 'gate_scan_events', null, [
            'filters' => [
                'gate_id' => isset($filters['gate_id']) ? (int)$filters['gate_id'] : null,
                'device_id' => isset($filters['device_id']) ? (int)$filters['device_id'] : null,
                'guard_user_id' => isset($filters['guard_user_id']) ? (int)$filters['guard_user_id'] : null,
                'result' => $filters['result'] ?? null,
                'scan_type' => $filters['scan_type'] ?? null,
                'from' => $filters['from'] ?? null,
                'to' => $filters['to'] ?? null,
            ],
            'max_rows' => 10000,
        ]);
        $this->service->streamCsv($filters, 10000);
        exit;
    }
}
