<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\Response\HtmlResponse;
use App\Http\Response\Response;
use App\Infrastructure\HealthCheck;
use App\Infrastructure\TwigService;
use App\Infrastructure\ViteHelper;
use DevToolbar\DataCollectors\CacheCollector;
use DevToolbar\DataCollectors\ExceptionCollector;
use DevToolbar\DataCollectors\HttpClientCollector;
use DevToolbar\DataCollectors\QueryCollector;
use DevToolbar\DataCollectors\TimelineCollector;
use DevToolbar\DevToolbar;
use DevToolbar\Guard\DevToolbarGuard;
use Exception;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * Welcome Controller
 *
 * Displays the main landing page with service status dashboard
 */
readonly class WelcomeController
{
    public function __construct(
        private ViteHelper $vite,
        private HealthCheck $health,
        private TwigService $twig,
    ) {}

    /**
     * Display the welcome page
     *
     * @throws LoaderError If template not found
     * @throws RuntimeError If error during rendering
     * @throws SyntaxError If template has syntax errors
     */
    public function index(): Response
    {
        // Demo DevToolbar features if enabled
        if (DevToolbarGuard::isEnabled()) {
            try {
                $this->demoDevToolbarFeatures();
            } catch (Throwable $e) {
                // If demo fails, track it as an exception
                ExceptionCollector::getInstance()->trackHandled($e);
            }
        }

        $env    = $this->health->getEnvironment();
        $status = $this->health->checkAll();

        // Check for API documentation (only in development)
        $docsBase             = '/var/www/html/docs/api';
        $phpDocsExist         = file_exists($docsBase . '/php/index.html');
        $nodeBackendDocsExist = file_exists($docsBase . '/node-backend/index.html');
        $hasPhpSource         = is_dir('/var/www/html/src/php');
        $hasNodeBackendSource = is_dir('/var/www/html/src/node/backend');
        $docsMissing          = ($hasPhpSource && !$phpDocsExist) || ($hasNodeBackendSource && !$nodeBackendDocsExist);

        $html = $this->twig->render('app/welcome.html.twig', [
            'vite'                  => $this->vite,
            'env'                   => $env,
            'status'                => $status,
            'phpDocsExist'          => $phpDocsExist,
            'nodeBackendDocsExist'  => $nodeBackendDocsExist,
            'hasPhpSource'          => $hasPhpSource,
            'hasNodeBackendSource'  => $hasNodeBackendSource,
            'docsMissing'           => $docsMissing,
        ]);

        return new HtmlResponse($html);
    }

    /**
     * Demo all DevToolbar features for testing
     */
    private function demoDevToolbarFeatures(): void
    {
        $toolbar = DevToolbar::getInstance();
        if (!$toolbar->isBooted()) {
            return;
        }

        // 1. Demo QUERIES Tab - Simulate database queries with N+1 pattern
        $this->demoQueries();

        // 2. Demo EXCEPTIONS Tab - Track a handled exception
        $this->demoExceptions();

        // 3. Demo HTTP Tab - Simulate HTTP requests
        $this->demoHttpRequests($toolbar);

        // 4. Demo CACHE Tab - Simulate cache operations
        $this->demoCacheOperations($toolbar);

        // 5. Demo TIMELINE Tab - Mark phases
        $this->demoTimeline($toolbar);
    }

    /**
     * Demo database queries with N+1 pattern
     */
    private function demoQueries(): void
    {
        $collector = QueryCollector::getInstance();

        // Simulate initial query
        $collector->trackQuery(
            'SELECT * FROM users WHERE status = ?',
            ['active'],
            15.5
        );

        // Simulate N+1 pattern - fetching posts for each user
        for ($i = 1; $i <= 5; $i++) {
            $collector->trackQuery(
                "SELECT * FROM posts WHERE user_id = {$i}",
                [$i],
                10.0 + ($i * 2)
            );
        }

        // Simulate a slow query
        $collector->trackQuery(
            'SELECT * FROM large_table WHERE created_at > ? ORDER BY id DESC',
            ['2024-01-01'],
            250.5
        );

        // Simulate fast queries
        $collector->trackQuery('SELECT COUNT(*) FROM users', [], 2.1);
        $collector->trackQuery('SELECT version()', [], 0.8);
    }

    /**
     * Demo exception tracking
     */
    private function demoExceptions(): void
    {
        // Get the singleton instance that's already started by DevToolbar
        $collector = ExceptionCollector::getInstance();

        try {
            throw new RuntimeException('Demo exception: This is a handled exception for testing the DevToolbar');
        } catch (Exception $e) {
            $collector->trackHandled($e);
        }

        try {
            throw new InvalidArgumentException('Demo validation error: Invalid user input provided');
        } catch (Exception $e) {
            $collector->trackHandled($e);
        }
    }

    /**
     * Demo HTTP client requests
     */
    private function demoHttpRequests(DevToolbar $toolbar): void
    {
        $collector = $toolbar->getCollector('http');
        if (!$collector instanceof HttpClientCollector) {
            return;
        }

        // Fast request (green)
        $collector->trackRequest('GET', 'https://api.example.com/users', 150.0, 200, ['Content-Type' => 'application/json'], '{"users": [{"id": 1, "name": "John"}]}');

        // Medium request (yellow)
        $collector->trackRequest('POST', 'https://api.example.com/auth', 350.0, 201, ['Content-Type' => 'application/json'], '{"token": "secret123", "expires": 3600}');

        // Slow request (red)
        $collector->trackRequest('GET', 'https://slow-api.example.com/data', 650.0, 200, ['Content-Type' => 'application/json'], '{"data": "large response..."}');
    }

    /**
     * Demo cache operations
     */
    private function demoCacheOperations(DevToolbar $toolbar): void
    {
        $collector = $toolbar->getCollector('cache');
        if (!$collector instanceof CacheCollector) {
            return;
        }

        // Cache hits (simulate successful reads)
        $collector->trackOperation('get', 'user:123', 2.1, '{"id": 123, "name": "John"}');
        $collector->trackOperation('get', 'user:456', 1.8, '{"id": 456, "name": "Jane"}');
        $collector->trackOperation('get', 'session:abc123', 2.3, '{"user_id": 123, "last_active": 1234567890}');

        // Cache misses
        $collector->trackOperation('get', 'user:999', 1.9);

        // Cache sets
        $collector->trackOperation('set', 'user:999', 3.2, '{"id": 999, "name": "Bob"}', 3600);
        /** @noinspection HtmlRequiredLangAttribute Demo data string, not actual HTML */
        $collector->trackOperation('set', 'page:home', 4.1, '<html>...</html>', 7200);

        // Cache delete
        $collector->trackOperation('delete', 'old_cache:*', 5.5, 10);
    }

    /**
     * Demo timeline phases
     */
    private function demoTimeline(DevToolbar $toolbar): void
    {
        $collector = $toolbar->getCollector('timeline');
        if (!$collector instanceof TimelineCollector) {
            return;
        }

        // Simulate bootstrap phase
        $collector->addEvent('bootstrap_done', 'Bootstrap Complete', 15.0, 'bootstrap');

        // Simulate middleware phase
        $collector->addEvent('middleware_auth', 'Auth Middleware', 5.0, 'middleware');
        $collector->addEvent('middleware_cors', 'CORS Middleware', 3.0, 'middleware');

        // Simulate controller phase
        $collector->startPhase('controller', 'Controller Execution', 'controller');
        usleep(50000); // 50ms
        $collector->endPhase('controller');

        // Add aggregated data from other collectors
        $queryData = QueryCollector::getInstance()->getData();
        if ($queryData['count'] > 0) {
            $collector->addAggregatedData(
                'Database Queries',
                $queryData['count'],
                $queryData['total_time'],
                'database'
            );
        }

        // Add HTTP requests to timeline
        $httpCollector = $toolbar->getCollector('http');
        if ($httpCollector instanceof HttpClientCollector) {
            $httpData = $httpCollector->getData();
            if ($httpData['count'] > 0) {
                $collector->addAggregatedData(
                    'HTTP Requests',
                    $httpData['count'],
                    $httpData['total_time'],
                    'http'
                );
            }
        }

        // Add cache operations to timeline
        $cacheCollector = $toolbar->getCollector('cache');
        if ($cacheCollector instanceof CacheCollector) {
            $cacheData = $cacheCollector->getData();
            if ($cacheData['count'] > 0) {
                $collector->addAggregatedData(
                    'Cache Operations',
                    $cacheData['count'],
                    $cacheData['total_time'],
                    'cache'
                );
            }
        }

        // View rendering
        $collector->addEvent('view_render', 'View Rendering', 45.0, 'view');
    }
}
