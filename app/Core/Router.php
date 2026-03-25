<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    private array $routes = [];

    public function add(string $method, string $path, callable|array $action, array $middlewares = []): void
    {
        $this->routes[strtoupper($method)][] = [
            'path' => $path,
            'regex' => $this->toRegex($path),
            'action' => $action,
            'middlewares' => $middlewares,
        ];
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $routes = $this->routes[strtoupper($method)] ?? [];

        foreach ($routes as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }

            $params = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = $value;
                }
            }

            foreach ($route['middlewares'] as $middlewareClass) {
                $middleware = new $middlewareClass();
                $allowed = $middleware->handle(static fn () => true);
                if ($allowed !== true) {
                    return;
                }
            }

            $action = $route['action'];
            if (is_array($action)) {
                [$class, $handler] = $action;
                $instance = new $class();
                if ($params === []) {
                    $instance->{$handler}();
                } else {
                    $instance->{$handler}($params);
                }
                return;
            }

            if ($params === []) {
                $action();
            } else {
                $action($params);
            }
            return;
        }

        Response::abort(404, 'Route not found');
    }

    private function toRegex(string $path): string
    {
        $pattern = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }
}
