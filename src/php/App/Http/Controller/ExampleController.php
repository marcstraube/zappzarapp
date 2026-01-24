<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\Response\JsonResponse;
use App\Http\Response\Response;
use App\Infrastructure\HealthCheck;
use App\Infrastructure\TlsConfig;
use Exception;

/**
 * Example Controller
 *
 * Demonstrates basic PHP MVC pattern.
 */
class ExampleController
{
    public function __construct(
        private readonly HealthCheck $healthCheck,
    ) {}

    public function index(): Response
    {
        return new JsonResponse([
            'message'     => 'Hello from PHP!',
            'timestamp'   => time(),
            'php_version' => PHP_VERSION,
            'server'      => 'PHP-FPM 8.4',
        ]);
    }

    /**
     * GET /api/health - Aggregated health check for PHP and Node backends
     *
     * Returns combined health status of both PHP and Node.js backends.
     */
    public function health(): Response
    {
        $result   = $this->checkAggregatedHealth();
        $httpCode = $result['status'] === 'ok' ? 200 : 503;

        return new JsonResponse($result, $httpCode);
    }

    /**
     * Check health of both PHP and Node backends
     *
     * @return array<string, mixed>
     */
    private function checkAggregatedHealth(): array
    {
        $backends      = [];
        $overallStatus = 'ok';

        // PHP backend is always ok (otherwise this code wouldn't run)
        $backends['php'] = [
            'status'     => 'ok',
            'latency_ms' => 0,
        ];

        // Check Node backend
        $nodeResult       = $this->checkNodeBackend();
        $backends['node'] = $nodeResult;

        if ($nodeResult['status'] !== 'ok' && $nodeResult['status'] !== 'disabled') {
            $overallStatus = 'degraded';
        }

        return [
            'status'    => $overallStatus,
            'timestamp' => date('c'),
            'backends'  => $backends,
        ];
    }

    /**
     * Check Node.js backend health with latency
     *
     * @return array<string, mixed>
     */
    private function checkNodeBackend(): array
    {
        $env        = $this->healthCheck->getEnvironment();
        $enableNode = $env['ENABLE_NODE'] ?? false;
        $nodeMode   = $env['NODE_MODE'] ?? 'none';

        // Check if Node backend is enabled
        $backendModes = ['api', 'backend', 'assets-api', 'framework-api'];
        if (!$enableNode || !in_array($nodeMode, $backendModes, true)) {
            return ['status' => 'disabled'];
        }

        $start = hrtime(true);

        try {
            $url     = 'https://node-backend:3000/health';
            $context = stream_context_create([
                'http' => [
                    'timeout'       => 2,
                    'ignore_errors' => true,
                ],
                'ssl' => TlsConfig::getSslContextOptions(),
            ]);

            set_error_handler(static fn (): bool => true);
            try {
                $response = file_get_contents($url, false, $context);
            } finally {
                restore_error_handler();
            }

            $latencyMs = (int) ((hrtime(true) - $start) / 1_000_000);

            if ($response === false) {
                return [
                    'status'  => 'unhealthy',
                    'message' => 'Node backend not reachable',
                ];
            }

            return [
                'status'     => 'ok',
                'latency_ms' => $latencyMs,
            ];
        } catch (Exception $exception) {
            return [
                'status'  => 'unhealthy',
                'message' => $exception->getMessage(),
            ];
        }
    }
}
