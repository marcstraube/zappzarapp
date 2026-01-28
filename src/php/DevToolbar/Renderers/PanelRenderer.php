<?php

declare(strict_types=1);

namespace DevToolbar\Renderers;

use DevToolbar\DataCollectors\CollectorInterface;
use DevToolbar\Analyzers\PerformanceAnalyzer;
use DevToolbar\Analyzers\QueryAnalyzer;
use DevToolbar\Renderers\Panels\CachePanelRenderer;
use DevToolbar\Renderers\Panels\ExceptionsPanelRenderer;
use DevToolbar\Renderers\Panels\HistoryPanelRenderer;
use DevToolbar\Renderers\Panels\HttpPanelRenderer;
use DevToolbar\Renderers\Panels\MessagesPanelRenderer;
use DevToolbar\Renderers\Panels\PanelRendererInterface;
use DevToolbar\Renderers\Panels\QueriesPanelRenderer;
use DevToolbar\Renderers\Panels\RequestPanelRenderer;
use DevToolbar\Renderers\Panels\TimelinePanelRenderer;
use DevToolbar\Storage\RequestStore;

/**
 * Renders expandable panel with tabs
 */
class PanelRenderer implements RendererInterface
{
    /** @var array<string, CollectorInterface> */
    private array $collectors;

    /** @var array<string, PanelRendererInterface> */
    private array $panelRenderers = [];

    /**
     * @param array<string, CollectorInterface> $collectors
     */
    public function __construct(array $collectors)
    {
        $this->collectors = $collectors;
        $this->registerPanelRenderers();
    }

    /**
     * Register all panel renderers
     *
     * @return void
     */
    private function registerPanelRenderers(): void
    {
        $this->panelRenderers = [
            'request' => new RequestPanelRenderer(),
            'messages' => new MessagesPanelRenderer(),
            'exceptions' => new ExceptionsPanelRenderer(),
            'http' => new HttpPanelRenderer(),
            'cache' => new CachePanelRenderer(),
            'timeline' => new TimelinePanelRenderer(),
            'queries' => new QueriesPanelRenderer(new QueryAnalyzer()),
            'history' => new HistoryPanelRenderer(),
        ];
    }

    public function render(): string
    {
        $tabs = $this->renderTabs();
        $alerts = $this->renderAlerts();
        $content = $this->renderTabContents();
        $requestSwitcher = $this->renderRequestSwitcher();

        return sprintf(
            '<div class="dev-toolbar-panel">
                <div class="dev-toolbar-panel-header">
                    %s
                    %s
                    <button class="dev-toolbar-panel-maximize" title="Maximize/Restore">⛶</button>
                    <button class="dev-toolbar-panel-close" title="Close">▼</button>
                </div>
                %s
                <div class="dev-toolbar-panel-content">
                    %s
                </div>
            </div>',
            $tabs,
            $requestSwitcher,
            $alerts,
            $content
        );
    }

    /**
     * Render performance alerts
     *
     * @return string Alerts HTML
     */
    private function renderAlerts(): string
    {
        // Collect all data from collectors
        $collectorData = [];
        foreach ($this->collectors as $name => $collector) {
            $collectorData[$name] = $collector->getData();
        }

        // Analyze performance
        $alerts = PerformanceAnalyzer::analyze($collectorData);

        if (empty($alerts)) {
            return '';
        }

        $html = '<div class="dev-toolbar-alerts">';
        $html .= sprintf(
            '<div class="dev-toolbar-alerts-header">
                <div class="dev-toolbar-alerts-title">⚠️ Performance Alerts (%d issues detected)</div>
                <button class="dev-toolbar-alerts-dismiss" title="Dismiss all alerts">×</button>
            </div>',
            count($alerts)
        );

        foreach ($alerts as $index => $alert) {
            $levelClass = 'alert-' . ($alert['level'] ?? 'info');
            $icon = $alert['icon'] ?? '⚪';
            $message = htmlspecialchars($alert['message'] ?? '');
            $action = htmlspecialchars($alert['action'] ?? '');

            $html .= sprintf(
                '<div class="dev-toolbar-alert %s" data-alert-index="%d">
                    <div class="dev-toolbar-alert-content">
                        <div class="dev-toolbar-alert-message">%s <strong>%s</strong></div>
                        <div class="dev-toolbar-alert-details">
                            Threshold: %s | Actual: %s
                        </div>
                        <div class="dev-toolbar-alert-action">Action: %s</div>
                    </div>
                    <button class="dev-toolbar-alert-close" title="Dismiss this alert">×</button>
                </div>',
                $levelClass,
                $index,
                $icon,
                $message,
                htmlspecialchars($alert['threshold'] ?? ''),
                htmlspecialchars($alert['actual'] ?? ''),
                $action
            );
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render tab buttons
     *
     * @return string Tab buttons HTML
     */
    private function renderTabs(): string
    {
        $tabs = '';
        $tabs .= '<!-- DevToolbar: Rendering tabs -->' . "\n";

        foreach ($this->collectors as $name => $collector) {
            $data = $collector->getData();
            $count = $data['count'] ?? 0;
            $label = strtoupper($name);

            // Always render badge for history tab (JavaScript will update it)
            // For other tabs, only render if count > 0
            if ($name === 'history') {
                $badge = '<span class="dev-toolbar-panel-tab-badge">0</span>';
                $tabs .= "<!-- Tab: $name, Count: $count, Badge: ALWAYS RENDERED -->\n";
            } else {
                $badge = $count > 0 ? sprintf('<span class="dev-toolbar-panel-tab-badge">%d</span>', $count) : '';
                $badgeStatus = $count > 0 ? "RENDERED (count=$count)" : "SKIPPED (count=0)";
                $tabs .= "<!-- Tab: $name, Count: $count, Badge: $badgeStatus -->\n";
            }

            $tabs .= sprintf(
                '<button class="dev-toolbar-panel-tab" data-tab="%s">
                    %s%s
                </button>',
                $name,
                $label,
                $badge
            );
        }

        $tabs .= '<!-- DevToolbar: Tabs rendered -->' . "\n";
        return $tabs;
    }

    /**
     * Render tab content panes
     *
     * @return string Tab content HTML
     */
    private function renderTabContents(): string
    {
        $contents = '';

        foreach ($this->collectors as $name => $collector) {
            $renderer = $this->panelRenderers[$name] ?? null;
            if (!$renderer) {
                continue; // Skip if no renderer registered
            }

            $data = $collector->getData();
            $content = $renderer->renderTab($data);

            $contents .= sprintf(
                '<div class="dev-toolbar-panel-tab-pane" data-tab="%s">%s</div>',
                htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
                $content
            );
        }

        return $contents;
    }

    /**
     * Render request switcher dropdown
     *
     * @return string HTML
     */
    private function renderRequestSwitcher(): string
    {
        $currentRequestId = $this->getCurrentRequestId();

        // With localStorage migration, dropdown is populated client-side by JavaScript
        // Only render the container structure - JavaScript will populate from localStorage
        $html = '<div class="dev-toolbar-request-switcher">';
        $html .= sprintf(
            '<button class="dev-toolbar-request-switcher-toggle" data-current="%s">
                <span class="dev-toolbar-request-switcher-label">Request</span>
                <span class="dev-toolbar-request-switcher-arrow">▼</span>
            </button>',
            htmlspecialchars($currentRequestId)
        );

        // Empty dropdown - JavaScript will populate via StorageManager
        $html .= '<div class="dev-toolbar-request-switcher-dropdown"></div>';

        $html .= '</div>';

        return $html;
    }

    /**
     * Get current request ID
     *
     * @return string Request ID
     */
    private function getCurrentRequestId(): string
    {
        // Try to get from stored requests (if this is a reload)
        $storedRequests = RequestStore::getAll();
        if (!empty($storedRequests)) {
            return array_key_first($storedRequests);
        }

        return 'current';
    }
}
