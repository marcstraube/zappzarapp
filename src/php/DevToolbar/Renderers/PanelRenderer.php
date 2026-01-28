<?php

declare(strict_types=1);

namespace DevToolbar\Renderers;

use DevToolbar\DataCollectors\CollectorInterface;
use DevToolbar\Analyzers\QueryAnalyzer;
use DevToolbar\Analyzers\PerformanceAnalyzer;
use DevToolbar\Storage\RequestStore;

/**
 * Renders expandable panel with tabs
 */
class PanelRenderer implements RendererInterface
{
    /** @var array<string, CollectorInterface> */
    private array $collectors;

    /**
     * @param array<string, CollectorInterface> $collectors
     */
    public function __construct(array $collectors)
    {
        $this->collectors = $collectors;
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
            $data = $collector->getData();
            $content = match ($name) {
                'request' => $this->renderRequestTab($data),
                'queries' => $this->renderQueriesTab($data),
                'messages' => $this->renderMessagesTab($data),
                'exceptions' => $this->renderExceptionsTab($data),
                'http' => $this->renderHttpTab($data),
                'cache' => $this->renderCacheTab($data),
                'timeline' => $this->renderTimelineTab($data),
                'history' => $this->renderHistoryTab($data),
                default => '<p>No data</p>',
            };

            $contents .= sprintf(
                '<div class="dev-toolbar-panel-tab-pane" data-tab="%s">%s</div>',
                $name,
                $content
            );
        }

        return $contents;
    }

    /**
     * Render REQUEST tab content
     *
     * @param array<string, mixed> $data
     * @return string HTML
     */
    private function renderRequestTab(array $data): string
    {
        $method = htmlspecialchars($data['method'] ?? 'GET');
        $uri = htmlspecialchars($data['uri'] ?? '/');
        $statusCode = $data['status_code'] ?? 200;
        $time = $data['execution_time'] ?? 0;
        $memory = $data['memory_peak'] ?? 0;

        $html = sprintf(
            '<div class="dev-toolbar-section">
                <div class="dev-toolbar-section-title">Current Request</div>
                <table class="dev-toolbar-kv-table">
                    <tr><td>Method</td><td>%s</td></tr>
                    <tr><td>URI</td><td>%s</td></tr>
                    <tr><td>Status</td><td>%d</td></tr>
                    <tr><td>Time</td><td>%.2fms</td></tr>
                    <tr><td>Memory</td><td>%.2fMB</td></tr>
                </table>
            </div>',
            $method,
            $uri,
            $statusCode,
            $time,
            $memory
        );

        // Render headers
        if (!empty($data['headers'])) {
            $html .= '<div class="dev-toolbar-section">
                <div class="dev-toolbar-section-title">Headers</div>
                <table class="dev-toolbar-kv-table">';

            foreach ($data['headers'] as $key => $value) {
                $html .= sprintf(
                    '<tr><td>%s</td><td>%s</td></tr>',
                    htmlspecialchars($key),
                    htmlspecialchars(is_array($value) ? json_encode($value) : $value)
                );
            }

            $html .= '</table></div>';
        }

        // Export button for current request
        $html .= '<div class="dev-toolbar-section">
            <button class="dev-toolbar-export-btn" data-action="export-current">
                ⬇ Export Current Request
            </button>
        </div>';

        return $html;
    }

    /**
     * Render QUERIES tab content
     *
     * @param array<string, mixed> $data
     * @return string HTML
     */
    private function renderQueriesTab(array $data): string
    {
        $queries = $data['queries'] ?? [];
        $totalTime = $data['total_time'] ?? 0;

        if (empty($queries)) {
            return '<p>No queries executed</p>';
        }

        $html = sprintf(
            '<div class="dev-toolbar-section">
                <div class="dev-toolbar-section-title">Summary: %d queries in %.2fms</div>
            </div>',
            count($queries),
            $totalTime
        );

        // Check for N+1 queries
        $nPlusOnes = QueryAnalyzer::detectNPlusOne($queries);
        if (!empty($nPlusOnes)) {
            $html .= '<div class="dev-toolbar-section">';
            $html .= sprintf(
                '<div class="dev-toolbar-section-title">⚠️ N+1 Query Detected! (%d patterns)</div>',
                count($nPlusOnes)
            );

            foreach ($nPlusOnes as $nPlusOne) {
                $html .= sprintf(
                    '<div class="dev-toolbar-n-plus-one">
                        <div><strong>Pattern:</strong> %s</div>
                        <div><strong>Executed:</strong> %d times with different parameters</div>
                        <div><strong>Total Time:</strong> %.2fms (avg: %.2fms)</div>
                        <div><strong>Called from:</strong> %s</div>
                        <div class="dev-toolbar-suggestion">💡 Suggestion: %s</div>
                    </div>',
                    htmlspecialchars($nPlusOne['pattern']),
                    $nPlusOne['count'],
                    $nPlusOne['total_time'],
                    $nPlusOne['avg_time'],
                    htmlspecialchars($nPlusOne['location']),
                    nl2br(htmlspecialchars($nPlusOne['suggestion']))
                );
            }

            $html .= '</div>';
        }

        $html .= '<div class="dev-toolbar-section"><div class="dev-toolbar-section-title">Query List:</div>';

        foreach ($queries as $query) {
            $time = $query['time'] ?? 0;
            $timeClass = $time < 100 ? 'fast' : ($time < 500 ? 'slow' : 'very-slow');
            $queryClass = $timeClass;

            $sql = htmlspecialchars($query['sql'] ?? '');
            $bindings = json_encode($query['bindings'] ?? [], JSON_PRETTY_PRINT);

            $html .= sprintf(
                '<div class="dev-toolbar-query %s">
                    <div class="dev-toolbar-query-header">
                        <span class="dev-toolbar-query-time %s">%.2fms</span>
                    </div>
                    <div class="dev-toolbar-query-sql">%s</div>',
                $queryClass,
                $timeClass,
                $time,
                $sql
            );

            if (!empty($query['bindings'])) {
                $html .= sprintf(
                    '<div class="dev-toolbar-query-bindings">Bindings: %s</div>',
                    htmlspecialchars($bindings)
                );
            }

            $html .= '</div>';
        }

        $html .= '</div>'; // Close "Query List" section

        return $html;
    }

    /**
     * Render MESSAGES tab content
     *
     * @param array<string, mixed> $data
     * @return string HTML
     */
    private function renderMessagesTab(array $data): string
    {
        $messages = $data['messages'] ?? [];

        if (empty($messages)) {
            return '<p>No log messages</p>';
        }

        $html = '';

        foreach ($messages as $message) {
            $level = $message['level'] ?? 'info';
            $levelName = htmlspecialchars($message['level_name'] ?? 'INFO');
            $text = htmlspecialchars($message['message'] ?? '');
            $time = htmlspecialchars($message['datetime'] ?? '');

            $html .= sprintf(
                '<div class="dev-toolbar-message %s">
                    <div class="dev-toolbar-message-header">
                        <span class="dev-toolbar-message-level">%s</span>
                        <span class="dev-toolbar-message-time">%s</span>
                    </div>
                    <div class="dev-toolbar-message-text">%s</div>
                </div>',
                $level,
                $levelName,
                $time,
                $text
            );
        }

        return $html;
    }

    /**
     * Render EXCEPTIONS tab content
     *
     * @param array<string, mixed> $data
     * @return string HTML
     */
    private function renderExceptionsTab(array $data): string
    {
        $exceptions = $data['exceptions'] ?? [];

        if (empty($exceptions)) {
            return '<p>No exceptions thrown</p>';
        }

        $html = '';

        foreach ($exceptions as $exception) {
            $handled = $exception['handled'] ?? true;
            $class = htmlspecialchars($exception['class'] ?? 'Exception');
            $message = htmlspecialchars($exception['message'] ?? '');
            $file = htmlspecialchars($exception['file'] ?? '');
            $line = $exception['line'] ?? 0;

            $html .= sprintf(
                '<div class="dev-toolbar-exception %s">
                    <div class="dev-toolbar-exception-class">%s</div>
                    <div class="dev-toolbar-exception-message">%s</div>
                    <div class="dev-toolbar-exception-location">%s:%d</div>
                </div>',
                $handled ? 'handled' : '',
                $class,
                $message,
                $file,
                $line
            );
        }

        return $html;
    }

    /**
     * Render HTTP tab content
     *
     * @param array<string, mixed> $data
     * @return string HTML
     */
    private function renderHttpTab(array $data): string
    {
        $requests = $data['requests'] ?? [];
        $totalTime = $data['total_time'] ?? 0;
        $count = $data['count'] ?? 0;

        if ($count === 0) {
            return '<p>No HTTP requests made</p>';
        }

        $html = sprintf(
            '<div class="dev-toolbar-section">
                <div class="dev-toolbar-section-title">HTTP Client (%d %s, %.2fms total)</div>
            </div>',
            $count,
            $count === 1 ? 'request' : 'requests',
            $totalTime
        );

        foreach ($requests as $request) {
            $method = htmlspecialchars($request['method'] ?? 'GET');
            $url = htmlspecialchars($request['url'] ?? '');
            $time = $request['time'] ?? 0;
            $status = $request['status'] ?? 0;
            $perfLevel = $request['performance_level'] ?? 'good';

            $icon = match ($perfLevel) {
                'good' => '🟢',
                'warning' => '🟡',
                'critical' => '🔴',
                default => '⚪',
            };

            $html .= sprintf(
                '<div class="dev-toolbar-http-request %s">
                    <div class="dev-toolbar-http-header">
                        %s <strong>%s</strong> %s <span class="dev-toolbar-http-time">(%.2fms)</span>
                    </div>
                    <div class="dev-toolbar-http-status">Status: %d</div>',
                $perfLevel,
                $icon,
                $method,
                $url,
                $time,
                $status
            );

            // Show location if available
            if (!empty($request['backtrace'])) {
                $location = $request['backtrace'][0];
                $html .= sprintf(
                    '<div class="dev-toolbar-http-location">Called at: %s:%d</div>',
                    htmlspecialchars($location['file'] ?? ''),
                    $location['line'] ?? 0
                );
            }

            $html .= '</div>';
        }

        return $html;
    }

    /**
     * Render CACHE tab content
     *
     * @param array<string, mixed> $data
     * @return string HTML
     */
    private function renderCacheTab(array $data): string
    {
        $operations = $data['operations'] ?? [];
        $hits = $data['hits'] ?? 0;
        $misses = $data['misses'] ?? 0;
        $hitRate = $data['hit_rate'] ?? 0;
        $totalTime = $data['total_time'] ?? 0;
        $count = $data['count'] ?? 0;

        if ($count === 0) {
            return '<p>No cache operations</p>';
        }

        $html = sprintf(
            '<div class="dev-toolbar-section">
                <div class="dev-toolbar-section-title">Cache Operations (%d operations, %.1f%% hit rate)</div>
                <div class="dev-toolbar-cache-stats">
                    <div>Hit Rate: %s %.1f%% (%d hits, %d misses)</div>
                    <div>Total Time: %.2fms</div>
                </div>
            </div>',
            $count,
            $hitRate,
            $this->renderProgressBar($hitRate),
            $hitRate,
            $hits,
            $misses,
            $totalTime
        );

        $html .= '<div class="dev-toolbar-section"><div class="dev-toolbar-section-title">Operations:</div>';

        foreach ($operations as $operation) {
            $type = strtoupper($operation['type'] ?? 'GET');
            $key = htmlspecialchars($operation['key'] ?? '');
            $time = $operation['time'] ?? 0;

            $icon = match ($type) {
                'GET' => ($operation['hit'] ?? false) ? '🟢 HIT' : '🔴 MISS',
                'SET' => '⚙️ SET',
                'DELETE' => '🗑️ DEL',
                default => '📝 ' . $type,
            };

            $html .= sprintf(
                '<div class="dev-toolbar-cache-operation">
                    <div class="dev-toolbar-cache-op-header">
                        %s <strong>%s(\'%s\')</strong> <span>%.2fms</span>
                    </div>
                ',
                $icon,
                strtolower($type),
                $key,
                $time
            );

            // Show TTL for GET operations
            if ($type === 'GET' && isset($operation['ttl'])) {
                $html .= sprintf(
                    '<div class="dev-toolbar-cache-ttl">TTL: %ds remaining</div>',
                    $operation['ttl']
                );
            }

            // Show size for SET operations
            if ($type === 'SET' && isset($operation['size'])) {
                $html .= sprintf(
                    '<div class="dev-toolbar-cache-size">Value Size: %s</div>',
                    htmlspecialchars($operation['size'])
                );
            }

            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render TIMELINE tab content
     *
     * @param array<string, mixed> $data
     * @return string HTML
     */
    private function renderTimelineTab(array $data): string
    {
        $timeline = $data['timeline'] ?? [];
        $totalTime = $data['total_time'] ?? 0;

        if (empty($timeline)) {
            return '<p>No timeline data available</p>';
        }

        $html = sprintf(
            '<div class="dev-toolbar-section">
                <div class="dev-toolbar-section-title">Request Timeline (Total: %.2fms)</div>
            </div>',
            $totalTime
        );

        foreach ($timeline as $item) {
            $label = htmlspecialchars($item['label'] ?? '');
            $duration = $item['duration'] ?? 0;
            $percentage = $item['percentage'] ?? 0;
            $isBottleneck = $item['is_bottleneck'] ?? false;

            $bottleneckClass = $isBottleneck ? 'bottleneck' : '';

            $html .= sprintf(
                '<div class="dev-toolbar-timeline-item %s">
                    <div class="dev-toolbar-timeline-label">%s</div>
                    <div class="dev-toolbar-timeline-bar-container">
                        <div class="dev-toolbar-timeline-bar" style="width: %d%%"></div>
                        <span class="dev-toolbar-timeline-time">%.2fms (%.1f%%)</span>
                    </div>',
                $bottleneckClass,
                $label,
                min(100, (int)$percentage),
                $duration,
                $percentage
            );

            // Show sub-events if available
            if (!empty($item['events'])) {
                $html .= '<div class="dev-toolbar-timeline-events">';
                foreach ($item['events'] as $event) {
                    $html .= sprintf(
                        '<div class="dev-toolbar-timeline-subevent">├─ %s (%.2fms)</div>',
                        htmlspecialchars($event['label'] ?? ''),
                        $event['duration'] ?? 0
                    );
                }
                $html .= '</div>';
            }

            $html .= '</div>';
        }

        return $html;
    }

    /**
     * Render a progress bar
     *
     * @param float $percentage Percentage (0-100)
     * @return string HTML progress bar
     */
    private function renderProgressBar(float $percentage): string
    {
        $filled = (int)($percentage / 5); // 20 blocks total
        $empty = 20 - $filled;

        return str_repeat('█', $filled) . str_repeat('░', $empty);
    }

    /**
     * Render HISTORY tab content
     *
     * @param array<string, mixed> $data
     * @return string HTML
     */
    private function renderHistoryTab(array $data): string
    {
        $requests = $data['requests'] ?? [];
        $stats = $data['statistics'] ?? [];
        $trends = $data['trends'] ?? [];

        return $this->renderHistoryFilters()
            . $this->renderHistoryStatistics($stats)
            . $this->renderHistoryTrends($trends)
            . $this->renderHistoryRequestList($requests)
            . $this->renderHistoryExport();
    }

    /**
     * Render history filters
     *
     * @return string HTML
     */
    private function renderHistoryFilters(): string
    {
        return '<div class="dev-toolbar-history-filters">
            <div class="dev-toolbar-history-filter-controls">
                <div class="dev-toolbar-filter-group">
                    <label for="history-filter-method">Method</label>
                    <select id="history-filter-method" class="dev-toolbar-filter-select">
                        <option value="">All Methods</option>
                        <option value="GET">GET</option>
                        <option value="POST">POST</option>
                        <option value="PUT">PUT</option>
                        <option value="DELETE">DELETE</option>
                        <option value="PATCH">PATCH</option>
                    </select>
                </div>
                <div class="dev-toolbar-filter-group">
                    <label for="history-filter-status">Status</label>
                    <select id="history-filter-status" class="dev-toolbar-filter-select">
                        <option value="">All Status</option>
                        <option value="2">2xx Success</option>
                        <option value="3">3xx Redirect</option>
                        <option value="4">4xx Client Error</option>
                        <option value="5">5xx Server Error</option>
                    </select>
                </div>
                <div class="dev-toolbar-filter-group">
                    <label for="history-filter-uri">URI Contains</label>
                    <input type="text" id="history-filter-uri" class="dev-toolbar-filter-input" placeholder="Search URI...">
                </div>
                <div class="dev-toolbar-filter-group">
                    <label for="history-filter-min-time">Min Time (ms)</label>
                    <input type="number" id="history-filter-min-time" class="dev-toolbar-filter-input" placeholder="0" min="0" step="10">
                </div>
                <div class="dev-toolbar-filter-group">
                    <button id="history-filter-reset" class="dev-toolbar-btn dev-toolbar-btn-secondary">Reset Filters</button>
                </div>
            </div>
        </div>';
    }

    /**
     * Render history statistics (placeholder for client-side rendering)
     *
     * @param array<string, mixed> $stats
     * @return string HTML
     */
    private function renderHistoryStatistics(array $stats): string
    {
        // Render placeholder - JavaScript will populate from localStorage
        $html = '<div class="dev-toolbar-section">
            <div class="dev-toolbar-section-title">Statistics</div>
            <div class="dev-toolbar-history-stats-grid" id="dev-toolbar-history-stats-grid">';

        $html .= '<div class="dev-toolbar-history-stat-card">
                <div class="dev-toolbar-history-stat-label">Total Requests</div>
                <div class="dev-toolbar-history-stat-value" data-history-stat="total">0</div>
            </div>';

        $html .= '<div class="dev-toolbar-history-stat-card">
                <div class="dev-toolbar-history-stat-label">Avg Time</div>
                <div class="dev-toolbar-history-stat-value" data-history-stat="avg_time">0ms</div>
            </div>';

        $html .= '<div class="dev-toolbar-history-stat-card">
                <div class="dev-toolbar-history-stat-label">Avg Memory</div>
                <div class="dev-toolbar-history-stat-value" data-history-stat="avg_memory">0MB</div>
            </div>';

        $html .= '<div class="dev-toolbar-history-stat-card">
                <div class="dev-toolbar-history-stat-label">Avg Queries</div>
                <div class="dev-toolbar-history-stat-value" data-history-stat="avg_queries">0</div>
            </div>';

        $html .= '<div class="dev-toolbar-history-stat-card">
                <div class="dev-toolbar-history-stat-label">Fastest</div>
                <div class="dev-toolbar-history-stat-value fast" data-history-stat="fastest">0ms</div>
            </div>';

        $html .= '<div class="dev-toolbar-history-stat-card">
                <div class="dev-toolbar-history-stat-label">Slowest</div>
                <div class="dev-toolbar-history-stat-value slow" data-history-stat="slowest">0ms</div>
            </div>';

        $html .= '</div></div>';

        return $html;
    }

    /**
     * Render history trends (ASCII sparkline)
     *
     * @param array<string, mixed> $trends
     * @return string HTML
     */
    private function renderHistoryTrends(array $trends): string
    {
        $times = $trends['time'] ?? [];

        if (empty($times)) {
            return '';
        }

        $sparkline = $this->generateSparkline($times);

        return sprintf(
            '<div class="dev-toolbar-section">
                <div class="dev-toolbar-section-title">Response Time Trend</div>
                <div class="dev-toolbar-history-trend">
                    <div class="dev-toolbar-history-sparkline">%s</div>
                </div>
            </div>',
            $sparkline
        );
    }

    /**
     * Generate ASCII sparkline from values
     *
     * @param array<int|float> $values
     * @return string Sparkline characters
     */
    private function generateSparkline(array $values): string
    {
        if (empty($values)) {
            return '';
        }

        $ticks = ['▁', '▂', '▃', '▄', '▅', '▆', '▇', '█'];
        $min = min($values);
        $max = max($values);
        $range = $max - $min;

        if ($range == 0) {
            return str_repeat($ticks[3], count($values)); // All middle
        }

        $sparkline = '';
        foreach ($values as $value) {
            $normalized = ($value - $min) / $range;
            $index = min(7, (int)($normalized * 8));
            $sparkline .= $ticks[$index];
        }

        return $sparkline;
    }

    /**
     * Render history request list
     *
     * @param array<string, array<string, mixed>> $requests
     * @return string HTML
     */
    private function renderHistoryRequestList(array $requests): string
    {
        // Render placeholder - JavaScript will populate from localStorage
        $html = '<div class="dev-toolbar-section">
            <div class="dev-toolbar-section-title" id="history-list-title">Request History (<span id="history-list-count">0</span>)</div>
            <div class="dev-toolbar-history-request-list" id="history-request-list-container">
                <p style="color: #888;">Loading history from localStorage...</p>
            </div>
        </div>';

        return $html;
    }

    /**
     * Render history export controls
     *
     * @return string HTML
     */
    private function renderHistoryExport(): string
    {
        return '<div class="dev-toolbar-section">
            <div class="dev-toolbar-section-title">Actions</div>
            <div class="dev-toolbar-history-export">
                <button id="history-export-json" class="dev-toolbar-btn dev-toolbar-btn-primary">📥 Export JSON</button>
                <button id="history-export-csv" class="dev-toolbar-btn dev-toolbar-btn-primary">📊 Export CSV</button>
                <button id="history-clear" class="dev-toolbar-btn dev-toolbar-btn-danger">🗑️ Clear History</button>
            </div>
        </div>';
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
