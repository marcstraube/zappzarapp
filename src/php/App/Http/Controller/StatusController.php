<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Infrastructure\HealthCheck;

/**
 * Status Controller
 *
 * Provides health check endpoints for Kubernetes probes and monitoring:
 * - /status: Full overview of all services (including disabled)
 * - /ready: Readiness probe for K8s
 */
class StatusController
{
    public function __construct(
        private readonly HealthCheck $health,
    ) {}

    /**
     * GET /status - Full status overview
     *
     * Returns status of all services including disabled ones.
     * Used for monitoring dashboards and debugging.
     */
    public function index(): void
    {
        $status = $this->health->checkAll();

        // Set appropriate HTTP status code
        $httpCode = $status['overall_status'] === 'ok' ? 200 : 503;
        http_response_code($httpCode);

        header('Content-Type: application/json');
        echo json_encode($status, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    }

    /**
     * GET /ready - Readiness probe
     *
     * Returns readiness status with latency metrics for enabled services.
     * Used by Kubernetes readinessProbe.
     */
    public function ready(): void
    {
        $result = $this->health->checkReadiness();

        // 200 if ok, 503 if degraded or unhealthy
        $httpCode = $result['status'] === 'ok' ? 200 : 503;
        http_response_code($httpCode);

        header('Content-Type: application/json');
        echo json_encode($result, JSON_THROW_ON_ERROR);
    }
}
