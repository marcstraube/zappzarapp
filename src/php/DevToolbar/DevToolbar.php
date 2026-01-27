<?php

declare(strict_types=1);

namespace DevToolbar;

use DevToolbar\DataCollectors\CollectorInterface;
use DevToolbar\DataCollectors\ExceptionCollector;
use DevToolbar\DataCollectors\MessageCollector;
use DevToolbar\DataCollectors\QueryCollector;
use DevToolbar\DataCollectors\RequestCollector;
use DevToolbar\Guard\DevToolbarGuard;
use DevToolbar\Middleware\DevToolbarMiddleware;

/**
 * Main Developer Toolbar class
 *
 * Singleton that coordinates all collectors and rendering.
 */
class DevToolbar
{
    private static ?self $instance = null;

    /** @var array<string, CollectorInterface> */
    private array $collectors = [];
    private bool $booted = false;

    private function __construct()
    {
    }

    /**
     * Get singleton instance
     *
     * @return self
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Boot the toolbar and start collecting data
     *
     * @return void
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        if (!DevToolbarGuard::isEnabled()) {
            return;
        }

        // Register collectors
        $this->registerCollectors();

        // Start collecting
        foreach ($this->collectors as $collector) {
            $collector->start();
        }

        $this->booted = true;
    }

    /**
     * Render toolbar HTML and inject into response
     *
     * @return void
     */
    public function render(): void
    {
        if (!$this->booted || !DevToolbarGuard::isEnabled()) {
            return;
        }

        // Stop all collectors
        foreach ($this->collectors as $collector) {
            $collector->stop();
        }

        // Get output buffer
        $output = ob_get_clean();

        if ($output === false || $output === '') {
            return;
        }

        // Inject toolbar HTML before </body>
        $middleware = new DevToolbarMiddleware($this->collectors);
        $modifiedOutput = $middleware->inject($output);

        echo $modifiedOutput;
    }

    /**
     * Register all data collectors
     *
     * @return void
     */
    private function registerCollectors(): void
    {
        $this->collectors['request'] = new RequestCollector();
        $this->collectors['queries'] = QueryCollector::getInstance();
        $this->collectors['messages'] = new MessageCollector();
        $this->collectors['exceptions'] = ExceptionCollector::getInstance();
    }

    /**
     * Get all collectors
     *
     * @return array<string, CollectorInterface>
     */
    public function getCollectors(): array
    {
        return $this->collectors;
    }

    /**
     * Check if toolbar is booted
     *
     * @return bool
     */
    public function isBooted(): bool
    {
        return $this->booted;
    }
}
