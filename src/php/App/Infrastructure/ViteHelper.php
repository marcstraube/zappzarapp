<?php

declare(strict_types=1);

namespace App\Infrastructure;

/**
 * Vite Helper - Dynamically loads Vite assets based on environment
 *
 * In Development (ENV=development): Loads Vite Dev Server with HMR
 * In Production (ENV=production): Loads built assets with correct hashed filenames
 */
class ViteHelper
{
    private readonly string $env;

    private readonly string $manifestPath;

    /** @var array<string, mixed>|null */
    private ?array $manifest = null;

    private readonly string $viteDevServerUrl;

    public function __construct()
    {
        // Determine environment from ENV variable, default to production
        $this->env          = $_ENV['ENV'] ?? getenv('ENV') ?: 'production';
        $this->manifestPath = __DIR__ . '/../../../public/build/.vite/manifest.json';

        // Determine Vite Dev Server URL based on request context
        // For HTTPS requests: Use nginx proxy (same origin) to avoid mixed content
        // For HTTP requests: Can use direct Vite server (but proxy also works)
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                   || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
                   || ($_SERVER['SERVER_PORT'] ?? '') === '443';

        if ($isHttps) {
            // Use relative URLs - nginx proxies /js/, /css/, /@vite/ to Vite
            $this->viteDevServerUrl = '';
        } else {
            // Direct Vite server access (HTTP only)
            $this->viteDevServerUrl = 'http://localhost:5173';
        }
    }

    /**
     * Check if running in development mode
     */
    public function isDevelopment(): bool
    {
        return $this->env === 'development';
    }

    /**
     * Get the manifest file content
     *
     * @return array<string, mixed>|null
     */
    private function getManifest(): ?array
    {
        if ($this->manifest !== null) {
            return $this->manifest;
        }

        if (!file_exists($this->manifestPath)) {
            return null;
        }

        $content = file_get_contents($this->manifestPath);
        if ($content === false) {
            return null;
        }

        $this->manifest = json_decode($content, true);

        return $this->manifest;
    }

    /**
     * Get the URL for a Vite entry point in production
     */
    private function getAssetUrl(string $entry): ?string
    {
        $manifest = $this->getManifest();

        if ($manifest === null) {
            return null;
        }

        // Vite manifest uses the source path relative to root as key
        // In vite.config.js root is 'resources', so the manifest key is like 'js/app.js'
        $sourceKey = $entry;

        if (!isset($manifest[$sourceKey])) {
            return null;
        }

        return '/build/' . $manifest[$sourceKey]['file'];
    }

    /**
     * Get the CSS URL for a Vite entry point in production
     *
     * @return array<int, string>|null
     */
    private function getCssUrl(string $entry): ?array
    {
        $manifest = $this->getManifest();

        if ($manifest === null) {
            return null;
        }

        // Vite manifest uses the source path relative to root as key
        $sourceKey = $entry;

        if (!isset($manifest[$sourceKey])) {
            return null;
        }

        $cssFiles = $manifest[$sourceKey]['css'] ?? [];

        return array_map(fn($file): string => '/build/' . $file, $cssFiles);
    }

    /**
     * Render script tags for JavaScript
     */
    public function renderScriptTags(string $entry = 'js/app.js'): string
    {
        if ($this->isDevelopment()) {
            // Development mode: Load Vite Dev Server with HMR
            /** @noinspection HtmlUnknownTarget */
            return sprintf(
                '<script type="module" src="%s/@vite/client"></script>' . "\n"
                . '    <script type="module" src="%s/%s"></script>',
                $this->viteDevServerUrl,
                $this->viteDevServerUrl,
                $entry
            );
        }

        // Production mode: Load built assets
        $assetUrl = $this->getAssetUrl($entry);

        if ($assetUrl === null) {
            return '<!-- Vite manifest not found or entry not found -->';
        }

        /** @noinspection HtmlUnknownTarget */
        return sprintf('<script type="module" src="%s"></script>', $assetUrl);
    }

    /**
     * Render link tags for CSS
     */
    public function renderCssTags(string $entry = 'js/app.js'): string
    {
        if ($this->isDevelopment()) {
            // Development mode: CSS is injected by Vite Dev Server
            return '<!-- CSS loaded via Vite HMR -->';
        }

        // Production mode: Load built CSS
        $cssUrls = $this->getCssUrl($entry);

        if ($cssUrls === null || $cssUrls === []) {
            return '<!-- No CSS found in manifest -->';
        }

        $tags = [];
        foreach ($cssUrls as $url) {
            /** @noinspection HtmlUnknownTarget */
            $tags[] = sprintf('<link rel="stylesheet" href="%s">', $url);
        }

        return implode("\n    ", $tags);
    }

    /**
     * Get current environment
     */
    public function getEnv(): string
    {
        return $this->env;
    }

    /**
     * Check if Vite dev server is running (development mode only)
     *
     * @SuppressWarnings("PHPMD.ErrorControlOperator")
     * @SuppressWarnings("PHPMD.UnusedLocalVariable")
     */
    public function isViteDevServerRunning(): bool
    {
        if (!$this->isDevelopment()) {
            return false;
        }

        // Try to connect to Vite dev server via Node container
        $socket = @fsockopen('node', 5173, $_errno, $errstr, 1);
        if ($socket !== false) {
            fclose($socket);

            return true;
        }

        return false;
    }

    /**
     * Check if assets are available (either Vite running or production build exists)
     */
    public function areAssetsAvailable(): bool
    {
        if ($this->isDevelopment()) {
            return $this->isViteDevServerRunning();
        }

        // Production mode: check if manifest exists
        return $this->getManifest() !== null;
    }
}
