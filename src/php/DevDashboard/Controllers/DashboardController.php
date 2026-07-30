<?php

declare(strict_types=1);

namespace DevDashboard\Controllers;

use DevDashboard\Infrastructure\TwigService;
use DevDashboard\Response\HtmlResponse;
use DevDashboard\Response\Response;
use DevDashboard\Services\DatabaseService;
use DevDashboard\Services\DocsService;
use DevDashboard\Services\HealthCheckService;
use DevDashboard\Services\LogService;
use DevDashboard\Services\QualityService;
use DevDashboard\Services\SystemInfoService;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * Development Dashboard Controller
 *
 * Handles all dashboard routes and renders Twig templates
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
        private TwigService $twig,
    ) {}

    /**
     * Dashboard home page with overview
     *
     * @noinspection PhpUnhandledExceptionInspection Exceptions propagate to routes.php error handler
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
            'requestUri'    => $_SERVER['REQUEST_URI'] ?? '/',
            'currentTime'   => date('Y-m-d H:i:s'),
        ];

        return $this->render('dashboard', $data);
    }

    /**
     * System information page (phpinfo, versions, environment)
     *
     * @noinspection PhpUnhandledExceptionInspection Exceptions propagate to routes.php error handler
     */
    public function system(): Response
    {
        $showPhpInfo  = $_GET['phpinfo'] ?? false;
        $phpinfoHtml  = '';

        // Process phpinfo if requested
        if ($showPhpInfo) {
            ob_start();
            // nosemgrep: phpinfo-use -- intentional: DevDashboard is a dev-only tool and this renders the system-info page.
            phpinfo(); // @phpstan-ignore ekinoBannedCode.function (Legitimate use in DevDashboard for system info display)
            // ob_get_clean() cannot return false here: the matching ob_start()
            // is right above, so a buffer is always active.
            $phpinfo = ob_get_clean();
            $phpinfo = preg_replace('%^.*<body>(.*)</body>.*$%ms', '$1', $phpinfo);
            if ($phpinfo !== null) {
                $phpinfoHtml = str_replace('<table', '<table class="w-full text-sm"', $phpinfo);
            }
        }

        $data = [
            'title'       => 'System Information',
            'phpVersion'  => $this->systemInfoService->getPhpVersion(),
            'extensions'  => $this->systemInfoService->getPhpExtensions(),
            'envVars'     => $this->systemInfoService->getEnvironmentVariables(),
            'showPhpInfo' => $showPhpInfo,
            'phpinfoHtml' => $phpinfoHtml,
            'gitStatus'   => $this->systemInfoService->getGitStatus(),
            'requestUri'  => $_SERVER['REQUEST_URI'] ?? '/',
            'currentTime' => date('Y-m-d H:i:s'),
        ];

        return $this->render('system', $data);
    }

    /**
     * Health check page (services, connections, ssl)
     *
     * @noinspection PhpUnhandledExceptionInspection Exceptions propagate to routes.php error handler
     */
    public function health(): Response
    {
        $data = [
            'title'       => 'Health Checks',
            'services'    => $this->healthCheckService->getServices(),
            'connections' => $this->healthCheckService->getConnections(),
            'ssl'         => $this->healthCheckService->getSslInfo(),
            'requestUri'  => $_SERVER['REQUEST_URI'] ?? '/',
            'currentTime' => date('Y-m-d H:i:s'),
        ];

        return $this->render('health', $data);
    }

    /**
     * Code quality dashboard
     *
     * @noinspection PhpUnhandledExceptionInspection Exceptions propagate to routes.php error handler
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
            'requestUri'    => $_SERVER['REQUEST_URI'] ?? '/',
            'currentTime'   => date('Y-m-d H:i:s'),
        ];

        return $this->render('quality', $data);
    }

    /**
     * Database tools page
     *
     * @noinspection PhpUnhandledExceptionInspection Exceptions propagate to routes.php error handler
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
            'requestUri'       => $_SERVER['REQUEST_URI'] ?? '/',
            'currentTime'      => date('Y-m-d H:i:s'),
        ];

        return $this->render('database', $data);
    }

    /**
     * Logs viewer page
     *
     * @noinspection PhpUnhandledExceptionInspection Exceptions propagate to routes.php error handler
     */
    public function logs(): Response
    {
        $data = [
            'title'        => 'Logs Viewer',
            'log_sources'  => $this->logService->getAvailableLogSources(),
            'log_commands' => $this->logService->getLogCommands(),
            'log_stats'    => $this->logService->getLogStatistics(),
            'requestUri'   => $_SERVER['REQUEST_URI'] ?? '/',
            'currentTime'  => date('Y-m-d H:i:s'),
        ];

        return $this->render('logs', $data);
    }

    /**
     * Render a view using Twig
     *
     * @param array<string, mixed> $data
     * @throws LoaderError If template not found
     * @throws RuntimeError If error during rendering
     * @throws SyntaxError If template has syntax errors
     */
    private function render(string $view, array $data = []): Response
    {
        $html = $this->twig->render(sprintf('dev-dashboard/%s.html.twig', $view), $data);

        return new HtmlResponse($html);
    }
}
