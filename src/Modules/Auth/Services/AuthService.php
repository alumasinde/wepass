<?php

declare(strict_types=1);

namespace App\Modules\Auth\Services;

use App\Modules\Auth\Repositories\UserRepository;
use App\Core\Permission;
use App\Core\Tenant;
use App\Core\DB;
use App\Core\Mailer;
use DateTimeImmutable;
use RuntimeException;

class AuthService
{
    private UserRepository $users;

    public function __construct(?UserRepository $users = null)
    {
        $this->users = $users ?? new UserRepository();
    }

    // ── SESSION SAFETY ───────────────────────────────────────

    private function ensureSessionStarted(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    // ── LOGIN ────────────────────────────────────────────────

    public function attempt(string $email, string $password): bool
    {
        $this->ensureSessionStarted();

        $user = $this->users->findActiveByEmail($email);

        // Constant-time mitigation (fake verify if user not found)
        $hash = $user['password_hash'] ?? password_hash('dummy', PASSWORD_DEFAULT);

        if (!password_verify($password, $hash) || !$user) {
            return false;
        }

        // Opportunistic rehash
        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            $this->users->updatePassword(
                (int) $user['id'],
                password_hash($password, PASSWORD_DEFAULT)
            );
        }

        session_regenerate_id(true);

        // Load permissions
        $permission = new Permission(DB::connect());
        $permission->loadForUser((int) $user['id']);

        // Store session. auth_version is the server-side revocation
        // anchor checked by AuthMiddleware on every authenticated request.
        $_SESSION['user'] = [
            'id'            => (int) $user['id'],
            'email'         => $user['email'],
            'first_name'    => $user['first_name'],
            'last_name'     => $user['last_name'],
            'role'          => $user['role'],
            'role_id'       => $user['role_id'],
            'department_id' => isset($user['department_id']) ? (int) $user['department_id'] : null,
        ];
        $_SESSION['auth_version'] = (int) ($user['auth_version'] ?? 1);

        // Tenant context
        Tenant::set([
            'code' => config('tenant.code'),
            'name' => config('tenant.name'),
            'plan' => config('tenant.plan'),
            'logo' => config('tenant.logo'),
        ]);

        return true;
    }

    // ── LOGOUT ───────────────────────────────────────────────

    public function logout(): void
    {
        $this->ensureSessionStarted();

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
        Tenant::clear();
    }

    // ── PASSWORD RESET ───────────────────────────────────────

    public function createResetTokenByEmail(string $email): string
    {
        $user = $this->users->findActiveByEmail($email);

        if (!$user) {
            // Prevent enumeration
            throw new RuntimeException('User not found.');
        }

        $expiresAt = (new DateTimeImmutable('+1 hour'));

        // Delegate token generation to repository (single source of truth)
        $rawToken = $this->users->createPasswordResetToken(
            (int) $user['id'],
            $expiresAt
        );

        $this->sendResetEmail($user['email'], $user['first_name'] ?? '', $rawToken);

        return $rawToken;
    }

    private function sendResetEmail(string $email, string $firstName, string $rawToken): void
    {
        $resetUrl = rtrim((string) config('app.url', ''), '/')
            . '/reset-password?token=' . urlencode($rawToken);

        $greeting = $firstName !== '' ? htmlspecialchars($firstName) : 'there';
        $tenant   = htmlspecialchars((string) config('tenant.name', 'Glee GPMS'));

        $html = '<p>Hi ' . $greeting . ',</p>'
            . '<p>We received a request to reset your ' . $tenant . ' password. This link expires in 1 hour:</p>'
            . '<p><a href="' . $resetUrl . '">' . $resetUrl . '</a></p>'
            . '<p>If you did not request this, you can safely ignore this email &mdash; your password will not change.</p>';

        Mailer::send($email, "Reset your {$tenant} password", $html);
    }

    public function resetPassword(string $rawToken, string $password): void
    {
        if (strlen($password) < 8) {
            throw new RuntimeException('Password must be at least 8 characters.');
        }

        $user = $this->users->findByValidResetToken($rawToken);

        if (!$user) {
            throw new RuntimeException('Invalid or expired reset token.');
        }

        // resetPasswordWithToken atomically changes the password and
        // increments auth_version, revoking all existing sessions.
        $this->users->resetPasswordWithToken(
            (int) $user['id'],
            $rawToken,
            password_hash($password, PASSWORD_DEFAULT)
        );
    }

    // ── TENANT CONTEXT ───────────────────────────────────────

    public function getTenantContext(): array
    {
        return [
            'tenant_name' => config('tenant.name', 'Glee GPMS'),
            'tenant_logo' => config('tenant.logo', '/assets/images/default-logoo.png'),
            'tenant_code' => config('tenant.code', ''),
            'tenant_plan' => config('tenant.plan', 'starter'),
        ];
    }
}
