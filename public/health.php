<?php

declare(strict_types=1);

/**
 * Minimal Health Check Endpoint
 *
 * Simple, fast health check for Docker HEALTHCHECK and monitoring.
 * For detailed service status, use GET /status endpoint.
 */

http_response_code(200);
header('Content-Type: application/json');

echo json_encode([
    'status' => 'ok',
    'service' => 'php-fpm',
    'timestamp' => date('c'),
], JSON_THROW_ON_ERROR);
