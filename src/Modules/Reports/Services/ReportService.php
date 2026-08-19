<?php

namespace App\Modules\Reports\Services;

use App\Modules\Reports\Repositories\GatepassReportRepository;
use App\Modules\Reports\Repositories\VisitorReportRepository;
use App\Modules\Reports\Repositories\VisitReportRepository;
use App\Modules\Reports\Repositories\AuditReportRepository;

class ReportService
{
    private GatepassReportRepository $gatepass;
    private VisitorReportRepository  $visitor;
    private VisitReportRepository    $visit;
    private AuditReportRepository    $audit;

    public function __construct()
    {
        $this->gatepass = new GatepassReportRepository();
        $this->visitor  = new VisitorReportRepository();
        $this->visit    = new VisitReportRepository();
        $this->audit    = new AuditReportRepository();
    }

    // FIX: removed int $tenantId param from all methods — BaseRepository::count() and list() no longer accept it
    public function summary(): array
    {
        return [
            'gatepasses_total' => $this->gatepass->count(),
            'visitors_total'   => $this->visitor->count(),
            'visits_total'     => $this->visit->count(),
            'audit_total'      => $this->audit->count(),
        ];
    }

    public function gatepasses(array $params = [])
    {
        return $this->gatepass->list($params);
    }

    public function visitors(array $params = []): array
    {
        return $this->visitor->list($params);
    }

    public function visits(array $params = [])
    {
        return $this->visit->list($params);
    }

    public function auditLogs(array $params = [])
    {
        return $this->audit->list($params);
    }
}
