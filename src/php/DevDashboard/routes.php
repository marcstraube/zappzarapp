<?php

declare(strict_types=1);

/**
 * Development Dashboard Routes
 *
 * All routes are prefixed with /_dev
 * Can be disabled in production via environment variable
 */

namespace DevDashboard;

use DevDashboard\Controllers\DashboardController;
use DI\ContainerBuilder;

// Only enable dashboard in development or when explicitly enabled
$isProduction     = (getenv('APP_ENV') ?: 'development') === 'production';
$dashboardEnabled = getenv('ENABLE_DEV_DASHBOARD') !== 'false';

if ($isProduction && !$dashboardEnabled) {
    return;
}

/**
 * Simple routing handler for development dashboard
 * Matches routes and calls appropriate controller methods
 */
function route(string $method, string $path, callable $handler): void
{
    $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $requestPath   = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

    if ($requestMethod === $method && $requestPath === $path) {
        $handler();
        exit;
    }
}

// DevDashboard uses its own DI container (isolated from App container)
// Only auto-wiring, no explicit configuration needed
$containerBuilder = new ContainerBuilder();
$container        = $containerBuilder->build();
$controller       = $container->get(DashboardController::class);

// Dashboard routes
route('GET', '/_dev', $controller->index(...));
route('GET', '/_dev/', $controller->index(...));
route('GET', '/_dev/system', $controller->system(...));
route('GET', '/_dev/health', $controller->health(...));
route('GET', '/_dev/quality', $controller->quality(...));
route('GET', '/_dev/database', $controller->database(...));
route('GET', '/_dev/logs', $controller->logs(...));

// API endpoints for dashboard (JSON responses)
route('GET', '/_dev/api/health-check', $controller->apiHealthCheck(...));
route('GET', '/_dev/api/services', $controller->apiServicesStatus(...));
route('GET', '/_dev/api/logs', $controller->apiLogContent(...));
