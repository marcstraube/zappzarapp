<?php

declare(strict_types=1);

namespace DevDashboard\Controllers;

use DevDashboard\Services\DatabaseService;
use DevDashboard\Services\HealthCheckService;
use DevDashboard\Services\LogService;
use DevDashboard\Services\QualityService;
use DevDashboard\Services\SystemInfoService;

/**
 * Development Dashboard Controller
 *
 * Handles all dashboard routes and renders views
 */
class DashboardController
{
    private readonly HealthCheckService $healthCheckService;

    private readonly SystemInfoService $systemInfoService;

    private readonly QualityService $qualityService;

    private readonly LogService $logService;

    private readonly DatabaseService $databaseService;

    public function __construct()
    {
        $this->healthCheckService = new HealthCheckService();
        $this->systemInfoService  = new SystemInfoService();
        $this->qualityService     = new QualityService();
        $this->logService         = new LogService();
        $this->databaseService    = new DatabaseService();
    }

    /**
     * Dashboard home page with overview
     */
    public function index(): void
    {
        $data = [
            'title'        => 'Development Dashboard',
            'healthStatus' => $this->healthCheckService->getOverallStatus(),
            'systemInfo'   => $this->systemInfoService->getBasicInfo(),
            'gitStatus'    => $this->systemInfoService->getGitStatus(),
        ];

        $this->render('dashboard', $data);
    }

    /**
     * System information page (phpinfo, versions, environment)
     */
    public function system(): void
    {
        $data = [
            'title'       => 'System Information',
            'phpVersion'  => $this->systemInfoService->getPhpVersion(),
            'extensions'  => $this->systemInfoService->getPhpExtensions(),
            'envVars'     => $this->systemInfoService->getEnvironmentVariables(),
            'showPhpInfo' => $_GET['phpinfo'] ?? false,
        ];

        $this->render('system', $data);
    }

    /**
     * Health check page (containers, services, databases)
     */
    public function health(): void
    {
        $data = [
            'title'      => 'Health Checks',
            'containers' => $this->healthCheckService->getContainerStatus(),
            'databases'  => $this->healthCheckService->getDatabaseStatus(),
            'services'   => $this->healthCheckService->getServiceStatus(),
            'ssl'        => $this->healthCheckService->getSslInfo(),
        ];

        $this->render('health', $data);
    }

    /**
     * Code quality dashboard
     */
    public function quality(): void
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

        $this->render('quality', $data);
    }

    /**
     * Database tools page
     */
    public function database(): void
    {
        $data = [
            'title'            => 'Database Tools',
            'overview'         => $this->databaseService->getDatabaseOverview(),
            'tables'           => $this->databaseService->getTables(),
            'connection_stats' => $this->databaseService->getConnectionStats(),
            'commands'         => $this->databaseService->getDatabaseCommands(),
        ];

        $this->render('database', $data);
    }

    /**
     * Logs viewer page
     */
    public function logs(): void
    {
        $data = [
            'title'        => 'Logs Viewer',
            'log_sources'  => $this->logService->getAvailableLogSources(),
            'log_commands' => $this->logService->getLogCommands(),
            'log_stats'    => $this->logService->getLogStatistics(),
        ];

        $this->render('logs', $data);
    }

    /**
     * API: Health check endpoint (JSON)
     */
    public function apiHealthCheck(): void
    {
        header('Content-Type: application/json');
        echo json_encode($this->healthCheckService->getOverallStatus());
    }

    /**
     * API: Container status endpoint (JSON)
     */
    public function apiContainerStatus(): void
    {
        header('Content-Type: application/json');
        echo json_encode($this->healthCheckService->getContainerStatus());
    }

    /**
     * Render a view with layout
     *
     * @param array<string, mixed> $data
     */
    private function render(string $view, array $data = []): void
    {
        extract($data);
        $viewPath   = __DIR__ . '/../Views/' . $view . '.php';
        $layoutPath = __DIR__ . '/../Views/layout.php';

        if (!file_exists($viewPath)) {
            http_response_code(404);
            echo 'View not found: ' . $view;
            return;
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
        include $layoutPath;
    }
}
