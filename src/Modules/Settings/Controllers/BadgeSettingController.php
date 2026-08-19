<?php

namespace App\Modules\Settings\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Settings\Services\TenantSettingService;

class BadgeSettingController extends Controller
{
    public function __construct()
    {
        if (! Auth::check()) {
            Response::redirect('/login');
        }
    }

    public function index()
    {
        $settingsService = new TenantSettingService();

        $defaults = [
            'prefix'       => 'BDG',
            'mode'         => 'sequential', // sequential | random
            'include_year' => false,
            'padding'      => 5,
            'reset_yearly' => false,
            'current_year' => (int) date('Y'),
            'sequence'     => 1,
        ];

        $saved  = $settingsService->get('badge_numbering') ?? [];
        $config = array_merge($defaults, $saved);

        return $this->view('Settings::badge-numbering', [
            'title'  => 'Badge Numbering Settings',
            'config' => $config,
        ]);
    }

    public function update(Request $request)
    {
        $settingsService = new TenantSettingService();

        $mode = $request->input('mode') === 'random' ? 'random' : 'sequential';

        $config = [
            'prefix'       => trim($request->input('prefix') ?: 'BDG'),
            'mode'         => $mode,
            'include_year' => (bool) $request->input('include_year'),
            'padding'      => max(1, (int) ($request->input('padding') ?: 5)),
            'reset_yearly' => (bool) $request->input('reset_yearly'),
            'current_year' => (int) date('Y'),
            'sequence'     => max(1, (int) ($request->input('sequence') ?: 1)),
        ];

        $settingsService->set('badge_numbering', $config);

        return $this->redirect('/settings/badge-numbering');
    }
}
