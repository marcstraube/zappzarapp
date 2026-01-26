<?php

declare(strict_types=1);

/**
 * Application Entry Point
 *
 * This file is the entry point for all HTTP requests routed through Nginx.
 */

use App\Http\Controller\ExampleController;
use App\Http\Controller\StatusController;
use App\Http\Controller\WelcomeController;
use App\Http\Middleware\CorsMiddleware;
use App\Http\Router;
use DI\ContainerBuilder;

// Load Composer Autoloader
require_once __DIR__ . '/../vendor/autoload.php';

/**
 * ============================================================================
 * DEPENDENCY INJECTION CONTAINER
 * ============================================================================
 * Initialize the PSR-11 compatible DI container with auto-wiring support.
 * Configuration is loaded from config/container.php.
 */
try {
    $containerBuilder = new ContainerBuilder();
    $containerBuilder->addDefinitions(__DIR__ . '/../config/container.php');
    $container = $containerBuilder->build();
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    // @phpstan-ignore-next-line - Entry point error handling requires echo/exit
    echo json_encode(['error' => 'Container initialization failed', 'message' => $e->getMessage()]);
    // @phpstan-ignore-next-line - Entry point error handling requires echo/exit
    exit(1);
}

/**
 * ============================================================================
 * CORS MIDDLEWARE
 * ============================================================================
 * Handles Cross-Origin Resource Sharing based on CORS_ORIGINS environment variable.
 * Must be called before any output to set headers correctly.
 */
$corsMiddleware = new CorsMiddleware();
if (!$corsMiddleware->handle()) {
    // OPTIONS preflight request handled - exit early
    // @phpstan-ignore-next-line - CORS preflight requires early exit
    exit;
}

/**
 * ============================================================================
 * DEVELOPMENT DASHBOARD ROUTING (DEV-ONLY)
 * ============================================================================
 *
 * The Development Dashboard is accessible at /_dev
 * It provides system info, health checks, logs, quality metrics, and more.
 *
 * IMPORTANT: Only available in development environment (ENV=development)
 * This is enforced because:
 * - Volume mounts for .git, Node.js configs are DEV-only (compose.override.yaml)
 * - Exposes sensitive information (env vars, phpinfo, database details)
 * - Git status and Node.js quality tools require DEV volume mounts
 */

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// Development Dashboard - Only enabled in development environment
$isDevelopment = getenv('ENV') === 'development';

if ($isDevelopment && str_starts_with($requestPath, '/_dev')) {
    if (file_exists(__DIR__ . '/../src/php/DevDashboard/routes.php')) {
        require_once __DIR__ . '/../src/php/DevDashboard/routes.php';
    }
    // If we reach here, dashboard route didn't match - fall through to 404
}

/**
 * ============================================================================
 * CONTENT SECURITY POLICY (CSP) - NONCE-BASED
 * ============================================================================
 *
 * ACTIVE: Nonce-based CSP enabled for maximum security
 *
 * This implementation:
 * - Enforces nonce for inline scripts/styles in BOTH dev and production
 * - Development: Allows 'unsafe-eval' for Vite HMR, but enforces nonce for inline scripts
 * - Production: Strict CSP without unsafe-* directives
 * - Generates unique cryptographically secure nonce per request
 *
 * USAGE IN TEMPLATES:
 * Use nonce() helper function in inline <script> and <style> tags:
 *    <script nonce="<?= nonce() ?>">console.log('test')</script>
 *    <style nonce="<?= nonce() ?>">body { margin: 0; }</style>
 *
 * Backwards compatible constant also available:
 *    <script nonce="<?= CSP_NONCE ?>">console.log('test')</script>
 *
 * DEVELOPMENT:
 * - HMR works via 'unsafe-eval' (required for Vite)
 * - Inline scripts still require nonce (same as production)
 * - Use Browser DevTools to debug CSP violations
 *
 * MORE INFO:
 * https://developer.mozilla.org/en-US/docs/Web/HTTP/CSP
 * https://web.dev/articles/csp
 */

use App\Security\CspNonceHelper;

// 1. Build and send CSP Header (before any output!)
$cspHeader = CspNonceHelper::buildCspHeader();
header("Content-Security-Policy: $cspHeader");

// 2. Define constant for backwards compatibility
define('CSP_NONCE', CspNonceHelper::get());

// Simple Routing Example
$router = new Router();

// Controllers (resolved via DI container with auto-wiring)
try {
    /** @var ExampleController $exampleController */
    $exampleController = $container->get(ExampleController::class);
    /** @var WelcomeController $welcomeController */
    $welcomeController = $container->get(WelcomeController::class);
    /** @var StatusController $statusController */
    $statusController  = $container->get(StatusController::class);
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    // @phpstan-ignore-next-line - Entry point error handling requires echo/exit
    echo json_encode(['error' => 'Controller initialization failed', 'message' => $e->getMessage()]);
    // @phpstan-ignore-next-line - Entry point error handling requires echo/exit
    exit(1);
}

// Routes
$router->get('/', [$welcomeController, 'index']);          // Main landing page
$router->get('/welcome', [$welcomeController, 'index']);   // Alias for /

// Health Check Routes
$router->get('/ready', [$statusController, 'ready']);      // Readiness probe (K8s)
$router->get('/status', [$statusController, 'index']);     // Full status overview
$router->get('/api/health', [$exampleController, 'health']); // Aggregated PHP + Node health

// Dispatch
$router->dispatch();
