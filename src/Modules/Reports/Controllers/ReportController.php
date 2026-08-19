<?php

namespace App\Modules\Reports\Controllers;

use App\Core\Controller;
use App\Core\Permission;
use App\Core\View;
use App\Core\Request;
use App\Modules\Reports\Services\ReportService;

class ReportController extends Controller
{
    private ReportService $service;

    public function __construct()
    {
        $this->service = new ReportService();
    }

    public function index()
    {
        $summary = $this->service->summary();
        return View::render('Reports::index', [
            'title'   => 'Reports Dashboard',
            'summary' => $summary,
        ], 'app');
    }

    public function gatepasses(Request $request)
    {
        $data = $this->service->gatepasses($request->all());
        return View::render('Reports::gatepasses', [
            'title' => 'Gatepass Report',
            'data'  => $data,
        ], 'app');
    }

    public function visitors(Request $request)
    {
        $data = $this->service->visitors($request->all());
        return View::render('Reports::visitors', [
            'title' => 'Visitor Report',
            'data'  => $data,
        ], 'app');
    }

    public function visits(Request $request)
    {
        $data = $this->service->visits($request->all());
        return View::render('Reports::visits', [
            'title' => 'Visit Report',
            'data'  => $data,
        ], 'app');
    }

    public function auditLogs(Request $request)
    {
        $data = $this->service->auditLogs($request->all());
        return View::render('Reports::audit', [
            'title' => 'Audit Logs',
            'data'  => $data,
        ], 'app');
    }

    public function exportGatepasses(Request $request)
    {
        $this->requireExportPermission();
        $params = $request->all();
        $params['per_page'] = 100000;
        $data = $this->service->gatepasses($params);
        return $this->streamCsv('gatepasses-report.csv', $data['data'] ?? []);
    }

    public function exportVisitors(Request $request)
    {
        $this->requireExportPermission();
        $params = $request->all();
        $params['per_page'] = 100000;
        $data = $this->service->visitors($params);
        return $this->streamCsv('visitors-report.csv', $data['data'] ?? []);
    }

    public function exportVisits(Request $request)
    {
        $this->requireExportPermission();
        $params = $request->all();
        $params['per_page'] = 100000;
        $data = $this->service->visits($params);
        return $this->streamCsv('visits-report.csv', $data['data'] ?? []);
    }

    public function exportAuditLogs(Request $request)
    {
        $this->requireExportPermission(['audit.export']);
        $params = $request->all();
        $params['per_page'] = 100000;
        $data = $this->service->auditLogs($params);
        return $this->streamCsv('audit-logs-report.csv', $data['data'] ?? []);
    }

    private function requireExportPermission(array $permissions = ['reports.export']): void
    {
        $userId = (int) ($_SESSION['user']['id'] ?? 0);
        Permission::requireAny($userId, $permissions, ['gatepass.view_all']);
    }

    private function streamCsv(string $filename, array $rows): never
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        if (!empty($rows)) {
            fputcsv($out, array_keys($rows[0]));
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
        } else {
            fputcsv($out, ['No data for the selected filters.']);
        }
        fclose($out);
        exit;
    }
}
