<?php

namespace App\Core;

use App\Core\Response;


class Router
{
    private array $routes = [];

    public function add(string $method, string $path, $handler, array $middleware = [])
    {
        $this->routes[$method][$path] = compact('handler', 'middleware');
    }

    public function get($path, $handler, $middleware = [])
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post($path, $handler, $middleware = [])
    {
        $this->add('POST', $path, $handler, $middleware);
    }


    // Dispatch function
    public function dispatch(Request $request)
{
    $method = $request->method();
    $uri    = rtrim($request->uri(), '/') ?: '/';

    $routes = $this->routes[$method] ?? [];

    foreach ($routes as $path => $route) {
        if ($path === '/') {
            $pattern = '#^/$#';
        } else {
            $pattern = preg_replace('#\{[^/]+\}#', '([^/]+)', $path);
            $pattern = '#^' . $pattern . '$#';
        }

        if (preg_match($pattern, $uri, $matches)) {
            array_shift($matches);

            // CSRF check — applied globally to every non-GET request,
            // before any route-specific middleware, so no route can
            // ship without it by omission. See CSRFMiddleware for why
            // this isn't just attached per-route.
            App::make(\App\Middleware\CSRFMiddleware::class)->handle($request);

            // Run middleware. Each entry is either a plain class name
            // (App::make($class)->handle($request)) or an array of
            // [class, ...params] for middleware that need arguments,
            // e.g. [PermissionMiddleware::class, 'gatepasses.create']
            // or [RateLimitMiddleware::class, 'login', 5, 120].
            foreach ($route['middleware'] as $middleware) {
                if (is_array($middleware)) {
                    $class  = array_shift($middleware);
                    $params = $middleware;
                } else {
                    $class  = $middleware;
                    $params = [];
                }

                App::make($class)->handle($request, ...$params);
            }

            $handler = $route['handler'];

            if (is_array($handler)) {
                [$class, $action] = $handler;
            } else {
                [$class, $action] = explode('@', $handler);
            }

            $controller = App::make($class);

            // -------------------------
            // CAST PARAMETERS
            // -------------------------
            $matches = array_map(function($v) {
                return ctype_digit($v) ? (int) $v : $v;
            }, $matches);

            return $controller->$action($request, ...$matches);
        }
    }

    Response::abort(404);
}
}