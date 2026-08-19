<?php

namespace App\Modules\Auth\Controllers;

use App\Modules\Auth\Services\AuthService;
use App\Core\Controller;
use App\Core\View;
use App\Core\Request;
use RuntimeException;

class LoginController extends Controller
{
    private AuthService $auth;

    private const MAX_ATTEMPTS = 5;
    private const LOCKOUT_SECONDS = 120;

    public function __construct(?AuthService $auth = null)
    {
        $this->auth = $auth ?? new AuthService();
    }

    // ─────────────────────────────────────────────
    // LOGIN PAGE
    // ─────────────────────────────────────────────
    public function index(): void
    {
        $this->startSession();

        if (!empty($_SESSION['user'])) {
            header('Location: /dashboard');
            exit;
        }

        $tenant = $this->auth->getTenantContext();

        View::render('Auth::login', [
            'title'        => 'Sign In',
            'csrf_token'   => $this->getCsrfToken(),
            'company_logo' => $tenant['tenant_logo'],
            'tenant_name'  => $tenant['tenant_name'],
            'old'          => $_SESSION['old'] ?? [],
            'flash'        => $_SESSION['flash'] ?? null,
        ], 'guest');

        unset($_SESSION['flash'], $_SESSION['old']);
    }

    // ─────────────────────────────────────────────
    // HANDLE LOGIN
    // ─────────────────────────────────────────────
    public function store(Request $request): void
    {
        $this->startSession();

        // Enforce POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit('Method Not Allowed');
        }

        // CSRF
        try {
            $this->assertValidCsrf($request->input('csrf_token'));
        } catch (\Throwable) {
            $this->fail('Session expired. Please refresh and try again.');
        }

        $email    = mb_strtolower(trim((string) $request->input('email')));
        $password = (string) $request->input('password');

        $_SESSION['old'] = ['email' => $email];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            $this->fail('Please enter a valid email and password.');
        }

        // ── THROTTLING ────────────────────────────

        $now = time();

        $_SESSION['login'] = $_SESSION['login'] ?? [
            'attempts' => 0,
            'last_attempt' => 0,
        ];

        if (
            $_SESSION['login']['attempts'] >= self::MAX_ATTEMPTS &&
            ($now - $_SESSION['login']['last_attempt']) < self::LOCKOUT_SECONDS
        ) {
            sleep(2); // slow down brute force
            $this->fail('Too many attempts. Please wait and try again.');
        }

        // ── AUTH ATTEMPT ──────────────────────────

        if (!$this->auth->attempt($email, $password)) {
            $_SESSION['login']['attempts']++;
            $_SESSION['login']['last_attempt'] = $now;

            sleep(1); // constant delay
            $this->fail('Invalid email or password.');
        }

        // ── SUCCESS ───────────────────────────────

        unset($_SESSION['login'], $_SESSION['old']);

        // Rotate CSRF on successful login
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        header('Location: /dashboard');
        exit;
    }

    // ─────────────────────────────────────────────
    // FAILURE HANDLER
    // ─────────────────────────────────────────────
    private function fail(string $message): never
    {
        $_SESSION['flash'] = [
            'type'    => 'danger',
            'message' => $message,
        ];

        header('Location: /login', true, 302);
        exit;
    }

    // ─────────────────────────────────────────────
    // CSRF
    // ─────────────────────────────────────────────
    private function getCsrfToken(): string
    {
        $this->startSession();

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
            throw new RuntimeException('Invalid CSRF token');
        }
    }

    // ─────────────────────────────────────────────
    // SESSION
    // ─────────────────────────────────────────────
    private function startSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start([
                'cookie_httponly' => true,
                'cookie_secure'   => isset($_SERVER['HTTPS']),
                'cookie_samesite' => 'Lax',
                'use_strict_mode' => true,
            ]);
        }
    }
}