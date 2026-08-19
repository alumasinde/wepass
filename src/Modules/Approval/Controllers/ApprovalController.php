<?php

namespace App\Modules\Approval\Controllers;

use App\Core\DB;
use App\Core\View;
use App\Core\Request;
use App\Core\Controller;
use App\Core\Response;
use App\Core\Permission;
use App\Modules\Approval\Services\ApprovalService;
use App\Modules\Approval\Policies\ApprovalPolicy;

class ApprovalController extends Controller
{
    private ApprovalService $service;
    private ApprovalPolicy  $policy;

    public function __construct()
    {
        $this->service = new ApprovalService();
        $this->policy  = new ApprovalPolicy(new Permission(DB::connect()));
    }

    private function user(): array
    {
        if (!isset($_SESSION['user'])) {
            header('Location: /login');
            exit;
        }
        return $_SESSION['user'];
    }

    public function index()
    {
        $user = $this->user();

        if (!$this->policy->viewAny()) {
            Response::abort(403);
        }

        // FIX: was $this->service->getPendingForUser(0, $user['id']) — removed stale 0 tenantId arg
        $approvals = $this->service->getPendingForUser($user['id']);

        // Only surface stalled workflows to whoever can actually fix
        // them (assign the missing role) — not the regular approver,
        // who has no action to take on this information.
        $stalled = can('settings.update') ? $this->service->getStalledInstances() : [];

        return View::render('Approval::index', [
            'title'     => 'My Approvals',
            'approvals' => $approvals,
            'stalled'   => $stalled,
        ], 'app');
    }

    public function approve(Request $request, $id)
    {
        $user = $this->user();

        if (!$this->policy->approve()) {
            Response::abort(403);
        }

        $approvalId = (int) $id;
        if ($approvalId <= 0) {
            Response::abort(400);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $approval = $this->service->findApproval($approvalId, $user['id']);
            if (!$approval) {
                Response::abort(404);
            }

            return View::render('Approval::approve', [
                'title'      => 'Approve Gatepass',
                'approval'   => $approval,
                'csrf_token' => csrf_token(),
            ], 'app');
        }

        $this->assertValidCsrf($request->input('csrf_token'));

        try {
            $comment = trim((string) $request->input('comment', ''));
            $this->service->approve($approvalId, $user['id'], $comment !== '' ? $comment : null);

            if ($request->wantsJson()) {
                Response::json(['success' => true, 'message' => 'Approved.']);
            }

            header('Location: /approvals');
            exit;

        } catch (\Throwable $e) {
            if ($request->wantsJson()) {
                Response::json(['success' => false, 'message' => $e->getMessage()], 400);
            }

            return View::render('Approval::index', [
                'title'     => 'My Approvals',
                'error'     => $e->getMessage(),
                // FIX: removed stale 0 tenantId arg
                'approvals' => $this->service->getPendingForUser($user['id']),
                'stalled'   => can('settings.update') ? $this->service->getStalledInstances() : [],
            ], 'app');
        }
    }

    public function reject(Request $request, $id)
    {
        $user = $this->user();

        if (!$this->policy->reject()) {
            Response::abort(403);
        }

        $approvalId = (int) $id;
        if ($approvalId <= 0) {
            Response::abort(400);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $approval = $this->service->findApproval($approvalId, $user['id']);
            if (!$approval) {
                Response::abort(404);
            }

            return View::render('Approval::reject', [
                'title'      => 'Reject Gatepass',
                'approval'   => $approval,
                'csrf_token' => csrf_token(),
            ], 'app');
        }

        $this->assertValidCsrf($request->input('csrf_token'));

        $reason = trim((string) $request->input('comment', ''));
        if ($reason === '') {
            if ($request->wantsJson()) {
                Response::json(['success' => false, 'message' => 'A rejection reason is required.'], 422);
            }

            $approval = $this->service->findApproval($approvalId, $user['id']);
            return View::render('Approval::reject', [
                'title'      => 'Reject Gatepass',
                'approval'   => $approval,
                'csrf_token' => csrf_token(),
                'error'      => 'A rejection reason is required.',
            ], 'app');
        }

        try {
            $this->service->reject($approvalId, $user['id'], $reason);

            if ($request->wantsJson()) {
                Response::json(['success' => true, 'message' => 'Rejected.']);
            }

            header('Location: /approvals');
            exit;

        } catch (\Throwable $e) {
            if ($request->wantsJson()) {
                Response::json(['success' => false, 'message' => $e->getMessage()], 400);
            }

            return View::render('Approval::index', [
                'title'     => 'My Approvals',
                'error'     => $e->getMessage(),
                // FIX: removed stale 0 tenantId arg
                'approvals' => $this->service->getPendingForUser($user['id']),
                'stalled'   => can('settings.update') ? $this->service->getStalledInstances() : [],
            ], 'app');
        }
    }

    private function assertValidCsrf(?string $token): void
    {
        if (empty($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
            Response::abort(419, 'Your session expired — please try again.');
        }
    }

    public function show(Request $request, $id)
    {
        $user = $this->user();

        if (!$this->policy->viewAny()) {
            Response::abort(403);
        }

        $approvalId = (int) $id;
        if ($approvalId <= 0) {
            Response::abort(400);
        }

        // FIX: was $this->service->findApproval(0, $approvalId, $user['id']) — removed stale 0 tenantId arg
        $approval = $this->service->findApproval($approvalId, $user['id']);

        if (!$this->policy->view($user, $approval ?? [])) {
            Response::abort(403);
        }

        return View::render('Approval::show', [
            'title'    => 'View Approval',
            'approval' => $approval,
        ], 'app');
    }
}
