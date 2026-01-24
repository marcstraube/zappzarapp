<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\Response\JsonResponse;
use App\Http\Response\Response;
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
    public function index(): Response
    {
        $status   = $this->health->checkAll();
        $httpCode = $status['overall_status'] === 'ok' ? 200 : 503;

        return new JsonResponse($status, $httpCode, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    }

    /**
     * GET /ready - Readiness probe
     *
     * Returns readiness status with latency metrics for enabled services.
     * Used by Kubernetes readinessProbe.
     */
    public function ready(): Response
    {
        $result   = $this->health->checkReadiness();
        $httpCode = $result['status'] === 'ok' ? 200 : 503;

        return new JsonResponse($result, $httpCode);
    }
}
