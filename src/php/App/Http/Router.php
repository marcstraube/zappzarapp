<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Simple Router
 *
 * Basic routing for demo purposes.
 * For production, use Symfony Router, FastRoute, or similar.
 */
class Router
{
    /** @var array<int, array{method: string, path: string, handler: callable}> */
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    private function addRoute(string $method, string $path, callable $handler): void
    {
        $this->routes[] = [
            'method'  => $method,
            'path'    => $path,
            'handler' => $handler,
        ];
    }

    public function dispatch(): void
    {
        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $requestPath   = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        // parse_url can return null, use fallback
        if ($requestPath === null || $requestPath === false) {
            $requestPath = '/';
        }

        foreach ($this->routes as $route) {
            if ($route['method'] === $requestMethod && $this->matchPath($route['path'], $requestPath)) {
                call_user_func($route['handler']);
                return;
            }
        }

        // 404 Not Found - Content-Negotiation
        $acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';

        if (str_contains((string) $acceptHeader, 'application/json')) {
            // API clients: JSON response
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Not Found', 'path' => $requestPath], JSON_THROW_ON_ERROR);
        } else {
            // Browsers: render dynamic error page
            ErrorPage::render(404, $requestPath);
        }
    }

    private function matchPath(string $routePath, string $requestPath): bool
    {
        return $routePath === $requestPath;
    }
}
