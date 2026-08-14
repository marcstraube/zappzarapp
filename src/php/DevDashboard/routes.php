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

// Defense in depth: public/index.php only routes /_dev here in development,
// and the dashboard's data sources (.git, Node tooling) are dev-only volume
// mounts - never serve the dashboard in production.
$isProduction = (getenv('ZAPPZARAPP_ENV') ?: 'development') === 'production';
if ($isProduction) {
    return;
}

// Development opt-out: ENABLE_DEV_DASHBOARD=false disables the dashboard.
if (getenv('ENABLE_DEV_DASHBOARD') === 'false') {
    return;
}

/**
 * Cross-site request protection for the mutating dashboard endpoints
 *
 * The dashboard has no authentication, so without this check any website
 * could fire POSTs (backup restore/delete, generators) at it from the
 * developer's browser. Modern browsers label every request with
 * Sec-Fetch-Site; when that header exists it alone decides (a cross-site
 * fetch can still carry custom headers after a CORS preflight, so
 * X-Requested-With must not override it). Legacy clients without the header
 * fall back to an Origin comparison, and header-less scripted clients
 * (curl) opt in explicitly via X-Requested-With: XMLHttpRequest.
 */
function isSameOriginRequest(): bool
{
    $fetchSite = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? '';
    if ($fetchSite !== '') {
        return in_array($fetchSite, ['same-origin', 'none'], true);
    }

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '') {
        $scheme = (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $origin === $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? '');
    }

    return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !isSameOriginRequest()) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Cross-site request rejected']); // @phpstan-ignore-line echo needed for error response
    exit; // @phpstan-ignore-line Intentional exit after rejecting the request
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
