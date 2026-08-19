<?php

namespace App\Modules\Tenant\Controllers;

use App\Core\Request;
use App\Core\View;
use App\Modules\Tenant\Repositories\TenantRepository;
use App\Modules\Tenant\Services\TenantService;
use InvalidArgumentException;
use RuntimeException;

class TenantController
{
    private TenantService $tenantService;
    private TenantRepository $tenantRepo;

    public function __construct()
    {
        $this->tenantService = new TenantService();
        $this->tenantRepo    = new TenantRepository();
    }

    /**
     * Shared by both the Connection screen and the New Tenant
     * "hosted separately" section — takes raw connection details
     * (never a tenant id, never touches the database), tries an
     * actual connection, and reports what it found. Nothing is
     * saved here.
     */
    public function testConnection(Request $request): void
    {
        $details = [
            'host'       => trim((string) $request->input('host', '')),
            'port'       => (int) $request->input('port', 3306),
            'database'   => trim((string) $request->input('database', '')),
            'username'   => trim((string) $request->input('username', '')),
            'password'   => (string) $request->input('password', ''),
            'ssl'        => !empty($request->input('ssl')),
            'ssl_verify' => !empty($request->input('ssl_verify')),
            'ssl_ca'     => trim((string) $request->input('ssl_ca', '')) ?: null,
            'ssl_cert'   => trim((string) $request->input('ssl_cert', '')) ?: null,
            'ssl_key'    => trim((string) $request->input('ssl_key', '')) ?: null,
        ];

        $result = \App\Core\TenantConnectionManager::testConnection($details);

        \App\Core\Response::json($result, $result['success'] ? 200 : 422);
    }

    public function editConnection(Request $request, int $id): void
    {
        $tenant = $this->tenantRepo->findById($id);
        if (!$tenant) {
            \App\Core\Response::abort(404, 'Tenant not found.');
        }

        View::render('Tenant::connection', [
            'title'      => 'Connection — ' . $tenant['name'],
            'tenant'     => $tenant,
            'csrf_token' => csrf_token(),
            'errors'     => $_SESSION['errors'] ?? [],
            'flash'      => $_SESSION['flash'] ?? null,
        ], 'master');

        unset($_SESSION['errors'], $_SESSION['flash']);
    }

    public function updateConnection(Request $request, int $id): void
    {
        $tenant = $this->tenantRepo->findById($id);
        if (!$tenant) {
            \App\Core\Response::abort(404, 'Tenant not found.');
        }

        try {
            $host = trim((string) $request->input('host', ''));
            $port = (int) $request->input('port', 3306);
            $db   = trim((string) $request->input('database', ''));
            $user = trim((string) $request->input('username', ''));
            $pass = (string) $request->input('password', '');

            if ($host === '' || $db === '' || $user === '') {
                throw new InvalidArgumentException('Host, database name, and username are required.');
            }
            if ($port <= 0 || $port > 65535) {
                throw new InvalidArgumentException('Port must be a valid port number.');
            }

            $details = [
                'host'          => $host,
                'port'          => $port,
                'database'      => $db,
                'username'      => $user,
                'password'      => $pass,
                'charset'       => 'utf8mb4',
                'ssl'           => !empty($request->input('ssl')),
                'ssl_verify'    => !empty($request->input('ssl_verify')),
                'ssl_ca'        => trim((string) $request->input('ssl_ca', '')) ?: null,
                'ssl_cert'      => trim((string) $request->input('ssl_cert', '')) ?: null,
                'ssl_key'       => trim((string) $request->input('ssl_key', '')) ?: null,
                'persistent'    => !empty($request->input('persistent')),
                'failover_host' => trim((string) $request->input('failover_host', '')) ?: null,
                'failover_port' => $request->input('failover_port') ? (int) $request->input('failover_port') : null,
            ];

            $encrypted = \App\Core\ConnectionCrypto::encrypt($details);

            $this->tenantRepo->updateConnectionString($id, $encrypted);

            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Connection details saved and encrypted.'];
        } catch (\Throwable $e) {
            $_SESSION['errors'] = [$e->getMessage()];
        }

        header("Location: /master/tenants/{$id}/connection");
        exit;
    }

    public function clearConnection(Request $request, int $id): void
    {
        $tenant = $this->tenantRepo->findById($id);
        if (!$tenant) {
            \App\Core\Response::abort(404, 'Tenant not found.');
        }

        $this->tenantRepo->updateConnectionString($id, null);

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Custom connection cleared — this tenant now uses the default shared connection again.'];

        header("Location: /master/tenants/{$id}/connection");
        exit;
    }

    public function uploadLogo(): void
    {
        // Ensure session is already started in bootstrap
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        // Enforce POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit('Method Not Allowed');
        }

        // Auth check
        if (empty($_SESSION['user'])) {
            header('Location: /login');
            exit;
        }

        // Validate tenant
        // FIX: was reading $_SESSION['user']['tenant_id'], a key
        // AuthService never actually sets — this always evaluated to
        // 0 and rejected every single upload attempt with a 403
        // before the file was ever even looked at.
        $tenantId = \App\Core\TenantContext::hasTenant()
            ? (int) (\App\Core\TenantContext::tenant()['id'] ?? 0)
            : 0;
        if ($tenantId <= 0) {
            http_response_code(403);
            exit('Invalid tenant');
        }

        // CSRF protection
        if (
            empty($_POST['csrf_token']) ||
            empty($_SESSION['csrf_token']) ||
            !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
        ) {
            http_response_code(403);
            exit('Invalid CSRF token');
        }

        // Validate file
        if (
            empty($_FILES['logo']) ||
            $_FILES['logo']['error'] === UPLOAD_ERR_NO_FILE ||
            !is_uploaded_file($_FILES['logo']['tmp_name'])
        ) {
            $_SESSION['flash']['error'] = 'No file uploaded';
            header('Location: /settings');
            exit;
        }

        // Call service (returns the stored web path on success, or false)
        $webPath = $this->tenantService->uploadAndSaveLogo(
            $_FILES['logo'],
            $tenantId
        );

        if (!$webPath) {
            $_SESSION['flash']['error'] = 'Logo upload failed';
        } else {
            $_SESSION['flash']['success'] = 'Logo updated successfully';

            // Immediate UI consistency
            $_SESSION['user']['tenant_logo'] = $webPath;
        }
        header('Location: /settings/company');
        exit;
    }

    // ── Super-admin tenant management (/master/tenants/*) ────

    public function index(): void
    {
        View::render('Tenant::index', [
            'title'   => 'Tenants',
            'tenants' => $this->tenantRepo->all(),
            'flash'   => $_SESSION['flash'] ?? null,
        ], 'master');

        unset($_SESSION['flash']);
    }

    public function create(): void
    {
        View::render('Tenant::create', [
            'title'       => 'New Tenant',
            'csrf_token'  => csrf_token(),
            'errors'      => $_SESSION['errors'] ?? [],
            'old'         => $_SESSION['old'] ?? [],
            'base_domain' => trim((string) config('platform.base_domain', '')),
        ], 'master');

        unset($_SESSION['errors'], $_SESSION['old']);
    }

    public function store(Request $request): void
    {
        $input = [
            'name'             => $request->input('name', ''),
            'code'             => $request->input('code', ''),
            'plan'             => $request->input('plan', 'starter'),
            'custom_domain'    => $request->input('custom_domain', ''),
            'admin_first_name' => $request->input('admin_first_name', ''),
            'admin_last_name'  => $request->input('admin_last_name', ''),
            'admin_email'      => $request->input('admin_email', ''),
            'admin_password'   => $request->input('admin_password', ''),

            // "Hosted separately" — this client's database already
            // exists on infrastructure they (or you, on their behalf)
            // set up and migrated by hand, not on this server via
            // DirectAdmin. When set, provisioning connects straight
            // to what's entered here instead of creating anything.
            'hosted_separately' => !empty($request->input('hosted_separately')),
            'conn_host'         => $request->input('conn_host', ''),
            'conn_port'         => $request->input('conn_port', 3306),
            'conn_database'     => $request->input('conn_database', ''),
            'conn_username'     => $request->input('conn_username', ''),
            'conn_password'     => $request->input('conn_password', ''),
            'conn_ssl'          => !empty($request->input('conn_ssl')),
            'conn_ssl_verify'   => !empty($request->input('conn_ssl_verify')),
        ];

        try {
            $tenant = $this->tenantService->provisionTenant($input);
        } catch (InvalidArgumentException $e) {
            $_SESSION['errors'] = [$e->getMessage()];
            $_SESSION['old']    = $input;
            header('Location: /master/tenants/create');
            exit;
        } catch (RuntimeException $e) {
            error_log('TenantController::store — ' . $e->getMessage());
            $_SESSION['errors'] = ['Could not create the tenant. Check the server log for details.'];
            $_SESSION['old']    = $input;
            header('Location: /master/tenants/create');
            exit;
        }

        $_SESSION['flash'] = [
            'type'    => 'success',
            'message' => "Tenant '{$tenant['name']}' created. Admin login: {$tenant['admin_email']}.",
        ];

        // The config.ini snippet is shown once on the tenant list —
        // simplest place that's guaranteed to render right after
        // creation without a dedicated "show" route.
        $_SESSION['new_tenant_snippet'] = $tenant['config_snippet'];

        header('Location: /master/tenants');
        exit;
    }
}