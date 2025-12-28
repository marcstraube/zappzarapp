<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Infrastructure\HealthCheck;

/**
 * Status Controller
 *
 * JSON health check endpoint for monitoring and Docker health checks
 */
class StatusController
{
    public function index(): void
    {
        $health = new HealthCheck();
        $status = $health->checkAll();

        // Set appropriate HTTP status code
        $httpCode = $status['overall_status'] === 'ok' ? 200 : 503;
        http_response_code($httpCode);

        header('Content-Type: application/json');
        echo json_encode($status, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    }
}
