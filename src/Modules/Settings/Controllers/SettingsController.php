<?php

namespace App\Modules\Settings\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Response;

class SettingsController extends Controller
{
    public function __construct()
    {
        // FIX: Auth guard added — was missing
        if (! Auth::check()) {
            Response::redirect('/login');
        }
    }

    public function index()
    {
        return $this->view('Settings::dashboard', [
            'title' => 'Settings',
        ]);
    }
}