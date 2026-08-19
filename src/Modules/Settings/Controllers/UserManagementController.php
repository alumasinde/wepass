<?php

namespace App\Modules\Settings\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use PDO;

/**
 * UserManagementController — per-database isolation model.
 * No tenant_id column filtering needed.
 */
class UserManagementController extends Controller
{
    private PDO $db;

    public function __construct()
    {
        if (!Auth::check()) {
            Response::redirect('/login');
        }
        $this->db = DB::connect();
    }

    public function index()
    {
        $stmt = $this->db->query("
            SELECT u.*,
                   GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ', ') AS role,
                   d.name AS department_name
            FROM users u
            LEFT JOIN user_roles ur ON ur.user_id = u.id
            LEFT JOIN roles       r  ON r.id       = ur.role_id
            LEFT JOIN departments d  ON d.id       = u.department_id
            GROUP BY u.id
            ORDER BY u.id DESC
        ");

        return $this->view('Settings::users/index', [
            'title' => 'Users',
            'users' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        ]);
    }

    public function create()
    {
        $roles       = $this->db->query("SELECT id, name FROM roles ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
        $departments = $this->db->query("SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

        return $this->view('Settings::users/create', [
            'title'       => 'Create User',
            'roles'       => $roles,
            'departments' => $departments,
        ]);
    }

public function store(Request $request)
{
    try {
        $email     = strtolower(trim((string) $request->input('email')));
        $username  = strtolower(trim((string) $request->input('username')));
        $firstName = trim((string) $request->input('first_name'));
        $lastName  = trim((string) $request->input('last_name'));
        $password  = (string) $request->input('password');
        $roles     = $request->input('roles', []);
        $is_active = $request->input('is_active') ? 1 : 0;
        $deptId    = (int) $request->input('department_id') ?: null;

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Invalid email.');
        }
        if ($username === '' || $password === '') {
            throw new \RuntimeException('Username and password are required.');
        }

        $check = $this->db->prepare("SELECT 1 FROM users WHERE email = :email OR username = :username LIMIT 1");
        $check->execute([':email' => $email, ':username' => $username]);
        if ($check->fetch()) {
            throw new \RuntimeException('Email or username already in use.');
        }

        DB::transaction(function () use ($email, $username, $firstName, $lastName, $password, $roles, $is_active, $deptId) {
            $this->db->prepare("
                INSERT INTO users (email, password_hash, first_name, last_name, username, department_id, is_active)
                VALUES (:email, :password, :first_name, :last_name, :username, :dept, :is_active)
            ")->execute([
                ':email'      => $email,
                ':password'   => password_hash($password, PASSWORD_DEFAULT),
                ':first_name' => $firstName,
                ':last_name'  => $lastName,
                ':username'   => $username,
                ':dept'       => $deptId,
                ':is_active'  => $is_active,
            ]);

            $userId = (int) $this->db->lastInsertId();

            if (!empty($roles)) {
                $roleStmt = $this->db->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)");
                foreach ($roles as $roleId) {
                    $roleStmt->execute([':user_id' => $userId, ':role_id' => (int) $roleId]);
                }
            }
        });

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'User created successfully.'];
        return $this->redirect('/settings/users');

    } catch (\Throwable $e) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => $e->getMessage()];
        return $this->redirect('/settings/users/create');
    }
}

    public function edit(Request $request, int $id)
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $editUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$editUser) {
            return $this->redirect('/settings/users');
        }

        $roles       = $this->db->query("SELECT id, name FROM roles ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
        $departments = $this->db->query("SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

        $urStmt = $this->db->prepare("SELECT role_id FROM user_roles WHERE user_id = :user_id");
        $urStmt->execute([':user_id' => $id]);
        $userRoles = array_column($urStmt->fetchAll(PDO::FETCH_ASSOC), 'role_id');

        return $this->view('Settings::users/edit', [
            'title'       => 'Edit User',
            'userData'    => $editUser,
            'roles'       => $roles,
            'userRoles'   => $userRoles,
            'departments' => $departments,
        ]);
    }

    public function update(Request $request, int $id)
    {
        $email     = strtolower(trim((string) $request->input('email')));
        $username  = strtolower(trim((string) $request->input('username')));
        $firstName = trim((string) $request->input('first_name'));
        $lastName  = trim((string) $request->input('last_name'));
        $roleIds   = $request->input('roles', []);
        $deptId    = (int) $request->input('department_id') ?: null;
        $is_active = $request->input('is_active') ? 1 : 0;

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Invalid email.');
        }

        $check = $this->db->prepare("SELECT 1 FROM users WHERE (email = :email OR username = :username) AND id != :id LIMIT 1");
        $check->execute([':email' => $email, ':username' => $username, ':id' => $id]);
        if ($check->fetch()) {
            throw new \RuntimeException('Email or username already in use.');
        }

        DB::transaction(function () use ($id, $email, $username, $firstName, $lastName, $roleIds, $deptId, $is_active) {
            $this->db->prepare("
                UPDATE users
                SET first_name    = :first_name,
                    last_name     = :last_name,
                    email         = :email,
                    username      = :username,
                    department_id = :dept,
                    is_active     = :is_active
                WHERE id = :id
            ")->execute([
                ':first_name' => $firstName,
                ':last_name'  => $lastName,
                ':email'      => $email,
                ':username'   => $username,
                ':dept'       => $deptId,
                ':is_active'  => $is_active,
                ':id'         => $id,
            ]);

            $this->db->prepare("DELETE FROM user_roles WHERE user_id = :user_id")->execute([':user_id' => $id]);

            if (!empty($roleIds)) {
                $roleStmt = $this->db->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)");
                foreach ($roleIds as $roleId) {
                    $roleStmt->execute([':user_id' => $id, ':role_id' => (int) $roleId]);
                }
            }
        });

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'User updated.'];
        return $this->redirect("/settings/users/{$id}/edit");
    }

    public function profile()
    {
        return $this->view('Settings::users/profile', [
            'title' => 'My Profile',
            'user'  => Auth::user(),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $userId    = Auth::id();
        $firstName = trim((string) $request->input('first_name'));
        $lastName  = trim((string) $request->input('last_name'));
        $email     = strtolower(trim((string) $request->input('email')));

        $currentPassword = (string) $request->input('current_password');
        $newPassword     = (string) $request->input('new_password');
        $confirmPassword = (string) $request->input('confirm_password');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid email address.'];
            return $this->redirect('/settings/users/profile');
        }

        try {
            DB::transaction(function () use ($userId, $firstName, $lastName, $email, $currentPassword, $newPassword, $confirmPassword) {
                $this->db->prepare("
                    UPDATE users SET first_name = :fn, last_name = :ln, email = :em WHERE id = :id
                ")->execute([':fn' => $firstName, ':ln' => $lastName, ':em' => $email, ':id' => $userId]);

                if ($newPassword !== '') {
                    if ($newPassword !== $confirmPassword) {
                        throw new \RuntimeException('Passwords do not match.');
                    }
                    $row = $this->db->prepare("SELECT password_hash FROM users WHERE id = :id LIMIT 1");
                    $row->execute([':id' => $userId]);
                    $dbUser = $row->fetch(PDO::FETCH_ASSOC);

                    if (!$dbUser || !password_verify($currentPassword, $dbUser['password_hash'])) {
                        throw new \RuntimeException('Current password is incorrect.');
                    }

                    $this->db->prepare("UPDATE users SET password_hash = :pwd WHERE id = :id")
                             ->execute([':pwd' => password_hash($newPassword, PASSWORD_DEFAULT), ':id' => $userId]);
                }
            });

            $_SESSION['user']['first_name'] = $firstName;
            $_SESSION['user']['last_name']  = $lastName;
            $_SESSION['user']['email']      = $email;

            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Profile updated successfully.'];
            return $this->redirect('/settings/users/profile');

        } catch (\Throwable $e) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => $e->getMessage()];
            return $this->redirect('/settings/users/profile');
        }
    }
}
