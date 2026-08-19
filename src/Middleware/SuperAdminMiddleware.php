<?php

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

class SuperAdminMiddleware
{
    public function handle(Request $request): void
    {
        if (empty($_SESSION['is_super_admin'])) {
            Response::abort(403, 'Super admin access required.');
        }
    }
}
