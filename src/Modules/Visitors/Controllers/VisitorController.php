<?php

declare(strict_types=1);

namespace App\Modules\Visitors\Controllers;

use App\Core\View;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Visitors\Services\VisitorService;
use App\Modules\Visitors\DTOs\VisitorDTO;

final class VisitorController
{
    private VisitorService $service;

    public function __construct()
    {
        $this->service = new VisitorService();
    }

    // ─────────────────────────────────────────────
    // SESSION
    // ─────────────────────────────────────────────
    private function startSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    private function user(): array
    {
        $this->startSession();

        if (empty($_SESSION['user'])) {
            Response::abort(403);
        }

        return $_SESSION['user'];
    }

    // ─────────────────────────────────────────────
    // LIST
    // ─────────────────────────────────────────────
    public function index(Request $request): void
    {
        $this->user();

        try {
            $visitors = $this->service->list() ?? [];

            View::render('Visitors::index', [
                'visitors' => $visitors
            ], 'app');

        } catch (\Throwable $e) {
    http_response_code(500);

    echo '<pre>';
    echo $e->getMessage() . "\n\n";
    echo $e->getFile() . ':' . $e->getLine() . "\n\n";
    echo $e->getTraceAsString();
    echo '</pre>';
    exit;
}
}

    // ─────────────────────────────────────────────
    // CREATE FORM
    // ─────────────────────────────────────────────
    public function create(Request $request): void
    {
        $this->user();

        try {
            View::render('Visitors::create', [
                'idTypes'   => $this->service->getIdentificationTypes(),
                'companies' => $this->service->getCompanies(),
            ], 'app');

        } catch (\Throwable $e) {
            error_log($e->getMessage());
            http_response_code(500);
            echo "Failed to load form.";
        }
    }

    // ─────────────────────────────────────────────
    // STORE
    // ─────────────────────────────────────────────
    public function store(Request $request): void
    {
        $user = $this->user();

        try {
            $data = $request->all();
            $data['created_by'] = (string) $user['id'];

            $newCompanyName = trim((string) ($data['new_company_name'] ?? ''));
            if ($newCompanyName !== '') {
                $data['company_id'] = (string) $this->service->getOrCreateCompany($newCompanyName);
            }

            $dto = VisitorDTO::fromArray($data);

            $this->service->create($dto);

            $_SESSION['flash'] = [
                'type' => 'success',
                'message' => 'Visitor created successfully.'
            ];

            header('Location: /visitors');
            exit;

        } catch (\Throwable $e) {
            error_log($e->getMessage());

            View::render('Visitors::create', [
                'error'     => $e->getMessage(),
                'idTypes'   => $this->service->getIdentificationTypes(),
                'companies' => $this->service->getCompanies(),
            ], 'app');
        }
    }

    // ─────────────────────────────────────────────
    // EDIT
    // ─────────────────────────────────────────────
    public function edit(Request $request, int $id): void
    {
        $this->user();

        try {
            $visitor = $this->service->find($id);

            if (!$visitor) {
                Response::abort(404);
            }

            View::render('Visitors::edit', [
                'visitor'   => $visitor,
                'idTypes'   => $this->service->getIdentificationTypes(),
                'companies' => $this->service->getCompanies(),
            ], 'app');

        } catch (\Throwable $e) {
            error_log($e->getMessage());
            http_response_code(500);
            echo "Failed to load visitor.";
        }
    }

    // ─────────────────────────────────────────────
    // UPDATE
    // ─────────────────────────────────────────────
    public function update(Request $request, int $id): void
    {
        $this->user();

        try {
            $data = $request->all();

            $newCompanyName = trim((string) ($data['new_company_name'] ?? ''));
            if ($newCompanyName !== '') {
                $data['company_id'] = (string) $this->service->getOrCreateCompany($newCompanyName);
            }

            $dto = VisitorDTO::fromArray($data);

            $this->service->update($id, $dto);

            $_SESSION['flash'] = [
                'type' => 'success',
                'message' => 'Visitor updated successfully.'
            ];

            header('Location: /visitors/' . $id);
            exit;

        } catch (\Throwable $e) {
            error_log($e->getMessage());

            $_SESSION['flash'] = [
                'type' => 'danger',
                'message' => $e->getMessage()
            ];

            header('Location: /visitors/' . $id . '/edit');
            exit;
        }
    }

    // ─────────────────────────────────────────────
    // VIEW
    // ─────────────────────────────────────────────
    public function view(Request $request, int $id): void
    {
        $this->user();

        try {
            $visitor = $this->service->findWithVisits($id);

            if (!$visitor) {
                Response::abort(404);
            }

            View::render('Visitors::view', [
                'visitor' => $visitor
            ], 'app');

        } catch (\Throwable $e) {
            error_log($e->getMessage());
            http_response_code(500);
            echo "Failed to load visitor.";
        }
    }

    // ─────────────────────────────────────────────
    // BLACKLIST
    // ─────────────────────────────────────────────
    public function blacklist(Request $request, int $visitorId): void
    {
        $this->user();

        try {
            $this->service->blacklist($visitorId);

            $_SESSION['flash'] = [
                'type' => 'warning',
                'message' => 'Visitor has been blacklisted.'
            ];

        } catch (\Throwable $e) {
            error_log($e->getMessage());

            $_SESSION['flash'] = [
                'type' => 'danger',
                'message' => $e->getMessage()
            ];
        }

        header('Location: /visitors');
        exit;
    }

    // ─────────────────────────────────────────────
    // UNBLACKLIST
    // ─────────────────────────────────────────────
    public function unblacklist(Request $request, int $visitorId): void
    {
        $this->user();

        try {
            $this->service->unblacklist($visitorId);

            $_SESSION['flash'] = [
                'type' => 'success',
                'message' => 'Visitor removed from blacklist.'
            ];

        } catch (\Throwable $e) {
            error_log($e->getMessage());

            $_SESSION['flash'] = [
                'type' => 'danger',
                'message' => $e->getMessage()
            ];
        }

        header('Location: /visitors');
        exit;
    }
}