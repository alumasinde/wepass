<?php

declare(strict_types=1);

namespace App\Modules\Visits\Controllers;

use App\Core\Controller;
use App\Core\Permission;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Modules\Visits\DTOs\VisitDTO;
use App\Modules\Visits\Services\VisitService;
use RuntimeException;

final class VisitController extends Controller
{
    private VisitService $service;

    public function __construct()
    {
        $this->service = new VisitService();
    }

    private function user(): array
    {
        if (!isset($_SESSION['user'])) {
            Response::abort(403);
        }
        return $_SESSION['user'];
    }

    public function index(): void
    {
        $this->user();
        $visits = $this->service->activeVisits();
        View::render('Visits::index', ['visits' => $visits], 'app');
    }

    public function create(Request $request): void
    {
        $user      = $this->user();
        Permission::requireAny((int) $user['id'], ['visits.create'], ['gatepass.create']);

        $visitorId = (int) $request->input('visitor_id');
        $visitor   = null;

        if ($visitorId > 0) {
            $visitor = $this->service->getVisitor($visitorId);
            if (!$visitor) {
                $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Visitor not found.'];
                header('Location: /visitors');
                exit;
            }
        }

        View::render('Visits::create', [
            'visitor'     => $visitor,
            'departments' => $this->service->getDepartments(),
            'hosts'       => $this->service->getHosts(),
            'visitTypes'  => $this->service->getVisitTypes(),
        ], 'app');
    }

    public function store(Request $request): void
    {
        $user = $this->user();
        Permission::requireAny((int) $user['id'], ['visits.create'], ['gatepass.create']);

        try {
            $dto = VisitDTO::fromArray($request->all(), $user['id']);
            $this->service->create($dto);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Visit created successfully.'];
        } catch (RuntimeException $e) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => $e->getMessage()];
        }

        header('Location: /visits');
        exit;
    }

    public function checkIn(Request $request, int $id): void
    {
        $user = $this->user();
        Permission::requireAny((int) $user['id'], ['visits.checkin'], ['gatepass.checkin']);

        try {
            $this->service->checkIn($id);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Visitor checked in successfully.'];
        } catch (RuntimeException $e) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => $e->getMessage()];
        }

        header('Location: /visits');
        exit;
    }

    public function checkOut(Request $request, int $id): void
    {
        $user = $this->user();
        Permission::requireAny((int) $user['id'], ['visits.checkout'], ['gatepass.checkout']);

        try {
            $this->service->checkOut($id);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Visitor checked out successfully.'];
        } catch (RuntimeException $e) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => $e->getMessage()];
        }

        header('Location: /visits');
        exit;
    }
}
