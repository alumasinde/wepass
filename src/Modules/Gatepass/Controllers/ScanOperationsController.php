<?php

declare(strict_types=1);

namespace App\Modules\Gatepass\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Gatepass\Services\ScanOperationsService;

final class ScanOperationsController extends Controller
{
    public function __construct(private readonly ScanOperationsService $service) {}

    public function index(Request $request): mixed
    {
        $filters = [
            'gate_id' => $request->input('gate_id'),
            'device_id' => $request->input('device_id'),
            'guard_user_id' => $request->input('guard_user_id'),
            'result' => $request->input('result'),
            'scan_type' => $request->input('scan_type'),
            'from' => $request->input('from'),
            'to' => $request->input('to'),
        ];

        return $this->view('Gatepass::scan-history', [
            'scans' => $this->service->recent($filters, (int) $request->input('limit', 100)),
            'filters' => $filters,
        ]);
    }

    public function json(Request $request): never
    {
        $filters = [
            'gate_id' => $request->input('gate_id'),
            'device_id' => $request->input('device_id'),
            'guard_user_id' => $request->input('guard_user_id'),
            'result' => $request->input('result'),
            'scan_type' => $request->input('scan_type'),
            'from' => $request->input('from'),
            'to' => $request->input('to'),
        ];

        Response::json(['success' => true, 'data' => $this->service->recent($filters, (int) $request->input('limit', 100))]);
    }
}
