<?php

declare(strict_types=1);

namespace DevToolbar;

use DevToolbar\Config\CookieConfigSource;
use DevToolbar\DataCollectors\CollectorFactory;
use DevToolbar\DataCollectors\CollectorInterface;
use DevToolbar\Guard\DevToolbarGuard;
use DevToolbar\Middleware\DevToolbarMiddleware;
use Zappzarapp\Security\Csp\Exception\InvalidDirectiveValueException;
use Zappzarapp\Security\Csp\Nonce\NonceRegistry;

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
    /** @noinspection PhpGetterAndSetterCanBeReplacedWithPropertyHooksInspection PHP 8.4 hooks crash PDepend/PHPMD */
    private bool $booted = false;

    private function __construct()
    {
    }

    public function isBooted(): bool
    {
        return $this->booted;
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

        // Inject toolbar HTML before </body>.
        // CookieConfigSource::fromGlobals() is the single audit point for
        // $_COOKIE / environment / filesystem reads in the DevToolbar.
        $middleware = new DevToolbarMiddleware(
            $this->collectors,
            CookieConfigSource::fromGlobals(),
        );
        return $middleware->inject($buffer);
    }

    /**
     * Register all data collectors
     *
     * @return void
     */
    private function registerCollectors(): void
    {
        $this->collectors = CollectorFactory::createDefault();
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
     * Validates input to prevent CSP injection attacks (Defense in Depth).
     *
     * @param string $nonce External nonce value (base64-encoded recommended)
     * @return void
     *
     * @throws InvalidDirectiveValueException If nonce contains invalid characters
     */
    public function setNonce(string $nonce): void
    {
        NonceRegistry::set($nonce);
    }

}
