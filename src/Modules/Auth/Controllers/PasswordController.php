<?php

namespace App\Modules\Auth\Controllers;

use App\Core\View;
use App\Core\Request;
use App\Core\Audit;
use App\Core\Response;
use App\Modules\Auth\Services\AuthService;
use RuntimeException;

class PasswordController
{
    private AuthService $auth;

    public function __construct(?AuthService $auth = null)
    {
        $this->auth = $auth ?? new AuthService();
    }

    public function forgot()
    {
        return View::render('Auth::forgot-password', [
            'title'      => 'Forgot Password',
            'csrf_token' => $this->generateCsrfToken(),
        ], 'guest');
    }

    public function sendReset(Request $request)
    {
        $this->assertValidCsrf($request->input('csrf_token'));

        // FIX: removed $tenantId = $this->resolveTenantId() — not needed in per-DB model
        $email = trim((string) $request->input('email'));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->flashRedirect('/forgot-password', 'Please enter a valid email address.', 'danger');
        }

        try {
            $this->auth->createResetTokenByEmail($email);
        } catch (\Throwable) {
            // Swallow — prevent enumeration
        }

        return $this->flashRedirect('/forgot-password', 'If the email exists, a reset link was sent.', 'success');
    }

    public function resetForm(Request $request)
    {
        $token = trim((string) $request->input('token'));

        if (!$this->isValidTokenFormat($token)) {
            return Response::abort(400, 'Invalid reset token.');
        }

        return View::render('Auth::reset-password', [
            'title'      => 'Reset Password',
            'token'      => $token,
            'csrf_token' => $this->generateCsrfToken(),
        ], 'guest');
    }

    public function reset(Request $request)
    {
        $this->assertValidCsrf($request->input('csrf_token'));

        // FIX: removed $tenantId = $this->resolveTenantId() — not needed in per-DB model
        $token    = trim((string) $request->input('token'));
        $password = (string) $request->input('password');
        $confirm  = (string) $request->input('password_confirmation');

        if (!$this->isValidTokenFormat($token) || empty($password) || $password !== $confirm) {
            return $this->flashRedirect('/reset-password?token=' . urlencode($token), 'Invalid input.', 'danger');
        }

        try {
            $this->auth->resetPassword($token, $password);

            if (!empty($_SESSION['user'])) {
                Audit::log('user.password_reset', 'user', (int) $_SESSION['user']['id'], [
                    'email' => $_SESSION['user']['email'],
                ]);
            }

            return $this->flashRedirect('/login', 'Password reset successfully. You can now login.', 'success');

        } catch (\Throwable $e) {
            return $this->flashRedirect('/reset-password?token=' . urlencode($token), 'Invalid or expired reset token.', 'danger');
        }
    }

    // ── HELPERS ──────────────────────────────────────────────

    private function flashRedirect(string $url, string $message, string $type): never
    {
        $_SESSION['flash'] = ['message' => $message, 'type' => $type];
        header("Location: $url");
        exit;
    }

    private function isValidTokenFormat(string $token): bool
    {
        return (bool) preg_match('/^[a-f0-9]{64}$/', $token);
    }

    private function generateCsrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    private function assertValidCsrf(?string $token): void
    {
        if (
            empty($token) ||
            empty($_SESSION['csrf_token']) ||
            !hash_equals($_SESSION['csrf_token'], $token)
        ) {
            throw new RuntimeException('Invalid CSRF token.');
        }
    }
}
