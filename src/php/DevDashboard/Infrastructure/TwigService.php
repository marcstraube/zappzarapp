<?php
/* @noinspection DuplicatedCode Intentional duplication - App and DevDashboard use separate namespaces */

declare(strict_types=1);

namespace DevDashboard\Infrastructure;

use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

/**
 * Twig Template Service for DevDashboard
 *
 * Manages Twig Environment configuration and template rendering for DevDashboard namespace.
 * Provides cache management, auto-escaping, and helper function registration.
 */
final readonly class TwigService
{
    private Environment $twig;

    /**
     * Private constructor - use factory methods instead
     *
     * @param string $templateDir Absolute path to templates directory
     * @param string $cacheDir Absolute path to cache directory
     * @param bool $enableCache Enable template caching
     * @param bool $autoReload Auto-reload templates when they change
     */
    private function __construct(
        string $templateDir,
        string $cacheDir,
        bool $enableCache,
        bool $autoReload
    ) {
        $loader = new FilesystemLoader($templateDir);

        $this->twig = new Environment($loader, [
            'cache'            => $enableCache ? $cacheDir : false,
            'auto_reload'      => $autoReload,
            'strict_variables' => true,
            'autoescape'       => 'html',
        ]);
    }

    /**
     * Create TwigService for development environment
     * - No caching (instant template updates)
     * - Auto-reload enabled
     */
    public static function createForDevelopment(string $templateDir, string $cacheDir): self
    {
        return new self($templateDir, $cacheDir, enableCache: false, autoReload: true);
    }

    /**
     * Create TwigService for production environment
     * - Caching enabled (better performance)
     * - Auto-reload disabled
     */
    public static function createForProduction(string $templateDir, string $cacheDir): self
    {
        return new self($templateDir, $cacheDir, enableCache: true, autoReload: false);
    }

    /**
     * Render a template with provided context
     *
     * @param string $template Template path relative to template directory (e.g., 'dev-dashboard/system.html.twig')
     * @param array<string, mixed> $context Variables to pass to template
     * @return string Rendered HTML
     * @throws LoaderError If template not found
     * @throws RuntimeError If error during rendering
     * @throws SyntaxError If template has syntax errors
     */
    public function render(string $template, array $context = []): string
    {
        return $this->twig->render($template, $context);
    }

    /**
     * Register a custom Twig function
     *
     * @param string $name Function name in templates
     * @param callable $callback PHP callable to execute
     */
    public function addFunction(string $name, callable $callback): void
    {
        $this->twig->addFunction(new TwigFunction($name, $callback));
    }

    /**
     * Register a global variable available in all templates
     *
     * @param string $name Variable name
     * @param mixed $value Variable value
     * @noinspection PhpUnused Public API method for template configuration
     */
    public function addGlobal(string $name, mixed $value): void
    {
        $this->twig->addGlobal($name, $value);
    }

    /**
     * Get underlying Twig Environment (for advanced configuration)
     *
     * @return Environment Twig Environment instance
     */
    public function getEnvironment(): Environment
    {
        return $this->twig;
    }
}
