<?php

declare(strict_types=1);

namespace DevDashboard\Controllers;

use DevDashboard\Services\HealthCheckService;
use DevDashboard\Services\SystemInfoService;

/**
 * Development Dashboard Controller
 *
 * Handles all dashboard routes and renders views
 */
class DashboardController
{
    private HealthCheckService $healthCheckService;
    private SystemInfoService $systemInfoService;

    public function __construct()
    {
        require_once __DIR__ . '/../Services/HealthCheckService.php';
        require_once __DIR__ . '/../Services/SystemInfoService.php';

        $this->healthCheckService = new HealthCheckService();
        $this->systemInfoService = new SystemInfoService();
    }

    /**
     * Dashboard home page with overview
     */
    public function index(): void
    {
        $data = [
            'title' => 'Development Dashboard',
            'healthStatus' => $this->healthCheckService->getOverallStatus(),
            'systemInfo' => $this->systemInfoService->getBasicInfo(),
            'gitStatus' => $this->systemInfoService->getGitStatus(),
        ];

        $this->render('dashboard', $data);
    }

    /**
     * System information page (phpinfo, versions, environment)
     */
    public function system(): void
    {
        $data = [
            'title' => 'System Information',
            'phpVersion' => $this->systemInfoService->getPhpVersion(),
            'extensions' => $this->systemInfoService->getPhpExtensions(),
            'envVars' => $this->systemInfoService->getEnvironmentVariables(),
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
            'title' => 'Health Checks',
            'containers' => $this->healthCheckService->getContainerStatus(),
            'databases' => $this->healthCheckService->getDatabaseStatus(),
            'services' => $this->healthCheckService->getServiceStatus(),
            'ssl' => $this->healthCheckService->getSslInfo(),
        ];

        $this->render('health', $data);
    }

    /**
     * Code quality dashboard
     */
    public function quality(): void
    {
        $data = [
            'title' => 'Code Quality',
            'message' => 'Quality metrics will be implemented in the next iteration',
        ];

        $this->render('quality', $data);
    }

    /**
     * Database tools page
     */
    public function database(): void
    {
        $data = [
            'title' => 'Database Tools',
            'message' => 'Database tools will be implemented in the next iteration',
        ];

        $this->render('database', $data);
    }

    /**
     * Logs viewer page
     */
    public function logs(): void
    {
        $data = [
            'title' => 'Logs Viewer',
            'message' => 'Log viewer will be implemented in the next iteration',
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
     */
    private function render(string $view, array $data = []): void
    {
        extract($data);
        $viewPath = __DIR__ . '/../Views/' . $view . '.php';
        $layoutPath = __DIR__ . '/../Views/layout.php';

        if (!file_exists($viewPath)) {
            http_response_code(404);
            echo "View not found: {$view}";
            return;
        }

        // Start output buffering for content
        ob_start();
        include $viewPath;
        $content = ob_get_clean();

        // Render with layout
        include $layoutPath;
    }
}
