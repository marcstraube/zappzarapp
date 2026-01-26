<?php

declare(strict_types=1);

/**
 * Development Dashboard Routes
 *
 * All routes are prefixed with /_dev
 * Can be disabled in production via environment variable
 */

namespace DevDashboard;

use DevDashboard\Controllers\ApiController;
use DevDashboard\Controllers\DashboardController;
use DevDashboard\Controllers\DocsController;
use DevDashboard\Response\Response;
use DI\ContainerBuilder;
use Exception;

// Load DevDashboard helper functions
require_once __DIR__ . '/helpers.php';

// Only enable dashboard in development or when explicitly enabled
$isProduction     = (getenv('APP_ENV') ?: 'development') === 'production';
$dashboardEnabled = getenv('ENABLE_DEV_DASHBOARD') !== 'false';

if ($isProduction && !$dashboardEnabled) {
    return;
}

/**
 * Simple routing handler for development dashboard
 * Matches routes and calls appropriate controller methods
 *
 * @param callable(): Response $handler
 * @throws Exception If handler throws an exception
 */
function route(string $method, string $path, callable $handler): void
{
    $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $requestPath   = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

    if ($requestMethod === $method && $requestPath === $path) {
        $response = $handler();
        $response->send();
        exit; // @phpstan-ignore-line Intentional exit after sending response
    }
}

// DevDashboard uses its own DI container (isolated from App container)
try {
    $containerBuilder = new ContainerBuilder();
    $containerBuilder->addDefinitions(__DIR__ . '/../../../config/dev-dashboard-container.php');
    $container        = $containerBuilder->build();
    $controller       = $container->get(DashboardController::class);
    $apiController    = $container->get(ApiController::class);
    $docsController   = $container->get(DocsController::class);
} catch (Exception $exception) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'DevDashboard initialization failed', 'message' => $exception->getMessage()]); // @phpstan-ignore-line echo needed for error response
    exit; // @phpstan-ignore-line Intentional exit after fatal error
}

// Dashboard routes
try {
    route('GET', '/_dev', $controller->index(...));
    route('GET', '/_dev/', $controller->index(...));
    route('GET', '/_dev/system', $controller->system(...));
    route('GET', '/_dev/health', $controller->health(...));
    route('GET', '/_dev/quality', $controller->quality(...));
    route('GET', '/_dev/database', $controller->database(...));
    route('GET', '/_dev/logs', $controller->logs(...));

    // API endpoints for dashboard (JSON responses)
    route('GET', '/_dev/api/health-check', $apiController->healthCheck(...));
    route('GET', '/_dev/api/services', $apiController->servicesStatus(...));
    route('GET', '/_dev/api/logs', $apiController->logContent(...));
    route('POST', '/_dev/api/docs/generate', $docsController->apiGenerateDocs(...));
    route('POST', '/_dev/api/coverage/generate', $apiController->generateCoverage(...));
    route('GET', '/_dev/api/backup/list', $apiController->listBackups(...));
    route('POST', '/_dev/api/backup/create', $apiController->createBackup(...));
    route('POST', '/_dev/api/backup/restore', $apiController->restoreBackup(...));
    route('POST', '/_dev/api/backup/delete', $apiController->deleteBackup(...));
} catch (Exception $exception) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Request handling failed', 'message' => $exception->getMessage()]); // @phpstan-ignore-line echo needed for error response
    exit; // @phpstan-ignore-line Intentional exit after fatal error
}
