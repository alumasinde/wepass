<?php

namespace App\Modules\Roles\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Modules\Roles\Services\UserRoleService;
use App\Modules\Roles\Services\RoleService;

class UserRoleController extends Controller
{
    private UserRoleService $service;
    private RoleService $roleService;

    public function __construct()
    {
        $this->service     = new UserRoleService();
        $this->roleService = new RoleService();
    }

    private function user(): array
    {
        if (!isset($_SESSION['user'])) {
            Response::abort(403);
        }
        return $_SESSION['user'];
    }

    public function index(Request $request, $userId)
    {
        $this->user();

        $userId = (int) $userId;
        if ($userId <= 0) {
            Response::abort(400);
        }

        if (!$this->validateUser($userId)) {
            Response::abort(404);
        }

        // FIX: was $this->roleService->all($tenantId) — $tenantId undefined; all() takes no tenantId
        $roles           = $this->roleService->all();
        $assignedRoleIds = $this->service->getUserRoleIds($userId);

        return View::render('Roles::user_roles', [
            'roles'           => $roles,
            'assignedRoleIds' => $assignedRoleIds,
            'userId'          => $userId,
            'title'           => 'Assign Roles to User',
        ], 'app');
    }

    public function update(Request $request, $userId)
    {
        $this->user();

        $userId = (int) $userId;
        if ($userId <= 0) {
            Response::abort(400);
        }

        if (!$this->validateUser($userId)) {
            Response::abort(404);
        }

        $roleIds = $request->input('roles', []);
        if (!is_array($roleIds)) {
            Response::abort(400);
        }

        $this->service->assignRoles($userId, $roleIds);
        header("Location: /users/{$userId}/roles");
        exit;
    }

    // FIX: was `int int $userId` — removed duplicate type keyword
    private function validateUser(int $userId): bool
    {
        $stmt = \App\Core\DB::connect()->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        return (bool) $stmt->fetchColumn();
    }
}
