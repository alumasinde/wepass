<?php

namespace App\Modules\MasterAdmin\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Modules\MasterAdmin\Services\MasterAuthService;
use RuntimeException;

class MasterLogoutController
{
    private MasterAuthService $auth;

    public function __construct(?MasterAuthService $auth = null)
    {
        $this->auth = $auth ?? new MasterAuthService();
    }

    public function __invoke(Request $request)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return Response::abort(405, 'Method Not Allowed');
        }

        if (
            empty($request->input('csrf_token')) ||
            empty($_SESSION['csrf_token']) ||
            !hash_equals($_SESSION['csrf_token'], $request->input('csrf_token'))
        ) {
            throw new RuntimeException('Invalid CSRF token.');
        }

        $this->auth->logout();

        session_start();
        session_regenerate_id(true);

        header('Location: /master/login');
        exit;
    }
}
