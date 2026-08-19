<?php

namespace App\Modules\MasterAdmin\Services;

use App\Modules\MasterAdmin\Repositories\MasterAdminRepository;
use RuntimeException;

class MasterAuthService
{
    private MasterAdminRepository $admins;

    public function __construct(?MasterAdminRepository $admins = null)
    {
        $this->admins = $admins ?? new MasterAdminRepository();
    }

    public function attempt(string $email, string $password): bool
    {
        $admin = $this->admins->findActiveByEmail($email);

        // Constant-time mitigation (fake verify if not found), same
        // pattern as the tenant AuthService.
        $hash = $admin['password_hash'] ?? password_hash('dummy', PASSWORD_DEFAULT);

        if (!password_verify($password, $hash) || !$admin) {
            return false;
        }

        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            $this->admins->updatePassword((int) $admin['id'], password_hash($password, PASSWORD_DEFAULT));
        }

        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id'         => (int) $admin['id'],
            'email'      => $admin['email'],
            'first_name' => $admin['first_name'],
            'last_name'  => $admin['last_name'],
            'role'       => 'Super Admin',
            'role_id'    => null,
        ];

        // Existing Permission::can() already has a super-admin bypass
        // keyed on this flag — reusing it means no changes needed
        // there, and PermissionMiddleware-gated tenant routes simply
        // never apply to a super admin who happens to hit them.
        $_SESSION['is_super_admin'] = true;

        $this->admins->touchLastLogin((int) $admin['id']);

        return true;
    }

    public function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}
