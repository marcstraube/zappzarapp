<?php

declare(strict_types=1);

namespace DevToolbar;

use DevToolbar\DataCollectors\CacheCollector;
use DevToolbar\DataCollectors\CollectorInterface;
use DevToolbar\DataCollectors\ExceptionCollector;
use DevToolbar\DataCollectors\HistoryCollector;
use DevToolbar\DataCollectors\HttpClientCollector;
use DevToolbar\DataCollectors\MessageCollector;
use DevToolbar\DataCollectors\QueryCollector;
use DevToolbar\DataCollectors\RequestCollector;
use DevToolbar\DataCollectors\TimelineCollector;
use DevToolbar\Guard\DevToolbarGuard;
use DevToolbar\Middleware\DevToolbarMiddleware;
use DevToolbar\Security\NonceHelper;

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
    private bool $booted      = false;

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
     * Output buffer callback - injects toolbar HTML into response
     *
     * Called automatically by ob_start() callback when buffer is flushed.
     * This is a secure alternative to using echo in a shutdown function.
     *
     * @param string $buffer Output buffer content
     * @return string Modified buffer with toolbar HTML injected
     */
    public function injectToolbar(string $buffer): string
    {
        if (!$this->booted || !DevToolbarGuard::isEnabled()) {
            return $buffer;
        }

        // Stop all collectors
        foreach ($this->collectors as $collector) {
            $collector->stop();
        }

        if ($buffer === '') {
            return $buffer;
        }

        // Inject toolbar HTML before </body>
        $middleware = new DevToolbarMiddleware($this->collectors);
        return $middleware->inject($buffer);
    }

    /**
     * Register all data collectors
     *
     * @return void
     */
    private function registerCollectors(): void
    {
        // Phase 1 Collectors
        $this->collectors['request']    = new RequestCollector();
        $this->collectors['queries']    = QueryCollector::getInstance();
        $this->collectors['messages']   = new MessageCollector();
        $this->collectors['exceptions'] = ExceptionCollector::getInstance();

        // Phase 2 Collectors
        $this->collectors['http']     = new HttpClientCollector();
        $this->collectors['cache']    = new CacheCollector();
        $this->collectors['timeline'] = new TimelineCollector();
        $this->collectors['history']  = new HistoryCollector(); // Client-side only (localStorage)
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

    /**
     * Get a specific collector
     *
     * @param string $name Collector name
     * @return CollectorInterface|null Collector instance or null
     */
    public function getCollector(string $name): ?CollectorInterface
    {
        return $this->collectors[$name] ?? null;
    }

    /**
     * Set CSP nonce from external source
     *
     * Allows host project to override the nonce if it has its own CSP implementation.
     * Should be called before boot() if used.
     *
     * @param string $nonce External nonce value
     * @return void
     */
    public function setNonce(string $nonce): void
    {
        NonceHelper::set($nonce);
    }

    /**
     * Get current CSP nonce
     *
     * @return string Current nonce value
     */
    public function getNonce(): string
    {
        return NonceHelper::get();
    }
}
