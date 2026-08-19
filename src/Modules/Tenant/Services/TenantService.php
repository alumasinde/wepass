<?php

namespace App\Modules\Tenant\Services;

use App\Core\DB;
use App\Modules\Tenant\Repositories\TenantRepository;
use finfo;
use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

class TenantService
{
    private TenantRepository $tenantRepo;
    private string $uploadPath;

    public function __construct()
    {
        $this->tenantRepo = new TenantRepository();
        // FIX: was '__DIR__ . /../../../public/uploads/tenants/' — that
        // resolves to <project_root>/src/public/uploads/tenants/, which
        // is nested inside src/, nowhere near the actual web root
        // (public_html/, confirmed repeatedly). Uploaded files were
        // being saved somewhere no browser could ever reach.
        $this->uploadPath = base_path('public_html/uploads/tenants/');
    }

    /**
     * @return string|false The web-accessible path now stored for this
     * tenant (e.g. '/uploads/tenants/tenant_2_ab12cd34.png') on success,
     * or false on any failure. Was declared to return bool and actually
     * did — silently returning true/false instead of the path the
     * controller expected as $filename, meaning a "successful" upload
     * still couldn't be used or displayed anywhere afterward.
     */
    public function uploadAndSaveLogo(array $file, int $tenantId): string|false
    {
        // Validate upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return false;
        }

        // Limit size (2MB)
        if ($file['size'] > 2 * 1024 * 1024) {
            return false;
        }

        // Validate MIME
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);

        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
        ];

        if (!isset($allowed[$mime])) {
            return false;
        }

        // Generate filename
        $filename = sprintf(
            'tenant_%d_%s.%s',
            $tenantId,
            bin2hex(random_bytes(8)),
            $allowed[$mime]
        );

        $destination = $this->uploadPath . $filename;

        // Ensure directory exists
        if (!is_dir($this->uploadPath)) {
            mkdir($this->uploadPath, 0755, true);
        }

        // Move file
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            return false;
        }

        // Delete old logo (if exists)
        $this->deleteOldLogo($tenantId);

        // Store a root-relative URL path, not a bare filename —
        // sidebar.php uses this value directly as <img src="...">.
        // A bare filename would resolve relative to whatever page
        // you're currently viewing, not to a fixed location, so it
        // would render as a broken image on every page except by
        // accident. deleteOldLogo() already strips this back down to
        // a bare filename via basename() before using it, so storing
        // the full path here doesn't break that.
        $webPath = '/uploads/tenants/' . $filename;

        if (!$this->tenantRepo->updateLogo($tenantId, $webPath)) {
            return false;
        }

        return $webPath;
    }

    private function deleteOldLogo(int $tenantId): void
    {
        $tenant = $this->tenantRepo->findById($tenantId);

        if (!$tenant || empty($tenant['logo'])) {
            return;
        }

        $oldFile = $this->uploadPath . basename($tenant['logo']);

        if (is_file($oldFile)) {
            unlink($oldFile);
        }
    }

    // ── TENANT PROVISIONING (super-admin "create tenant" flow) ──

    /**
     * Provisions a brand new tenant end-to-end:
     *   1. Validates the code is unique and well-formed.
     *   2. Creates the tenant's own database.
     *   3. Runs 001_schema.sql then 002_seed_reference.sql against it
     *      (reference/lookup data only — no personal or demo data).
     *   4. Creates that tenant's own first admin user from the input
     *      given (never a shared/reused account).
     *   5. Seeds sane default tenant_settings.
     *   6. Registers the tenant in the glee_master registry.
     *
     * Returns the created tenant row plus a ready-to-copy config.ini
     * snippet for whoever sets up that client's deployment — this
     * method provisions the DATABASE and registry entry; pointing an
     * actual web deployment (vhost/subdomain + this codebase +
     * that config.ini) at it is still a server-level step done
     * outside the app.
     *
     * @param array{
     *   name: string, code: string, plan?: string,
     *   admin_first_name: string, admin_last_name: string,
     *   admin_email: string, admin_password: string
     * } $input
     */
    public function provisionTenant(array $input): array
    {
        $name  = trim((string) ($input['name'] ?? ''));
        $code  = $this->normalizeCode((string) ($input['code'] ?? ''));
        $plan  = trim((string) ($input['plan'] ?? 'starter')) ?: 'starter';

        $adminFirst = trim((string) ($input['admin_first_name'] ?? ''));
        $adminLast  = trim((string) ($input['admin_last_name'] ?? ''));
        $adminEmail = mb_strtolower(trim((string) ($input['admin_email'] ?? '')));
        $adminPass  = (string) ($input['admin_password'] ?? '');
        $customDomain = mb_strtolower(trim((string) ($input['custom_domain'] ?? '')));

        $this->validateProvisionInput($name, $code, $adminFirst, $adminEmail, $adminPass, $customDomain);

        if ($this->tenantRepo->codeExists($code)) {
            throw new InvalidArgumentException("Tenant code '{$code}' is already taken.");
        }

        if ($customDomain !== '' && $this->tenantRepo->customDomainExists($customDomain)) {
            throw new InvalidArgumentException("Custom domain '{$customDomain}' is already in use by another tenant.");
        }

        if (!empty($input['hosted_separately'])) {
            return $this->provisionHostedSeparately(
                $input, $name, $code, $plan, $adminFirst, $adminLast, $adminEmail, $adminPass, $customDomain
            );
        }

        return $this->provisionOnThisServer(
            $name, $code, $plan, $adminFirst, $adminLast, $adminEmail, $adminPass, $customDomain
        );
    }

    /**
     * "Database hosted separately" — this client's database already
     * exists somewhere else entirely (their own server, a laptop
     * being used as a test server, whatever), already has the schema
     * and reference seed run against it by hand (see
     * database/001_schema.sql through database/011_visitor_notes.sql
     * — deliberately NOT run automatically here; this app has no
     * business running schema-altering SQL against infrastructure it
     * doesn't own). This just connects to what's already there,
     * creates the first admin user, and stores the encrypted
     * connection details — no database is created or migrated by
     * this method at all.
     */
    private function provisionHostedSeparately(
        array $input,
        string $name,
        string $code,
        string $plan,
        string $adminFirst,
        string $adminLast,
        string $adminEmail,
        string $adminPass,
        string $customDomain
    ): array {
        $host      = trim((string) ($input['conn_host'] ?? ''));
        $port      = (int) ($input['conn_port'] ?? 3306);
        $database  = trim((string) ($input['conn_database'] ?? ''));
        $username  = trim((string) ($input['conn_username'] ?? ''));
        $password  = (string) ($input['conn_password'] ?? '');
        $ssl       = !empty($input['conn_ssl']);
        $sslVerify = !empty($input['conn_ssl_verify']);

        if ($host === '' || $database === '' || $username === '') {
            throw new InvalidArgumentException('Host, database name, and username are required for a separately-hosted tenant.');
        }
        if ($port <= 0 || $port > 65535) {
            throw new InvalidArgumentException('Port must be a valid port number.');
        }

        $connectionDetails = [
            'host'       => $host,
            'port'       => $port,
            'database'   => $database,
            'username'   => $username,
            'password'   => $password,
            'charset'    => 'utf8mb4',
            'ssl'        => $ssl,
            'ssl_verify' => $sslVerify,
        ];

        try {
            $tenantDb = new PDO(
                "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_TIMEOUT            => 10,
                ]
            );
        } catch (PDOException $e) {
            throw new RuntimeException(
                "Could not connect to {$host}:{$port}/{$database} — confirm the schema/seed migrations have " .
                "already been run against it, this server can actually reach it (DNS/firewall/tunnel), and " .
                "the credentials entered are correct. Underlying error: " . $e->getMessage()
            );
        }

        // The single most likely mistake here is registering before
        // running the migrations. Catching it explicitly turns that
        // into a clear, specific message instead of a confusing
        // failure a few lines further down when the INSERT hits a
        // table that doesn't exist.
        $hasUsersTable = $tenantDb->query("SHOW TABLES LIKE 'users'")->fetchColumn();
        if (!$hasUsersTable) {
            throw new RuntimeException(
                "Connected to {$host}:{$port}/{$database} successfully, but it doesn't look like the schema " .
                "has been set up yet (no 'users' table found). Run database/001_schema.sql through " .
                "011_visitor_notes.sql, in order, against it first — then register this tenant again."
            );
        }

        try {
            $username_ = $this->deriveUsername($tenantDb, $adminEmail);

            $tenantDb->prepare("
                INSERT INTO users (email, password_hash, first_name, last_name, username)
                VALUES (:email, :hash, :first, :last, :username)
            ")->execute([
                ':email'    => $adminEmail,
                ':hash'     => password_hash($adminPass, PASSWORD_DEFAULT),
                ':first'    => $adminFirst,
                ':last'     => $adminLast,
                ':username' => $username_,
            ]);

            $adminUserId = (int) $tenantDb->lastInsertId();

            $tenantDb->prepare("
                INSERT INTO user_roles (user_id, role_id) VALUES (:uid, 1)
            ")->execute([':uid' => $adminUserId]);

            $this->seedDefaultTenantSettings($tenantDb);
        } catch (Throwable $e) {
            // Deliberately no cleanup/rollback of the external
            // database here — this app never created it, so it's
            // not this app's place to drop or alter it further just
            // because this one step failed. Only the app's own
            // registration record is at risk, and that simply never
            // gets written if this throws.
            throw new RuntimeException(
                'Connected successfully, but setting up the first admin user failed: ' . $e->getMessage(),
                previous: $e
            );
        }

        $encryptedConnectionString = \App\Core\ConnectionCrypto::encrypt($connectionDetails);

        $tenantId = $this->tenantRepo->create([
            'name'              => $name,
            'code'              => $code,
            'db_name'           => $database, // informational display only in this mode — connection_string is what TenantConnectionManager actually uses
            'db_username'       => null,
            'connection_string' => $encryptedConnectionString,
            'plan'              => $plan,
            'email'             => $adminEmail,
            'custom_domain'     => $customDomain !== '' ? $customDomain : null,
        ]);

        return [
            'id'             => $tenantId,
            'name'           => $name,
            'code'           => $code,
            'db_name'        => $database,
            'plan'           => $plan,
            'admin_email'    => $adminEmail,
            'custom_domain'  => $customDomain !== '' ? $customDomain : null,
            'config_snippet' => $this->buildConfigSnippet($code, $name, $database, $plan),
        ];
    }

    /**
     * The original provisioning path — creates the tenant's database
     * on THIS server (via DirectAdmin's API on shared hosting, or a
     * direct CREATE DATABASE where that's allowed), runs schema/seed
     * against it, and creates the first admin user. Unchanged from
     * before hosted-separately provisioning existed.
     */
    private function provisionOnThisServer(
        string $name,
        string $code,
        string $plan,
        string $adminFirst,
        string $adminLast,
        string $adminEmail,
        string $adminPass,
        string $customDomain
    ): array {
        $dbNameSuffix    = 'glee_tenant_' . $code;
        $directAdminHost = trim((string) config('directadmin.host', ''));
        $admin           = DB::adminConnection();
        $dbUsername      = null; // set below when using DirectAdmin; stored on the tenant row either way

        if ($directAdminHost !== '') {
            // ── 1. Create the tenant database via DirectAdmin's API ──
            // Shared hosting typically has no CREATE DATABASE privilege
            // over a plain MySQL connection — database creation is
            // locked behind the hosting panel instead, so this calls
            // out to DirectAdmin rather than running SQL directly.
            //
            // One dedicated MySQL user PER TENANT, generated fresh
            // here — not a single shared user reused across every
            // tenant. DirectAdmin's API reliably grants a user access
            // to a database only when both are created together in
            // the same call (confirmed by testing on this host);
            // reusing an existing user across multiple later calls
            // does not reliably apply the grant, even though the API
            // reports success. The password stays the single shared
            // [database] password — only the username varies per
            // tenant, so this needs no per-tenant secret storage.
            //
            // Hash-derived rather than built from $code directly:
            // DirectAdmin prepends the account username to whatever
            // is sent here too (same as the database name), and
            // MySQL usernames have a hard 32-character limit —
            // $code alone can be up to 40 characters (see
            // validateProvisionInput()), which would silently
            // overflow that limit for a long tenant code. A short,
            // fixed-length hash never can, regardless of code length.
            $daClient       = new \App\Core\DirectAdminClient();
            $dbName         = $daClient->buildFullDbName($dbNameSuffix);
            $dbUserSuffix   = 't_' . substr(md5($code), 0, 16);
            $dbUsername     = $daClient->buildFullDbName($dbUserSuffix);

            $this->assertDatabaseNotExists($admin, $dbName);

            $sharedDbPass = (string) config('database.password', '');

            $daClient->createDatabase($dbNameSuffix, $dbUserSuffix, $sharedDbPass);
        } else {
            // ── 1. Create the tenant database directly ───────────
            // (VPS / any host where the [mysql] account already has
            // CREATE DATABASE privilege.)
            $dbName = $dbNameSuffix;

            $this->assertDatabaseNotExists($admin, $dbName);

            $admin->exec("CREATE DATABASE `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }

        try {
            if ($directAdminHost !== '') {
                // Connect with the dedicated user + shared password
                // DirectAdmin just created and granted together with
                // this database — the exact pattern already confirmed
                // reliable, not the previous shared-user assumption.
                //
                // Retried a few times with a short delay regardless:
                // cheap insurance against any grant-propagation delay
                // on this host, harmless if the grant was already
                // effective on the first attempt.
                $tenantDb = $this->connectWithRetry(
                    $this->directAdminMysqlHost(),
                    $dbName,
                    $dbUsername,
                    $sharedDbPass
                );
            } else {
                $tenantDb = DB::adminConnection($dbName);
            }

            // ── 2. Schema + reference seed ───────────────────
            $this->runSqlFile($tenantDb, base_path('database/001_schema.sql'));
            $this->runSqlFile($tenantDb, base_path('database/002_seed_reference.sql'));

            // ── 3. This tenant's own first admin ─────────────
            $username = $this->deriveUsername($tenantDb, $adminEmail);

            $tenantDb->prepare("
                INSERT INTO users (email, password_hash, first_name, last_name, username)
                VALUES (:email, :hash, :first, :last, :username)
            ")->execute([
                ':email'    => $adminEmail,
                ':hash'     => password_hash($adminPass, PASSWORD_DEFAULT),
                ':first'    => $adminFirst,
                ':last'     => $adminLast,
                ':username' => $username,
            ]);

            $adminUserId = (int) $tenantDb->lastInsertId();

            // role_id 1 = 'admin', full permissions — seeded by
            // 002_seed_reference.sql's role_permissions insert.
            $tenantDb->prepare("
                INSERT INTO user_roles (user_id, role_id) VALUES (:uid, 1)
            ")->execute([':uid' => $adminUserId]);

            // ── 4. Default tenant settings ───────────────────
            $this->seedDefaultTenantSettings($tenantDb);

        } catch (Throwable $e) {
            // Best-effort cleanup: don't leave a half-provisioned
            // database behind if any step failed.
            try {
                if ($directAdminHost !== '') {
                    (new \App\Core\DirectAdminClient())->deleteDatabase($dbName);
                } else {
                    $admin->exec("DROP DATABASE IF EXISTS `{$dbName}`");
                }
            } catch (Throwable) {
                // Ignore — surfacing the original error matters more.
            }

            throw new RuntimeException('Tenant provisioning failed: ' . $e->getMessage(), previous: $e);
        }

        // ── 5. Register in the master tenant registry ───────
        $tenantId = $this->tenantRepo->create([
            'name'          => $name,
            'code'          => $code,
            'db_name'       => $dbName,
            'db_username'   => $dbUsername,
            'plan'          => $plan,
            'email'         => $adminEmail,
            'custom_domain' => $customDomain !== '' ? $customDomain : null,
        ]);

        return [
            'id'             => $tenantId,
            'name'           => $name,
            'code'           => $code,
            'db_name'        => $dbName,
            'plan'           => $plan,
            'admin_email'    => $adminEmail,
            'custom_domain'  => $customDomain !== '' ? $customDomain : null,
            'config_snippet' => $this->buildConfigSnippet($code, $name, $dbName, $plan),
        ];
    }

    /**
     * The actual MySQL server host/port — separate from
     * config.ini [directadmin] host/port, which is DirectAdmin's
     * control-panel API (port 2222), not the database server itself.
     * On shared hosting these are almost always the same physical
     * box but reached differently; MySQL is normally still on
     * localhost:3306 from the app's point of view either way.
     */
    /**
     * Retries a fresh MySQL connection a few times with a short
     * delay — used only right after a DirectAdmin API call grants a
     * user access to a brand-new database, since that grant isn't
     * guaranteed to be immediately visible to a new connection on
     * every host. Throws the LAST attempt's exception if every retry
     * fails, so the caller's own error message/cleanup logic is
     * unaffected either way.
     */
    private function connectWithRetry(string $hostAndPort, string $dbName, string $user, string $pass): PDO
    {
        $attempts = 3;
        $delaySeconds = [0, 1, 2];
        $lastException = null;

        for ($i = 0; $i < $attempts; $i++) {
            if ($delaySeconds[$i] > 0) {
                sleep($delaySeconds[$i]);
            }

            try {
                return new PDO(
                    "mysql:host={$hostAndPort};dbname={$dbName};charset=utf8mb4",
                    $user,
                    $pass,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
                );
            } catch (PDOException $e) {
                $lastException = $e;
            }
        }

        throw $lastException;
    }

    private function directAdminMysqlHost(): string
    {
        $host = (string) config('database.host', 'localhost');
        $port = (string) config('database.port', '3306');
        return "{$host};port={$port}";
    }

    private function assertDatabaseNotExists(PDO $admin, string $dbName): void
    {
        $exists = $admin->query("
            SELECT SCHEMA_NAME FROM information_schema.SCHEMATA
            WHERE SCHEMA_NAME = " . $admin->quote($dbName)
        )->fetchColumn();

        if ($exists) {
            throw new InvalidArgumentException("Database '{$dbName}' already exists — choose a different code.");
        }
    }

    /**
     * Subdomains that must never be claimable as a tenant code — real
     * infrastructure already uses these names (or reasonably could),
     * and since the wildcard DNS/SSL setup means EVERY subdomain now
     * resolves to this app, a tenant registering as e.g. "mail" would
     * silently shadow mail.albatechsolutions.co.ke instead of being
     * caught by a DNS/SSL-level failure the way it would have been
     * before the wildcard was set up.
     */
    private const RESERVED_CODES = [
        'www', 'mail', 'webmail', 'smtp', 'pop', 'pop3', 'imap', 'ftp', 'sftp',
        'ns1', 'ns2', 'ns3', 'mx', 'autodiscover', 'autoconfig',
        'cpanel', 'whm', 'webdisk', 'admin', 'api', 'app', 'cdn', 'static',
    ];

    private function validateProvisionInput(
        string $name,
        string $code,
        string $adminFirst,
        string $adminEmail,
        string $adminPass,
        string $customDomain = ''
    ): void {
        if ($name === '') {
            throw new InvalidArgumentException('Company name is required.');
        }

        if (!preg_match('/^[a-z0-9-]{2,40}$/', $code)) {
            throw new InvalidArgumentException('Tenant code must be 2-40 lowercase letters, numbers, or hyphens.');
        }

        // The platform's own admin subdomain is reserved dynamically
        // (read from config, not hardcoded) — stays correct even if
        // [platform] admin_subdomain is ever changed.
        $adminSubdomain = mb_strtolower(trim((string) config('platform.admin_subdomain', 'gpms')));

        if ($code === $adminSubdomain || in_array($code, self::RESERVED_CODES, true)) {
            throw new InvalidArgumentException("'{$code}' is reserved and can't be used as a tenant code.");
        }

        if ($adminFirst === '') {
            throw new InvalidArgumentException("Admin's first name is required.");
        }

        if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('A valid admin email is required.');
        }

        if (strlen($adminPass) < 12) {
            throw new InvalidArgumentException("Admin password must be at least 12 characters.");
        }

        if ($customDomain !== '' && !preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $customDomain)) {
            throw new InvalidArgumentException('Custom domain must be a valid hostname (e.g. gatepass.example.co.ke).');
        }
    }

    private function normalizeCode(string $code): string
    {
        return mb_strtolower(trim($code));
    }

    private function deriveUsername(PDO $tenantDb, string $email): string
    {
        $base = preg_replace('/[^a-z0-9]/', '', explode('@', $email)[0]) ?: 'admin';
        $username = $base;
        $suffix = 1;

        $stmt = $tenantDb->prepare('SELECT 1 FROM users WHERE username = :u LIMIT 1');

        while (true) {
            $stmt->execute([':u' => $username]);
            if (!$stmt->fetchColumn()) {
                return $username;
            }
            $username = $base . $suffix++;
        }
    }

    private function seedDefaultTenantSettings(PDO $tenantDb): void
    {
        // IMPORTANT: TenantSettingService and GatepassService/BadgeService
        // read/write ONLY the `config_json` column, as the settings
        // object directly (e.g. {"prefix":"GP","padding":5,...}) — NOT
        // a {value, config:{type}} wrapper, and NOT the `setting_value`
        // column (unused by every real consumer). Keep this shape in
        // sync with GatepassSettingController's $defaults and
        // BadgeSettingController's $defaults.
        $settings = [
            'gatepass_numbering' => [
                'prefix'        => 'GP',
                'include_year'  => true,
                'include_month' => false,
                'padding'       => 5,
                'reset_yearly'  => true,
                'current_year'  => (int) date('Y'),
                'sequence'      => 1,
            ],
            'badge_numbering' => [
                'prefix'       => 'BDG',
                'mode'         => 'sequential', // sequential | random
                'include_year' => false,
                'padding'      => 5,
                'reset_yearly' => false,
                'current_year' => (int) date('Y'),
                'sequence'     => 1,
            ],
        ];

        $stmt = $tenantDb->prepare("
            INSERT INTO tenant_settings (setting_key, config_json, updated_at)
            VALUES (:key, :config, NOW())
            ON DUPLICATE KEY UPDATE config_json = VALUES(config_json), updated_at = NOW()
        ");

        foreach ($settings as $key => $config) {
            $stmt->execute([
                ':key'    => $key,
                ':config' => json_encode($config),
            ]);
        }
    }

    private function runSqlFile(PDO $pdo, string $file): void
    {
        if (!file_exists($file)) {
            throw new RuntimeException("Missing SQL file: {$file}");
        }

        $sql = file_get_contents($file);
        $sql = preg_replace('/--.*(\n|$)/', '', $sql);
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

        $stmts = array_filter(array_map('trim', explode(";\n", $sql)));

        foreach ($stmts as $stmt) {
            if ($stmt === '') {
                continue;
            }

            try {
                $pdo->exec($stmt);
            } catch (PDOException $e) {
                $msg = $e->getMessage();
                if (str_contains($msg, 'exists') || str_contains($msg, 'Duplicate')) {
                    continue;
                }
                throw $e;
            }
        }
    }

    private function buildConfigSnippet(string $code, string $name, string $dbName, string $plan): string
    {
        $baseDomain = trim((string) config('platform.base_domain', ''));

        if ($baseDomain !== '') {
            $url = "https://{$code}.{$baseDomain}";
            return "Dynamic domain mode is active — no deployment step needed.\n"
                . "This tenant is live now at: {$url}\n"
                . "(Custom domain, if you set one, works as soon as its DNS/CNAME points here.)";
        }

        return <<<INI
            [app]
            debug = false

            [tenant]
            code = "{$code}"
            name = "{$name}"
            plan = "{$plan}"

            [database]
            name = "{$dbName}"
            ; host/port/username/password: same server as other tenants,
            ; or point at a dedicated DB server for this client.
            INI;
    }
}