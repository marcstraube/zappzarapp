<?php

declare(strict_types=1);

namespace DevDashboard\Controllers;

use DevDashboard\Response\HtmlResponse;
use DevDashboard\Response\JsonResponse;
use DevDashboard\Response\Response;
use DevDashboard\Services\DatabaseService;
use DevDashboard\Services\DocsService;
use DevDashboard\Services\HealthCheckService;
use DevDashboard\Services\LogService;
use DevDashboard\Services\QualityService;
use DevDashboard\Services\SystemInfoService;

/**
 * Development Dashboard Controller
 *
 * Handles all dashboard routes and renders views
 */
readonly class DashboardController
{
    public function __construct(
        private HealthCheckService $healthCheckService,
        private SystemInfoService $systemInfoService,
        private QualityService $qualityService,
        private LogService $logService,
        private DatabaseService $databaseService,
        private DocsService $docsService,
    ) {}

    /**
     * Dashboard home page with overview
     */
    public function index(): Response
    {
        $data = [
            'title'         => 'Development Dashboard',
            'healthStatus'  => $this->healthCheckService->getOverallStatus(),
            'systemInfo'    => $this->systemInfoService->getBasicInfo(),
            'gitStatus'     => $this->systemInfoService->getGitStatus(),
            'dbStats'       => $this->databaseService->getQuickStats(),
            'apiDocsStatus' => $this->docsService->getApiDocsStatus(),
        ];

        return $this->render('dashboard', $data);
    }

    /**
     * System information page (phpinfo, versions, environment)
     */
    public function system(): Response
    {
        $data = [
            'title'       => 'System Information',
            'phpVersion'  => $this->systemInfoService->getPhpVersion(),
            'extensions'  => $this->systemInfoService->getPhpExtensions(),
            'envVars'     => $this->systemInfoService->getEnvironmentVariables(),
            'showPhpInfo' => $_GET['phpinfo'] ?? false,
            'gitStatus'   => $this->systemInfoService->getGitStatus(),
        ];

        return $this->render('system', $data);
    }

    /**
     * Health check page (services, connections, ssl)
     */
    public function health(): Response
    {
        $data = [
            'title'       => 'Health Checks',
            'services'    => $this->healthCheckService->getServices(),
            'connections' => $this->healthCheckService->getConnections(),
            'ssl'         => $this->healthCheckService->getSslInfo(),
        ];

        return $this->render('health', $data);
    }

    /**
     * Code quality dashboard
     */
    public function quality(): Response
    {
        $metrics = $this->qualityService->getQualityMetrics();

        $data = [
            'title'         => 'Code Quality',
            'php_quality'   => $metrics['php'],
            'node_quality'  => $metrics['node'],
            'code_stats'    => $metrics['code_stats'],
            'test_coverage' => $metrics['test_coverage'],
            'quick_actions' => $this->qualityService->getQuickActions(),
        ];

        return $this->render('quality', $data);
    }

    /**
     * Database tools page
     */
    public function database(): Response
    {
        $data = [
            'title'            => 'Database Tools',
            'overview'         => $this->databaseService->getDatabaseOverview(),
            'tables'           => $this->databaseService->getTables(),
            'connection_stats' => $this->databaseService->getConnectionStats(),
            'commands'         => $this->databaseService->getDatabaseCommands(),
            'db_tools'         => $this->databaseService->getDbToolsStatus(),
            'backup_stats'     => $this->databaseService->getBackupStats(),
        ];

        return $this->render('database', $data);
    }

    /**
     * Logs viewer page
     */
    public function logs(): Response
    {
        $data = [
            'title'        => 'Logs Viewer',
            'log_sources'  => $this->logService->getAvailableLogSources(),
            'log_commands' => $this->logService->getLogCommands(),
            'log_stats'    => $this->logService->getLogStatistics(),
        ];

        return $this->render('logs', $data);
    }

    /**
     * API: Health check endpoint (JSON)
     */
    public function apiHealthCheck(): Response
    {
        return new JsonResponse($this->healthCheckService->getOverallStatus());
    }

    /**
     * API: Services status endpoint (JSON)
     */
    public function apiServicesStatus(): Response
    {
        return new JsonResponse($this->healthCheckService->getServices());
    }

    /**
     * API: Get log file content (JSON)
     */
    public function apiLogContent(): Response
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
    public function apiGenerateCoverage(): Response
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
    public function apiListBackups(): Response
    {
        $result = $this->databaseService->listBackups();

        return new JsonResponse($result, 200);
    }

    /**
     * API: Create database backup (JSON)
     */
    public function apiCreateBackup(): Response
    {
        $retention = isset($_GET['retention']) ? (int) $_GET['retention'] : null;

        $result = $this->databaseService->createBackup($retention);

        return new JsonResponse($result, $result['success'] ? 200 : 500);
    }

    /**
     * API: Restore database from backup (JSON)
     */
    public function apiRestoreBackup(): Response
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
    public function apiDeleteBackup(): Response
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

    /**
     * Render a view with layout
     *
     * @param array<string, mixed> $data
     */
    private function render(string $view, array $data = []): Response
    {
        extract($data);
        $templateDir = __DIR__ . '/../../../../templates/dev-dashboard';
        $viewPath    = $templateDir . '/' . $view . '.php';
        $layoutPath  = $templateDir . '/layout.php';

        if (!file_exists($viewPath)) {
            return new HtmlResponse('View not found: ' . $view, 404);
        }

        // Start output buffering for content
        ob_start();
        include $viewPath;
        $content = ob_get_clean();

        // Ensure content is string (prevent PHPMD false positive - variable used in layout.php)
        if ($content === false) {
            $content = '';
        }

        // Render with layout
        ob_start();
        include $layoutPath;
        $html = ob_get_clean();

        return new HtmlResponse($html ?: '');
    }
}
