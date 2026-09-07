<?php

declare(strict_types=1);

namespace App\Core;

use App\Helpers\Response;
use App\Middleware\ApiKeyMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;

class Router
{
    private array $routes = [];

    private const MIDDLEWARE_MAP = [
        'auth'  => AuthMiddleware::class,
    ];

    // -------------------------------------------------------
    // Route registration
    // -------------------------------------------------------

    public function get(string $path, array $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, array $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    public function put(string $path, array $handler, array $middleware = []): void
    {
        $this->add('PUT', $path, $handler, $middleware);
    }

    public function patch(string $path, array $handler, array $middleware = []): void
    {
        $this->add('PATCH', $path, $handler, $middleware);
    }

    public function delete(string $path, array $handler, array $middleware = []): void
    {
        $this->add('DELETE', $path, $handler, $middleware);
    }

    private function add(string $method, string $path, array $handler, array $middleware): void
    {
        $this->routes[] = compact('method', 'path', 'handler', 'middleware');
    }

    // -------------------------------------------------------
    // Dispatch
    // -------------------------------------------------------

    public function dispatch(): void
    {
        $method  = $_SERVER['REQUEST_METHOD'];
        $uri     = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $base    = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
        if ($base && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        $uri = rtrim($uri, '/') ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $params = $this->match($route['path'], $uri);

            if ($params === null) {
                continue;
            }

            // Run middleware
            $this->runMiddleware($route['middleware']);

            // Dispatch to controller
            [$controllerClass, $action] = $route['handler'];
            $controller = new $controllerClass();
            $controller->$action($params);
            return;
        }

        Response::error('Endpoint not found', 404);
    }

    /**
     * Match a route pattern against a URI.
     * Returns array of named params if matched, null otherwise.
     * Pattern: /api/requests/{id}
     */
    private function match(string $pattern, string $uri): ?array
    {
        // Convert {param} to named capture groups
        $regex = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';

        if (!preg_match($regex, $uri, $matches)) {
            return null;
        }

        // Keep only named captures (no numeric keys)
        return array_filter($matches, fn($k) => is_string($k), ARRAY_FILTER_USE_KEY);
    }

    private function runMiddleware(array $middleware): void
    {
        foreach ($middleware as $item) {
            if ($item === 'auth') {
                (new AuthMiddleware())->handle();
                continue;
            }

            if ($item === 'apikey') {
                (new ApiKeyMiddleware())->handle();
                continue;
            }

            if (str_starts_with($item, 'role:')) {
                $role = substr($item, 5);
                (new RoleMiddleware())->handle($role);
                continue;
            }
        }
    }
}
