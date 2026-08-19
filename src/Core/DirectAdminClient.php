<?php

namespace App\Core;

use RuntimeException;

/**
 * DirectAdminClient — thin wrapper around DirectAdmin's classic HTTP
 * API (CMD_API_DATABASES), used for exactly one thing: creating a
 * new tenant's database on shared hosting where the MySQL account
 * itself has no CREATE DATABASE privilege (that's locked behind the
 * hosting panel, not grantable over a plain MySQL connection).
 *
 * Auth is HTTP Basic with your DirectAdmin username + a Login Key
 * (config.ini [directadmin] username/login_key) — never your real
 * account password. Create the key with ONLY CMD_API_DATABASES
 * allowed, ideally IP-restricted to this server (DirectAdmin
 * Account Manager → Login Keys).
 *
 * DirectAdmin prepends your account username to both the database
 * name and the database user (`<da_username>_<name>`) — callers
 * don't need to do this themselves; buildFullDbName() does it so
 * the rest of the app always deals in the real, final name.
 *
 * Design choice: every call reuses the SAME db user (config.ini
 * [directadmin] runtime_db_user) across every tenant database
 * created this way, rather than minting a new user per tenant.
 * DirectAdmin's create action grants whatever user you name access
 * to the new database — reusing one user means it only ever
 * accumulates per-database grants for the databases it was
 * explicitly added to, never server-wide CREATE/DROP, while keeping
 * the rest of the app's connection logic (one shared [database]
 * credential) unchanged. Documented DirectAdmin behavior: reusing
 * an existing user resets that user's password to whatever is
 * passed in `passwd` — always passing the SAME password (the one
 * already in [database]) makes this a no-op rather than an
 * unexpected credential change.
 */
class DirectAdminClient
{
    private string $host;
    private int $port;
    private string $daUsername;
    private string $loginKey;
    private bool $verifySsl;

    public function __construct()
    {
        $this->host       = (string) config('directadmin.host', '');
        $this->port       = (int) config('directadmin.port', 2222);
        $this->daUsername = (string) config('directadmin.username', '');
        $this->loginKey   = (string) config('directadmin.login_key', '');
        // Shared hosting sometimes fronts :2222 with a self-signed
        // or panel-default cert that isn't in the system CA bundle.
        // Verified by default — only disable if you've confirmed
        // that's genuinely why requests are failing, and understand
        // it removes TLS's protection against a MITM on this call.
        $this->verifySsl  = (bool) config('directadmin.verify_ssl', true);

        if ($this->host === '' || $this->daUsername === '' || $this->loginKey === '') {
            throw new RuntimeException(
                'DirectAdmin API is not configured — set [directadmin] host/username/login_key in config.ini.'
            );
        }
    }

    /**
     * Prepends the DirectAdmin account username the way DirectAdmin
     * itself will — so callers can predict and store the real,
     * final database name before creating it.
     */
    public function buildFullDbName(string $suffix): string
    {
        return $this->daUsername . '_' . $suffix;
    }

    /**
     * Creates a new database (and grants the shared runtime user
     * access to it, creating that user on first use). Throws on any
     * failure — including "already exists", since provisioning
     * should never silently continue against an unexpected database.
     */
    public function createDatabase(string $dbNameSuffix, string $runtimeDbUser, string $runtimeDbPassword): string
    {
        $fullDbName = $this->buildFullDbName($dbNameSuffix);

        $response = $this->request('CMD_API_DATABASES', [
            'action'  => 'create',
            'name'    => $dbNameSuffix,     // DA prepends the username itself
            'user'    => $runtimeDbUser,    // existing user -> just gets granted access
            'passwd'  => $runtimeDbPassword,
            'passwd2' => $runtimeDbPassword,
        ]);

        if (isset($response['error']) && (int) $response['error'] !== 0) {
            $message = $response['text'] ?? $response['details'] ?? 'Unknown DirectAdmin API error.';
            throw new RuntimeException("DirectAdmin refused to create database '{$fullDbName}': {$message}");
        }

        return $fullDbName;
    }

    /**
     * @return array<string,string> Parsed response — DirectAdmin's
     * classic API returns application/x-www-form-urlencoded body
     * text (error=0&text=...), not JSON.
     */
    private function request(string $command, array $params): array
    {
        $url = sprintf('https://%s:%d/%s', $this->host, $this->port, $command);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_USERPWD        => $this->daUsername . ':' . $this->loginKey,
            CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => $this->verifySsl,
            CURLOPT_SSL_VERIFYHOST => $this->verifySsl ? 2 : 0,
            CURLOPT_TIMEOUT        => 20,
        ]);

        $body       = curl_exec($ch);
        $curlErrno  = curl_errno($ch);
        $curlError  = curl_error($ch);
        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlErrno !== 0) {
            throw new RuntimeException("DirectAdmin API request failed: {$curlError}");
        }

        if ($httpStatus === 401) {
            throw new RuntimeException(
                'DirectAdmin API rejected the credentials (401) — check [directadmin] username/login_key, ' .
                'and that the Login Key allows CMD_API_DATABASES from this server\'s IP.'
            );
        }

        if ($httpStatus !== 200) {
            throw new RuntimeException("DirectAdmin API returned unexpected HTTP status {$httpStatus}.");
        }

        parse_str((string) $body, $parsed);

        return $parsed;
    }

    /**
     * Best-effort cleanup helper — deletes a database this client
     * created, via the same API rather than a direct SQL DROP
     * (which is typically just as restricted as CREATE on shared
     * hosting, for the same reason). $fullDbName is the already-
     * prefixed name, e.g. from buildFullDbName() or createDatabase()'s
     * return value.
     */
    public function deleteDatabase(string $fullDbName): void
    {
        $this->request('CMD_API_DATABASES', [
            'action'  => 'delete',
            'select0' => $fullDbName,
        ]);
        // Deliberately not checking the response here — this is
        // already only ever called from a best-effort cleanup path
        // that swallows its own errors; surfacing a secondary
        // failure would only obscure the original one.
    }
}
