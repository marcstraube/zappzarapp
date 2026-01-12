<?php

declare(strict_types=1);

/**
 * Application Entry Point
 *
 * This file is the entry point for all HTTP requests routed through Nginx.
 */

// Load Composer Autoloader
require_once __DIR__ . '/../vendor/autoload.php';

/**
 * ============================================================================
 * CORS MIDDLEWARE
 * ============================================================================
 * Handles Cross-Origin Resource Sharing based on CORS_ORIGINS environment variable.
 * Must be called before any output to set headers correctly.
 */
$corsMiddleware = new \App\Http\Middleware\CorsMiddleware();
if (!$corsMiddleware->handle()) {
    // OPTIONS preflight request handled - exit early
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

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

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
 * CONTENT SECURITY POLICY (CSP) - NONCE-BASED (OPTIONAL)
 * ============================================================================
 *
 * CURRENT STATE:
 * A basic CSP is configured in docker/nginx/conf.d/default.conf with
 * 'unsafe-inline' for immediate compatibility.
 *
 * FOR MAXIMUM SECURITY (Production):
 * 1. REMOVE the static CSP header from Nginx config
 * 2. UNCOMMENT the code below (lines 27-47)
 * 3. Use CSP_NONCE constant in your inline <script> and <style> tags:
 *    <script nonce="<?= CSP_NONCE ?>">...</script>
 *    <style nonce="<?= CSP_NONCE ?>">...</style>
 *
 * BENEFITS:
 * - Blocks XSS attacks by only allowing scripts/styles with valid nonce
 * - 'strict-dynamic' allows dynamically loaded scripts from trusted sources
 * - No need to maintain a whitelist of script sources
 *
 * MORE INFO:
 * https://developer.mozilla.org/en-US/docs/Web/HTTP/CSP
 * https://web.dev/articles/csp
 */

/*
// 1. Generate Cryptographically Secure Nonce
$nonce = base64_encode(random_bytes(16));

// 2. Build CSP Header
$csp_directives = [
    "default-src 'self'",
    "script-src 'self' 'nonce-{$nonce}' 'strict-dynamic'",
    "style-src 'self' 'nonce-{$nonce}'",
    "img-src 'self' data: https:",
    "font-src 'self'",
    "connect-src 'self'",
    "frame-ancestors 'self'",
    "base-uri 'self'",
    "form-action 'self'",
];
$csp_header = implode('; ', $csp_directives);

// 3. Send CSP Header (before any output!)
header("Content-Security-Policy: {$csp_header}");

// 4. Define Constant for Template Access
define('CSP_NONCE', $nonce);
*/

// Simple Routing Example
use App\Http\Router;
use App\Http\Controller\ExampleController;
use App\Http\Controller\WelcomeController;
use App\Http\Controller\StatusController;

$router = new Router();

// Controllers
$exampleController = new ExampleController();
$welcomeController = new WelcomeController();
$statusController  = new StatusController();

// Routes
$router->get('/', [$welcomeController, 'index']);          // Main landing page
$router->get('/welcome', [$welcomeController, 'index']);   // Alias for /
$router->get('/status', [$statusController, 'index']);     // JSON health check
$router->get('/api/health', [$exampleController, 'health']); // Legacy health endpoint

// Dispatch
$router->dispatch();
