<?php

declare(strict_types=1);

namespace App\Http\Middleware;

/**
 * CORS Middleware
 *
 * Handles Cross-Origin Resource Sharing (CORS) headers.
 * Reads allowed origins from CORS_ORIGINS environment variable.
 *
 * Configuration via CORS_ORIGINS:
 * - '*': Allow all origins (development only!)
 * - 'https://example.com,https://app.example.com': Specific origins (production)
 * - Empty/unset: No CORS headers added
 */
final readonly class CorsMiddleware
{
    private string $allowedOrigins;

    public function __construct(?string $allowedOrigins = null)
    {
        $this->allowedOrigins = $allowedOrigins ?? (getenv('CORS_ORIGINS') ?: '');
    }

    /**
     * Handle CORS headers for the current request
     *
     * @return bool True if request should continue, false if handled (OPTIONS preflight)
     */
    public function handle(): bool
    {
        // No CORS configuration - skip
        if ($this->allowedOrigins === '') {
            return true;
        }

        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        // Check if origin is allowed
        if ($this->isOriginAllowed($origin)) {
            $allowOrigin = $this->allowedOrigins === '*' ? '*' : $origin;
            header('Access-Control-Allow-Origin: ' . $allowOrigin);
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Max-Age: 3600');
        }

        // Handle OPTIONS preflight request
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            return false;
        }

        return true;
    }

    /**
     * Check if the given origin is allowed
     */
    private function isOriginAllowed(string $origin): bool
    {
        // No origin header - internal request or same-origin
        if ($origin === '') {
            return false;
        }

        // Wildcard allows all origins
        if ($this->allowedOrigins === '*') {
            return true;
        }

        // Check against comma-separated list
        $allowed = array_map(trim(...), explode(',', $this->allowedOrigins));

        return in_array($origin, $allowed, true);
    }
}
