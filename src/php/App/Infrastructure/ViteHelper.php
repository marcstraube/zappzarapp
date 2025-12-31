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
    private string $env;
    private string $manifestPath;
    private ?array $manifest = null;
    private string $viteDevServerUrl;

    public function __construct()
    {
        // Determine environment from ENV variable, default to production
        $this->env          = $_ENV['ENV'] ?? getenv('ENV') ?: 'production';
        $this->manifestPath = __DIR__ . '/../../../public/build/.vite/manifest.json';
        // Use localhost:5173 for Vite Dev Server with CORS enabled
        $this->viteDevServerUrl = 'http://localhost:5173';
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
     */
    private function getManifest(): ?array
    {
        if ($this->manifest !== null) {
            return $this->manifest;
        }

        if (!file_exists($this->manifestPath)) {
            return null;
        }

        $content        = file_get_contents($this->manifestPath);
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

        return array_map(fn($file) => '/build/' . $file, $cssFiles);
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

        if (empty($cssUrls)) {
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
}
