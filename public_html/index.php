<?php
declare(strict_types=1);

use App\Core\App;
use App\Core\Request;
//use App\Core\Router;


require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';

$request = new Request();

$router = require base_path('routes/web.php');

$router->dispatch($request);
