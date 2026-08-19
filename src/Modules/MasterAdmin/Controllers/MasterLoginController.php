<?php

namespace App\Modules\MasterAdmin\Controllers;

use App\Core\Request;
use App\Core\View;
use App\Modules\MasterAdmin\Services\MasterAuthService;
use RuntimeException;

class MasterLoginController
{
    private MasterAuthService $auth;

    public function __construct(?MasterAuthService $auth = null)
    {
        $this->auth = $auth ?? new MasterAuthService();
    }

    public function index(): void
    {
        $this->startSession();
        $this->guardDeployment();

        if (!empty($_SESSION['user']) && !empty($_SESSION['is_super_admin'])) {
            header('Location: /master/tenants');
            exit;
        }

        View::render('MasterAdmin::login', [
            'title'      => 'Platform Admin Sign In',
            'csrf_token' => $this->csrfToken(),
            'flash'      => $_SESSION['flash'] ?? null,
        ], 'guest');

        unset($_SESSION['flash']);
    }

    public function store(Request $request): void
    {
        $this->startSession();
        $this->guardDeployment();

        $this->assertValidCsrf($request->input('csrf_token'));

        $email    = mb_strtolower(trim((string) $request->input('email')));
        $password = (string) $request->input('password');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            $this->fail('Please enter a valid email and password.');
        }

        if (!$this->auth->attempt($email, $password)) {
            $this->fail('Invalid email or password.');
        }

        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        header('Location: /master/tenants');
        exit;
    }

    // ── helpers ──────────────────────────────────────────────

    /**
     * Defense in depth: master-admin routes should only ever be
     * reachable on the platform's admin host — the admin subdomain
     * in dynamic-domain mode, or a deployment with a blank [tenant]
     * code in legacy mode. TenantContext::isAdminHost() already
     * accounts for both (see bootstrap/app.php). If someone reaches
     * these routes on an actual tenant host, refuse outright.
     */
    private function guardDeployment(): void
    {
        if (!\App\Core\TenantContext::isAdminHost()) {
            http_response_code(404);
            exit('Not found.');
        }
    }

    private function fail(string $message): never
    {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => $message];
        header('Location: /master/login');
        exit;
    }

    private function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    private function assertValidCsrf(?string $token): void
    {
        if (empty($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
            throw new RuntimeException('Invalid CSRF token.');
        }
    }

    private function startSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }
}
