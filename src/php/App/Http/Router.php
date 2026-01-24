<?php

declare(strict_types=1);

namespace App\Http;

use App\Http\Response\JsonResponse;
use App\Http\Response\Response;

/**
 * Simple Router
 *
 * Basic routing for demo purposes.
 * For production, use Symfony Router, FastRoute, or similar.
 */
class Router
{
    /** @var array<int, array{method: string, path: string, handler: callable(): Response}> */
    private array $routes = [];

    /**
     * @param callable(): Response $handler
     */
    public function get(string $path, callable $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    /**
     * @param callable(): Response $handler
     */
    public function post(string $path, callable $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    /**
     * @param callable(): Response $handler
     */
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
                $response = call_user_func($route['handler']);
                $response->send();

                return;
            }
        }

        // 404 Not Found - Content-Negotiation
        $acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';

        if (str_contains((string) $acceptHeader, 'application/json')) {
            // API clients: JSON response
            $response = new JsonResponse(['error' => 'Not Found', 'path' => $requestPath], 404);
        } else {
            // Browsers: render dynamic error page
            $response = ErrorPage::render(404, $requestPath);
        }

        $response->send();
    }

    private function matchPath(string $routePath, string $requestPath): bool
    {
        return $routePath === $requestPath;
    }
}
