<?php

namespace App\Modules\Dashboard\Controllers;

use App\Core\Controller;
use App\Core\View;
use App\Core\Audit;
use App\Modules\Dashboard\Services\DashboardService;

class DashboardController extends Controller
{
    private DashboardService $service;

    public function __construct()
    {
        $this->service = new DashboardService();
    }

    public function index()
    {
        $user  = $_SESSION['user'];
        $stats = $this->service->getStats($user);

        Audit::log('dashboard.viewed', 'dashboard', (int) $user['id'], [
            'email' => $user['email'],
        ]);

        // FIX: was $this->service->getChartData(0) — 0 was leftover tenantId; method takes no tenantId
        return View::render('Dashboard::index', [
            'title'  => 'Dashboard',
            'user'   => $user,
            'stats'  => $stats,
            'charts' => $this->service->getChartData(),
        ], 'app');
    }

    public function charts()
    {
        $days = (int) ($_GET['days'] ?? 30);

        // FIX: was $this->service->getChartData(0, $days) — removed stale 0 tenantId arg
        $data = $this->service->getChartData($days);

        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
