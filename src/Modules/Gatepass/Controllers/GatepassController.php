<?php

namespace App\Modules\Gatepass\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Permission;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Modules\Gatepass\Services\GatepassService;
use App\Modules\Gatepass\Policies\GatepassPolicy;
use App\Modules\Gatepass\Repositories\GatepassTypeRepository;
use App\Modules\Gatepass\Repositories\GatepassStatusRepository;
use App\Modules\Gatepass\DTOs\GatepassDTO;

/**
 * Rebuilt from scratch — this file had been accidentally overwritten
 * with GatepassTypeController's entire contents (wrong namespace,
 * wrong class, none of the methods below existed), which is exactly
 * why the whole Gatepass module stopped loading. Reconstructed
 * against routes/web.php's method list, GatepassService/GatepassPolicy's
 * real signatures, and what each view under Views/ actually expects,
 * matching every fix already established this session (Request as
 * the first parameter on every handler — Router always calls
 * $controller->method($request, ...$routeParams) — and object-level
 * policy checks on show/edit/update/delete).
 */
class GatepassController
{
    private GatepassService $service;
    private GatepassPolicy $policy;
    private GatepassTypeRepository $typeRepo;
    private GatepassStatusRepository $statusRepo;

    public function __construct()
    {
        $this->service    = new GatepassService();
        $this->policy     = new GatepassPolicy(new Permission(DB::connect()));
        $this->typeRepo   = new GatepassTypeRepository();
        $this->statusRepo = new GatepassStatusRepository();
    }

    private function user(): array
    {
        if (empty($_SESSION['user'])) {
            Response::abort(401, 'Not authenticated.');
        }
        return $_SESSION['user'];
    }

    private function findOrFail(int $id): array
    {
        $gatepass = $this->service->find($id);
        if (!$gatepass) {
            Response::abort(404, 'Gatepass not found.');
        }
        return $gatepass;
    }

    // ───────────────── INDEX ─────────────────

    public function index(Request $request): void
    {
        $user       = $this->user();
        $canViewAll = $this->policy->canViewAll();

        $gatepasses = $this->service->list((int) $user['id'], $canViewAll);

        foreach ($gatepasses as &$g) {
            $g['actions'] = $this->service->getAvailableActions($g);
        }
        unset($g);

        View::render('Gatepass::index', [
            'title'      => 'Gatepasses',
            'gatepasses' => $gatepasses,
            'user'       => $user,
        ], 'app');
    }

    // ───────────────── CREATE ─────────────────

    public function create(Request $request): void
    {
        $user = $this->user();

        View::render('Gatepass::create', [
            'title'  => 'Create Gatepass',
            'types'  => $this->typeRepo->findAll(),
            'visits' => $this->service->getVisitsForUser($user['department_id'] ?? null),
            'user'   => $user,
            'error'  => $_SESSION['flash']['message'] ?? null,
        ], 'app');

        unset($_SESSION['flash']);
    }

    // ───────────────── STORE ─────────────────

    public function store(Request $request): void
    {
        $user = $this->user();

        try {
            $dto = GatepassDTO::fromRequest($request->all(), (int) $user['id']);
            $id  = $this->service->create($dto);

            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Gatepass created successfully.'];
            header("Location: /gatepasses/{$id}");
            exit;
        } catch (\Throwable $e) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => $e->getMessage()];
            header('Location: /gatepasses/create');
            exit;
        }
    }

    // ───────────────── SHOW ─────────────────

    public function show(Request $request, int $id): void
    {
        $user     = $this->user();
        $gatepass = $this->findOrFail($id);

        if (!$this->policy->view($user, $gatepass)) {
            Response::abort(403, "You don't have permission to view this gatepass.");
        }

        View::render('Gatepass::show', [
            'title'    => 'Gatepass ' . ($gatepass['gatepass_number'] ?? ''),
            'gatepass' => $gatepass,
            'items'    => $gatepass['items'] ?? [],
            'actions'  => $this->service->getAvailableActions($gatepass),
        ], 'app');
    }

    // ───────────────── EDIT ─────────────────

    public function edit(Request $request, int $id): void
    {
        $user     = $this->user();
        $gatepass = $this->findOrFail($id);

        if (!$this->policy->update($user, $gatepass)) {
            Response::abort(403, "This gatepass can't be edited.");
        }

        View::render('Gatepass::edit', [
            'title'    => 'Edit Gatepass',
            'gatepass' => $gatepass,
            'items'    => $gatepass['items'] ?? [],
            'types'    => $this->typeRepo->findAll(),
            'statuses' => $this->statusRepo->findAll(),
            'visits'   => $this->service->getVisitsForUser($user['department_id'] ?? null),
            'error'    => $_SESSION['flash']['message'] ?? null,
        ], 'app');

        unset($_SESSION['flash']);
    }

    // ───────────────── UPDATE ─────────────────

    public function update(Request $request, int $id): void
    {
        $user     = $this->user();
        $gatepass = $this->findOrFail($id);

        if (!$this->policy->update($user, $gatepass)) {
            Response::abort(403, "This gatepass can't be edited.");
        }

        try {
            $dto = GatepassDTO::fromRequest($request->all(), (int) $user['id']);
            $this->service->update($id, $dto);

            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Gatepass updated.'];
            header("Location: /gatepasses/{$id}");
            exit;
        } catch (\Throwable $e) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => $e->getMessage()];
            header("Location: /gatepasses/{$id}/edit");
            exit;
        }
    }

    // ───────────────── DELETE ─────────────────

    public function delete(Request $request, int $id): void
    {
        $user     = $this->user();
        $gatepass = $this->findOrFail($id);

        if (!$this->policy->delete($user, $gatepass)) {
            Response::abort(403, "This gatepass can't be deleted — either it's not yours, it's no longer pending, or it's already been checked in.");
        }

        try {
            $this->service->delete($id);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Gatepass deleted.'];
        } catch (\Throwable $e) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => $e->getMessage()];
        }

        header('Location: /gatepasses');
        exit;
    }

    // ───────────────── CHECK IN ─────────────────

    public function checkIn(Request $request, mixed $id): never
    {
        $id   = (int) $id;
        $user = $this->user();

        try {
            $this->service->checkIn($id, $user['id']);

            if ($request->wantsJson()) {
                $gatepass = $this->service->find($id);
                $actions  = $this->service->getAvailableActions($gatepass);
                Response::json([
                    'success'      => true,
                    'message'      => 'Checked in successfully.',
                    'status_name'  => $gatepass['status_name'] ?? null,
                    'status_code'  => strtolower($gatepass['status_code'] ?? ''),
                    'can_checkin'  => (bool) ($actions['can_checkin']  ?? false),
                    'can_checkout' => (bool) ($actions['can_checkout'] ?? false),
                ]);
            }

            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Checked in successfully.'];
        } catch (\Throwable $e) {
            if ($request->wantsJson()) {
                Response::json(['success' => false, 'message' => $e->getMessage()], 400);
            }
            $_SESSION['flash'] = ['type' => 'danger', 'message' => $e->getMessage()];
        }

        header("Location: /gatepasses/{$id}");
        exit;
    }

    // ───────────────── CHECK OUT ─────────────────

    public function checkOut(Request $request, mixed $id): never
    {
        $id   = (int) $id;
        $user = $this->user();

        try {
            $this->service->checkOut($id, $user['id']);

            if ($request->wantsJson()) {
                $gatepass = $this->service->find($id);
                $actions  = $this->service->getAvailableActions($gatepass);
                Response::json([
                    'success'      => true,
                    'message'      => 'Checked out successfully.',
                    'status_name'  => $gatepass['status_name'] ?? null,
                    'status_code'  => strtolower($gatepass['status_code'] ?? ''),
                    'can_checkin'  => (bool) ($actions['can_checkin']  ?? false),
                    'can_checkout' => (bool) ($actions['can_checkout'] ?? false),
                ]);
            }

            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Checked out successfully.'];
        } catch (\Throwable $e) {
            if ($request->wantsJson()) {
                Response::json(['success' => false, 'message' => $e->getMessage()], 400);
            }
            $_SESSION['flash'] = ['type' => 'danger', 'message' => $e->getMessage()];
        }

        header("Location: /gatepasses/{$id}");
        exit;
    }
}
