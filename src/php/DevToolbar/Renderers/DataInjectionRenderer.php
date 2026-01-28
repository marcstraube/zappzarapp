<?php

declare(strict_types=1);

namespace DevToolbar\Renderers;

use DevToolbar\DataCollectors\CollectorInterface;
use DevToolbar\Security\NonceHelper;
use DevToolbar\Storage\RequestStore;
use ReflectionClass;

/**
 * Injects DevToolbar data as JavaScript for localStorage storage
 *
 * Renders current request data as <script> tag with structured JSON payload.
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
        $this->collectors = $collectors;
        $this->panelRenderer = new PanelRenderer($collectors);
    }

    /**
     * Render data injection script tag
     *
     * @return string JavaScript tag with window.__DEV_TOOLBAR_DATA__ and migration data
     */
    public function render(): string
    {
        $nonce = NonceHelper::get();
        $scripts = '';

        // Inject current request data
        $requestId = RequestStore::generateId();
        $metadata = $this->extractMetadata($requestId);
        $tabs = $this->renderAllTabs();

        $currentPayload = [
            'id' => $requestId,
            'metadata' => $metadata,
            'tabs' => $tabs,
        ];

        $json = json_encode($currentPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        $scripts .= sprintf(
            '<script nonce="%s">window.__DEV_TOOLBAR_DATA__ = %s;</script>',
            htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8'),
            $json
        );

        // Check if migration is needed (session data exists and not yet migrated)
        $migrationData = $this->getMigrationData();
        if (!empty($migrationData)) {
            $migrationJson = json_encode($migrationData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

            $scripts .= sprintf(
                '<script nonce="%s">window.__DEV_TOOLBAR_MIGRATION__ = %s;</script>',
                htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8'),
                $migrationJson
            );
        }

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
        $queryData = isset($this->collectors['queries']) ? $this->collectors['queries']->getData() : [];

        // Collect badge counts for all tabs
        $badgeCounts = [];
        foreach ($this->collectors as $name => $collector) {
            $data = $collector->getData();
            $badgeCounts[$name] = $data['count'] ?? 0;
        }

        return [
            'id' => $requestId,
            'method' => $requestData['method'] ?? 'GET',
            'uri' => $requestData['uri'] ?? '/',
            'status' => $requestData['status_code'] ?? 200,
            'time' => $requestData['execution_time'] ?? 0,
            'memory' => $requestData['memory_peak'] ?? 0,
            'query_count' => $queryData['count'] ?? 0,
            'timestamp' => time(),
            'badge_counts' => $badgeCounts,
        ];
    }

    /**
     * Render all tab contents as key-value pairs
     *
     * Uses reflection to access PanelRenderer's panel renderers and call renderTab().
     *
     * @return array<string, string> Tab name => HTML content
     */
    private function renderAllTabs(): array
    {
        $tabs = [];
        $reflection = new ReflectionClass($this->panelRenderer);

        // Access private $panelRenderers property
        $panelRenderersProperty = $reflection->getProperty('panelRenderers');
        $panelRenderersProperty->setAccessible(true);
        $panelRenderers = $panelRenderersProperty->getValue($this->panelRenderer);

        foreach ($this->collectors as $name => $collector) {
            $data = $collector->getData();
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

    /**
     * Get migration data from session storage
     *
     * Returns array of historical requests with rendered tabs if migration is needed.
     *
     * @return array<int, array<string, mixed>> Migration data array
     */
    private function getMigrationData(): array
    {
        // Check if already migrated
        if (isset($_SESSION['dev_toolbar_migrated'])) {
            return [];
        }

        // Get requests from session
        $migrationRequests = RequestStore::getAllForMigration();

        if (empty($migrationRequests)) {
            return [];
        }

        $migrationData = [];
        $reflection = new ReflectionClass($this->panelRenderer);

        // Access private $panelRenderers property
        $panelRenderersProperty = $reflection->getProperty('panelRenderers');
        $panelRenderersProperty->setAccessible(true);
        $panelRenderers = $panelRenderersProperty->getValue($this->panelRenderer);

        foreach ($migrationRequests as $request) {
            $requestId = $request['id'];
            $metadata = $request['metadata'];
            $collectorData = $request['data'];

            // Render tabs for this historical request
            $tabs = [];

            foreach ($collectorData as $collectorName => $data) {
                $renderer = $panelRenderers[$collectorName] ?? null;

                if ($renderer) {
                    $tabs[$collectorName] = $renderer->renderTab($data);
                } else {
                    $tabs[$collectorName] = '<p>No data available</p>';
                }
            }

            $migrationData[] = [
                'id' => $requestId,
                'metadata' => $metadata,
                'tabs' => $tabs,
            ];
        }

        // Mark as migrated
        $_SESSION['dev_toolbar_migrated'] = true;

        return $migrationData;
    }
}
