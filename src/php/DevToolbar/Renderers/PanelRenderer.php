<?php

declare(strict_types=1);

namespace DevToolbar\Renderers;

use DevToolbar\Analyzers\PerformanceAnalyzer;
use DevToolbar\Analyzers\QueryAnalyzer;
use DevToolbar\DataCollectors\CollectorInterface;
use DevToolbar\Renderers\Panels\CachePanelRenderer;
use DevToolbar\Renderers\Panels\ExceptionsPanelRenderer;
use DevToolbar\Renderers\Panels\HistoryPanelRenderer;
use DevToolbar\Renderers\Panels\HttpPanelRenderer;
use DevToolbar\Renderers\Panels\MessagesPanelRenderer;
use DevToolbar\Renderers\Panels\PanelRendererInterface;
use DevToolbar\Renderers\Panels\QueriesPanelRenderer;
use DevToolbar\Renderers\Panels\RequestPanelRenderer;
use DevToolbar\Renderers\Panels\TimelinePanelRenderer;

/**
 * Renders expandable panel with tabs
 */
class PanelRenderer implements RendererInterface
{
    /** @var array<string, PanelRendererInterface>
     * @noinspection PhpGetterAndSetterCanBeReplacedWithPropertyHooksInspection PDepend crashes on property hooks
     */
    private array $panelRenderers = [];

    /**
     * @param array<string, CollectorInterface> $collectors
     */
    public function __construct(private readonly array $collectors)
    {
        $this->registerPanelRenderers();
    }

    /**
     * Register all panel renderers
     */
    private function registerPanelRenderers(): void
    {
        $this->panelRenderers = [
            'request'    => new RequestPanelRenderer(),
            'messages'   => new MessagesPanelRenderer(),
            'exceptions' => new ExceptionsPanelRenderer(),
            'http'       => new HttpPanelRenderer(),
            'cache'      => new CachePanelRenderer(),
            'timeline'   => new TimelinePanelRenderer(),
            'queries'    => new QueriesPanelRenderer(new QueryAnalyzer()),
            'history'    => new HistoryPanelRenderer(),
        ];
    }

    /**
     * Get all panel renderers
     *
     * @return array<string, PanelRendererInterface>
     */
    public function getPanelRenderers(): array
    {
        return $this->panelRenderers;
    }

    public function render(): string
    {
        $tabs            = $this->renderTabs();
        $alerts          = $this->renderAlerts();
        $content         = $this->renderTabContents();
        $requestSwitcher = $this->renderRequestSwitcher();

        return sprintf(
            '<div class="dev-toolbar-panel">
                <div class="dev-toolbar-panel-header">
                    %s
                    %s
                    <button class="dev-toolbar-panel-settings" title="Settings">⚙️</button>
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
        $collectorData = array_map(
            fn(CollectorInterface $collector): array => $collector->getData(),
            $this->collectors
        );

        // Analyze performance (with optional custom thresholds from cookie)
        $alerts = PerformanceAnalyzer::analyze($collectorData, $this->getCustomThresholds());

        if ($alerts === []) {
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
            $icon       = $alert['icon'] ?? '⚪';
            $message    = htmlspecialchars($alert['message'] ?? '');
            $action     = htmlspecialchars($alert['action'] ?? '');

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

        return $html . '</div>';
    }

    /**
     * Read custom performance thresholds from cookie
     *
     * @return array<string, int>|null Custom thresholds or null for defaults
     */
    private function getCustomThresholds(): ?array
    {
        $json = $_COOKIE['devbar_thresholds'] ?? null;
        if ($json === null) {
            return null;
        }

        $decoded = json_decode(urldecode((string) $json), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Render tab buttons
     *
     * @return string Tab buttons HTML
     */
    private function renderTabs(): string
    {
        $tabs = '<!-- DevToolbar: Rendering tabs -->' . "\n";

        foreach ($this->collectors as $name => $collector) {
            $data  = $collector->getData();
            $count = $data['count'] ?? 0;
            $label = strtoupper($name);

            // Always render badge for history tab (JavaScript will update it)
            // For other tabs, only render if count > 0
            if ($name === 'history') {
                $badge = '<span class="dev-toolbar-panel-tab-badge">0</span>';
                $tabs .= "<!-- Tab: $name, Count: $count, Badge: ALWAYS RENDERED -->\n";
            } else {
                $badge       = $count > 0 ? sprintf('<span class="dev-toolbar-panel-tab-badge">%d</span>', $count) : '';
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
        return $tabs . ('<!-- DevToolbar: Tabs rendered -->' . "\n");
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

            $data    = $collector->getData();
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

        return $html . '</div>';
    }

    /**
     * Get current request ID
     *
     * Note: With localStorage-based storage, the "current" request is always
     * the active one. Historical requests are managed client-side.
     *
     * @return string Request ID
     */
    private function getCurrentRequestId(): string
    {
        return 'current';
    }
}
