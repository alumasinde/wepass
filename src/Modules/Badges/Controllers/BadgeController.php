<?php

declare(strict_types=1);

namespace App\Modules\Badges\Controllers;

use App\Core\Permission;
use App\Core\Response;
use App\Core\Request;
use App\Modules\Badges\Services\BadgeService;

final class BadgeController
{
    private BadgeService $service;

    public function __construct()
    {
        $this->service = new BadgeService();
    }

    private function user(): array
    {
        if (!isset($_SESSION['user'])) {
            Response::abort(403);
        }
        return $_SESSION['user'];
    }

    public function issue(Request $request, int $visitId): void
    {
        $user = $this->user();
        Permission::requireAny((int) $user['id'], ['badges.issue'], ['gatepass.checkin']);

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
        Permission::requireAny((int) $user['id'], ['badges.return'], ['gatepass.checkout']);

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
