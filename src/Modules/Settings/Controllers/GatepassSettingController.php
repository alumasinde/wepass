<?php

namespace App\Modules\Settings\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Settings\Services\TenantSettingService;

class GatepassSettingController extends Controller
{
    public function __construct()
    {
        // FIX: Guard added — was missing entirely
        if (! Auth::check()) {
            Response::redirect('/login');
        }
    }

    /**
     * Show numbering settings page
     */
    public function index()
    {
        $settingsService = new TenantSettingService();

        $defaults = [
            'prefix'        => 'GP',
            'include_year'  => true,
            'include_month' => false,
            'padding'       => 4,
            'reset_yearly'  => true,
            'current_year'  => date('Y'),
            'sequence'      => 1,
        ];

        $saved  = $settingsService->get('gatepass_numbering') ?? [];
        $config = array_merge($defaults, $saved);

        return $this->view('Settings::gatepass-numbering', [
            'title'  => 'Gatepass Numbering Settings',
            'config' => $config,
        ]);
    }

    /**
     * Update numbering settings
     */
    public function update(Request $request)
    {
        $settingsService = new TenantSettingService();

        $config = [
            'prefix'        => trim($request->input('prefix') ?: 'GP'),
            'include_year'  => (bool) $request->input('include_year'),
            'include_month' => (bool) $request->input('include_month'),
            'padding'       => max(1, (int) ($request->input('padding') ?: 4)),
            'reset_yearly'  => (bool) $request->input('reset_yearly'),
            'current_year'  => date('Y'),
            'sequence'      => max(1, (int) ($request->input('sequence') ?: 1)),
        ];

        $settingsService->set('gatepass_numbering', $config);

        // FIX: Use Response::redirect() instead of raw header() + exit
        return $this->redirect('/settings/gatepass-numbering');
    }
}