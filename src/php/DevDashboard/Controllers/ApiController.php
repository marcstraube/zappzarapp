<?php

declare(strict_types=1);

namespace DevDashboard\Controllers;

use DevDashboard\Response\JsonResponse;
use DevDashboard\Response\Response;
use DevDashboard\Services\DatabaseService;
use DevDashboard\Services\HealthCheckService;
use DevDashboard\Services\LogService;
use DevDashboard\Services\QualityService;

/**
 * API Controller
 *
 * Handles all JSON API endpoints for the Development Dashboard
 */
readonly class ApiController
{
    public function __construct(
        private HealthCheckService $healthCheckService,
        private QualityService $qualityService,
        private LogService $logService,
        private DatabaseService $databaseService,
    ) {}

    /**
     * API: Health check endpoint (JSON)
     */
    public function healthCheck(): Response
    {
        return new JsonResponse($this->healthCheckService->getOverallStatus());
    }

    /**
     * API: Services status endpoint (JSON)
     */
    public function servicesStatus(): Response
    {
        return new JsonResponse($this->healthCheckService->getServices());
    }

    /**
     * API: Get log file content (JSON)
     */
    public function logContent(): Response
    {
        $filename = $_GET['file'] ?? '';
        $lines    = (int) ($_GET['lines'] ?? 100);

        if ($filename === '') {
            return new JsonResponse(['error' => 'No filename provided'], 400);
        }

        $result = $this->logService->readLogFile($filename, min($lines, 500));

        return new JsonResponse($result);
    }

    /**
     * API: Generate test coverage (JSON)
     */
    public function generateCoverage(): Response
    {
        $type = $_GET['type'] ?? 'php';

        if ($type !== 'php') {
            return new JsonResponse([
                'success' => false,
                'message' => 'Only PHP coverage can be generated from dashboard. Run "make test-coverage-node" for Node coverage.',
            ], 400);
        }

        $result = $this->qualityService->runPhpCoverage();

        return new JsonResponse($result, $result['success'] ? 200 : 500);
    }

    /**
     * API: List database backups (JSON)
     */
    public function listBackups(): Response
    {
        $result = $this->databaseService->listBackups();

        return new JsonResponse($result, 200);
    }

    /**
     * API: Create database backup (JSON)
     */
    public function createBackup(): Response
    {
        $retention = isset($_GET['retention']) ? (int) $_GET['retention'] : null;

        $result = $this->databaseService->createBackup($retention);

        return new JsonResponse($result, $result['success'] ? 200 : 500);
    }

    /**
     * API: Restore database from backup (JSON)
     */
    public function restoreBackup(): Response
    {
        $requestBody = file_get_contents('php://input');
        $data        = json_decode($requestBody ?: '{}', true);

        if (!isset($data['filename']) || !is_string($data['filename'])) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Missing required parameter: filename',
            ], 400);
        }

        $result = $this->databaseService->restoreBackup($data['filename']);

        return new JsonResponse($result, $result['success'] ? 200 : 500);
    }

    /**
     * API: Delete backup file (JSON)
     */
    public function deleteBackup(): Response
    {
        $requestBody = file_get_contents('php://input');
        $data        = json_decode($requestBody ?: '{}', true);

        if (!isset($data['filename']) || !is_string($data['filename'])) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Missing required parameter: filename',
            ], 400);
        }

        $result = $this->databaseService->deleteBackup($data['filename']);

        return new JsonResponse($result, $result['success'] ? 200 : 500);
    }
}
