<?php

declare(strict_types=1);

namespace DevToolbar\Renderers;

use DevToolbar\DataCollectors\CollectorInterface;
use DevToolbar\Security\NonceHelper;
use DevToolbar\Utils\RequestUtils;
use Throwable;

/**
 * Injects DevToolbar data as JavaScript for localStorage storage
 *
 * Renders current request data as <script> tag with JSON payload.
 * Eliminates AJAX round-trips by embedding data directly in HTML.
 */
class DataInjectionRenderer implements RendererInterface
{
    /** @var array<string, CollectorInterface> */
    private array $collectors;

    private PanelRenderer $panelRenderer;

    /**
     * @param array<string, CollectorInterface> $collectors
     */
    public function __construct(array $collectors)
    {
        $this->collectors    = $collectors;
        $this->panelRenderer = new PanelRenderer($collectors);
    }

    /**
     * Render data injection script tag
     *
     * @return string JavaScript tag with window.__DEV_TOOLBAR_DATA__ and migration data
     */
    public function render(): string
    {
        $nonce   = NonceHelper::get();
        $scripts = '';

        // Inject current request data
        $requestId = RequestUtils::generateId();
        $metadata  = $this->extractMetadata($requestId);
        $tabs      = $this->renderAllTabs();
        $jsonData  = $this->extractJsonData();

        $currentPayload = [
            'id'        => $requestId,
            'metadata'  => $metadata,
            'tabs'      => $tabs,
            'json_data' => $jsonData,
        ];

        $json = json_encode($currentPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        $scripts .= sprintf(
            '<script nonce="%s">window.__DEV_TOOLBAR_DATA__ = %s;</script>',
            htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8'),
            $json
        );

        // Inject Xdebug configuration (live state, not historical)
        $xdebugConfig = [
            'enabled' => extension_loaded('xdebug'),
        ];
        $xdebugJson = json_encode($xdebugConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $scripts .= sprintf(
            '<script nonce="%s">window.__XDEBUG_CONFIG__ = %s;</script>',
            htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8'),
            $xdebugJson
        );

        return $scripts;
    }

    /**
     * Extract lightweight metadata for request
     *
     * @param string $requestId Generated request ID
     * @return array<string, mixed> Metadata array
     */
    private function extractMetadata(string $requestId): array
    {
        // Get request data from collector
        $requestData = isset($this->collectors['request']) ? $this->collectors['request']->getData() : [];
        $queryData   = isset($this->collectors['queries']) ? $this->collectors['queries']->getData() : [];

        // Collect badge counts for all tabs
        $badgeCounts = [];
        foreach ($this->collectors as $name => $collector) {
            $data               = $collector->getData();
            $badgeCounts[$name] = $data['count'] ?? 0;
        }

        $timestamp = time();

        return [
            'id'                 => $requestId,
            'method'             => $requestData['method'] ?? 'GET',
            'uri'                => $requestData['uri'] ?? '/',
            'status'             => $requestData['status_code'] ?? 200,
            'time'               => $requestData['execution_time'] ?? 0,
            'memory'             => $requestData['memory_peak'] ?? 0,
            'query_count'        => $queryData['count'] ?? 0,
            'timestamp'          => $timestamp,
            'date'               => date('Y-m-d H:i:s', $timestamp),
            'badge_counts'       => $badgeCounts,
            'minibar_label_type' => $this->getMinibarLabelType(),
            'git_branch'         => $this->getGitBranch(),
            'branch_colors'      => $this->getBranchColors(),
            'request_id'         => $requestId,
        ];
    }

    /**
     * Get minibar label type from cookie (set by client-side JS)
     *
     * Returns 'branding' as default if cookie is not set or invalid.
     */
    private function getMinibarLabelType(): string
    {
        $labelType = $_COOKIE['devbar_label'] ?? 'branding';
        $allowed   = ['branding', 'branch', 'route', 'request-id'];
        return in_array($labelType, $allowed, true) ? $labelType : 'branding';
    }

    /**
     * Get current git branch from .git/HEAD (read-only, no shell execution)
     */
    private function getGitBranch(): ?string
    {
        // Try environment variable first (can be set in CI/CD or .env)
        $envBranch = getenv('GIT_BRANCH');
        if ($envBranch !== false && $envBranch !== '') {
            return $envBranch;
        }

        // Fallback: Read from .git/HEAD (safer than shell_exec)
        $gitHeadPath = getcwd() . '/.git/HEAD';
        if (!file_exists($gitHeadPath)) {
            return null;
        }

        try {
            $headContent = file_get_contents($gitHeadPath);
            if ($headContent === false) {
                return null;
            }

            // Format: "ref: refs/heads/branch-name" or commit hash
            if (str_starts_with($headContent, 'ref: refs/heads/')) {
                return trim(substr($headContent, 16));
            }

            // Detached HEAD (commit hash)
            return null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Get branch colors from cookie or defaults
     *
     * Client-side can override these colors via cookie (set from localStorage).
     * These defaults match DEFAULT_BRANCH_COLORS in StorageConfig.ts.
     *
     * @return array<string, string>
     */
    private function getBranchColors(): array
    {
        $defaults = [
            'feat'    => '#3b82f6',    // Blue
            'fix'     => '#f59e0b',     // Orange
            'hotfix'  => '#ef4444',  // Red
            'chore'   => '#6b7280',   // Gray
            'default' => '#10b981', // Green
        ];

        if (!isset($_COOKIE['devbar_colors'])) {
            return $defaults;
        }

        try {
            $decoded = json_decode(urldecode($_COOKIE['devbar_colors']), true);
            if (is_array($decoded)) {
                // Merge with defaults to ensure all keys exist
                return array_merge($defaults, $decoded);
            }
        } catch (Throwable $e) {
            // Invalid JSON, return defaults
        }

        return $defaults;
    }

    /**
     * Extract JSON data from collectors
     *
     * Returns JSON data from all collectors for export.
     * This data is cleaner for external consumption than rendered HTML.
     *
     * @return array<string, mixed> Tab name => JSON data
     */
    private function extractJsonData(): array
    {
        $jsonData = [];

        foreach ($this->collectors as $name => $collector) {
            $jsonData[$name] = $collector->getData();
        }

        return $jsonData;
    }

    /**
     * Render all tab contents as key-value pairs
     *
     * @return array<string, string> Tab name => HTML content
     */
    private function renderAllTabs(): array
    {
        $tabs           = [];
        $panelRenderers = $this->panelRenderer->getPanelRenderers();

        foreach ($this->collectors as $name => $collector) {
            $data     = $collector->getData();
            $renderer = $panelRenderers[$name] ?? null;

            if ($renderer) {
                $tabs[$name] = $renderer->renderTab($data);
            } else {
                // Fallback for tabs without dedicated render methods
                $tabs[$name] = '<p>No data available</p>';
            }
        }

        return $tabs;
    }

}
