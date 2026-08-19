<?php

declare(strict_types=1);

namespace App\Core;

use App\Middleware\RouteAuthorizationMiddleware;

class Router
{
    private array $routes = [];

    public function add(string $method, string $path, $handler, array $middleware = []): void
    {
        $this->routes[$method][$path] = compact('handler', 'middleware');
    }

    public function get($path, $handler, $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post($path, $handler, $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    public function dispatch(Request $request)
    {
        $method = $request->method();
        $uri = rtrim($request->uri(), '/') ?: '/';
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

                // CSRF is global for every non-GET request.
                App::make(\App\Middleware\CSRFMiddleware::class)->handle($request);

                // Route-level authorization is centralized here so a new
                // controller cannot accidentally expose a sensitive route
                // merely by using AuthMiddleware.
                App::make(RouteAuthorizationMiddleware::class)->handle($request);

                foreach ($route['middleware'] as $middleware) {
                    if (is_array($middleware)) {
                        $class = array_shift($middleware);
                        $params = $middleware;
                    } else {
                        $class = $middleware;
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

                $matches = array_map(static function ($value) {
                    return ctype_digit($value) ? (int) $value : $value;
                }, $matches);

                return $controller->$action($request, ...$matches);
            }
        }

        Response::abort(404);
    }
}
