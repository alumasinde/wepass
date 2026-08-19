<?php

declare(strict_types=1);

namespace App\Modules\Badges\Controllers;

use App\Core\Permission;
use App\Core\Response;
use App\Core\Request;
use App\Modules\Badges\Services\BadgeService;
use App\Modules\Visits\Policies\VisitPolicy;
use App\Modules\Visits\Repositories\VisitRepository;

final class BadgeController
{
    private BadgeService $service;
    private VisitRepository $visitRepository;

    public function __construct()
    {
        $this->service = new BadgeService();
        $this->visitRepository = new VisitRepository();
    }

    private function user(): array
    {
        if (!isset($_SESSION['user'])) {
            Response::abort(403);
        }
        return $_SESSION['user'];
    }

    private function authorizeVisit(array $user, int $visitId, string $permission, string $fallback): void
    {
        $visit = $this->visitRepository->find($visitId);
        if (!$visit) {
            Response::abort(404);
        }

        $permissionService = new Permission((int) $user['id']);
        $allowedByPermission = $permissionService->can($permission)
            || $permissionService->can($fallback);

        if (!$allowedByPermission) {
            Response::abort(403);
        }

        $policy = new VisitPolicy($permissionService);
        $allowedByScope = $permissionService->can('visits.view_all')
            || $policy->view($user, $visit);

        if (!$allowedByScope) {
            Response::abort(403);
        }
    }

    public function issue(Request $request, int $visitId): void
    {
        $user = $this->user();
        $this->authorizeVisit($user, $visitId, 'badges.issue', 'gatepass.checkin');

        try {
            $badgeCode = $this->service->issue($visitId);
            $_SESSION['flash'] = [
                'type'    => 'success',
                'message' => "Badge issued successfully. Code: {$badgeCode}",
            ];
        } catch (\Throwable $e) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => $e->getMessage()];
        }

        header('Location: /visits');
        exit;
    }

    public function return(Request $request, int $visitId): void
    {
        $user = $this->user();
        $this->authorizeVisit($user, $visitId, 'badges.return', 'gatepass.checkout');

        try {
            $this->service->returnBadge($visitId);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Badge returned successfully.'];
        } catch (\Throwable $e) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => $e->getMessage()];
        }

        header('Location: /visits');
        exit;
    }
}
