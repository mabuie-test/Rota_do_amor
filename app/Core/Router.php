<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    private array $routes = [];

    public function add(string $method, string $path, callable|array $action): void
    {
        $this->routes[strtoupper($method)][$path] = $action;
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $action = $this->routes[strtoupper($method)][$path] ?? null;

        if ($action === null) {
            http_response_code(404);
            echo 'Route not found';
            return;
        }

        if (is_array($action)) {
            [$class, $handler] = $action;
            (new $class())->{$handler}();
            return;
        }

        $action();
    }
}
