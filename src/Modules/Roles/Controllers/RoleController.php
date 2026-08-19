<?php

declare(strict_types=1);

namespace App\Modules\Roles\Controllers;

use App\Core\Controller;
use App\Core\Permission;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Modules\Roles\Services\RoleService;

class RoleController extends Controller
{
    private RoleService $service;

    public function __construct()
    {
        $this->service = new RoleService();
    }

    private function user(): array
    {
        if (!isset($_SESSION['user'])) {
            Response::abort(403);
        }
        return $_SESSION['user'];
    }

    private function requirePermission(string $permission): array
    {
        $user = $this->user();
        Permission::requireAny((int) $user['id'], [$permission]);
        return $user;
    }

    public function index(Request $request)
    {
        $this->requirePermission('roles.view');
        $roles = $this->service->all();

        return View::render('Roles::index', [
            'roles' => $roles,
            'title' => 'Roles Management',
        ], 'app');
    }

    public function create(Request $request)
    {
        $this->requirePermission('roles.create');
        return View::render('Roles::create', ['title' => 'Create Role'], 'app');
    }

    public function store(Request $request)
    {
        $this->requirePermission('roles.create');

        $name = trim((string) $request->input('name'));
        if ($name === '') {
            Response::abort(400, 'Role name is required.');
        }

        $this->service->create($name);

        header('Location: /roles');
        exit;
    }

    public function edit(Request $request, $id)
    {
        $this->requirePermission('roles.update');

        $roleId = (int) $id;
        if ($roleId <= 0) {
            Response::abort(400);
        }

        $role = $this->service->find($roleId);
        if (!$role) {
            Response::abort(404);
        }

        return View::render('Roles::edit', ['role' => $role, 'title' => 'Edit Role'], 'app');
    }

    public function update(Request $request, $id)
    {
        $this->requirePermission('roles.update');

        $roleId = (int) $id;
        if ($roleId <= 0) {
            Response::abort(400);
        }

        $name = trim((string) $request->input('name'));
        if ($name === '') {
            Response::abort(400, 'Role name is required.');
        }

        if (!$this->service->find($roleId)) {
            Response::abort(404);
        }

        $this->service->update($roleId, $name);
        header('Location: /roles');
        exit;
    }

    public function delete(Request $request, $id)
    {
        $this->requirePermission('roles.update');

        $roleId = (int) $id;
        if ($roleId <= 0) {
            Response::abort(400);
        }

        if (!$this->service->find($roleId)) {
            Response::abort(404);
        }

        $this->service->delete($roleId);
        header('Location: /roles');
        exit;
    }

    public function permissions(Request $request, $id)
    {
        $this->requirePermission('roles.assign');

        $roleId = (int) $id;
        if (!$role = $this->service->find($roleId)) {
            Response::abort(404);
        }

        $rolePermissions   = $this->service->getRolePermissions($roleId);
        $rolePermissionIds = array_column($rolePermissions, 'id');
        $allPermissions    = $this->service->allPermissions();

        return View::render('Roles::permissions', [
            'role'            => $role,
            'rolePermissions' => $rolePermissionIds,
            'allPermissions'  => $allPermissions,
            'title'           => 'Assign Permissions',
        ], 'app');
    }

    public function updatePermissions(Request $request, $id)
    {
        $this->requirePermission('roles.assign');

        $roleId = (int) $id;
        if ($roleId <= 0) {
            Response::abort(400);
        }

        if (!$this->service->find($roleId)) {
            Response::abort(404);
        }

        $permissionIds = $request->input('permissions', []);
        if (!is_array($permissionIds)) {
            Response::abort(400);
        }

        $this->service->assignPermissions($roleId, $permissionIds);
        header("Location: /roles/{$roleId}/permissions");
        exit;
    }
}
