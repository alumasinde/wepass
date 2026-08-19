<?php

namespace App\Modules\Settings\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use PDO;

/**
 * CompanySettingController — reads/writes the tenants table in glee_master.
 *
 * This controller is intentionally thin: company identity is stored in
 * glee_master.tenants (not in the tenant DB), so we read from config.ini
 * for display and write back to glee_master via a separate master connection.
 */
class CompanySettingController extends Controller
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
        // FIX: was reading config('tenant.name'/'tenant.code') — the
        // same always-shows-the-static-default bug found in
        // sidebar.php. Every tenant's Company Settings page showed
        // the same name/code regardless of who was actually logged
        // in. TenantContext::tenant() holds the real resolved row.
        $resolvedTenant = \App\Core\TenantContext::hasTenant() ? \App\Core\TenantContext::tenant() : null;

        $company = [
            'name'    => $resolvedTenant['name'] ?? config('tenant.name',  ''),
            'code'    => $resolvedTenant['code'] ?? config('tenant.code',  ''),
            'logo'    => $resolvedTenant['logo'] ?? '',
            'email'   => $resolvedTenant['email']   ?? '',
            'phone'   => $resolvedTenant['phone']   ?? '',
            'country' => $resolvedTenant['country'] ?? '',
        ];

        return $this->view('Settings::company', [
            'title'   => 'Company Settings',
            'company' => $company,
        ]);
    }

    public function update(Request $request)
    {
        // FIX: this used to be a stub that never actually saved
        // anything — just showed "managed via config.ini" regardless
        // of what was submitted. That was accurate once (tenant
        // identity really did only live in config.ini), but stopped
        // being true the moment index() above was fixed to read the
        // real per-tenant row from glee_master — update() was never
        // brought up to match, so submitting the form silently did
        // nothing even though the page now correctly showed real
        // per-tenant data.
        if (!\App\Core\TenantContext::hasTenant()) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'No tenant resolved for this request.'];
            return $this->redirect('/settings/company');
        }

        $tenantId = (int) (\App\Core\TenantContext::tenant()['id'] ?? 0);

        $name = trim((string) $request->input('company_name', ''));
        if ($name === '') {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Company name is required.'];
            return $this->redirect('/settings/company');
        }

        $email = trim((string) $request->input('email', ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Enter a valid email address, or leave it blank.'];
            return $this->redirect('/settings/company');
        }

        $tenantRepo = new \App\Modules\Tenant\Repositories\TenantRepository();
        $tenantRepo->updateCompanyDetails($tenantId, [
            'name'    => $name,
            'email'   => $email,
            'phone'   => trim((string) $request->input('phone', '')),
            'country' => trim((string) $request->input('country', '')),
        ]);

        // 'code' is deliberately never read from the request here —
        // see updateCompanyDetails()'s own docblock for why.

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Company settings updated.'];
        return $this->redirect('/settings/company');
    }
}