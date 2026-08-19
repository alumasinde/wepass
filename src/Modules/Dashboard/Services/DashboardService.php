<?php

namespace App\Modules\Dashboard\Services;

use App\Core\DB;
use App\Core\Helpers\DateHelper;
use App\Core\Helpers\ChartHelper;
use App\Modules\Approval\Services\ApprovalService;
use PDO;

class DashboardService
{
    private PDO $db;
    private ApprovalService $approvalService;

    public function __construct()
    {
        $this->db = DB::connect();
        $this->approvalService = new ApprovalService();
    }

    // ── PUBLIC API ───────────────────────────────────────────

    public function getStats(array $user): array
    {
        $userId = (int) $user['id'];

        return [
            'total_gatepasses'     => $this->countGatepasses(),
            'my_pending_approvals' => $this->countMyPendingApprovals($userId),
            'checked_in_today'     => $this->countCheckinsToday(),
            'checked_out_today'    => $this->countCheckoutsToday(),
            'active_visitors'      => $this->countActiveVisitors(),
            'total_visitors'       => $this->totalVisitors(),
            // Reuses ApprovalService's own query rather than
            // duplicating it — the same "stalled" definition
            // ApprovalController already surfaces on the Approvals
            // page, just as a count here for the dashboard card.
            'stalled_workflows'    => count($this->approvalService->getStalledInstances()),
        ];
    }

    // ── STATS QUERIES ────────────────────────────────────────

    private function countGatepasses(): int
    {
        return (int) $this->db->query("SELECT COUNT(*) FROM gatepasses")->fetchColumn();
    }

    // FIX: was `int int $userId` — removed duplicate type keyword
    private function countMyPendingApprovals(int $userId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM gatepass_approvals ga
            JOIN gatepass_workflow_instances gwi
                ON ga.workflow_instance_id = gwi.id
            WHERE ga.approver_user_id = :user
              AND ga.status           = 'pending'
              AND gwi.status          = 'in_progress'
        ");
        // FIX: was ':tenant' => ':user' => $userId — broken cascaded array key
        $stmt->execute([':user' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    private function countCheckinsToday(): int
    {
        $today = DateHelper::today();
        $stmt  = $this->db->prepare("SELECT COUNT(*) FROM gatepasses WHERE DATE(actual_in) = :today");
        // FIX: was ':tenant' => ':today' => $today
        $stmt->execute([':today' => $today]);
        return (int) $stmt->fetchColumn();
    }

    private function countCheckoutsToday(): int
    {
        $today = DateHelper::today();
        $stmt  = $this->db->prepare("SELECT COUNT(*) FROM gatepasses WHERE DATE(actual_out) = :today");
        $stmt->execute([':today' => $today]);
        return (int) $stmt->fetchColumn();
    }

    private function countActiveVisitors(): int
    {
        return (int) $this->db->query("
            SELECT COUNT(*) FROM visits
            WHERE checkin_time IS NOT NULL AND checkout_time IS NULL
        ")->fetchColumn();
    }

    private function totalVisitors(): int
    {
        return (int) $this->db->query("SELECT COUNT(*) FROM visitors WHERE is_blacklisted = 0")->fetchColumn();
    }

    // ── CHARTS ───────────────────────────────────────────────

    // FIX: was `int int $days` — removed duplicate type keyword
    public function getChartData(int $days = 30): array
    {
        $range = DateHelper::rangeDays($days);

        $workflowRows = $this->db->query("
            SELECT status, COUNT(*) AS total
            FROM gatepass_workflow_instances
            GROUP BY status
        ")->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $this->db->prepare("
            SELECT DATE(created_at) AS date, COUNT(*) AS total
            FROM gatepasses
            WHERE created_at BETWEEN :start AND :end
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ");
        // FIX: was ':tenant' => ':start' => $range['start']
        $stmt->execute([':start' => $range['start'], ':end' => $range['end']]);
        $gatepassRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $this->db->prepare("
            SELECT DATE(checkin_time) AS date, COUNT(*) AS total
            FROM visits
            WHERE checkin_time BETWEEN :start AND :end
            GROUP BY DATE(checkin_time)
            ORDER BY date ASC
        ");
        $stmt->execute([':start' => $range['start'], ':end' => $range['end']]);
        $visitRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'workflow_status'  => ChartHelper::dataset($workflowRows,  'status', 'total'),
            'daily_gatepasses' => ChartHelper::dataset($gatepassRows, 'date',   'total'),
            'weekly_visits'    => ChartHelper::dataset($visitRows,    'date',   'total'),
        ];
    }
}
