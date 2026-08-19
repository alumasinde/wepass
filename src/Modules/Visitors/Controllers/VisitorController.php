<?php

declare(strict_types=1);

namespace App\Modules\Visitors\Controllers;

use App\Core\Permission;
use App\Core\View;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Visitors\Services\VisitorService;
use App\Modules\Visitors\DTOs\VisitorDTO;
use App\Modules\Visitors\Policies\VisitorPolicy;

final class VisitorController
{
    private VisitorService $service;
    private VisitorPolicy $policy;

    public function __construct()
    {
        $this->service = new VisitorService();
        $userId = (int) ($_SESSION['user']['id'] ?? 0);
        $this->policy = new VisitorPolicy(new Permission($userId));
    }

    private function user(): array
    {
        if (empty($_SESSION['user'])) {
            Response::abort(403);
        }
        return $_SESSION['user'];
    }

    public function index(Request $request): void
    {
        $user = $this->user();
        Permission::requireAny((int) $user['id'], ['visitors.view', 'visitors.view_all'], ['gatepass.view', 'gatepass.view_all']);

        $visitors = $this->service->list() ?? [];
        if (!$this->policy->view()) {
            Response::abort(403);
        }

        View::render('Visitors::index', ['visitors' => $visitors], 'app');
    }

    public function create(Request $request): void
    {
        $user = $this->user();
        Permission::requireAny((int) $user['id'], ['visitors.create'], ['gatepass.create']);

        View::render('Visitors::create', [
            'idTypes' => $this->service->getIdentificationTypes(),
            'companies' => $this->service->getCompanies(),
        ], 'app');
    }

    public function store(Request $request): void
    {
        $user = $this->user();
        Permission::requireAny((int) $user['id'], ['visitors.create'], ['gatepass.create']);

        try {
            $data = $request->all();
            $data['created_by'] = (string) $user['id'];

            $newCompanyName = trim((string) ($data['new_company_name'] ?? ''));
            if ($newCompanyName !== '') {
                $data['company_id'] = (string) $this->service->getOrCreateCompany($newCompanyName);
            }

            $this->service->create(VisitorDTO::fromArray($data));
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Visitor created successfully.'];
        } catch (\Throwable $e) {
            error_log($e->getMessage());
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Unable to create visitor.'];
        }

        header('Location: /visitors');
        exit;
    }

    public function edit(Request $request, int $id): void
    {
        $user = $this->user();
        Permission::requireAny((int) $user['id'], ['visitors.update', 'visitors.update_all'], ['gatepass.update']);

        $visitor = $this->service->find($id);
        if (!$visitor || !$this->policy->canUpdateRecord($visitor)) {
            Response::abort(403);
        }

        View::render('Visitors::edit', [
            'visitor' => $visitor,
            'idTypes' => $this->service->getIdentificationTypes(),
            'companies' => $this->service->getCompanies(),
        ], 'app');
    }

    public function update(Request $request, int $id): void
    {
        $user = $this->user();
        Permission::requireAny((int) $user['id'], ['visitors.update', 'visitors.update_all'], ['gatepass.update']);

        $visitor = $this->service->find($id);
        if (!$visitor || !$this->policy->canUpdateRecord($visitor)) {
            Response::abort(403);
        }

        try {
            $data = $request->all();
            $newCompanyName = trim((string) ($data['new_company_name'] ?? ''));
            if ($newCompanyName !== '') {
                $data['company_id'] = (string) $this->service->getOrCreateCompany($newCompanyName);
            }

            $this->service->update($id, VisitorDTO::fromArray($data));
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Visitor updated successfully.'];
        } catch (\Throwable $e) {
            error_log($e->getMessage());
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Unable to update visitor.'];
        }

        header('Location: /visitors/' . $id . '/edit');
        exit;
    }

    public function view(Request $request, int $id): void
    {
        $user = $this->user();
        Permission::requireAny((int) $user['id'], ['visitors.view', 'visitors.view_all'], ['gatepass.view', 'gatepass.view_all']);

        $visitor = $this->service->findWithVisits($id);
        if (!$visitor || !$this->policy->canViewRecord($visitor)) {
            Response::abort(403);
        }

        View::render('Visitors::view', ['visitor' => $visitor], 'app');
    }

    public function blacklist(Request $request, int $visitorId): void
    {
        $user = $this->user();
        Permission::requireAny((int) $user['id'], ['visitors.blacklist', 'visitors.manage'], ['gatepass.update']);

        $visitor = $this->service->find($visitorId);
        if (!$visitor || !$this->policy->canBlacklistRecord($visitor)) {
            Response::abort(403);
        }

        $this->service->blacklist($visitorId);
        $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Visitor has been blacklisted.'];
        header('Location: /visitors');
        exit;
    }

    public function unblacklist(Request $request, int $visitorId): void
    {
        $user = $this->user();
        Permission::requireAny((int) $user['id'], ['visitors.blacklist', 'visitors.manage'], ['gatepass.update']);

        $visitor = $this->service->find($visitorId);
        if (!$visitor || !$this->policy->canBlacklistRecord($visitor)) {
            Response::abort(403);
        }

        $this->service->unblacklist($visitorId);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Visitor removed from blacklist.'];
        header('Location: /visitors');
        exit;
    }
}
